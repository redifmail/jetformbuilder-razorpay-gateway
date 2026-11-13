<?php

namespace JFB_Razorpay\Compatibility\Jet_Form_Builder\Tabs;

use Jet_Form_Builder\Admin\Tabs_Handlers\Base_Handler;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Razorpay_Tab extends Base_Handler {
	public function slug() {
		return 'razorpay';
	}

	public function on_get_request() {
		// Nonce and capability checks are handled by Base_Handler::on_raw_request().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$options = array(
			'key_id'         => sanitize_text_field( wp_unslash( $_POST['key_id'] ?? '' ) ),
			'secret'         => sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) ),
			'webhook_secret' => sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->send_response( $this->update_options( $options ) );
	}

	public function on_load() {
		$options = $this->get_options(
			array(
				'key_id'         => '',
				'secret'         => '',
				'webhook_secret' => '',
			)
		);

		return array(
			'key_id'         => $options['key_id'] ?? '',
			'secret'         => $options['secret'] ?? '',
			'webhook_secret' => $options['webhook_secret'] ?? '',
		);
	}

	public function before_assets() {
		wp_enqueue_script(
			'jfb-razorpay-admin-settings',
			JFB_RAZORPAY_URL . 'assets/js/admin-settings.js',
			array( 'wp-hooks', 'wp-i18n' ),
			JFB_RAZORPAY_VERSION,
			true
		);

		wp_localize_script(
			'jfb-razorpay-admin-settings',
			'JFBRazorpayAdminSettings',
			array(
				'webhook_endpoint' => esc_url_raw( rest_url( 'jfb-razorpay/v1/webhook' ) ),
			)
		);
	}

	public static function get_credentials(): array {
		$options = ( new self() )->on_load();

		return array(
			'key_id'         => $options['key_id'] ?? '',
			'secret'         => $options['secret'] ?? '',
			'webhook_secret' => $options['webhook_secret'] ?? '',
		);
	}
}
