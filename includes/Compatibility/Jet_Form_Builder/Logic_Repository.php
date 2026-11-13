<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

use JFB_Modules\Gateways\Scenarios_Abstract\Scenario_Logic_Repository;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Logic\Pay_Now_Logic;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Logic_Repository extends Scenario_Logic_Repository {
	public function rep_instances(): array {
		return array(
			new Pay_Now_Logic(),
		);
	}
}
