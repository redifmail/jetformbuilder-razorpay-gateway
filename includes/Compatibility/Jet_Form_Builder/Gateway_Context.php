<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder;

use Jet_Form_Builder\Exceptions\Gateway_Exception;
use JFB_Modules\Gateways\Db_Models\Payment_Model;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Gateway_Context {
	const ERROR_CODE_AMOUNT_MISMATCH = 1001;

	/**
	 * Prepares JetFormBuilder's Razorpay gateway controller for recovery paths.
	 *
	 * Browser-return processing is handled by JetFormBuilder directly, but webhook
	 * and admin reconciliation run outside the normal form request. They need the
	 * gateway module to load the correct per-form gateway settings before success
	 * actions can be resumed.
	 *
	 * Compatibility note: this recovery path uses JetFormBuilder's internal
	 * JFB_Modules\Gateways\Module API. It was tested with JetFormBuilder 3.6.4.1
	 * and should be rechecked after major JetFormBuilder gateway-module changes.
	 *
	 * @throws Gateway_Exception
	 */
	public static function prepare_controller_for_form( int $form_id ) {
		if ( ! $form_id || ! class_exists( '\\JFB_Modules\\Gateways\\Module' ) ) {
			throw new Gateway_Exception( __( 'JetFormBuilder gateway module is not available.', 'jet-form-builder-razorpay-gateway' ) );
		}

		try {
			$module     = \JFB_Modules\Gateways\Module::instance();
			$module->set_gateways_options_by_form_id( $form_id );
			$gateways   = $module->gateways();
			$controller = $module->get_gateway_controller( 'razorpay' );
			$controller->set_form_meta( $module->with_global_settings( $gateways, 'razorpay' ) );
			$controller->set_current_gateway_options();

			return $controller;
		} catch ( \Throwable $exception ) {
			throw new Gateway_Exception( $exception->getMessage() );
		}
	}

	/**
	 * Verifies a Razorpay payment entity belongs to the expected JFB payment row.
	 *
	 * @throws Gateway_Exception
	 */
	public static function assert_payment_matches_row( array $payment, array $row, string $expected_order_id ): void {
		$status = sanitize_text_field( (string) ( $payment['status'] ?? '' ) );

		if ( ! in_array( $status, array( 'captured', 'authorized' ), true ) ) {
			throw new Gateway_Exception( sprintf( __( 'Razorpay payment is not in a valid state: %s.', 'jet-form-builder-razorpay-gateway' ), $status ?: __( 'unknown', 'jet-form-builder-razorpay-gateway' ) ), $payment );
		}

		$order_id = sanitize_text_field( (string) ( $payment['order_id'] ?? '' ) );

		if ( $order_id !== $expected_order_id ) {
			throw new Gateway_Exception( __( 'Razorpay payment does not belong to the expected order.', 'jet-form-builder-razorpay-gateway' ), $payment );
		}

		$expected_amount = (int) round( (float) ( $row['amount_value'] ?? 0 ) * 100 );
		$actual_amount   = (int) ( $payment['amount'] ?? 0 );

		if ( $expected_amount > 0 && $actual_amount !== $expected_amount ) {
			throw ( new Gateway_Exception( __( 'Razorpay payment amount does not match the JetFormBuilder payment.', 'jet-form-builder-razorpay-gateway' ), $payment ) )
				->set_code( self::ERROR_CODE_AMOUNT_MISMATCH );
		}

		$expected_currency = strtoupper( (string) ( $row['amount_code'] ?? 'INR' ) );
		$actual_currency   = strtoupper( (string) ( $payment['currency'] ?? '' ) );

		if ( $expected_currency && $actual_currency && $actual_currency !== $expected_currency ) {
			throw ( new Gateway_Exception( __( 'Razorpay payment currency does not match the JetFormBuilder payment.', 'jet-form-builder-razorpay-gateway' ), $payment ) )
				->set_code( self::ERROR_CODE_AMOUNT_MISMATCH );
		}
	}

	public static function is_amount_mismatch_exception( \Throwable $exception ): bool {
		return self::ERROR_CODE_AMOUNT_MISMATCH === (int) $exception->getCode();
	}

	public static function persist_amount_mismatch( array $row ): void {
		$payment_id = (int) ( $row['id'] ?? 0 );

		if ( ! $payment_id ) {
			return;
		}

		try {
			( new Payment_Model() )->update(
				array(
					'status' => 'AMOUNT_MISMATCH',
				),
				array(
					'id' => $payment_id,
				)
			);
		} catch ( \Throwable $exception ) {
			error_log( '[JFB Razorpay] Failed to persist amount mismatch: ' . $exception->getMessage() );
		}
	}

	public static function enrich_payload_with_payment_details( array $payload, array $payment ): array {
		return array_merge( $payload, self::payment_details_to_meta_payload( $payment ) );
	}

	public static function payment_details_to_meta_payload( array $payment ): array {
		$currency = strtoupper( sanitize_text_field( (string) ( $payment['currency'] ?? 'INR' ) ) );
		$acquirer = isset( $payment['acquirer_data'] ) && is_array( $payment['acquirer_data'] ) ? $payment['acquirer_data'] : array();
		$card     = isset( $payment['card'] ) && is_array( $payment['card'] ) ? $payment['card'] : array();
		$payload  = array();

		$map = array(
			'razorpay_payment_method'              => $payment['method'] ?? '',
			'razorpay_payment_bank'                => $payment['bank'] ?? '',
			'razorpay_payment_wallet'              => $payment['wallet'] ?? '',
			'razorpay_payment_vpa'                 => $payment['vpa'] ?? '',
			'razorpay_payment_email'               => $payment['email'] ?? '',
			'razorpay_payment_contact'             => $payment['contact'] ?? '',
			'razorpay_payment_card_id'             => $payment['card_id'] ?? '',
			'razorpay_payment_invoice_id'          => $payment['invoice_id'] ?? '',
			'razorpay_payment_rrn'                 => $acquirer['rrn'] ?? '',
			'razorpay_payment_upi_transaction_id'  => $acquirer['upi_transaction_id'] ?? '',
			'razorpay_payment_bank_transaction_id' => $acquirer['bank_transaction_id'] ?? '',
			'razorpay_payment_auth_code'           => $acquirer['auth_code'] ?? '',
			'razorpay_payment_card_network'        => $card['network'] ?? '',
			'razorpay_payment_card_last4'          => $card['last4'] ?? '',
		);

		foreach ( $map as $key => $value ) {
			$value = sanitize_text_field( (string) $value );

			if ( '' !== $value ) {
				$payload[ $key ] = $value;
			}
		}

		foreach ( array( 'fee', 'tax' ) as $amount_key ) {
			if ( ! isset( $payment[ $amount_key ] ) || '' === $payment[ $amount_key ] ) {
				continue;
			}

			$amount = (float) $payment[ $amount_key ] / 100;
			$payload[ 'razorpay_payment_' . $amount_key ] = trim( number_format_i18n( $amount, 2 ) . ' ' . $currency );
		}

		if ( isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] ) ) {
			$payload['razorpay_payment_created_at'] = date_i18n( 'M j, Y, H:i:s', (int) $payment['created_at'] );
		}

		if ( isset( $payment['captured'] ) ) {
			$payload['razorpay_payment_captured'] = ! empty( $payment['captured'] ) ? 'yes' : 'no';
		}

		return $payload;
	}
}
