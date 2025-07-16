<?php

/**
 * LAPIClient Decision Request Test.
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

namespace MediaWiki\Extension\CrowdSec\Tests;

use MediaWiki\Extension\CrowdSec\LAPIClient;
use MediaWiki\MediaWikiServices;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrowdSec
 */
class DecisionTest extends \MediaWikiIntegrationTestCase {
	use \MockHttpTrait;

	/**
	 * Setup a mock HTTP response for a ban decision.
	 *
	 * @param string $ip The IP address to ban.
	 * @param string $type The type of decision to return.
	 */
	protected function setupDecision( string $ip, string $type ) {
		if ( $type === "ok" ) {
			$this->installMockHttp(
				$this->makeFakeHttpRequest( '' )
			);
			return;
		}

		$this->installMockHttp(
			$this->makeFakeHttpRequest( json_encode( [
					[
						'id' => 1,
						'origin' => 'test',
						'type' => $type,
						'scope' => 'Ip',
						'value' => $ip,
						'duration' => "4h0m0s",
						'scenario' => 'test',
						'simulated' => true,
					]
				], JSON_UNESCAPED_SLASHES ),
			)
		);
	}

	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testBanDecision() {
		$decision = "ban";
		$this->setupDecision( "127.0.0.1", $decision );

		$client = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		$this->assertSame( $client->getDecision( "127.0.0.1" ), $decision );
	}

	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testCaptchaDecision() {
		$decision = "captcha";
		$this->setupDecision( "127.0.0.2", $decision );

		$client = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		$this->assertSame( $client->getDecision( "127.0.0.2" ), $decision );
	}

	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testOkDecision() {
		$decision = "ok";
		$this->setupDecision( "127.0.0.3", $decision );

		$client = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		$this->assertSame( $client->getDecision( "127.0.0.3" ), $decision );
	}
}
