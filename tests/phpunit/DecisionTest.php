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
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrowdSec
 */
class DecisionTest extends \MediaWikiIntegrationTestCase {
	use \MockHttpTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'CrowdSecEnabled' => true,
			'CrowdSecAPIKey' => 'TestKey1',
			'CrowdSecCache' => false,
			'CrowdSecReportOnly' => false,
		] );
	}

	/**
	 * Setup a mock HTTP response for a ban decision.
	 *
	 * @param string $ip The IP address to ban.
	 * @param string $type The type of decision to return.
	 */
	protected function getDecisionClient( $ip, $decision ) {
		$expectedResponse = $decision === "ok" ? '' : json_encode( [
			[
				'id' => 1,
				'origin' => 'test',
				'type' => $decision,
				'scope' => 'Ip',
				'value' => $ip,
				'duration' => "4h0m0s",
				'scenario' => 'test',
			]
		], JSON_UNESCAPED_SLASHES );

		$mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$mockHttpRequest = $this->createMock( \MWHttpRequest::class );
		$mockHttpRequest->method( 'setHeader' )->willReturn( $mockHttpRequest );
		$mockHttpRequest->expects( $this->once() )->method( 'execute' )->willReturn( Status::newGood() );
		$mockHttpRequest->expects( $this->once() )->method( 'getContent' )->willReturn( $expectedResponse );
		$mockHttpRequest->expects( $this->once() )->method( 'getStatus' )->willReturn( Status::newGood() );
		$mockHttpRequestFactory->method( 'create' )->willReturn( $mockHttpRequest );

		return new LAPIClient( MediaWikiServices::getInstance()->getMainConfig(), $mockHttpRequestFactory );
	}

	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testBanDecision() {
		$decision = "ban";
		$ip = "127.0.0.1";
		$this->setupDecision( $ip, $decision );

		$client = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		$this->assertSame( $client->getDecision( $ip ), $decision );
	}

	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testCaptchaDecision() {
		$decision = "captcha";
		$ip = "127.0.0.2";
		$this->setupDecision( $ip, $decision );

		$client = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		$this->assertSame( $client->getDecision( $ip ), $decision );
	}

	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testOkDecision() {
		$decision = "ok";
		$ip = "127.0.0.3";
		$this->setupDecision( $ip, $decision );

		$client = new LAPIClient( MediaWikiServices::getInstance()->getMainConfig() );
		$this->assertSame( $client->getDecision( $ip ), $decision );
	}
}
