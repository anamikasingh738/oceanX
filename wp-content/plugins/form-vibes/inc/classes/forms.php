<?php

namespace FormVibes\Classes;

class Forms {

	private static $instance = null;

	public static $forms = array();

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {

		self::$forms = $this->get_all_forms();
	}

	public function get_all_forms() {

		// get forms saved in options
		$forms = get_option( 'fv_forms' );
		// phpcs:ignore
		$forms = apply_filters( 'formvibes/forms', $forms );

		return $forms;
	}

}
