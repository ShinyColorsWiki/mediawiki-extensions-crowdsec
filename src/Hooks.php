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

// === Compatibility for MediaWiki 1.39 ===
if ( class_exists( 'User' ) && !class_exists( 'MediaWiki\User\User' ) ) {
	class_alias( 'User', 'MediaWiki\User\User' );
}

if ( class_exists( 'Title' ) && !class_exists( 'MediaWiki\Title\Title' ) ) {
	class_alias( 'Title', 'MediaWiki\Title\Title' );
}

if ( class_exists( 'Config' ) && !class_exists( 'MediaWiki\Config\Config' ) ) {
	class_alias( 'Config', 'MediaWiki\Config\Config' );
}

if ( class_exists( 'RequestContext' ) && !class_exists( 'MediaWiki\Context\RequestContext' ) ) {
	class_alias( 'RequestContext', 'MediaWiki\Context\RequestContext' );
}

if ( class_exists( 'DatabaseBlock' ) && !class_exists( 'MediaWiki\Block\DatabaseBlock' ) ) {
	class_alias( 'DatabaseBlock', 'MediaWiki\Block\DatabaseBlock' );
}

if ( class_exists( 'Html' ) && !class_exists( 'MediaWiki\Html\Html' ) ) {
	class_alias( 'Html', 'MediaWiki\Html\Html' );
}
// === End of Compatibility for MediaWiki 1.39 ===

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\IPUtils;

class Hooks {
	/** @var Config|null */
	private $config;

