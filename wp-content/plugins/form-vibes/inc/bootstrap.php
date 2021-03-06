<?php

namespace FormVibes;

use FormVibes\Classes\Export;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Cf7;
use FormVibes\Integrations\Elementor;
use FormVibes\Integrations\Caldera;
use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\Settings;
use FormVibes\Integrations\BeaverBuilder;
use function GuzzleHttp\Promise\all;
use FormVibes\Classes\Forms;
use FormVibes\Classes\DbTables;

class Plugin {


	private static $instance = null;
	private $panel_number;
	private $data;
	private $current_tab = '';
	// phpcs:ignore
	private static $_forms      = null;
	private $cap_fv_leads       = 'publish_posts';
	private $cap_fv_analytics   = 'publish_posts';
	private $cap_fv_export      = 'publish_posts';
	private $cap_fv_view_logs   = 'publish_posts';
	private $fv_title           = 'Form Vibes';
	private static $show_notice = true;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 10, 1 );

		add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );
		add_action( 'plugins_loaded', array( 'FormVibes\Classes\DbTables', 'fv_plugin_activated' ) );

		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( WPV_FV_PATH . 'form-vibes.php' ), array( $this, 'settings_link' ), 10 );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'caldera-forms/caldera-core.php' ) ) {
			// phpcs:ignore
			$caldera = new Caldera();
		}
		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			// phpcs:ignore
			$cf7 = new Cf7();
		}
		if ( is_plugin_active( 'elementor-pro/elementor-pro.php' ) || is_plugin_active( 'pro-elements/pro-elements.php' ) ) {
			// phpcs:ignore
			$ef = new Elementor();
		}
		if ( is_plugin_active( 'bb-plugin/fl-builder.php' ) ) {
			// phpcs:ignore
			$bb = new BeaverBuilder();
		}

		Settings::instance();
		// phpcs:ignore
		// new \FormVibes\Classes\Submissions('elementor');

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );

		add_action( 'admin_menu', array( $this, 'admin_menu_after_pro' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		// phpcs:ignore
		// add_action('fv_reports', [$this,'do_this_hourly']); // future plan

		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );

		add_action( 'init', array( $this, 'fv_export_csv' ) );
		add_action( 'init', array( $this, 'fv_db_update' ) );
		add_filter( 'formvibes/submissions/query/join', array( $this, 'fv_prepare_query_joins' ), 10, 2 );
		add_filter( 'formvibes/submissions/query/where', array( $this, 'fv_prepare_query_where' ), 10, 2 );

		add_action( 'in_admin_header', array( $this, 'in_admin_header' ) );
		// phpcs:ignore
		$this->fv_title = apply_filters( 'formvibes/fv_title', 'Form Vibes' );

		self::$_forms = Forms::instance();

		new Export( '' );
	}

	public function fv_prepare_query_joins( $joins, $params ) {
		global $wpdb;
		$query_filters  = $params['query_filters'];
		$query_relation = $params['filter_relation'];

		if ( '' === $query_filters ) {
			return $joins;
		}
		if ( count( $query_filters ) === 0 ) {
			return $joins;
		}
		$condition  = 'data_id';
		$table_name = $wpdb->prefix . 'fv_entry_meta';
		if ( 'caldera' === $params['plugin'] || 'Caldera' === $params['plugin'] ) {
			$condition  = 'entry_id';
			$table_name = $wpdb->prefix . 'cf_form_entry_values';
		}
		foreach ( $query_filters as $key => $value ) {

			$joins[ $key ] =
				'INNER JOIN ' . $table_name . ' as e' . ( $key + 1 ) . ' ON (entry.id = e' . ( $key + 1 ) . '.' . $condition . ' ) ';
		}
		// phpcs:ignore
		// echo '<pre>';
		// print_r($joins);
		// echo '</pre>';
		if ( 'OR' === $query_relation ) {
			$temp   = array();
			$temp[] = $joins[0];
			return $temp;
		}
		return $joins;
	}

	public function fv_prepare_query_where( $where, $params ) {

		$entry_fields   = Utils::get_entry_table_fields();
		$query_filters  = $params['query_filters'];
		$query_relation = $params['filter_relation'];
		$filter_query   = '(';
		$condition_key  = 'meta_key';
		$condition_val  = 'meta_value';
		if ( '' === $query_filters ) {
			return $where;
		}
		if ( count( $query_filters ) === 0 ) {
			return $where;
		}
		if ( 'caldera' === $params['plugin'] || 'Caldera' === $params['plugin'] ) {
			$condition_key = 'slug';
			$condition_val = 'value';
		}
		if ( '' === $query_filters[0]['value'] ) {
			$filter_query .= '(e1.' . $condition_key . " LIKE '%%' AND e1." . $condition_val . " LIKE '%%')";
			$filter_query .= ')';
			$where[]       = $filter_query;
			return $where;
		}
		foreach ( $query_filters as $key => $value ) {
			$filter_key   = $value['filter'];
			$filter_value = trim( $value['value'] );
			$operator     = $value['operator'];
			$relation     = $query_relation;
			$table_alias  = 'e';
			if ( count( $query_filters ) === $key + 1 ) {
				$relation = '';
			}
			if ( 'OR' === $query_relation ) {
				$key = 0;
			}

			if ( in_array( $value['filter'], $entry_fields, true ) ) {
				$table_alias = 'entry';
			}

			switch ( $operator ) {
				case 'equal':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " = '$filter_value') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " = '$filter_value') $relation";
					}
					break;
				case 'not_equal':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " != '$filter_value') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " != '$filter_value') $relation";
					}
					break;
				case 'contain':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " LIKE '%$filter_value%') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " LIKE '%$filter_value%') $relation";
					}
					break;
				case 'not_contain':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " NOT LIKE '%$filter_value%') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " NOT LIKE '%$filter_value%') $relation";
					}
					break;
				default:
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '%%' AND e" . ( $key + 1 ) . '.' . $condition_val . " = '%%')";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " = '%%')";
					}
					break;
			}
		}
		$filter_query .= ')';
		$where[]       = $filter_query;
		// phpcs:ignore
		// echo "where =>  ";
		// print_r($where);
		// die();
		return $where;
	}

	public function fv_export_csv() {
		// phpcs:ignore
		if ( isset( $_POST['btnExport'] ) ) {
			// phpcs:ignore
			$params = (array) json_decode( stripslashes( $_REQUEST['fv_export_data'] ) );

			new Export( $params );
		}
	}

	public function init_rest_api() {

		$controllers = array(
			new \FormVibes\Api\AdminRest(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
	public function go_pro_link( $links ) {
		$links['go_pro'] = sprintf( '<a href="%1$s" target="_blank" class="fv-pro-link">%2$s</a>', 'https://wpvibes.com', __( 'Go Pro', 'wpv-fv' ) );

		return $links;
	}

	public function admin_footer_text( $footer_text ) {
		$screen = get_current_screen();
		// Todo:: Show on plugin screens
		$fv_screens = array(
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
			'edit-fv_data_profile',
			'edit-fv_export_profile',
		);

		if ( in_array( $screen->id, $fv_screens, true ) ) {
			$footer_text = sprintf(
				/* translators: 1: Form Vibes, 2: Link to plugin review */
				__( 'Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'wpv-fv' ),
				'<strong>' . __( 'Form Vibes', 'wpv-fv' ) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}

	public function wpv_fv() {
		global $wpv_fv;
		if ( ! isset( $wpv_fv ) ) {
			// Include Freemius SDK.
			require_once WPV_FV_PATH . '/freemius/start.php';
			$wpv_fv = fs_dynamic_init(
				array(
					'id'             => '4666',
					'slug'           => 'form-vibes',
					'type'           => 'plugin',
					'public_key'     => 'pk_321780b7f1d1ee45009cf6da38431',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => false,
					'menu'           => array(
						'slug'       => 'fv-leads',
						'first-path' => 'admin.php?page=fv-db-settings',
						'account'    => false,
						'contact'    => false,
						'support'    => false,
					),
				)
			);
		}
		return $wpv_fv;
	}

	public function do_this_hourly() {
		$this->write_log( '=============Cron Job Executed Time ===================' . current_time( 'Y-m-d H:i:s', 0 ) );
	}

	public function write_log( $log ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			// phpcs:ignore
			error_log( print_r( $log, true ) );
		} else {
			// phpcs:ignore
			error_log( $log );
		}
	}
	// phpcs:ignore
	public function fv_plugin_activation( $plugin, $network_activation ) {
		$url = admin_url() . 'admin.php?page=fv-db-settings';
		if ( $plugin === 'form-vibes/form-vibes.php' ) {
			header( 'Location: ' . $url );
			die();
		}
	}

	private function register_autoloader() {

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public function autoload( $class ) {

		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		if ( ! class_exists( $class ) ) {

			$filename = strtolower(
				preg_replace(
					array( '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ),
					array( '', '$1-$2', '-', DIRECTORY_SEPARATOR ),
					$class
				)
			);

			$filename = WPV_FV_PATH . '/inc/' . $filename . '.php';
			if ( is_readable( $filename ) ) {
				include $filename;
			}
		}
	}

	private function get_fv_keys() {
		$temp = get_option( 'fv-keys' );
		if ( '' === $temp || false === $temp ) {
			return array();
		}
		$fv_keys = array();
		foreach ( $temp as $key => $value ) {
			// phpcs:ignore
			foreach ( $value as $val_key => $val_val ) {
				$fv_keys[ $key ][ $val_val['colKey'] ] = $val_val;
			}
		}
		return $fv_keys;
	}

	public function admin_scripts() {
		$screen = get_current_screen();

		$settings = Settings::instance();

		// Todo:: Load on plugin screens only
		if ( 1 ) {
			// phpcs:ignore
			// wp_enqueue_style('fv-select-css', WPV_FV_URL. 'assets/css/select2.min.css',[],WPV_FV_VERSION);
			wp_enqueue_style( 'fv-style-css', WPV_FV_URL . 'assets/css/style.css', array(), WPV_FV_VERSION );
			wp_enqueue_script( 'fv-js', WPV_FV_URL . 'assets/script/index.js', array( 'jquery-ui-datepicker' ), WPV_FV_VERSION, true );

			$user      = wp_get_current_user();
			$user_role = $user->roles;
			// phpcs:ignore
			// $user_role     = $user_role[0];
			$settings = $settings->get();
			// phpcs:ignore
			if ( isset( $_REQUEST['post'] ) ) {
				// phpcs:ignore
				$post_id   = $_REQUEST['post'];
				$post_type = get_post_type( $post_id );
				// phpcs:ignore
				$post_meta = '';
				if ( 'fv_utility' === $post_type ) {
					// phpcs:ignore
					$post_meta       = get_post_meta( $post_id, 'fv_sc_data', true );
					// phpcs:ignore
					$post_meta_style = get_post_meta( $post_id, 'fv_sc_style_data', true );
					// phpcs:ignore
					$post_key        = get_post_meta( $post_id, 'fv_data_key', true );
					// phpcs:ignore
					$d_type          = get_post_meta( $post_id, 'fv_data_type', true );
				}
			} else {
				// phpcs:ignore
				$post_id         = '';
				// phpcs:ignore
				$post_meta       = '';
				// phpcs:ignore
				$d_type          = '';
				// phpcs:ignore
				$post_key        = '';
				// phpcs:ignore
				$post_meta_style = '';
			}
			// phpcs:ignore
			$gdpr_settings = Utils::get_settings();
			// phpcs:ignore
			$settings                      = apply_filters( 'formvibes/settings', $settings );
			// phpcs:ignore
			$is_pro                        = apply_filters( 'formvibes/is_pro', is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' ) );
			// phpcs:ignore
			$show_submission_filter        = apply_filters( 'formvibes/submission/filter', 0 );
			// phpcs:ignore
			$show_submission_editable_data = apply_filters( 'formvibes/submission/editable_data', 0 );

			wp_localize_script(
				'fv-js',
				'fvGlobalVar',
				array(
					'site_url'                      => site_url(),
					'ajax_url'                      => admin_url( 'admin-ajax.php' ),
					'admin_url'                     => admin_url(),
					'rest_url'                      => get_rest_url(),
					'fv_version'                    => WPV_FV_VERSION,
					'user'                          => $user_role,
					'nonce'                         => wp_create_nonce( 'wp_rest' ),
					'ajax_nonce'                    => wp_create_nonce( 'fv_ajax_nonce' ),
					'forms'                         => $this->prepare_forms_data(),
					'fv_options'                    => $this->get_fv_options(),
					'config'                        => $settings,
					'db_settings'                   => get_option( 'fv-db-settings' ),
					'fv_dashboard_widget_settings'  => get_option( 'fv_dashboard_widget_settings' ),
					'saved_columns'                 => $this->get_fv_keys(),
					'show_submission_filter'        => $show_submission_filter,
					'entry_table_fields'            => Utils::get_entry_table_fields(),
					'show_submission_editable_data' => $show_submission_editable_data,
					'is_pro'                        => $is_pro,
				)
			);

			add_action( 'admin_print_scripts', array( $this, 'fv_disable_admin_notices' ) );

			wp_enqueue_style( 'wp-components' );
		}

		if ( 'dashboard' === $screen->id ) {
			wp_enqueue_script( 'dashboard-js', WPV_FV_URL . 'assets/js/dashboard.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_script( 'dashboard-select-form-js', WPV_FV_URL . 'assets/script/add-dashboard-widget-gear-icon.js', array(), WPV_FV_VERSION, true );
		}
		if ( 'form-vibes_page_fv-render-controls' === $screen->id ) {
			wp_enqueue_script( 'renderControls-js', WPV_FV_URL . 'assets/js/renderControls.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_style( 'fv-renderControls-css', WPV_FV_URL . 'assets/js/renderControls.css', '', WPV_FV_VERSION );
			wp_enqueue_style( 'fv-select-css', WPV_FV_URL . 'assets/css/select2.min.css', array(), WPV_FV_VERSION );
		}
		if ( 'fv_utility' === $screen->id || 'edit-fv_utility' === $screen->id || 'toplevel_page_fv-leads' === $screen->id || 'form-vibes_page_db-manager' === $screen->id || 'fv_utility' === $screen->id ) {
			wp_enqueue_script( 'submissions-js', WPV_FV_URL . 'assets/js/submissions.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_style( 'fv-submission-css', WPV_FV_URL . 'assets/js/submissions.css', '', WPV_FV_VERSION );
		}
		if ( 'form-vibes_page_fv-db-settings' === $screen->id ) {
			wp_enqueue_script( 'setting-js', WPV_FV_URL . 'assets/js/settings.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_style( 'setting-css', WPV_FV_URL . 'assets/js/settings.css', '', WPV_FV_VERSION );
		}
		if ( 'form-vibes_page_fv-analytics' === $screen->id ) {
			wp_enqueue_script( 'analytics-js', WPV_FV_URL . 'assets/js/analytics.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_style( 'analytics-css', WPV_FV_URL . 'assets/js/analytics.css', '', WPV_FV_VERSION );
		}
		if ( 'form-vibes_page_fv-logs' === $screen->id ) {
			wp_enqueue_script( 'analytics-js', WPV_FV_URL . 'assets/js/eventLogs.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_style( 'analytics-css', WPV_FV_URL . 'assets/js/eventLogs.css', '', WPV_FV_VERSION );
		}
		if ( 'dashboard' === $screen->id ) {
			wp_enqueue_script( 'dashboard-js', WPV_FV_URL . 'assets/js/dashboard.js', array( 'wp-components' ), WPV_FV_VERSION, true );
			wp_enqueue_script( 'script-js', WPV_FV_URL . 'assets/script/index.js', '', WPV_FV_VERSION, true );
			wp_enqueue_style( 'dashboard-css', WPV_FV_URL . 'assets/js/dashboard.css', '', WPV_FV_VERSION );
		}
	}


	public function add_dashboard_widgets() {
		$settings     = Settings::instance();
		$widget_count = $settings->get()['widgetCount'];

		if ( $widget_count === 0 ) {
			return;
		}
		if ( ! $this->check_capability( $this->cap_fv_analytics ) ) {
			return;
		}

		$is_dashboard_active = get_option( 'fv-db-settings' );

		if ( false === $is_dashboard_active || '' === $is_dashboard_active ) {
			$is_dashboard_active = array();
		}

		if ( ! array_key_exists( 'widget', $is_dashboard_active ) ) {
			$is_dashboard_active['widget'] = true;
		};

		if ( 0 !== $is_dashboard_active['widget'] || '' === $is_dashboard_active ) {
			add_meta_box( 'form_vibes_widget-0', 'Form Vibes Analytics', array( $this, 'dashboard_widget' ), null, 'normal', 'high', 0 );
		}
	}

	public function dashboard_widget( $vars, $i ) {
		//phpcs:ignore
		echo '<div name="dashboard-widget" id="fv-dashboard-widgets-' . $i['args']  . '">
				</div>';
	}

	public function prepare_forms_data() {
		// TODO :: Refactor and Migrate Logic
		global $wpdb;
		$forms                = array();
		$data                 = array();
		$data['forms_plugin'] = apply_filters( 'fv_forms', $forms );

		$gdpr_settings = Utils::get_settings();

		$debug_mode = $gdpr_settings['debugMode'];
		// phpcs:ignore
		// $form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e";

		$form_res = $wpdb->get_results( "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e", OBJECT_K );

		$inserted_forms = get_option( 'fv_forms' );

		$plugin_forms = array();

		foreach ( $data['forms_plugin'] as $key => $value ) {
			$res = array();

			if ( 'caldera' === $key || 'ninja' === $key ) {
				$class = '\FormVibes\Integrations\\' . ucfirst( $key );

				$res = $class::get_forms( $key );
			} else {
				foreach ( $form_res as $form_key => $form_value ) {

					if ( array_key_exists( $key, $inserted_forms ) && array_key_exists( $form_key, $inserted_forms[ $key ] ) ) {
						$name = $inserted_forms[ $key ][ $form_key ]['name'];
					} else {
						$name = $form_key;
					}
					if ( $form_res[ $form_key ]->form_plugin === $key ) {
						$res[ $form_key ] = array(
							'id'   => $form_key,
							'name' => $name,
						);
					}
				}
			}

			if ( null !== $res ) {
				$plugin_forms[ $key ] = $res;
			}
		}

		$all_forms = array();

		foreach ( $data['forms_plugin'] as $key => $value ) {
			if ( $plugin_forms[ $key ] ) {
				array_push(
					$all_forms,
					array(
						'label'   => $value,
						'options' => array(),
					)
				);
			}
		}

		$all_forms_count = count( $all_forms );

		for ( $i = 0; $i < $all_forms_count; ++$i ) {
			foreach ( $data['forms_plugin'] as $key => $value ) {
				// phpcs:ignore
				foreach ( $plugin_forms[ $key ] as $key1 => $value1 ) {
					$options = array();
					if ( true === $debug_mode || 'yes' === $debug_mode ) {
						array_push(
							$options,
							array(
								'label'      => $value1['name'] . '(' . $value1['id'] . ')',
								'value'      => $value1['id'],
								'pluginName' => $value,
								'formName'   => $value1['name'],
							)
						);
					} else {
						array_push(
							$options,
							array(
								'label'      => $value1['name'],
								'value'      => $value1['id'],
								'pluginName' => $value,
								'formName'   => $value1['name'],
							)
						);
					}

					if ( $all_forms[ $i ]['label'] === $value ) {
						array_push( $all_forms[ $i ]['options'], $options[0] );
					}
				}
			}
		}

		for ( $i = 0; $i < $all_forms_count; ++$i ) {
			if ( count( $all_forms[ $i ]['options'] ) === 0 ) {
				unset( $all_forms[ $i ] );
			}
		}

		$all_forms = array_values( $all_forms );

		$data['allForms'] = $all_forms;

		return $data;

	}

	public function get_fv_options() {
		// TODO:: all options here expect setting popup options.
	}

	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( WPV_FV__PLUGIN_BASE === $plugin_file ) {
			$row_meta = array(
				'docs'    => '<a href="https://wpvibes.link/go/fv-all-docs-pp/" aria-label="' . esc_attr( __( 'View Documentation', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Read Docs', 'wpv-fv' ) . '</a>',
				'support' => '<a href="https://wpvibes.link/go/form-vibes-support/" aria-label="' . esc_attr( __( 'Support', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Need Support', 'wpv-fv' ) . '</a>',
			);

			$plugin_meta = array_merge( $plugin_meta, $row_meta );
		}

		return $plugin_meta;
	}


	public function admin_menu() {
		// phpcs:ignore
		$this->cap_fv_leads     = apply_filters( 'formvibes/cap/view_submissions', 'publish_posts' );
		// phpcs:ignore
		$this->cap_fv_analytics = apply_filters( 'formvibes/cap/view_fv_analytics', 'publish_posts' );
		// phpcs:ignore
		$this->cap_fv_export    = apply_filters( 'formvibes/cap/export_fv_submissions', 'publish_posts' );

		add_menu_page( 'Form Vibes Leads', 'Form Vibes', $this->cap_fv_leads, 'fv-leads', array( $this, 'display_react_table' ), 'dashicons-analytics', 30 );
		add_submenu_page( 'fv-leads', 'Form Vibes Submissions', 'Submissions', $this->cap_fv_leads, 'fv-leads', array( $this, 'display_react_table' ), 1 );
		add_submenu_page( 'fv-leads', 'Form Vibes Analytics', 'Analytics', $this->cap_fv_analytics, 'fv-analytics', array( $this, 'fv_analytics' ), 2 );
	}

	public function export_data() {
		?>
		<div class="fv-export-process">
			<h1>Export in process...</h1>
			<em>Do not close the browser until process is complete...</em>
			<div class="fv-export-box">
				<div id="fv-export-data"></div>
			</div>
		</div>

		<?php
	}

	public function admin_menu_after_pro() {
		// phpcs:ignore
		$this->cap_fv_view_logs = apply_filters( 'formvibes/cap/view_fv_logs', 'publish_posts' );
		add_submenu_page( 'fv-leads', 'Form Vibes Settings', 'Settings', 'manage_options', 'fv-db-settings', array( $this, 'fv_db_settings' ), 5 );
		add_submenu_page( 'fv-leads', 'Form Vibes Logs', 'Event Logs', $this->cap_fv_view_logs, 'fv-logs', array( $this, 'fv_logs' ), 6 );
		// phpcs:ignore
		// add_submenu_page('fv-leads', 'Go Pro', 'Go Pro', 'manage_options',  'fvpro', [ $this, 'handle_pro'], 8);
		global $submenu;
		// phpcs:ignore
		$submenu['fv-leads'][100] = array( '<span class="dashicons dashicons-star-filled"></span><span class="fv-go-pro"> Go Pro</span>', 'manage_options', 'https://wpvibes.link/go/form-vibes-pro' );
	}
	public function fv_disable_admin_notices() {
		global $wp_filter;
		$screen     = get_current_screen();
		$fv_screens = array(
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
		);

		if ( in_array( $screen->id, $fv_screens, true ) ) {
			if ( is_user_admin() ) {
				if ( isset( $wp_filter['user_admin_notices'] ) ) {
					unset( $wp_filter['user_admin_notices'] );
				}
			} elseif ( isset( $wp_filter['admin_notices'] ) ) {
				unset( $wp_filter['admin_notices'] );
			}
			if ( isset( $wp_filter['all_admin_notices'] ) ) {
				unset( $wp_filter['all_admin_notices'] );
			}
		}

		// Form Vibes Pro Notice
		// phpcs:ignore
		/*
		if(WPV_FV_PLAN === 'FREE'){
			add_action( 'admin_notices', [ $this,'fv_pro_notice'] );
		}*/
		// phpcs:ignore
		// add_action('admin_notices', [$this, 'fv_pro_notice']);
		$this->fv_review_box();
		$this->fv_pro_purchase();

		add_action( 'admin_notices', array( $this, 'fv_table_notice' ) );
	}
	public function fv_pro_notice() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
		if ( ! $is_pro_activated ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p> Get More features in Form Vibes Pro Version.
					<a href="#">Go Pro</a>
				</p>
			</div>
			<?php
		} else {
			// pro activated.
			// check if free version has compilable version to run pro.
			$free_version     = WPV_FV_VERSION;
			$required_version = WPV_FV_MIN_VERSION;
			$status           = version_compare( $free_version, $required_version, '>=' );
			if ( ! $status ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p>Please update to the latest version of Form Vibes. <a href="#">Update Now</a></p>

				</div>
				<?php
			}
		}
	}
	public function fv_review_box() {
		// phpcs:ignore
		if ( isset( $_GET['remind_later'] ) ) {
			add_action( 'admin_notices', array( $this, 'fv_remind_later' ) );
			// phpcs:ignore
		} elseif ( isset( $_GET['review_done'] ) ) {
			add_action( 'admin_notices', array( $this, 'fv_review_done' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'fv_review' ) );
		}
	}

	public function fv_review() {
		$show_review = get_transient( 'fv_remind_later' );

			$review_status = get_option( 'fv-review' );

		if ( 'done' !== $review_status ) {
			if ( ( '' === $show_review || false === $show_review ) && self::$show_notice ) {
				global $wpdb;

				$rowcount       = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries" );
				$current_screen = get_current_screen();
				$page_id        = $current_screen->id;
				$fv_page_id_arr = array(
					'toplevel_page_fv-leads',
					'form-vibes_page_fv-analytics',
					'edit-fv_export_profile',
					'edit-fv_data_profile',
					'form-vibes_page_fv-db-settings',
					'form-vibes_page_fv-logs',
				);
				$hide_logo      = '';
				if ( in_array( $page_id, $fv_page_id_arr, true ) ) {
					$hide_logo = 'fv-hide-logo';
				}
				if ( $rowcount > 9 ) {
					self::$show_notice = false;
					?>
					<div class="fv-review notice notice-success is-dismissible">
						<div class="fv-logo
						<?php
							//phpcs:ignore
							echo $hide_logo ; ?>"
						>
							<svg viewBox="0 0 1340 1340" version="1.1">
								<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
									<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
										<g id="g2950" transform="translate(533.017848, 2415.845322)">
											<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
											<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
										</g>
									</g>
								</g>
							</svg>
						</div>
						<div class="fv-review-content">
							<p class="fv-review-desc">
							<?php
							//phpcs:ignore
							 echo 'Form Vibes has already captured 10+ form submissions. That???s awesome! Could you please do a BIG favor and give it a 5-star rating on WordPress? <br/> Just to help us spread the word and boost our motivation. <br/><b>~ Anand Upadhyay</b>' ?></p>
							<span class="fv-notic-link-wrapper">
								<a class="fv-notice-link" target="_blank" href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" class="button button-primary"><span class="dashicons dashicons-heart"></span><?php esc_html_e( 'Ok, you deserve it!', 'wpv-fv' ); ?></a>
								<a class="fv-notice-link" href="<?php echo esc_html( add_query_arg( 'remind_later', 'later' ) ); ?>"><span class="dashicons dashicons-schedule"></span><?php esc_html_e( 'May Be Later', 'wpv-fv' ); ?></a>
								<a class="fv-notice-link" href="<?php echo esc_html( add_query_arg( 'review_done', 'done' ) ); ?>"><span class="dashicons dashicons-smiley"></span><?php esc_html_e( 'Already Done', 'wpv-fv' ); ?></a>
							</span>
						</div>
					</div>
					<?php
				}
			}
		}
	}
	public function fv_remind_later() {
		set_transient( 'fv_remind_later', 'show again', WEEK_IN_SECONDS );
	}

	public function fv_review_done() {
		update_option( 'fv-review', 'done', false );
	}

	private function check_capability( $cap ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! $user->has_cap( $cap ) ) {
				return false;
			}
		}
		return true;
	}

	public function display_react_table() {
		if ( ! $this->check_capability( $this->cap_fv_leads ) ) {
			return;
		}
		?>
		<div id="fv-submissions">

		</div>
		<?php
	}

	public function fv_logs() {
		if ( ! $this->check_capability( $this->cap_fv_view_logs ) ) {
			return;
		}

		?>
		<div id="fv-logs" class="fv-logs">
			<div class="fv-wrapper">
				<div class="fv-data-wrapper">
					<div id="fv-event-log-wrapper" class="fv-event-log-wrapper">
					</div>
					<?php $this->sidebar(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function sidebar() {
		echo ''
		?>
		<div class="fv-sidebar">
			<div class="fv-sidebar-wrapper">
				<div class="fv-sidebar-box" style="display: none">
					<div class="fv-sidebar-inner">
						<div class="fv-free-version">
							<h4 class="fv_title">Form Vibes:</h4>
							<span class="fv_version"><?php echo WPV_FV_VERSION; ?></span>
						</div>
						<?php
						$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
						if ( $is_pro_activated ) {
							?>
							<div class="fv-pro-version">
								<h4>Form Vibes Pro:</h4>
								<span><?php echo WPV_PRO_FV_VERSION; ?></span>
							</div>
							<?php
						}
						?>
					</div>
				</div>
				<div class="fv-sidebar-box">
					<h3>Need Help?</h3>
					<div class="fv-sidebar-inner">
						<ul>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-getting-started/">Getting Started</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-view-submitted-data/">View Submitted Data</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-export-form-data-to-csv/">Export to CSV</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-data-analytics/">View Data Analytics</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-add-dashboard-widget/">Add Dashboard Widgets</a></li>
						</ul><a target="_blank" href="https://wpvibes.link/go/fv-all-docs/"><b>View All Documentation <i class="dashicons dashicons-arrow-right"></i></b></a><br><a target="_blank" href="https://wpvibes.link/go/form-vibes-support/"><b>Get Support <i class="dashicons dashicons-arrow-right"></i></b></a>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	public function fv_analytics() {
		if ( ! $this->check_capability( $this->cap_fv_analytics ) ) {
			return;
		}

		?>
		<div id="fv-analytics" class="fv-analytics"></div>
		<?php
	}
	public function fv_db_settings() {
		// phpcs:ignore
		if ( isset( $_GET['tab'] ) ) {
			// phpcs:ignore
			$this->current_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		}

		$setting_pages = array(
			'general' => __( 'General', 'wpv-fv' ),
		);
		// phpcs:ignore
		$setting_pages = apply_filters( 'formvibes/settings/pages', $setting_pages );

		?>

		<div class="fv-settings-wrapper">

			<div class="fv-data-wrapper">
				<div class="fv-settings-content-wrapper">
					<nav class="fv-nav-tab-wrapper">
						<?php
						foreach ( $setting_pages as $key => $label ) {
							?>
							<a class="fv-nav-tab <?php echo ( ( '' === $this->current_tab && 'general' === $key ) || $key === $this->current_tab ) ? 'fv-tab-active' : ''; ?>" href="admin.php?page=fv-db-settings&tab=<?php echo esc_html( $key ); ?>"><?php echo esc_html( $label ); ?></a>
							<?php
						}
						?>
					</nav>

					<div class="fv-settings-tab-content-wrapper">

						<?php
						if ( '' === $this->current_tab || 'general' === $this->current_tab ) {
							?>
							<div id="fv-settings-general"></div>
							<?php
						}
						// phpcs:ignore
						do_action( 'formvibes/settings/' . $this->current_tab );
						?>

					</div>

				</div>

				<?php $this->sidebar(); ?>
			</div>

		</div>

		<?php
	}
	public function fv_render_controls() {
		?>
		<div id="fv-render-controls" class="fv-render-controls-wrapper"></div>
		<?php
	}

	public function in_admin_header() {
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );

		$nav_links = $this->get_nav_links();
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! in_array( 'administrator', $user->roles, true ) ) {
				// remove setting page link
				unset( $nav_links['form-vibes_page_fv-db-settings'] );
			}
			if ( ! in_array( 'administrator', $user->roles, true ) && $is_pro_activated ) {
				if ( ! $user->has_cap( 'view_fv_submissions' ) ) {
					// remove submissions page link
					unset( $nav_links['toplevel_page_fv-leads'] );
				}
				if ( ! $user->has_cap( 'view_fv_analytics' ) ) {
					// remove analytics page link
					unset( $nav_links['form-vibes_page_fv-analytics'] );
				}
			}
		}
		$current_screen = get_current_screen();

		if ( ! isset( $nav_links[ $current_screen->id ] ) ) {
			return;
		}

		?>

		<div class="fv-admin-topbar">
			<div class="fv-branding">
				<svg viewBox="0 0 1340 1340" version="1.1">
					<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
						<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
							<g id="g2950" transform="translate(533.017848, 2415.845322)">
								<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
								<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
							</g>
						</g>
					</g>
				</svg>
				<h1><?php echo esc_html( $this->fv_title ); ?></h1>
				<span class="fv-version"><?php echo esc_html( WPV_FV_VERSION ); ?></span>
			</div>

			<nav class="fv-nav">
				<ul>
					<?php
					if ( isset( $nav_links ) && count( $nav_links ) ) {
						foreach ( $nav_links as $id => $link ) {

							if ( false === $link['top_nav'] ) {
								continue;
							}

							$active = ( $current_screen->id === $id ) ? 'fv-nav-active' : '';
							$target = 'Get Support' === $link['label'] ? 'target="_blank"' : '';
							?>
							<li class="<?php echo esc_html( $active ); ?>">
								<a <?php echo esc_html( $target ); ?> href="<?php echo esc_html( $link['link'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
							</li>
							<?php
						}
					}
					?>
				</ul>
			</nav>
		</div>

		<?php
		do_action( 'fv_notices' );
	}

	public function get_nav_links() {

		$nav = array(
			'toplevel_page_fv-leads'         => array(
				'label'   => __( 'Submissions', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-leads' ),
				'top_nav' => true,
			),

			'form-vibes_page_fv-analytics'   => array(
				'label'   => __( 'Analytics', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-analytics' ),
				'top_nav' => true,
			),

			'form-vibes_page_fv-db-settings' => array(
				'label'   => __( 'Settings', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-db-settings' ),
				'top_nav' => true,
			),

			'get_support'                    => array(
				'label'   => __( 'Get Support', 'wpv-fv' ),
				'link'    => 'https://wpvibes.link/go/form-vibes-support/',
				'top_nav' => true,
			),

			'form-vibes_page_fv-logs'        => array(
				'label'   => __( 'Event Log', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-logs' ),
				'top_nav' => false,

			),
		);
		// phpcs:ignore
		$nav = apply_filters( 'formvibes/nav_links', $nav );

		return $nav;
	}

	public function handle_pro() {
		wp_safe_redirect( 'https://go.elementor.com/docs-admin-menu/' );
		die();
	}

	public function fv_pro_purchase() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
		if ( $is_pro_activated ) {
			return;
		}
		// phpcs:ignore
		if ( isset( $_GET['fv_pro_later'] ) ) {
			add_action( 'admin_notices', array( $this, 'fv_pro_later' ) );
			// phpcs:ignore
		} elseif ( isset( $_GET['fv_pro_done'] ) ) {
			add_action( 'admin_notices', array( $this, 'fv_pro_done' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'fv_pro_purchase' ) );
		}

		$check_review = get_option( 'fv_pro_purchase' );

		if ( ! $check_review ) {
			$review = array(
				'installed' => current_time( 'yy/m/d' ),
				'status'    => '',
			);

			update_option( 'fv_pro_purchase', $review );
		}

		$check_review = get_option( 'fv_pro_purchase' );

		$start = $check_review['installed'];
		$end   = current_time( 'yy/m/d' );

		$days = $this->date_diff( $start, $end );

		if ( $days < 6 ) {
			return;
		}

		if ( ( '' === $check_review['status'] || 'remind_later' === $check_review['status'] ) && self::$show_notice ) {

			add_action( 'admin_notices', array( $this, 'fv_pro_purchase_box' ), 10 );
		}
	}

	public function date_diff( $start, $end ) {
		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );
		$date_diff  = $end_time - $start_time;
		return round( $date_diff / 86400 );
	}

	public function fv_pro_purchase_box( $review ) {
		if ( ! self::$show_notice ) {
			return;
		}

		$review = get_option( 'fv_pro_purchase' );

		$remind_later   = get_transient( 'fv_pro_remind_later' );
		$status         = $review['status'];
		$current_screen = get_current_screen();
		$page_id        = $current_screen->id;
		$fv_page_id_arr = array(
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'edit-fv_export_profile',
			'edit-fv_data_profile',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
		);

		$hide_logo = '';
		if ( in_array( $page_id, $fv_page_id_arr, true ) ) {
			$hide_logo = 'fv-hide-logo';
		}
		if ( 'done' !== $status ) {
			if ( '' === $status && false === $remind_later ) {
				?>
				<div class="fv-pro-box notice notice-success">
					<div class="fv-logo <?php echo esc_html( $hide_logo ); ?>">
						<svg viewBox="0 0 1340 1340" version="1.1">
							<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
								<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
									<g id="g2950" transform="translate(533.017848, 2415.845322)">
										<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
										<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
									</g>
								</g>
							</g>
						</svg>
					</div>
					<div class="fv-pro-content">
						<span>
							<?php echo __( 'Enjoying Form Vibes? Explore <b>Form Vibes Pro</b> for more advanced features.  ', 'wpv-fv' ); ?>

						</span>
						<span class="fv-go-pro-button">
							<a class="button button-primary " target="_blank" href="https://wpvibes.link/go/form-vibes-pro"><?php esc_html_e( 'Explore Pro!', 'wpv-fv' ); ?></a>

						</span>
						<a class="notice-dismiss" href="<?php echo esc_html( add_query_arg( 'fv_pro_later', 'later' ) ); ?>"></a>
					</div>
				</div>
				<?php
			}
		}
	}


	public function fv_pro_later() {
		set_transient( 'fv_pro_remind_later', 'show again', MONTH_IN_SECONDS );
	}

	public function fv_pro_done() {
		$review              = get_option( 'fv_pro_purchase' );
		$review['status']    = 'done';
		$review['purchased'] = current_time( 'yy/m/d' );
		update_option( 'fv_pro_purchase', $review, false );
	}

	public function settings_link( $links ) {
		$url           = admin_url( 'admin.php' ) . '?page=fv-db-settings';
		$settings_link = '<a class="fv-go-pro-menu" href=' . $url . '>Settings</a>';

		array_unshift( $links, $settings_link );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
		if ( ! $is_pro_activated ) {
			$mylinks = array(
				'<a class="fv-go-pro-menu" style="font-weight: bold; color : #93003c; text-shadow:1px 1px 1px #eee;" target="_blank" href="https://wpvibes.link/go/form-vibes-pro">Go Pro</a>',
			);
			$links   = array_merge( $links, $mylinks );
		}

		return $links;
	}

	public function fv_table_notice() {
		$screen = get_current_screen();

		if ( $screen->id === 'form-vibes_page_fv-db-settings' ) {
			global $wpdb;
			$table_exist = true;

			$gdpr_settings = Utils::get_settings();

			$debug_mode = $gdpr_settings['debugMode'];

			if ( ! $debug_mode ) {
				return;
			}

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_enteries'" ) === null ) {
				$table_exist = false;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_entry_meta'" ) === null ) {
				$table_exist = false;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_logs'" ) === null ) {
				$table_exist = false;
			}

			if ( $table_exist ) {
				return;
			} else {
				?>
				<div class="fv-notice notice notice-error">
					<div class="fv-notice-content">
						<span>
							<?php esc_html_e( 'Database update required.', 'wpv-fv' ); ?>
						</span>
						<span class="fv-notice-action">
							<a href="<?php echo esc_html( add_query_arg( 'fv_db_update', 'yes' ) ); ?>"><?php esc_html_e( 'Click here!', 'wpv-fv' ); ?></a>
						</span>
					</div>
				</div>
				<?php
			}
		}
	}

	public function fv_db_update() {
		// phpcs:ignore
		if ( isset( $_GET['fv_db_update'] ) ) {
			// phpcs:ignore
			DbTables::create_db_table();
		}
	}
}

Plugin::instance();
