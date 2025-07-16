<?php

/**
 * CrowdSec LocalAPI Client implementation using MediaWiki Service.
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
// if ( class_exists( '\BagOStuff' ) && !class_exists( '\MediaWiki\Cache\BagOStuff' ) ) {
// 	class_alias( '\BagOStuff', '\MediaWiki\Cache\BagOStuff' );
// }
if ( class_exists( 'ObjectCache' ) && !class_exists( 'MediaWiki\\Cache\\ObjectCache' ) ) {
	class_alias( 'ObjectCache', 'MediaWiki\\Cache\\ObjectCache' );
}

if ( class_exists( 'RequestContext' ) && !class_exists( 'MediaWiki\\Context\\RequestContext' ) ) {
	class_alias( 'RequestContext', 'MediaWiki\\Context\\RequestContext' );
}

if ( class_exists( 'Status' ) && !class_exists( 'MediaWiki\\Status\\Status' ) ) {
	class_alias( 'Status', 'MediaWiki\\Status\\Status' );
}

// Due to MW 1.40, FormatJson is declared weird in global namespace.
// So can't use FormatJson directly. need to alias it to our own namespace.
if ( class_exists( 'FormatJson' ) ) {
	class_alias( 'FormatJson', 'MediaWiki\\Extension\\CrowdSec\\MWFormatJson' );
} elseif ( class_exists( 'MediaWiki\\Json\\FormatJson' ) ) {
	class_alias( 'MediaWiki\\Json\\FormatJson', 'MediaWiki\\Extension\\CrowdSec\\MWFormatJson' );
}
// === End of Compatibility for MediaWiki 1.39 ===

use MediaWiki\Cache\ObjectCache as MWObjectCache;
use MediaWiki\Context\RequestContext as MWRequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status as MWStatus;

class LAPIClient {
	/** @var mixed */
	private $error = null;
	/** @var MediaWiki\Cache\BagOStuff */
	private $cache;
	/** @var Psr\Log\LoggerInterface */
	private $logger;
	/** @var LAPIClient|null */
	protected static $instance = null;
	/** @var MediaWiki\Config\Config|null */
	private $config;

	/**
	 * Constructor of LAPIClient
	 * @param MediaWiki\Config\Config $config main config
	 */
	public function __construct( $config ) {
		$this->logger = LoggerFactory::getInstance( 'CrowdSecLocalAPI' );
		$this->cache = MWObjectCache::getLocalClusterInstance();
		$this->config = $config;
	}

	public static function singleton() {
		if ( self::$instance === null ) {
			self::$instance = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		}

		return self::$instance;
	}

	public static function destroy() {
		self::$instance = null;
	}

	/**
	 * handle lapi url for safe.
	 * @param string $url
	 * @return string
	 */
	private static function apiUrlHandler( string $url ) {
		return str_ends_with( $url, "/" ) ? $url : $url . "/";
	}

	/**
	 * get decision from cache and lapi
	 * @param string $ip
	 * @return string
	 */
	public function getDecision( string $ip ) {
		$cacheEnabled = $this->config->get( 'CrowdSecCache' );
		$cacheTTL = $this->config->get( 'CrowdSecCacheTTL' );

		$this->logDebug( __METHOD__ . ': got request for ip ' . $ip );

		if ( !$cacheEnabled ) {
			$result = $this->requestDecision( $ip );

			$this->logDebug( __METHOD__ . ': [no cache] The result of IP "' . $ip . '" is "' . $result . '".' );
			return $result;
		}

		$cacheKey = $this->getCacheKey( $ip );
		$result = $this->cache->get( $cacheKey );
		// if not found on cache
		if ( $result === false ) {
			$this->logDebug( __METHOD__ . ': The IP "' . $ip . '" wasn\'t found on cache. request decision.' );
			$result = $this->requestDecision( $ip );
			$this->cache->set( $cacheKey, $result, $cacheTTL );
		}

		$this->logDebug( __METHOD__ . ': The result of IP "' . $ip . '" is "' . $result . '".' );
		return $result;
	}

	/**
	 * request decision to local api
	 * @param string $ip
	 * @return string
	 */
	private function requestDecision( string $ip ) {
		$apiKey = $this->config->get( 'CrowdSecAPIKey' );
		$apiUrl = $this->config->get( 'CrowdSecAPIUrl' );

		$webRequest = MWRequestContext::getMain()->getRequest();

		$url = self::apiUrlHandler( $apiUrl ) . 'v1/decisions?scope=ip&ip=' . $ip;
		$options = [
			'method' => 'GET',
		];

		$request = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $url, $options, __METHOD__ );
		$request->setHeader( 'Accept', 'application/json' );
		$request->setHeader( 'X-Api-Key', $apiKey );

		$status = $request->execute();
		if ( !$status->isOK() ) {
			$this->error = 'http';
			$this->logError( $status );
			$this->logDebug( $request->getContent() );
			return false;
		}

		$content = $request->getContent();
		if ( $content === "" || $content === "null" || $content === "[]" ) {
			return "ok";
		}

		$response = MWFormatJson::decode( $content, true );
		if ( !$response ) {
			$this->error = 'json';
			$this->logError( $this->error );
			return false;
		}
		if ( !isset( $response[0] ) || !isset( $response[0]['type'] ) ) {
			$this->error = 'crowdsec-lapi';
			$this->logError( $content );
			return false;
		}

		return $response[0]['type'];
	}

	/**
	 * log error
	 * @param mixed $info
	 */
	private function logError( $info ): void {
		if ( $info instanceof MWStatus ) {
			$errors = $info->getErrorsArray();
			$error = $errors[0][0];
		} elseif ( is_array( $info ) ) {
			$error = json_encode( $info );
		} else {
			$error = $info;
		}

		// phpunit
		if ( defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
			fwrite( STDERR, $info );
		}

		$this->logger->error( 'Unable to validate response: {error}', [ 'error' => $error ] );
	}

	/**
	 * log debug
	 * @param mixed $info
	 */
	private function logDebug( $info ): void {
		// phpunit
		if ( defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
			fwrite( STDERR, $info );
		}

		$this->logger->debug( $info );
	}

	/**
	 * log info
	 * @param mixed $info
	 */
	private function logInfo( $info ): void {
		// phpunit
		if ( defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
			fwrite( STDERR, $info );
		}

		$this->logger->info( $info );
	}

	/**
	 * Get cache key for ip
	 * @param string $ip
	 * @return string
	 */
	protected function getCacheKey( $ip ) {
		return $this->cache->makeKey( 'CrowdSecLocalAPI', 'decision', $ip );
	}
}
