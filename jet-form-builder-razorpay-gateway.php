<?php
/**
 * Plugin Name: JetFormBuilder Razorpay Gateway
 * Description: Razorpay payment gateway integration for JetFormBuilder.
 * Plugin URI:  https://www.mkdeesigns.com/
 * Version:     0.3.0
 * Author:      MK Designs
 * Author URI:  https://www.mkdeesigns.com/
 * Text Domain: jet-form-builder-razorpay-gateway
 * Requires Plugins: jetformbuilder
 * Requires PHP: 7.4
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

define( 'JFB_RAZORPAY_VERSION', '0.3.0' );
define( 'JFB_RAZORPAY_FILE', __FILE__ );
define( 'JFB_RAZORPAY_BASE', plugin_basename( __FILE__ ) );
define( 'JFB_RAZORPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'JFB_RAZORPAY_URL', plugins_url( '/', __FILE__ ) );

add_action( 'plugins_loaded', 'jfb_razorpay_gateway_init', 100 );

function jfb_razorpay_gateway_init(): void {
	if ( ! jfb_razorpay_check_php_version() ) {
		return;
	}

	if ( ! jfb_razorpay_check_jetformbuilder() ) {
		return;
	}

	require_once JFB_RAZORPAY_PATH . 'vendor/razorpay/Razorpay.php';
	require_once JFB_RAZORPAY_PATH . 'includes/autoloader.php';

	\JFB_Razorpay\Autoloader::run();
	\JFB_Razorpay\Plugin::instance();
}

function jfb_razorpay_check_php_version(): bool {
	if ( version_compare( PHP_VERSION, '7.4.0', '>=' ) ) {
		return true;
	}

	add_action( 'admin_notices', 'jfb_razorpay_php_version_notice' );

	return false;
}

function jfb_razorpay_check_jetformbuilder(): bool {
	if ( class_exists( 'Jet_Form_Builder\\Plugin' ) ) {
		return true;
	}

	add_action( 'admin_notices', 'jfb_razorpay_jetformbuilder_notice' );

	return false;
}

function jfb_razorpay_php_version_notice(): void {
	printf(
		'<div class="%1$s"><p>%2$s</p></div>',
		esc_attr( 'notice notice-error' ),
		wp_kses_post( __( '<strong>JetFormBuilder Razorpay Gateway</strong> requires PHP 7.4 or higher.', 'jet-form-builder-razorpay-gateway' ) )
	);
}

function jfb_razorpay_jetformbuilder_notice(): void {
	printf(
		'<div class="%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
		esc_attr( 'notice notice-error' ),
		esc_html__( 'JetFormBuilder Razorpay Gateway', 'jet-form-builder-razorpay-gateway' ),
		esc_html__( 'requires JetFormBuilder to be installed and activated.', 'jet-form-builder-razorpay-gateway' )
	);
}
