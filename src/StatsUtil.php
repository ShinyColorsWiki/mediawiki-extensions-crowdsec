<?php

/**
 * Stats utility for Mediawiki CrowdSec Integration.
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

use MediaWiki\MediaWikiServices;

/**
 * Stats utility class for CrowdSec extension.
 */
class StatsUtil {
	/** @var MediaWiki\Metrics\MetricsFactory */
	private $statsFactory;

	/**
	 * private constructor.
	 * @param MediaWiki\Metrics\MetricsFactory $statsFactory
	 */
	private function __construct( $statsFactory ) {
		$this->statsFactory = $statsFactory;
	}

	/**
	 * Create a new instance and return it (__construct's alias).
	 * @param MediaWiki\MediaWikiServices|null $service MediaWikiServices Instance
	 * @return StatsUtil
	 */
	public static function create( $service = null ): StatsUtil {
		if ( $service === null ) {
			$service = MediaWikiServices::getInstance();
		}
		// MW 1.40 and above: check if getStatsFactory method exists
		if ( method_exists( $service, 'getStatsFactory' ) ) {
			$sf = $service->getStatsFactory();
			// MW 1.41 and above: check if withComponent method exists
			if ( method_exists( $sf, 'withComponent' ) ) {
				return new self( $sf->withComponent( 'CrowdSec' ) );
			}
		}

		// MW 1.40 and below: use null since there is no default instance
		return new self( null );
	}

	/**
	 * Increment decision query count.
	 * @param string $context 컨텍스트 (선택적)
	 * @param string $action 액션 유형 (선택적)
	 */
	public function incrementDecisionQuery( string $context = '', string $action = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'decision_queries_total' );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		if ( $action ) {
			$counter->setLabel( 'action', $action );
		}
		$counter->increment();
	}

	/**
	 * Increment LAPI error count.
	 * @param string $context 컨텍스트 (선택적)
	 * @param string $action 액션 유형 (선택적)
	 */
	public function incrementLAPIError( string $context = '', string $action = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'lapi_errors_total' );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		if ( $action ) {
			$counter->setLabel( 'action', $action );
		}
		$counter->increment();
	}

	/**
	 * Increment report only mode trigger count.
	 * @param string $type decision type
	 * @param string $context context (optional)
	 * @param string $action action type (optional)
	 */
	public function incrementReportOnly( string $type, string $context = '', string $action = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'report_only_total' )
			->setLabel( 'type', $type );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		if ( $action ) {
			$counter->setLabel( 'action', $action );
		}
		$counter->increment();
	}

	/**
	 * Increment block count.
	 * @param string $type decision type
	 * @param string $context context (optional)
	 * @param string $action action type (optional)
	 */
	public function incrementBlock( string $type, string $context = '', string $action = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'blocks_total' )
			->setLabel( 'type', $type );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		if ( $action ) {
			$counter->setLabel( 'action', $action );
		}
		$counter->increment();
	}
}