	/**
	 * Constructor of Hooks
	 * @param Config $config main config
	 */
	public function __construct( $config ) {
		$this->config = $config;
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
	public function onGetUserPermissionsErrorsExpensive( &$title, &$user, $action, &$result ) {
		if ( !$this->isConfigOk() ) {
			// Not configured
			return true;
		}

		if ( $action === 'read' && !$this->config->get( 'CrowdSecRestrictRead' ) ) {
			return true;
		}
		if ( $this->config->get( 'BlockAllowsUTEdit' ) && $title->equals( $user->getTalkPage() ) ) {
			// Let a user edit their talk page
			return true;
		}

		$logger = LoggerFactory::getInstance( 'CrowdSec' );
		$ip = self::getIPFromUser( $user );

		$exemptReasons = [];

		// attempt to get ip from user
		if ( $ip === false ) {
			$exemptReasons[] = "Unable to obtain IP information for {user}.";
			$logger->info( $exemptReasons[0], [ 'user' => $user->getName() ] );
		}

		// allow if user has crowdsec-bypass
		if ( $user->isAllowed( 'crowdsec-bypass' ) ) {
			$exemptReasons[] = "{user} is exempt from CrowdSec blocks. on {title} doing {action}";
			$logger->info(
				$exemptReasons[count( $exemptReasons ) - 1],
				[
					'action' => $action,
					'clientip' => $ip,
					'title' => $title->getPrefixedText(),
					'user' => $user->getName()
				]
			);
		}

		// allow if user is exempted from autoblocks (borrowed from TorBlock)
		if ( self::isExemptedFromAutoblocks( $ip ) ) {
			$exemptReasons[] = "{clientip} is in autoblock exemption list. Exempting from crowdsec blocks.";
			$logger->info(
				$exemptReasons[count( $exemptReasons ) - 1],
				[ 'clientip' => $ip ]
			);
		}

		$client = LAPIClient::singleton();
		$lapiResult = $client->getDecision( $ip );

		StatsUtil::singleton()->incrementDecisionQuery( 'permissions' );

		if ( $lapiResult == false ) {
			StatsUtil::singleton()->incrementLAPIError( 'permissions' );
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
			$fallback = $this->config->get( 'CrowdSecFallback' );
			if ( $fallback === 'ban' ) {
				// Treat as ban for fallback
				$lapiResult = 'ban';
			} elseif ( $fallback === 'captcha' ) {
				// Treat as captcha for fallback
				$lapiResult = 'captcha';
			} else {
				// Treat as ok for fallback
				$lapiResult = 'ok';
			}
		}

		$treatTypesAsBan = $this->config->get( 'CrowdSecTreatTypesAsBan' );
		$isBlocked = ( $lapiResult != "ok" && in_array( $lapiResult, $treatTypesAsBan ) );

		if ( !$this->config->get( 'CrowdSecReportOnly' ) ) {
			// Enforce mode: if not blocked or has exemptions, allow
			if ( !$isBlocked || count( $exemptReasons ) > 0 ) {
				return true;
			}
		} elseif ( $isBlocked && count( $exemptReasons ) > 0 ) {
			// Report-only mode + blocked + exemptions: log exemptions and allow
			$exemptReasonsStr = implode( ', ', $exemptReasons );
			$logger->info(
				$exemptReasonsStr,
				[
					'action' => $action,
					'clientip' => $ip,
					'title' => $title->getPrefixedText(),
					'user' => $user->getName(),
					'reportonly' => true
				]
			);
			return true;
		} elseif ( !$isBlocked ) {
			// Report-only mode + not blocked: allow
			return true;
		}

		// Log the block (or potential block in report-only)
		$blockVerb = ( $this->config->get( 'CrowdSecReportOnly' ) ) ? 'would have been' : 'was';
		$logger->info(
			"{user} {$blockVerb} blocked by CrowdSec from doing {action} ({type}) "
			. "by using {clientip} on \"{title}\".",
			[
				'action' => $action,
				'type' => $lapiResult,
				'clientip' => $ip,
				'title' => $title->getPrefixedText(),
				'user' => $user->getName()
			]
		);

		if ( $this->config->get( 'CrowdSecReportOnly' ) ) {
			StatsUtil::singleton()->incrementReportOnly( $lapiResult, 'permissions' );
			return true;
		} else {
			StatsUtil::singleton()->incrementBlock( $lapiResult, 'permissions' );
		}

		// Set error and block
		$result = [ 'crowdsec-blocked', $ip ];
		return false;
	}

	/**
	 * @param array &$msg
	 * @param string $ip
	 * @return bool
	 */
	public function onOtherBlockLogLink( &$msg, $ip ) {
		if ( !$this->isConfigOk() || $this->config->get( 'CrowdSecReportOnly' ) ) {
			return true;
		}

		$client = LAPIClient::singleton();
		$lapiType = $client->getDecision( $ip );
		StatsUtil::singleton()->incrementDecisionQuery( 'blocklog' );
		if ( $lapiType === false ) {
			StatsUtil::singleton()->incrementLAPIError( 'blocklog' );
		} elseif ( IPUtils::isIPAddress( $ip ) && $lapiType != "ok" ) {
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
	private function isConfigOk() {
		$enabled = $this->config->get( 'CrowdSecEnable' );
		$apiKey = $this->config->get( 'CrowdSecAPIKey' );
		$apiUrl = $this->config->get( 'CrowdSecAPIUrl' );
		$localApi = ( isset( $apiKey ) && isset( $apiUrl ) )
				&& !( empty( $apiKey ) || empty( $apiUrl ) );
		return $enabled && $localApi;
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
		// Mediawiki <= 1.41
		if ( method_exists( DatabaseBlock::class, 'isExemptedFromAutoblocks' ) ) {
			return DatabaseBlock::isExemptedFromAutoblocks( $ip );
		}

		// Mediawiki >= 1.42
		return MediaWikiServices::getInstance()->getAutoblockExemptionList()->isExempt( $ip );
	}

	/**
	 * Get an IP address for a User if possible
	 *
	 * @param User $user
	 * @return bool|string IP address or false
	 */
	private static function getIPFromUser( $user ) {
		$context = RequestContext::getMain();
		if ( $context->getUser()->getName() === $user->getName() ) {
			// Only use the main context if the users are the same
			return $context->getRequest()->getIP();
		}

		// Couldn't figure out an IP address
		return false;
	}
}
