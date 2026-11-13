<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

if ( ! defined( 'WPINC' ) ) {
	die();
}

trait Pay_Now_Connector {
	public static function scenario_id(): string {
		return 'PAY_NOW';
	}
}
