<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

use JFB_Modules\Gateways\Scenarios_Abstract\Scenarios_Manager_Abstract;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Scenarios_Manager extends Scenarios_Manager_Abstract {
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		$this->set_logic_manager( new Logic_Repository() );
		$this->set_view_manager( new View_Repository() );

		parent::__construct();
	}
}
