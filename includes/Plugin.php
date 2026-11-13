<?php

namespace JFB_Razorpay;

use JFB_Razorpay\Compatibility\Jet_Form_Builder\Manager as JFB_Manager;
use JFB_Razorpay\Admin\Reconciliation;
use JFB_Razorpay\Admin\Razorpay_Details_Box;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Plugin {
	private static $instance = null;
	private $frontend_assets_enqueued = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		JFB_Manager::instance();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_for_checkout_return' ), 20 );
		add_filter( 'jet-form-builder/after-start-form', array( $this, 'maybe_enqueue_for_form' ), 10, 2 );
		add_action( 'jet-form-builder/editor-assets/before', array( $this, 'enqueue_editor_assets' ) );

		if ( is_admin() ) {
			( new Reconciliation() )->init();
			add_filter( 'jet-form-builder/page-containers/jfb-payments-single', array( $this, 'add_razorpay_payment_details_box' ) );
		}
	}


	public function add_razorpay_payment_details_box( array $containers ): array {
		if ( empty( $containers[1] ) || ! method_exists( $containers[1], 'add_meta_box' ) ) {
			return $containers;
		}

		$containers[1]->add_meta_box( new Razorpay_Details_Box() );

		return $containers;
	}

	public function register_frontend_assets(): void {
		wp_register_script(
			'jfb-razorpay-checkout',
			JFB_RAZORPAY_URL . 'assets/js/razorpay-checkout.js',
			array( 'jquery' ),
			JFB_RAZORPAY_VERSION,
			true
		);
	}

	public function maybe_enqueue_for_checkout_return(): void {
		// Nonce is not applicable here: this only detects a short-lived checkout token in the URL.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$razorpay = isset( $_GET['razorpay'] ) && is_array( $_GET['razorpay'] ) ? wp_unslash( $_GET['razorpay'] ) : array();
		$token    = sanitize_text_field( $razorpay['token'] ?? '' );

		if ( $token ) {
			$this->enqueue_frontend_assets();
		}
	}

	public function maybe_enqueue_for_form( string $html, $form_builder ): string {
		$form_id = 0;

		if ( is_object( $form_builder ) && isset( $form_builder->form_id ) ) {
			$form_id = (int) $form_builder->form_id;
		}

		if ( $form_id && $this->form_uses_razorpay( $form_id ) ) {
			$this->enqueue_frontend_assets();
		}

		return $html;
	}

	private function form_uses_razorpay( int $form_id ): bool {
		$gateways = get_post_meta( $form_id, '_jf_gateways', true );

		if ( is_string( $gateways ) ) {
			$decoded = json_decode( $gateways, true );

			if ( JSON_ERROR_NONE === json_last_error() ) {
				$gateways = $decoded;
			}
		}

		if ( ! is_array( $gateways ) ) {
			return false;
		}

		if ( 'razorpay' === ( $gateways['gateway'] ?? '' ) ) {
			return true;
		}

		return isset( $gateways['razorpay'] );
	}

	public function enqueue_frontend_assets(): void {
		if ( $this->frontend_assets_enqueued ) {
			return;
		}

		$this->frontend_assets_enqueued = true;

		wp_enqueue_script( 'jfb-razorpay-checkout' );

		wp_localize_script(
			'jfb-razorpay-checkout',
			'JFBRazorpaySettings',
			array(
				'checkout_endpoint' => esc_url_raw( rest_url( 'jfb-razorpay/v1/checkout' ) ),
				'i18n'              => array(
					'checkout_failed'  => __( 'Razorpay checkout could not be opened. Please try again.', 'jet-form-builder-razorpay-gateway' ),
					'checkout_expired' => __( 'Razorpay checkout session has expired. Please submit the form again.', 'jet-form-builder-razorpay-gateway' ),
					'payment_failed'   => __( 'Payment was not completed. Please try again.', 'jet-form-builder-razorpay-gateway' ),
				),
			)
		);
	}

	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			'jfb-razorpay-editor',
			JFB_RAZORPAY_URL . 'assets/js/editor.js',
			array( 'wp-element', 'wp-components', 'wp-compose', 'wp-data', 'wp-hooks', 'wp-i18n' ),
			JFB_RAZORPAY_VERSION,
			true
		);
	}
}
