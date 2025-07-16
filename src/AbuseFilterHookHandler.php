<?php

namespace MediaWiki\Extension\CrowdSec;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterBuilderHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterComputeVariableHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateUserVarsHook;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;

class AbuseFilterHookHandler implements
	AbuseFilterBuilderHook,
	AbuseFilterComputeVariableHook,
	AbuseFilterGenerateUserVarsHook
{
	/** @var MediaWiki\Config\Config|null */
	private $config;

	/**
	 * Constructor of AbuseFilterHookHandler
	 * @param MediaWiki\Config\Config $config main config
	 */
	public function __construct( $config ) {
		$this->config = $config;
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
	 * Computes the crowdsec-blocked variable
	 * @param string $method
	 * @param VariableHolder $vars
	 * @param array $parameters
	 * @param null &$result
	 * @return bool
	 */
    // phpcs:ignore
	public function onAbuseFilter_computeVariable( $method, $vars, $parameters, &$result ) {
		if ( $method == 'crowdsec-decision' ) {
			$ip = Hooks::getIPFromUser( $parameters['user'] );
			if ( $ip === false ) {
				$result = 'unknown';
			} else {
				$decision = LAPIClient::singleton()->getDecision( $ip );
				StatsUtil::singleton()->incrementDecisionQuery( 'abusefilter' );
				if ( $decision === false ) {
					StatsUtil::singleton()->incrementLAPIError( 'abusefilter' );
					$result = 'error';
				} else {
					$result = $decision;
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * Load our blocked variable
	 * @param VariableHolder $vars
	 * @param MediaWiki\User\User $user
	 * @param ?MediaWiki\RecentChanges\RecentChange $rc
	 * @return bool
	 */
    // phpcs:ignore
	public function onAbuseFilter_generateUserVars( $vars, $user, $rc ) {
		if ( $this->isConfigOk() ) {
			$vars->setLazyLoadVar( 'crowdsec_decision', 'crowdsec-decision', [ 'user' => $user ] );
		}
		return true;
	}

	/**
	 * Tell AbuseFilter about our crowdsec-blocked variable
	 * @param array &$builderValues
	 * @return bool
	 */
    // phpcs:ignore
	public function onAbuseFilter_builder( &$builderValues ) {
		if ( $this->isConfigOk() ) {
			// Uses: 'abusefilter-edit-builder-vars-crowdsec-decision'
			$builderValues['vars']['crowdsec_decision'] = 'crowdsec-decision';
		}
		return true;
	}
}
