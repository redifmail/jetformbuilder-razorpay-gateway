<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder\Views;

use JFB_Modules\Gateways\Scenarios_Abstract\Scenario_View_Base;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Pay_Now_Connector;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Pay_Now_View extends Scenario_View_Base {
	use Pay_Now_Connector;

	public function get_title(): string {
		return _x( 'Pay Now', 'Razorpay gateway editor data', 'jet-form-builder-razorpay-gateway' );
	}

	public function get_editor_labels(): array {
		return array();
	}
}
