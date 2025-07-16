<?php

/**
 * CrowdSec Mock HTTP Trait.
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

use MockHttpTrait;

trait CrowdSecMockHttpTrait {
	use MockHttpTrait;

	/**
	 * Setup a mock HTTP response for a ban decision.
	 *
	 * @param string $ip The IP address to ban.
	 * @param string $decision The decision to return.
	 */
	protected function setupBanDecision( string $ip, string $decision ) {
		$this->installMockHttp(
			$this->makeFakeHttpMultiClient( [ [
				'code' => 200,
				'body' => FormatJson::encode( [
					[
						'id' => 1,
						'uuid' => '00000000-0000-0000-0000-000000000000',
						'origin' => 'test',
						'type' => $decision,
						'scope' => 'ip',
						'value' => $ip,
						'duration' => 3600,
						'until' => time() + 3600,
						'scenario' => 'test',
						'simulated' => true,
					]
				] ),
			] ] )
		);
	}

}
