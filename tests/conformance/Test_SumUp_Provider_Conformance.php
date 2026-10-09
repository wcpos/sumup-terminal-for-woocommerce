<?php
/**
 * Pro's money-path lessons against the real SumUp adapter over a scripted SumUp.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance;

use WCPOS\WooCommercePOSPro\Tests\Conformance\Conformance_Fixture;
use WCPOS\WooCommercePOSPro\Tests\Conformance\Provider_Conformance_Test_Case;

require_once __DIR__ . '/SumUp_Conformance_Fixture.php';

/** The transcripts in ./transcripts are the certified record; a change there is a re-certification. */
class Test_SumUp_Provider_Conformance extends Provider_Conformance_Test_Case {
	protected function fixture(): Conformance_Fixture {
		return new SumUp_Conformance_Fixture();
	}
}
