<?php

/**
 * Mediawiki Hooks implementation for CrowdSec Integration.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Extension\CrowdSec;

use Html;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Logger\LoggerFactory;
use RequestContext;
use Title;
use User;
use Wikimedia\IPUtils;

class Hooks {
	/**
	 * Computes the crowdsec-blocked variable
	 * @param string $method
	 * @param VariableHolder $vars
	 * @param array $parameters
	 * @param null &$result
	 * @return bool
	 */
	public static function abuseFilterComputeVariable( $method, $vars, $parameters, &$result ) {
		if ( $method == 'crowdsec-blocked' ) {
			$ip = self::getIPFromUser( $parameters['user'] );
			if ( $ip === false ) {
				$result = false;
			} else {
				$result = ( LAPIClient::singleton() )->getDecision( $ip );
			}

			return false;
		}

		return true;
	}

	/**
	 * Load our blocked variable
	 * @param VariableHolder $vars
	 * @param User $user
	 * @return bool
	 */
	public static function abuseFilterGenerateUserVars( $vars, $user ) {
		if ( self::isConfigOk() ) {
			$vars->setLazyLoadVar( 'crowdsec_blocked', 'crowdsec-blocked', [ 'user' => $user ] );
		}

		return true;
	}

	/**
	 * Tell AbuseFilter about our crowdsec-blocked variable
	 * @param array &$builderValues
	 * @return bool
	 */
	public static function abuseFilterBuilder( &$builderValues ) {
		if ( self::isConfigOk() ) {
			// Uses: 'abusefilter-edit-builder-vars-crowdsec-blocked'
			$builderValues['vars']['crowdsec_blocked'] = 'crowdsec-blocked';
		}

		return true;
	}

	/**
	 * Get an IP address for a User if possible
	 *
	 * @param User $user
	 * @return bool|string IP address or false
	 */
	private static function getIPFromUser( User $user ) {
		if ( $user->isAnon() ) {
			return $user->getName();
		}

		$context = RequestContext::getMain();
		if ( $context->getUser()->getName() === $user->getName() ) {
			// Only use the main context if the users are the same
			return $context->getRequest()->getIP();
		}

		// Couldn't figure out an IP address
		return false;
	}

	/**
	 * If an IP address is denylisted, don't let them edit.
	 *
	 * @param Title &$title Title being acted upon
	 * @param User &$user User performing the action
	 * @param string $action Action being performed
	 * @param array &$result Will be filled with block status if blocked
	 * @return bool
	 */
	public static function onGetUserPermissionsErrorsExpensive( &$title, &$user, $action, &$result ) {
		global $wgCrowdSecReportOnly, $wgBlockAllowsUTEdit, $wgCrowdSecTreatTypesAsBan,
			   $wgCrowdSecFallbackBan, $wgCrowdSecRestrictRead;

		if ( !self::isConfigOk() ) {
			// Not configured
			return true;
		}

		if ( $action === 'read' && !$wgCrowdSecRestrictRead ) {
			return true;
		}
		if ( $wgBlockAllowsUTEdit && $title->equals( $user->getTalkPage() ) ) {
			// Let a user edit their talk page
			return true;
		}

		$logger = LoggerFactory::getInstance( 'crowdsec' );
		$ip = self::getIPFromUser( $user );

		// attempt to get ip from user
		if ( $ip === false ) {
			$logger->info(
				"Unable to obtain IP information for {user}.",
				[ 'user' => $user->getName() ]
			);
			return true;
		}

		// allow if user has crowdsec-bypass
		if ( $user->isAllowed( 'crowdsec-bypass' ) ) {
			$logger->info(
				"{user} is exempt from CrowdSec blocks. on {title} doing {action}",
				[
					'action' => $action,
					'clientip' => $ip,
					'title' => $title->getPrefixedText(),
					'user' => $user->getName()
				]
			);
			return true;
		}

		// allow if user is exempted from autoblocks (borrowed from TorBlock)
		if ( self::isExemptedFromAutoblocks( $ip ) ) {
			$logger->info(
				"{clientip} is in autoblock exemption list. Exempting from crowdsec blocks.",
				[ 'clientip' => $ip ]
			);
			return true;
		}

		$client = LAPIClient::singleton();
		$lapiResult = $client->getDecision( $ip );

		if ( $lapiResult == false ) {
			$logger->info(
				"{user} tripped CrowdSec List doing {action} "
				. "by using {clientip} on \"{title}\". "
				. "But lapi throws error. fallback...",
				[
					'action' => $action,
					'clientip' => $ip,
					'title' => $title->getPrefixedText(),
					'user' => $user->getName()
				]
			);
			return !$wgCrowdSecFallbackBan;
		}

		if ( $lapiResult == "ok" ) {
			return true;
		}

		if ( $wgCrowdSecReportOnly ) {
			$logger->info(
				"Report Only: {user} tripped CrowdSec List doing {action} ({type}) "
				. "by using {clientip} on \"{title}\".",
				[
					'action' => $action,
					'type' => $lapiResult,
					'clientip' => $ip,
					'title' => $title->getPrefixedText(),
					'user' => $user->getName()
				]
			);
			return true;
		}

		if ( !in_array( $lapiResult, $wgCrowdSecTreatTypesAsBan ) ) {
			return true;
		}

		// log action when blocked, return error msg
		$logger->info(
			"{user} was blocked by CrowdSec from doing {action} ({type}) "
			. "by using {clientip} on \"{title}\".",
			[
				'action' => $action,
				'type' => $lapiResult,
				'clientip' => $ip,
				'title' => $title->getPrefixedText(),
				'user' => $user->getName()
			]
		);

		// default: set error msg result and return false
		$result = [ 'crowdsec-blocked', $ip ];
		return false;
	}

	/**
	 * @param array &$msg
	 * @param string $ip
	 * @return bool
	 */
	public static function onOtherBlockLogLink( &$msg, $ip ) {
		if ( !self::isConfigOk() ) {
			return true;
		}

		$client = LAPIClient::singleton();
		$lapiType = $client->getDecision( $ip );
		if ( IPUtils::isIPAddress( $ip ) && $lapiType != "ok" ) {
			$msg[] = Html::rawElement(
				'span',
				[ 'class' => 'mw-crowdsec-denylisted' ],
				wfMessage( 'crowdsec-is-blocked', $ip, $lapiType )->parse()
			);
		}

		return true;
	}

	/**
	 * Check config is ok
	 * @return bool
	 */
	private static function isConfigOk() {
		global $wgCrowdSecEnable, $wgCrowdSecAPIKey, $wgCrowdSecAPIUrl;
		$localApi = ( isset( $wgCrowdSecAPIKey ) && isset( $wgCrowdSecAPIUrl ) )
				&& !( empty( $wgCrowdSecAPIKey ) || empty( $wgCrowdSecAPIUrl ) );
		return $wgCrowdSecEnable && $localApi;
	}

	/**
	 * Checks whether a given IP is on the autoblock whitelist.
	 * This is fix for compatibility with 1.35.
	 * As WikiMedia replaces function name `isWhitelistedFromAutoblocks` to `isExemptedFromAutoblocks`
	 *
	 * @param string $ip The IP to check
	 * @return bool
	 */
	private static function isExemptedFromAutoblocks( $ip ) {
		// Mediawiki >= 1.36
		if ( method_exists( DatabaseBlock::class, 'isExemptedFromAutoblocks' ) ) {
			return DatabaseBlock::isExemptedFromAutoblocks( $ip );
		}

		// Mediawiki <= 1.35
		if ( method_exists( DatabaseBlock::class, 'isWhitelistedFromAutoblocks' ) ) {
			return DatabaseBlock::isWhitelistedFromAutoblocks( $ip );
		}

		return false;
	}
}
