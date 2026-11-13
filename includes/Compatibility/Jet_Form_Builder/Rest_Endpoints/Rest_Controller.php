<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder\Rest_Endpoints;

use Jet_Form_Builder\Exceptions\Gateway_Exception;
use Jet_Form_Builder\Exceptions\Query_Builder_Exception;
use JFB_Modules\Gateways\Query_Views\Payment_With_Record_View;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Gateway_Context;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Logic\Pay_Now_Logic;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Tabs\Razorpay_Tab;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Rest_Controller {
	const NAMESPACE        = 'jfb-razorpay/v1';
	const CHECKOUT_ROUTE   = '/checkout';
	const WEBHOOK_ROUTE    = '/webhook';
	const TRANSIENT_PREFIX = 'jfb_razorpay_checkout_';

	public function rest_api_init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::CHECKOUT_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::WEBHOOK_ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function checkout( \WP_REST_Request $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$data  = self::get_checkout_data( $token );

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error(
				'jfb_razorpay_checkout_expired',
				__( 'Razorpay checkout session has expired. Please submit the form again.', 'jet-form-builder-razorpay-gateway' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $data );
	}

	public function webhook( \WP_REST_Request $request ) {
		$credentials = Razorpay_Tab::get_credentials();
		$webhook_secret = (string) ( $credentials['webhook_secret'] ?? '' );

		if ( '' === $webhook_secret ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'disabled',
					'message' => __( 'Razorpay webhook secret is not configured.', 'jet-form-builder-razorpay-gateway' ),
				),
				400
			);
		}

		if ( empty( $credentials['key_id'] ) || empty( $credentials['secret'] ) ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'configuration_error',
					'message' => __( 'Razorpay API credentials are not configured.', 'jet-form-builder-razorpay-gateway' ),
				),
				500
			);
		}

		$body      = (string) $request->get_body();
		$signature = sanitize_text_field( (string) $request->get_header( 'x-razorpay-signature' ) );

		if ( '' === $body || '' === $signature ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'bad_request',
					'message' => __( 'Razorpay webhook body or signature is missing.', 'jet-form-builder-razorpay-gateway' ),
				),
				400
			);
		}

		try {
			$api = new Api( $credentials['key_id'], $credentials['secret'] );
			$api->utility->verifyWebhookSignature( $body, $signature, $webhook_secret );
		} catch ( SignatureVerificationError $exception ) {
			error_log( '[JFB Razorpay] Webhook signature verification failed: ' . $exception->getMessage() );

			return new \WP_REST_Response(
				array(
					'status' => 'invalid_signature',
				),
				401
			);
		} catch ( \Exception $exception ) {
			error_log( '[JFB Razorpay] Webhook verification failed: ' . $exception->getMessage() );

			return new \WP_REST_Response(
				array(
					'status' => 'verification_error',
				),
				500
			);
		}

		$event = json_decode( $body, true );

		if ( ! is_array( $event ) ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'bad_request',
					'message' => __( 'Invalid Razorpay webhook JSON.', 'jet-form-builder-razorpay-gateway' ),
				),
				400
			);
		}

		$event_name = sanitize_text_field( (string) ( $event['event'] ?? '' ) );

		if ( ! in_array( $event_name, array( 'payment.captured', 'payment.authorized' ), true ) ) {
			return new \WP_REST_Response(
				array(
					'status' => 'ok',
					'action' => 'ignored_event',
					'event'  => $event_name,
				),
				200
			);
		}

		$payment_entity = (array) ( $event['payload']['payment']['entity'] ?? array() );
		$payment_id     = sanitize_text_field( (string) ( $payment_entity['id'] ?? '' ) );
		$order_id       = sanitize_text_field( (string) ( $payment_entity['order_id'] ?? '' ) );

		if ( '' === $payment_id || '' === $order_id ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'ok',
					'action'  => 'ignored_missing_payment_data',
					'event'   => $event_name,
				),
				200
			);
		}

		$row = $this->find_payment_by_order_id( $order_id );

		if ( empty( $row ) ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'ok',
					'action'  => 'payment_not_found',
					'order_id' => $order_id,
				),
				200
			);
		}

		try {
			$controller = Gateway_Context::prepare_controller_for_form( (int) ( $row['form_id'] ?? 0 ) );
			$fetched    = $controller->api()->payment->fetch( $payment_id );
			$details    = method_exists( $fetched, 'toArray' ) ? $fetched->toArray() : array();

			Gateway_Context::assert_payment_matches_row( $details, $row, $order_id );

			$logic   = ( new Pay_Now_Logic() )->load_scenario_row( $row );
			$payload = Gateway_Context::enrich_payload_with_payment_details(
				array(
					'razorpay_order_id'          => $order_id,
					'razorpay_payment_id'        => $payment_id,
					'razorpay_payment_status'    => sanitize_text_field( (string) ( $details['status'] ?? '' ) ),
					'razorpay_completion_source' => 'webhook',
					'razorpay_webhook_event'     => $event_name,
				),
				$details
			);
			$result = $logic->complete_verified_payment( $payload );

			if ( 'completed' === $result ) {
				$logic->init_request();
				$logic->init_actions();
				$logic->process_status( 'success' );
				jet_fb_action_handler()->set_current_action( false );
				do_action( 'jet-form-builder/gateways/before-send', 'success', false, $logic );
			}
		} catch ( Gateway_Exception $exception ) {
			if ( Gateway_Context::is_amount_mismatch_exception( $exception ) ) {
				Gateway_Context::persist_amount_mismatch( $row );
				error_log( '[JFB Razorpay] Webhook amount mismatch: ' . $exception->getMessage() );

				return new \WP_REST_Response(
					array(
						'status' => 'ok',
						'action' => 'amount_mismatch',
					),
					200
				);
			}

			error_log( '[JFB Razorpay] Webhook processing failed: ' . $exception->getMessage() );

			return new \WP_REST_Response(
				array(
					'status' => 'processing_error',
				),
				500
			);
		} catch ( \Throwable $exception ) {
			error_log( '[JFB Razorpay] Webhook processing failed: ' . $exception->getMessage() );

			return new \WP_REST_Response(
				array(
					'status' => 'processing_error',
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'status'     => 'ok',
				'action'     => $result,
				'order_id'   => $order_id,
				'payment_id' => $payment_id,
			),
			200
		);
	}

	public static function create_checkout_token( array $checkout_data, int $expiration = 1200 ): string {
		$token = wp_generate_password( 32, false, false );

		set_transient(
			self::transient_key( $token ),
			$checkout_data,
			$expiration
		);

		return $token;
	}

	public static function get_checkout_data( string $token ): array {
		if ( '' === $token ) {
			return array();
		}

		$data = get_transient( self::transient_key( $token ) );

		return is_array( $data ) ? $data : array();
	}

	private static function transient_key( string $token ): string {
		return self::TRANSIENT_PREFIX . md5( $token );
	}

	private function find_payment_by_order_id( string $order_id ): array {
		try {
			return Payment_With_Record_View::findOne(
				array(
					'initial_transaction_id' => $order_id,
				)
			)->query()->query_one();
		} catch ( Query_Builder_Exception $exception ) {
			return array();
		}
	}

}