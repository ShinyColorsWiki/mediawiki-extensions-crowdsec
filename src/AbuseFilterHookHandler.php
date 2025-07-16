<?php

namespace MediaWiki\Extension\CrowdSec;

use MediaWiki\Config\Config;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterBuilderHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterComputeVariableHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateUserVarsHook;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\User\User;
use MediaWiki\RecentChanges\RecentChange;

class AbuseFilterHookHandler implements AbuseFilterBuilderHook, AbuseFilterComputeVariableHook, AbuseFilterGenerateUserVarsHook {
    /** @var Config */
    private Config $config;

    /**
     * @param Config $config
     */
    public function __construct( Config $config ) {
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
    public function onAbuseFilter_computeVariable( $method, $vars, $parameters, &$result ) {
        if ( $method == 'crowdsec-blocked' ) {
            $ip = Hooks::getIPFromUser( $parameters['user'] );
            if ( $ip === false ) {
                $result = false;
            } else {
                $decision = LAPIClient::singleton()->getDecision( $ip );
                StatsUtil::singleton()->incrementDecisionQuery();
                if ( $decision === false ) {
                    StatsUtil::singleton()->incrementLAPIError();
                    $result = false; // Treat LAPI error as not blocked
                } else {
                    $result = ( $decision != 'ok' ); // True if any non-ok decision (blocked)
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Load our blocked variable
     * @param VariableHolder $vars
     * @param User $user
     * @param ?RecentChange $rc
     * @return bool
     */
    public function onAbuseFilter_generateUserVars( $vars, $user, ?RecentChange $rc ) {
        if ( $this->isConfigOk() ) {
            $vars->setLazyLoadVar( 'crowdsec_blocked', 'crowdsec-blocked', [ 'user' => $user ] );
        }
        return true;
    }

    /**
     * Tell AbuseFilter about our crowdsec-blocked variable
     * @param array &$builderValues
     * @return bool
     */
    // AbuseFilter에 crowdsec-blocked 변수 알림
    public function onAbuseFilter_builder( &$builderValues ) {
        if ( $this->isConfigOk() ) {
            // Uses: 'abusefilter-edit-builder-vars-crowdsec-blocked'
            $builderValues['vars']['crowdsec_blocked'] = 'crowdsec-blocked';
        }
        return true;
    }
} 