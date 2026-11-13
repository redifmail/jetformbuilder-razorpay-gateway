<?php

namespace JFB_Razorpay\Admin\Payment_Details;

use Jet_Form_Builder\Admin\Table_Views\Column_Advanced_Base;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Detail_Column extends Column_Advanced_Base {
	private $label;

	public function __construct( string $column = '', string $label = '' ) {
		$this->label = $label;
		parent::__construct( $column );
	}

	public function get_label(): string {
		return $this->label;
	}
}
