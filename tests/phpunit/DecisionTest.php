<?php

namespace MediaWiki\Extension\CrowdSec\Tests;

use MediaWiki\Extension\CrowdSec\LAPIClient;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrowdSec
 */
class DecisionTest extends \MediaWikiIntegrationTestCase {
	/**
	 * @covers \MediaWiki\Extension\CrowdSec\LAPIClient
	 */
	public function testDecision() {
		$client = LAPIClient::singleton();
		$this->assertSame( "ban", $client->getDecision( "127.0.0.1" ) );
		$this->assertSame( "captcha", $client->getDecision( "127.0.0.2" ) );
		$this->assertSame( "ok", $client->getDecision( "127.0.0.3" ) );
	}
}
