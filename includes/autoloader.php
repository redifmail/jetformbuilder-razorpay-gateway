<?php

namespace JFB_Razorpay;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Autoloader {
	public static function run(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		$length = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $length ) ) {
			return;
		}

		$relative = substr( $class, $length );
		$relative = str_replace( '\\', '/', $relative );
		$file     = JFB_RAZORPAY_PATH . 'includes/' . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
