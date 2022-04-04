<?php
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
				$result = ( new LAPIClient() )->getDecision( $ip );
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
		global $wgCrowdSecReportOnly, $wgBlockAllowsUTEdit, $wgCrowdSecTreatCaptchaAsBan,
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
				"{user} is exempt from CrowdSec blocks.",
				[
					'clientip' => $ip,
					'reportonly' => $wgCrowdSecReportOnly,
					'user' => $user->getName()
				]
			);
			return true;
		}

		// allow if user is exempted from autoblocks (borrowed from TorBlock)
		if ( DatabaseBlock::isExemptedFromAutoblocks( $ip ) ) {
			$logger->info(
				"{clientip} is in autoblock exemption list. Exempting from crowdsec blocks.",
				[ 'clientip' => $ip, 'reportonly' => $wgCrowdSecReportOnly ]
			);
			return true;
		}

		$client = new LAPIClient();
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

		if ( $lapiResult == "captcha" && !$wgCrowdSecTreatCaptchaAsBan ) {
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
		if ( !$isConfigOk ) {
			return true;
		}

		$client = new LAPIClient();
		if ( IPUtils::isIPAddress( $ip ) && $client->getDecision( $ip ) != "ok" ) {
			$msg[] = Html::rawElement(
				'span',
				[ 'class' => 'mw-crowdsec-denylisted' ],
				wfMessage( 'crowdsec-is-blocked', $ip )->parse()
			);
		}

		return true;
	}

	/**
	 * Check config is ok
	 * @return bool
	 */
	private static function isConfigOk() {
		global $wgCrowdSecEnabled, $wgCrowdSecAPIKey, $wgCrowdSecAPIUrl;
		$localApi = ( isset( $wgCrowdSecAPIKey ) && isset( $wgCrowdSecAPIUrl ) )
				&& !( empty( $wgCrowdSecAPIKey ) || empty( $wgCrowdSecAPIUrl ) );
		return $wgCrowdSecEnabled && $localApi;
	}
}
