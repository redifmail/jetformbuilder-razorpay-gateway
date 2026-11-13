<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

use Jet_Form_Builder\Admin\Tabs_Handlers\Tab_Handler_Manager;
use JFB_Modules\Gateways\Module;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Rest_Endpoints\Rest_Controller;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Tabs\Razorpay_Tab;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Manager {
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'jet-form-builder/register-tabs-handlers', array( $this, 'register_tab_handler' ) );
		add_action( 'init', array( $this, 'ensure_tab_handler' ), 20 );
		add_action( 'jet-form-builder/gateways/register', array( $this, 'register_gateway' ) );

		( new Rest_Controller() )->rest_api_init();
	}

	public function register_tab_handler( array $tabs ): array {
		$tabs[] = new Razorpay_Tab();

		return $tabs;
	}

	public function ensure_tab_handler(): void {
		if ( ! class_exists( Tab_Handler_Manager::class ) ) {
			return;
		}

		Tab_Handler_Manager::instance()->install( new Razorpay_Tab() );
	}

	public function register_gateway( Module $manager ): void {
		$manager->register_gateway( new Controller() );
	}
}
