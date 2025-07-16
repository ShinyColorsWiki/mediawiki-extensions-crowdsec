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
 * CrowdSec 확장을 위한 통계 유틸리티 클래스.
 */
class StatsUtil {
	/** @var StatsUtil|null */
	private static $instance = null;

	/** @var \MediaWiki\Metrics\MetricsFactory */
	private $statsFactory;

	/**
	 * private 생성자.
	 */
	private function __construct() {
		try {
			$this->statsFactory = MediaWikiServices::getInstance()->getStatsFactory()->withComponent( 'CrowdSec' );
		} catch ( \Exception $e ) {
			// It is supported since MediaWiki 1.41+
			$this->statsFactory = null;
		}
	}

	/**
	 * 싱글톤 인스턴스를 반환합니다.
	 * @return StatsUtil
	 */
	public static function singleton(): StatsUtil {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 결정 쿼리 카운트를 증가시킵니다.
	 * @param string $context 컨텍스트 (선택적)
	 */
	public function incrementDecisionQuery( string $context = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'decision_queries_total' );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		$counter->increment();
	}

	/**
	 * LAPI 오류 카운트를 증가시킵니다.
	 * @param string $context 컨텍스트 (선택적)
	 */
	public function incrementLAPIError( string $context = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'lapi_errors_total' );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		$counter->increment();
	}

	/**
	 * 보고 전용 모드 트리거 카운트를 증가시킵니다.
	 * @param string $type 결정 유형
	 * @param string $context 컨텍스트 (선택적)
	 */
	public function incrementReportOnly( string $type, string $context = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'report_only_total' )
			->setLabel( 'type', $type );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		$counter->increment();
	}

	/**
	 * 차단 카운트를 증가시킵니다.
	 * @param string $type 결정 유형
	 * @param string $context 컨텍스트 (선택적)
	 */
	public function incrementBlock( string $type, string $context = '' ) {
		if ( $this->statsFactory === null ) {
			return;
		}
		$counter = $this->statsFactory->getCounter( 'blocks_total' )
			->setLabel( 'type', $type );
		if ( $context ) {
			$counter->setLabel( 'context', $context );
		}
		$counter->increment();
	}
}
