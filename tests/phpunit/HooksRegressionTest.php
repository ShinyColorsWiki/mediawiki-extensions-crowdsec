<?php

namespace MediaWiki\Extension\CrowdSec\Tests;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CrowdSec\AbuseFilterHookHandler;
use MediaWiki\Extension\CrowdSec\Hooks;
use MediaWiki\Extension\CrowdSec\LAPIClient;
use MediaWiki\User\User;

/**
 * @covers \MediaWiki\Extension\CrowdSec\Hooks
 * @covers \MediaWiki\Extension\CrowdSec\AbuseFilterHookHandler
 */
class HooksRegressionTest extends \MediaWikiIntegrationTestCase {
	public function testAbuseFilterForDifferentUserReturnsUnknown() {
		$user = $this->createMock( User::class );
		$user->method( 'getName' )->willReturn( 'Not the current request user' );
		$handler = new AbuseFilterHookHandler( new HashConfig( [] ) );
		$result = null;
		$this->assertFalse( $handler->onAbuseFilter_computeVariable(
			'crowdsec-decision', null, [ 'user' => $user ], $result
		) );
		$this->assertSame( 'unknown', $result );
	}

	public function testAbuseFilterForCurrentUserUsesRequestIp() {
		$user = RequestContext::getMain()->getUser();
		$client = $this->createMock( LAPIClient::class );
		$client->expects( $this->once() )->method( 'getDecision' )
			->with( RequestContext::getMain()->getRequest()->getIP() )->willReturn( 'ban' );
		$handler = new AbuseFilterHookHandler( new HashConfig( [] ) );
		$this->setClient( $handler, $client );
		$result = null;
		$this->assertFalse( $handler->onAbuseFilter_computeVariable(
			'crowdsec-decision', null, [ 'user' => $user ], $result
		) );
		$this->assertSame( 'ban', $result );
	}

	/** @dataProvider provideBlockLogDecisions */
	public function testBlockLogDecisions( $decision, array $treatedAsBan, $reportOnly, $expectedCount ) {
		$hooks = new Hooks( new HashConfig( [
			'CrowdSecEnable' => true,
			'CrowdSecAPIKey' => 'test-key',
			'CrowdSecAPIUrl' => 'http://unused.invalid',
			'CrowdSecReportOnly' => $reportOnly,
			'CrowdSecTreatTypesAsBan' => $treatedAsBan,
		] ) );
		$client = $this->createMock( LAPIClient::class );
		$client->expects( $reportOnly ? $this->never() : $this->once() )
			->method( 'getDecision' )->with( '192.0.2.1' )->willReturn( $decision );
		$this->setClient( $hooks, $client );
		$messages = [];
		$this->assertTrue( $hooks->onOtherBlockLogLink( $messages, '192.0.2.1' ) );
		$this->assertCount( $expectedCount, $messages );
	}

	public static function provideBlockLogDecisions() {
		return [
			'ban' => [ 'ban', [], false, 1 ],
			'configured captcha' => [ 'captcha', [ 'captcha' ], false, 1 ],
			'ignored captcha' => [ 'captcha', [], false, 0 ],
			'allowed' => [ 'ok', [], false, 0 ],
			'failure' => [ false, [], false, 0 ],
			'report only' => [ 'ban', [], true, 0 ],
		];
	}

	private function setClient( $handler, LAPIClient $client ) {
		$property = new \ReflectionProperty( $handler, 'lapiClient' );
		$property->setAccessible( true );
		$property->setValue( $handler, $client );
	}
}
