<?php

namespace JFB_Razorpay\Admin;

use Jet_Form_Builder\Admin\Exceptions\Empty_Box_Exception;
use Jet_Form_Builder\Admin\Single_Pages\Meta_Boxes\Base_List_Box;
use JFB_Modules\Gateways\Db_Models\Payment_Meta_Model;
use JFB_Modules\Gateways\Db_Models\Payment_Model;
use JFB_Razorpay\Admin\Payment_Details\Detail_Column;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Razorpay_Details_Box extends Base_List_Box {
	private $details = null;

	public function get_title(): string {
		return __( 'Razorpay Details', 'jet-form-builder-razorpay-gateway' );
	}

	public function get_columns(): array {
		$labels = array(
			'razorpay_payment_id'              => __( 'Payment ID', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_order_id'                => __( 'Order ID', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_status'          => __( 'Razorpay Status', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_method'          => __( 'Method', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_vpa'             => __( 'UPI VPA', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_bank'            => __( 'Bank', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_wallet'          => __( 'Wallet', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_rrn'             => __( 'Bank RRN', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_upi_transaction_id' => __( 'UPI Transaction ID', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_bank_transaction_id' => __( 'Bank Transaction ID', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_auth_code'       => __( 'Auth Code', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_card_id'         => __( 'Card ID', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_card_network'    => __( 'Card Network', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_card_last4'      => __( 'Card Last 4', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_invoice_id'      => __( 'Invoice ID', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_fee'             => __( 'Razorpay Fee', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_tax'             => __( 'Razorpay Tax', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_created_at'      => __( 'Razorpay Created', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_payment_captured'        => __( 'Captured', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_completion_source'       => __( 'Completion Source', 'jet-form-builder-razorpay-gateway' ),
			'razorpay_webhook_event'           => __( 'Webhook Event', 'jet-form-builder-razorpay-gateway' ),
		);

		$details = $this->get_details();
		$columns = array();

		foreach ( $labels as $key => $label ) {
			if ( ! isset( $details[ $key ] ) || '' === $details[ $key ] ) {
				continue;
			}

			$columns[ $key ] = new Detail_Column( $key, $label );
		}

		return $columns;
	}

	/**
	 * @return array
	 * @throws Empty_Box_Exception
	 */
	public function get_list(): array {
		$details = $this->get_details();

		if ( empty( $details ) ) {
			throw new Empty_Box_Exception( esc_html__( 'No Razorpay details found for this payment.', 'jet-form-builder-razorpay-gateway' ) );
		}

		return $details;
	}

	/**
	 * @return array<string,string>
	 * @throws Empty_Box_Exception
	 */
	private function get_details(): array {
		if ( null !== $this->details ) {
			return $this->details;
		}

		global $wpdb;

		$payment_id = $this->get_id();

		if ( ! $payment_id ) {
			throw new Empty_Box_Exception( esc_html__( 'Payment ID is empty.', 'jet-form-builder-razorpay-gateway' ) );
		}

		$payments_table = Payment_Model::table();
		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, gateway_id, transaction_id FROM {$payments_table} WHERE id = %d LIMIT 1",
				$payment_id
			),
			ARRAY_A
		);

		if ( empty( $payment ) || 'razorpay' !== ( $payment['gateway_id'] ?? '' ) ) {
			throw new Empty_Box_Exception( esc_html__( 'This payment was not processed by Razorpay.', 'jet-form-builder-razorpay-gateway' ) );
		}

		$meta_table = Payment_Meta_Model::table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$meta_table} WHERE payment_id = %d AND meta_key LIKE %s ORDER BY id ASC",
				$payment_id,
				'razorpay_%'
			),
			ARRAY_A
		);

		$details = array();

		foreach ( (array) $rows as $row ) {
			$key = sanitize_key( (string) ( $row['meta_key'] ?? '' ) );

			if ( '' === $key ) {
				continue;
			}

			$details[ $key ] = sanitize_text_field( (string) ( $row['meta_value'] ?? '' ) );
		}

		if ( empty( $details['razorpay_payment_id'] ) && ! empty( $payment['transaction_id'] ) ) {
			$details['razorpay_payment_id'] = sanitize_text_field( (string) $payment['transaction_id'] );
		}

		$this->details = array_filter(
			$details,
			static function ( $value ): bool {
				return '' !== (string) $value;
			}
		);

		if ( empty( $this->details ) ) {
			throw new Empty_Box_Exception( esc_html__( 'No Razorpay details found for this payment.', 'jet-form-builder-razorpay-gateway' ) );
		}

		return $this->details;
	}
}
