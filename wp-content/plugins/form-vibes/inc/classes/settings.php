<?php

namespace FormVibes\Classes;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\Utils;

class Settings {


	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_ajax_fv_save_config', array( $this, 'fv_save_config' ) );
		add_action( 'wp_ajax_fv_save_status', array( $this, 'fv_save_status' ) );
		add_action( 'wp_ajax_fv_save_role_config', array( $this, 'fv_save_role_config' ) );
		add_action( 'wp_ajax_fv_save_exclude_forms', array( $this, 'fv_save_exclude_forms' ) );
		add_action( 'wp_ajax_nopriv_fv_save_exclude_forms', array( $this, 'fv_save_exclude_forms' ) );

		add_action( 'wp_ajax_fv_delete_forms', array( $this, 'fv_delete_forms' ) );
		add_action( 'wp_ajax_nopriv_fv_delete_forms', array( $this, 'fv_delete_forms' ) );
	}

	public function get() {
		// set default array
		$defaults = array(
			'ip'               => true,
			'userAgent'        => false,
			'debugMode'        => false,
			'exportReason'     => false,
			'autoRefresh'      => false,
			'autoRefreshValue' => 30000,
			'widgetCount'      => 1,
			'persistFilter'    => true,
			'enableNotes'      => true,
		);

		// fetch from db
		$settings = get_option( 'fvSettings' );

		// merge

		$settings = wp_parse_args( $settings, $defaults );

		// apply filter
		// phpcs:ignore
		$settings = apply_filters( 'formvibes/settings', $settings );

		// return
		return $settings;
	}

	public function fv_save_status() {
		global $wpdb;
		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			$wpdb->update( $wpdb->prefix . 'fv_enteries', array( 'fv_status' => sanitize_text_field( $_REQUEST['value'] ) ), array( 'id' => sanitize_text_field( $_REQUEST['id'] ) ) );
		}
	}

	public function fv_save_configuration( $params ) {
		$settings                     = array();
		$settings['ip']               = $params['ip'];
		$settings['userAgent']        = $params['userAgent'];
		$settings['debugMode']        = $params['debugMode'];
		$settings['exportReason']     = $params['exportReason'];
		$settings['autoRefresh']      = $params['autoRefresh'];
		$settings['autoRefreshValue'] = $params['autoRefreshValue'];
		$settings['widgetCount']      = $params['widgetCount'];
		$settings['enableNotes']      = $params['enableNotes'];

		$message = array();
		if ( current_user_can( 'manage_options' ) ) {
			// phpcs:ignore
			$fv_db_settings     = update_option( 'fvSettings', $settings, false );
			$message['status']  = 'success';
			$message['message'] = 'Settings Saved';
			return $message;
		}
	}

	public function fv_save_config() {
		// phpcs:ignore
		$data = $_REQUEST['data'];

		$widget     = $data['widget'];
		$panel_data = get_option( 'fv-db-settings' );

		if ( 'yes' === $widget ) {
			$widget = 1;
		}

		$default_forms = Utils::get_first_plugin_form();

		for ( $i = 0; $i < $widget; $i++ ) {
			if ( ! array_key_exists( $i, $panel_data['panel_data'] ) ) {
				$panel_data[ $i ] = array(
					'queryType'      => 'Last_30_Days',
					'formName'       => $default_forms['formName'],
					'selectedPlugin' => $default_forms['selectedPlugin'],
					'selectedForm'   => $default_forms['selectedForm'],
				);
			}
		}
		$widget_data                = array();
		$widget_data['panelNumber'] = $widget;
		$widget_data['panel_data']  = $panel_data;

		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			update_option( 'fv-db-settings', $widget_data, false );
		}

		$save_ip       = sanitize_text_field( $_REQUEST['data']['ip'] );
		$save_ua       = sanitize_text_field( $_REQUEST['data']['ua'] );
		$debug_mode    = sanitize_text_field( $_REQUEST['data']['debugMode'] );
		$export_reason = sanitize_text_field( $_REQUEST['data']['exportReason'] );

		$gdpr                  = array();
		$gdpr['ip']            = $save_ip;
		$gdpr['ua']            = $save_ua;
		$gdpr['debug_mode']    = $debug_mode;
		$gdpr['export_reason'] = $export_reason;

		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			update_option( 'fv_gdpr_settings', $gdpr, false );
		}
	}

	public function fv_save_exclude_forms() {
		// phpcs:ignore
		// $forms = sanitize_text_field($_REQUEST['myForms']);
		// phpcs:ignore
		$forms = $_REQUEST['myForms'];
		if ( array_key_exists( 'cf7', $_REQUEST['myForms'] ) ) {
			$forms['cf7'] = array_map( 'sanitize_text_field', $_REQUEST['myForms']['cf7'] );
		}
		if ( array_key_exists( 'elementor', $_REQUEST['myForms'] ) ) {
			$forms['elementor'] = array_map( 'sanitize_text_field', $_REQUEST['myForms']['elementor'] );
		}
		if ( array_key_exists( 'beaverBuilder', $_REQUEST['myForms'] ) ) {
			$forms['beaverBuilder'] = array_map( 'sanitize_text_field', $_REQUEST['myForms']['beaverBuilder'] );
		}
		if ( array_key_exists( 'wpforms', $_REQUEST['myForms'] ) ) {
			$forms['wpforms'] = array_map( 'sanitize_text_field', $_REQUEST['myForms']['wpforms'] );
		}

		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			update_option( 'fv_exclude_forms', $forms, false );
		}
	}

	public function fv_save_role_config() {
		// phpcs:ignore
		// $data = sanitize_text_field($_REQUEST['role_data']);
		$data = array();
		if ( array_key_exists( 'editor', $_REQUEST['role_data'] ) ) {
			$data['editor'] = array_map( 'sanitize_text_field', $_REQUEST['role_data']['editor'] );
		}
		if ( array_key_exists( 'author', $_REQUEST['role_data'] ) ) {
			$data['author'] = array_map( 'sanitize_text_field', $_REQUEST['role_data']['author'] );
		}

		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			update_option( 'fv_user_role', $data, false );
		}
	}

	public function fv_delete_forms() {
		$form_id = sanitize_text_field( $_REQUEST['formId'] );
		$plugin  = sanitize_text_field( $_REQUEST['plugin'] );

		$inserted_forms = get_option( 'fv_forms' );
		unset( $inserted_forms[ $plugin ][ $form_id ] );

		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			update_option( 'fv_forms', $inserted_forms );
		}
	}
}
