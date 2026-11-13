<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

use JFB_Modules\Gateways\Scenarios_Abstract\Scenarios_View_Repository;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Views\Pay_Now_View;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class View_Repository extends Scenarios_View_Repository {
	public function rep_instances(): array {
		return array(
			new Pay_Now_View(),
		);
	}
}
