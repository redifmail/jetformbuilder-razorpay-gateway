<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder\Logic;

use JFB_Modules\Form_Record\Action_Types\Save_Record;
use Jet_Form_Builder\Db_Queries\Exceptions\Sql_Exception;
use Jet_Form_Builder\Db_Queries\Execution_Builder;
use Jet_Form_Builder\Exceptions\Gateway_Exception;
use Jet_Form_Builder\Exceptions\Query_Builder_Exception;
use Jet_Form_Builder\Exceptions\Repository_Exception;
use JFB_Modules\Gateways\Base_Gateway;
use JFB_Modules\Gateways\Db_Models\Payer_Model;
use JFB_Modules\Gateways\Db_Models\Payer_Shipping_Model;
use JFB_Modules\Gateways\Db_Models\Payment_Meta_Model;
use JFB_Modules\Gateways\Db_Models\Payment_Model;
use JFB_Modules\Gateways\Db_Models\Payment_To_Payer_Shipping_Model;
use JFB_Modules\Gateways\Db_Models\Payment_To_Record;
use JFB_Modules\Gateways\Query_Views\Payment_With_Record_View;
use JFB_Modules\Form_Record\Query_Views\Record_Fields_View;
use JFB_Modules\Gateways\Scenarios_Abstract\Scenario_Logic_Base;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Gateway_Context;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Pay_Now_Connector;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Rest_Endpoints\Rest_Controller;
use Razorpay\Api\Errors\SignatureVerificationError;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Pay_Now_Logic extends Scenario_Logic_Base {
	use Pay_Now_Connector;

	const QUERY_VAR = 'razorpay_order_id';
	const META_SUCCESS_ACTIONS_EXECUTED = 'razorpay_success_actions_executed';

	private $skip_status_processing = false;

	protected function query_token() {
		// Nonce is not applicable here: the query var is returned by Razorpay after checkout.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		return sanitize_text_field( wp_unslash( $_REQUEST[ self::QUERY_VAR ] ?? '' ) );
	}

	public function get_failed_statuses() {
		return array( 'CREATED', 'FAILED', 'VOIDED', 'CANCELLED' );
	}

	/**
	 * @throws Gateway_Exception
	 * @throws Repository_Exception
	 */
	public function after_actions() {
		$this->set_gateway_data();

		$order = $this->create_resource();

		$this->add_context(
			array(
				'payment_id' => $this->save_resource( $order ),
			)
		);

		jet_fb_action_handler()->add_response(
			array(
				'razorpay' => $this->get_checkout_options( $order ),
			)
		);

		Save_Record::add_hidden();

		add_action( 'jet-form-builder/form-handler/after-send', array( $this, 'attach_record_id' ) );
	}

	/**
	 * @throws Gateway_Exception
	 */
	public function set_gateway_data() {
		jet_fb_gateway_current()->set_price_field();
		jet_fb_gateway_current()->set_price_from_filed();
		jet_fb_gateway_current()->set_current_gateway_options();
	}

	/**
	 * @throws Gateway_Exception
	 */
	public function create_resource(): array {
		$controller = jet_fb_gateway_current();
		$amount     = (int) round( $controller->get_price_var() * 100 );
		$currency   = 'INR';

		if ( $amount < 100 ) {
			throw new Gateway_Exception( __( 'Minimum Razorpay payment amount is ₹1.', 'jet-form-builder-razorpay-gateway' ) );
		}

		$request = $this->get_current_request_values();
		$notes   = array_filter(
			array(
				'form_id' => (string) jet_fb_handler()->form_id,
				'user_id' => (string) get_current_user_id(),
				'name'    => $this->get_mapped_value( $request, 'full_name_field', array( 'full_name', 'name', 'your_name', 'customer_name', 'billing_name' ) ),
				'email'   => $this->get_mapped_value( $request, 'email_field', array( 'email', 'your_email', 'customer_email', 'billing_email', 'user_email' ) ),
				'phone'   => $this->get_mapped_value( $request, 'phone_field', array( 'phone', 'mobile', 'contact', 'telephone', 'whatsapp', 'billing_phone' ) ),
			)
		);

		try {
			$order = $controller->api()->order->create(
				array(
					'amount'   => $amount,
					'currency' => $currency,
					'receipt'  => substr( 'jfb_' . jet_fb_handler()->form_id . '_' . wp_generate_password( 12, false, false ), 0, 40 ),
					'notes'    => $notes,
				)
			);
		} catch ( \Exception $exception ) {
			throw new Gateway_Exception( $exception->getMessage() );
		}

		$order = $order->toArray();

		if ( empty( $order['id'] ) ) {
			throw new Gateway_Exception( __( 'Razorpay order was not created.', 'jet-form-builder-razorpay-gateway' ), $order );
		}

		return $order;
	}

	/**
	 * @throws Repository_Exception
	 */
	private function get_checkout_options( array $order ): array {
		$checkout_data = $this->build_checkout_options( $order );
		$token         = Rest_Controller::create_checkout_token( $checkout_data );

		return array(
			'token' => $token,
		);
	}

	/**
	 * Builds full checkout options. These are stored server-side behind a short-lived
	 * token so non-AJAX redirects do not expose or trust raw Razorpay data in the URL.
	 *
	 * @throws Repository_Exception
	 */
	private function build_checkout_options( array $order ): array {
		$controller = jet_fb_gateway_current();
		$return_url = add_query_arg(
			array(
				self::QUERY_VAR => $order['id'],
			),
			$this->get_referrer_url( Base_Gateway::SUCCESS_TYPE )
		);

		return array(
			'key'         => $controller->get_key_id(),
			'amount'      => (int) ( $order['amount'] ?? 0 ),
			'currency'    => strtoupper( $order['currency'] ?? 'INR' ),
			'order_id'    => $order['id'],
			'name'        => get_bloginfo( 'name' ),
			'description' => __( 'Form payment', 'jet-form-builder-razorpay-gateway' ),
			'prefill'     => $this->get_checkout_prefill(),
			'return_url'  => $return_url,
		);
	}

	private function get_checkout_prefill(): array {
		$request = $this->get_current_request_values();
		$name    = $this->get_mapped_value( $request, 'full_name_field', array( 'full_name', 'name', 'your_name', 'customer_name', 'billing_name' ) );
		$email   = $this->get_mapped_value( $request, 'email_field', array( 'email', 'your_email', 'customer_email', 'billing_email', 'user_email' ) );
		$phone   = $this->get_mapped_value( $request, 'phone_field', array( 'phone', 'mobile', 'contact', 'telephone', 'whatsapp', 'billing_phone' ) );

		return array_filter(
			array(
				'name'    => $name,
				'email'   => $email,
				'contact' => $phone,
			)
		);
	}


	private function request_value( string $field ): string {
		if ( ! $field ) {
			return '';
		}

		$value = jet_fb_context()->get_value( $field );

		if ( is_array( $value ) ) {
			$value = implode( ' ', array_filter( $value ) );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * @throws Sql_Exception
	 * @throws Repository_Exception
	 */
	public function attach_record_id(): void {
		$record_id  = jet_fb_action_handler()->get_context( Save_Record::ID, 'id' );
		$payment_id = $this->get_context( 'payment_id' );

		if ( ! $record_id || ! $payment_id ) {
			return;
		}

		( new Payment_To_Record() )->insert(
			array(
				'record_id'  => $record_id,
				'payment_id' => $payment_id,
			)
		);
	}

	/**
	 * @throws Gateway_Exception
	 */
	public function process_after() {
		$current_status = $this->get_scenario_row( 'status' );

		if ( 'COMPLETED' === $current_status ) {
			$this->skip_status_processing = $this->has_success_actions_executed();
			return;
		}

		if ( 'CREATED' !== $current_status ) {
			throw new Gateway_Exception( __( 'Payment was already processed.', 'jet-form-builder-razorpay-gateway' ) );
		}

		$failed_payload = $this->get_failed_return_payload();

		if ( ! empty( $failed_payload ) ) {
			$this->mark_voided( $failed_payload['message'], $failed_payload );
		}

		$payload = $this->get_signature_payload();
		$payload['razorpay_completion_source'] = 'browser_return';

		try {
			jet_fb_gateway_current()->api()->utility->verifyPaymentSignature( $payload );
			$this->assert_payment_status( $payload );
		} catch ( SignatureVerificationError $exception ) {
			$this->mark_voided( $exception->getMessage(), $payload );
		} catch ( Gateway_Exception $exception ) {
			if ( Gateway_Context::is_amount_mismatch_exception( $exception ) ) {
				$this->mark_amount_mismatch( $exception->getMessage(), $payload );
			}

			$this->mark_voided( $exception->getMessage(), $payload );
		} catch ( \Exception $exception ) {
			$this->mark_voided( $exception->getMessage(), $payload );
		}

		try {
			Execution_Builder::instance()->transaction_start();
			$this->save_completed_payment( $payload );
			Execution_Builder::instance()->transaction_commit();
		} catch ( Sql_Exception $exception ) {
			Execution_Builder::instance()->transaction_rollback();
			throw new Gateway_Exception( $exception->getMessage() );
		}
	}


	public function load_scenario_row( array $row ): self {
		$this->queried_row   = $row;
		$this->queried_token = (string) ( $row['initial_transaction_id'] ?? $row['transaction_id'] ?? '' );

		return $this;
	}

	/**
	 * Completes a payment that was already verified through a trusted webhook.
	 *
	 * @throws Gateway_Exception
	 */
	public function complete_verified_payment( array $payload ): string {
		$current_status = $this->get_scenario_row( 'status' );

		if ( 'COMPLETED' === $current_status ) {
			return 'already_completed';
		}

		if ( 'CREATED' !== $current_status ) {
			return 'ignored_status';
		}

		try {
			Execution_Builder::instance()->transaction_start();
			$this->save_completed_payment( $payload );
			Execution_Builder::instance()->transaction_commit();
		} catch ( Sql_Exception $exception ) {
			Execution_Builder::instance()->transaction_rollback();
			throw new Gateway_Exception( $exception->getMessage() );
		}

		return 'completed';
	}

	/**
	 * Prevent duplicate paid actions when both the Razorpay webhook and browser return
	 * arrive for the same payment.
	 *
	 * @throws \Jet_Form_Builder\Exceptions\Action_Exception
	 */
	public function process_status( $type = 'success' ) {
		if ( $this->skip_status_processing ) {
			return;
		}

		if ( 'success' === $type && $this->has_success_actions_executed() ) {
			return;
		}

		parent::process_status( $type );

		if ( 'success' === $type ) {
			$this->mark_success_actions_executed();
		}
	}

	private function get_failed_return_payload(): array {
		// Nonce is not applicable here: data arrives from Razorpay after checkout.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$failed     = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_payment_failed'] ?? '' ) );
		$order_id   = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_order_id'] ?? '' ) );
		$code       = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_error_code'] ?? '' ) );
		$desc       = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_error_description'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		if ( ! $failed ) {
			return array();
		}

		if ( $order_id && $order_id !== $this->get_scenario_row( 'transaction_id' ) ) {
			return array();
		}

		return array(
			'razorpay_order_id' => $order_id,
			'error_code'        => $code,
			'error_description' => $desc,
			'message'           => $desc ?: __( 'Razorpay payment was not completed.', 'jet-form-builder-razorpay-gateway' ),
		);
	}

	/**
	 * @throws Gateway_Exception
	 */
	private function assert_payment_status( array &$payload ): void {
		try {
			$payment = jet_fb_gateway_current()->api()->payment->fetch( $payload['razorpay_payment_id'] );
			$details = method_exists( $payment, 'toArray' ) ? $payment->toArray() : array();
		} catch ( \Exception $exception ) {
			throw new Gateway_Exception( $exception->getMessage(), $payload );
		}

		Gateway_Context::assert_payment_matches_row(
			$details,
			$this->get_scenario_row(),
			(string) $payload['razorpay_order_id']
		);

		$payload['razorpay_payment_status'] = sanitize_text_field( (string) ( $details['status'] ?? '' ) );
		$payload = Gateway_Context::enrich_payload_with_payment_details( $payload, $details );
	}

	/**
	 * @throws Gateway_Exception
	 */
	private function get_signature_payload(): array {
		// Nonce is not applicable here: data arrives from Razorpay after checkout.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$order_id   = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_order_id'] ?? '' ) );
		$payment_id = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_payment_id'] ?? '' ) );
		$signature  = sanitize_text_field( wp_unslash( $_REQUEST['razorpay_signature'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		if ( empty( $order_id ) || empty( $payment_id ) || empty( $signature ) ) {
			throw new Gateway_Exception( __( 'Razorpay payment verification data is missing.', 'jet-form-builder-razorpay-gateway' ) );
		}

		if ( $order_id !== $this->get_scenario_row( 'transaction_id' ) ) {
			throw new Gateway_Exception( __( 'Razorpay order does not match this form payment.', 'jet-form-builder-razorpay-gateway' ) );
		}

		return array(
			'razorpay_order_id'   => $order_id,
			'razorpay_payment_id' => $payment_id,
			'razorpay_signature'  => $signature,
		);
	}

	/**
	 * @throws Gateway_Exception
	 */
	private function mark_amount_mismatch( string $message, array $payload ): void {
		$this->scenario_row(
			array(
				'status' => 'AMOUNT_MISMATCH',
			)
		);

		Gateway_Context::persist_amount_mismatch( $this->get_scenario_row() );

		throw new Gateway_Exception( $message, $payload );
	}

	/**
	 * @throws Gateway_Exception
	 */
	private function mark_voided( string $message, array $payload ): void {
		$this->scenario_row(
			array(
				'status' => 'VOIDED',
			)
		);

		try {
			( new Payment_Model() )->update(
				array(
					'status' => 'VOIDED',
				),
				array(
					'id' => $this->get_scenario_row( 'id' ),
				)
			);
		} catch ( Sql_Exception $exception ) {
			error_log( '[JFB Razorpay] Failed to void payment: ' . $exception->getMessage() );
		}

		throw new Gateway_Exception( $message, $payload );
	}

	/**
	 * @throws Sql_Exception
	 */
	private function save_completed_payment( array $payload ): void {
		( new Payment_Model() )->update(
			array(
				'transaction_id' => $payload['razorpay_payment_id'],
				'status'         => 'COMPLETED',
			),
			array(
				'id' => $this->get_scenario_row( 'id' ),
			)
		);

		$this->scenario_row(
			array(
				'transaction_id' => $payload['razorpay_payment_id'],
				'status'         => 'COMPLETED',
			)
		);

		$this->save_razorpay_meta( $payload );

		$this->save_payer();
	}


	/**
	 * @throws Sql_Exception
	 */
	private function save_razorpay_meta( array $payload ): void {
		$keys = array(
			'razorpay_order_id',
			'razorpay_payment_id',
			'razorpay_payment_status',
			'razorpay_completion_source',
			'razorpay_webhook_event',
			'razorpay_payment_method',
			'razorpay_payment_bank',
			'razorpay_payment_wallet',
			'razorpay_payment_vpa',
			'razorpay_payment_email',
			'razorpay_payment_contact',
			'razorpay_payment_card_id',
			'razorpay_payment_invoice_id',
			'razorpay_payment_rrn',
			'razorpay_payment_upi_transaction_id',
			'razorpay_payment_bank_transaction_id',
			'razorpay_payment_auth_code',
			'razorpay_payment_card_network',
			'razorpay_payment_card_last4',
			'razorpay_payment_fee',
			'razorpay_payment_tax',
			'razorpay_payment_created_at',
			'razorpay_payment_captured',
		);

		foreach ( $keys as $key ) {
			if ( ! isset( $payload[ $key ] ) || '' === (string) $payload[ $key ] ) {
				continue;
			}

			$this->save_payment_meta( $key, sanitize_text_field( (string) $payload[ $key ] ) );
		}
	}

	/**
	 * @throws Sql_Exception
	 */
	private function save_payment_meta( string $key, string $value ): void {
		( new Payment_Meta_Model() )->insert(
			array(
				'payment_id' => $this->get_scenario_row( 'id' ),
				'meta_key'   => $key,
				'meta_value' => $value,
			)
		);
	}

	private function save_payer(): void {
		$request = $this->get_saved_record_request_values();

		if ( empty( $request ) ) {
			$request = $this->get_current_request_values();
		}

		if ( empty( $request ) ) {
			return;
		}

		$full_name = $this->get_mapped_value( $request, 'full_name_field', array( 'full_name', 'name', 'your_name', 'customer_name', 'billing_name' ) );
		$first     = $this->get_mapped_value( $request, 'first_name_field', array( 'first_name', 'fname', 'billing_first_name' ) );
		$last      = $this->get_mapped_value( $request, 'last_name_field', array( 'last_name', 'lname', 'billing_last_name' ) );
		$email     = sanitize_email( $this->get_mapped_value( $request, 'email_field', array( 'email', 'your_email', 'customer_email', 'billing_email', 'user_email' ) ) );
		$phone     = $this->get_mapped_value( $request, 'phone_field', array( 'phone', 'mobile', 'contact', 'telephone', 'whatsapp', 'billing_phone' ) );

		if ( $email && ! is_email( $email ) ) {
			$email = '';
		}

		if ( ! $full_name && ( $first || $last ) ) {
			$full_name = trim( $first . ' ' . $last );
		}

		if ( $full_name && ( ! $first || ! $last ) ) {
			$name_parts = preg_split( '/\s+/', trim( $full_name ), 2 );
			$first      = $first ?: ( $name_parts[0] ?? '' );
			$last       = $last ?: ( $name_parts[1] ?? '' );
		}

		$has_details = array_filter(
			array(
				$full_name,
				$first,
				$last,
				$email,
				$phone,
				$this->get_mapped_value( $request, 'address_line_1_field', array( 'address', 'address_line_1', 'street_address', 'billing_address_1' ) ),
				$this->get_mapped_value( $request, 'city_field', array( 'city', 'billing_city' ) ),
				$this->get_mapped_value( $request, 'postal_code_field', array( 'postal_code', 'postcode', 'zip', 'billing_postcode' ) ),
			)
		);

		if ( empty( $has_details ) ) {
			return;
		}

		try {
			$payer_id = Payer_Model::insert_or_update(
				array(
					'user_id'    => $this->get_scenario_row( 'user_id' ),
					'payer_id'   => $email ?: 'jfb-razorpay-' . $this->get_scenario_row( 'id' ),
					'first_name' => $first,
					'last_name'  => $last,
					'email'      => $email,
				)
			);

			$payer_ship_id = ( new Payer_Shipping_Model() )->insert(
				array(
					'payer_id'       => $payer_id,
					'full_name'      => $full_name,
					'address_line_1' => $this->get_mapped_value( $request, 'address_line_1_field', array( 'address', 'address_line_1', 'street_address', 'billing_address_1' ) ),
					'address_line_2' => $this->get_mapped_value( $request, 'address_line_2_field', array( 'address_line_2', 'billing_address_2' ) ),
					'admin_area_2'   => $this->get_mapped_value( $request, 'city_field', array( 'city', 'billing_city' ) ),
					'admin_area_1'   => $this->get_mapped_value( $request, 'state_field', array( 'state', 'province', 'billing_state' ) ),
					'postal_code'    => $this->get_mapped_value( $request, 'postal_code_field', array( 'postal_code', 'postcode', 'zip', 'billing_postcode' ) ),
					'country_code'   => $this->get_mapped_value( $request, 'country_field', array( 'country', 'billing_country' ) ),
				)
			);

			( new Payment_To_Payer_Shipping_Model() )->insert(
				array(
					'payment_id'        => $this->get_scenario_row( 'id' ),
					'payer_shipping_id' => $payer_ship_id,
				)
			);

			if ( $phone ) {
				$this->save_payment_meta( 'payer_phone', $phone );
			}
		} catch ( \Exception $exception ) {
			error_log( '[JFB Razorpay] Failed to attach payer details: ' . $exception->getMessage() );
			return;
		}
	}

	private function get_current_request_values(): array {
		$request = array();

		if ( function_exists( 'jet_fb_context' ) && method_exists( jet_fb_context(), 'resolve_request' ) ) {
			$request = (array) jet_fb_context()->resolve_request();
		}

		if ( empty( $request ) && function_exists( 'jet_fb_action_handler' ) && isset( jet_fb_action_handler()->request_data ) ) {
			$request = (array) jet_fb_action_handler()->request_data;
		}

		return $request;
	}

	private function get_saved_record_request_values(): array {
		$record = $this->get_scenario_row( 'record', array() );

		if ( ! is_array( $record ) || empty( $record['id'] ) ) {
			return array();
		}

		try {
			return Record_Fields_View::get_request_list( (int) $record['id'] );
		} catch ( \Exception $exception ) {
			return array();
		}
	}

	private function get_mapped_value( array $source, string $setting_key, array $fallback_keys = array() ): string {
		$settings = (array) $this->get_settings();
		$field    = $settings[ $setting_key ] ?? '';

		if ( $field ) {
			$value = $this->array_value( $source, $field );

			if ( '' !== $value ) {
				return $value;
			}
		}

		foreach ( $fallback_keys as $key ) {
			$value = $this->array_value( $source, $key );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	private function array_value( array $source, string $key ): string {
		if ( ! $key || ! isset( $source[ $key ] ) ) {
			return '';
		}

		$value = $source[ $key ];

		if ( is_array( $value ) ) {
			$value = implode( ' ', array_filter( array_map( 'strval', $value ) ) );
		}

		return sanitize_text_field( (string) $value );
	}


	private function has_success_actions_executed(): bool {
		$payment_id = (int) $this->get_scenario_row( 'id', 0 );

		if ( ! $payment_id ) {
			return false;
		}

		global $wpdb;
		$table = Payment_Meta_Model::table();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE payment_id = %d AND meta_key = %s LIMIT 1",
				$payment_id,
				self::META_SUCCESS_ACTIONS_EXECUTED
			)
		);
	}

	private function mark_success_actions_executed(): void {
		if ( $this->has_success_actions_executed() ) {
			return;
		}

		try {
			$this->save_payment_meta( self::META_SUCCESS_ACTIONS_EXECUTED, current_time( 'mysql' ) );
		} catch ( Sql_Exception $exception ) {
			error_log( '[JFB Razorpay] Failed to mark success actions executed: ' . $exception->getMessage() );
		}
	}


	/**
	 * @throws Gateway_Exception
	 */
	protected function query_scenario_row() {
		try {
			return Payment_With_Record_View::findOne(
				array(
					'initial_transaction_id' => $this->get_queried_token(),
				)
			)->query()->query_one();
		} catch ( Query_Builder_Exception $exception ) {
			throw new Gateway_Exception( $exception->getMessage() );
		}
	}

	/**
	 * @throws Gateway_Exception
	 */
	public function save_resource( $resource ) {
		$payment_row = array(
			'transaction_id'         => $resource['id'],
			'initial_transaction_id' => $resource['id'],
			'form_id'                => jet_fb_handler()->form_id,
			'user_id'                => get_current_user_id(),
			'gateway_id'             => jet_fb_gateway_current()->get_id(),
			'scenario'               => self::scenario_id(),
			'amount_value'           => jet_fb_gateway_current()->get_price_var(),
			'amount_code'            => strtoupper( $resource['currency'] ?? 'INR' ),
			'type'                   => Base_Gateway::PAYMENT_TYPE_INITIAL,
			'status'                 => 'CREATED',
		);

		try {
			return ( new Payment_Model() )->insert( $payment_row );
		} catch ( Sql_Exception $exception ) {
			throw new Gateway_Exception( $exception->getMessage() );
		}
	}
}
