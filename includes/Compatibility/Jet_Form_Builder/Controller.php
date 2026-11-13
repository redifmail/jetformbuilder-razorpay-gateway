<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

use Jet_Form_Builder\Classes\Tools;
use Jet_Form_Builder\Exceptions\Gateway_Exception;
use Jet_Form_Builder\Exceptions\Repository_Exception;
use JFB_Modules\Gateways\Base_Scenario_Gateway;
use JFB_Modules\Gateways\Scenarios_Abstract\Scenario_Logic_Base;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Logic\Pay_Now_Logic;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Tabs\Razorpay_Tab;
use Razorpay\Api\Api;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Controller extends Base_Scenario_Gateway {
	protected $token_query_name = 'razorpay_order_id';

	public function get_id() {
		return 'razorpay';
	}

	public function get_name() {
		return __( 'Razorpay', 'jet-form-builder-razorpay-gateway' );
	}

	protected function options_list() {
		return array(
			'key_id'       => array(
				'label'    => __( 'Key ID', 'jet-form-builder-razorpay-gateway' ),
				'required' => false,
			),
			'secret'       => array(
				'label'    => __( 'Key Secret', 'jet-form-builder-razorpay-gateway' ),
				'required' => false,
			),
			'gateway_type' => array(
				'label'   => __( 'Gateway Action', 'jet-form-builder-razorpay-gateway' ),
				'default' => Pay_Now_Logic::scenario_id(),
			),
		);
	}

	public function get_scenario(): Scenario_Logic_Base {
		return Scenarios_Manager::instance()->get_logic( $this );
	}

	public function query_scenario(): Scenario_Logic_Base {
		return Scenarios_Manager::instance()->query_logic();
	}

	public function custom_labels(): array {
		return array(
			'scenario' => Scenarios_Manager::instance()->view()->get_editor_labels(),
		);
	}

	public function additional_editor_data(): array {
		return array_merge(
			array(
				'version'   => 1,
				'scenarios' => Tools::with_placeholder(
					Scenarios_Manager::instance()->view()->get_items_list(),
					__( 'Choose scenario...', 'jet-form-builder-razorpay-gateway' )
				),
			),
			Scenarios_Manager::instance()->view()->get_editor_data()
		);
	}

	public function get_current_scenario_id(): string {
		return Pay_Now_Logic::scenario_id();
	}

	public function required_credentials_fields(): array {
		return array();
	}

	public static function get_credentials(): array {
		return Razorpay_Tab::get_credentials();
	}

	public function get_active_credentials(): array {
		$current = $this->current_gateway( '', array() );

		if ( ! empty( $current['use_global'] ) ) {
			return self::get_credentials();
		}

		if ( empty( $current['key_id'] ) && empty( $current['secret'] ) ) {
			return self::get_credentials();
		}

		return array(
			'key_id' => $current['key_id'] ?? '',
			'secret' => $current['secret'] ?? '',
		);
	}

	/**
	 * @throws Gateway_Exception
	 */
	public function api(): Api {
		$credentials = $this->get_active_credentials();

		if ( empty( $credentials['key_id'] ) || empty( $credentials['secret'] ) ) {
			throw new Gateway_Exception( __( 'Razorpay credentials are empty.', 'jet-form-builder-razorpay-gateway' ) );
		}

		return new Api( $credentials['key_id'], $credentials['secret'] );
	}

	public function get_key_id(): string {
		$credentials = $this->get_active_credentials();

		return (string) ( $credentials['key_id'] ?? '' );
	}

	protected function get_price( $price ) {
		return round( (float) $price, 2 );
	}

	protected function retrieve_gateway_meta() {
		return $this->query_scenario()->get_gateways_meta();
	}
}
