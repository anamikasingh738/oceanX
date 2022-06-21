<?php

namespace FormVibes\Classes;

use Carbon\Carbon;
use Stripe\Util\Util;

class Utils {


	public static function dashes_to_camel_case( $string, $capitalize_first_character = true ) {
		$str = str_replace( '-', '', ucwords( $string, '-' ) );
		if ( ! $capitalize_first_character ) {
			$str = lcfirst( $str );
		}
		return $str;
	}

	public static function get_plugin_key_by_name( $name ) {
		if ( 'Contact Form 7' === $name ) {
			return 'cf7';
		} elseif ( 'Elementor Forms' === $name ) {
			return 'elementor';
		} elseif ( 'Beaver Builder' === $name ) {
			return 'beaverBuilder';
		} elseif ( 'WP Forms' === $name ) {
			return 'wpforms';
		} elseif ( 'Caldera' === $name ) {
			return 'caldera';
		} elseif ( 'Ninja Forms' === $name ) {
			return 'ninja';
		}
		return $name;
	}

	public static function get_query_dates( $query_type, $param ) {
		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

		if ( $hours >= 0 ) {
			$time_zone = '+' . $hours . ':' . $minutes;
		} else {
			$time_zone = $hours . ':' . $minutes;
		}

		if ( 'Custom' !== $query_type ) {
			$dates = self::get_date_interval( $query_type, $time_zone );

			$from_date = $dates['fromDate'];
			$to_date   = $dates['endDate'];
		} else {
			$tz        = new \DateTimeZone( $time_zone );
			$from_date = new \DateTime( $param['fromDate'] );
			$from_date->setTimezone( $tz );
			$to_date = new \DateTime( $param['toDate'] );
			$to_date->setTimezone( $tz );
		}

		return array( $from_date, $to_date );
	}

	public static function get_date_interval( $query_type, $time_zone ) {
		$dates = array();
		switch ( $query_type ) {
			case 'Today':
				$dates['fromDate'] = Carbon::now( $time_zone );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'Yesterday':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDay();
				$dates['endDate']  = Carbon::now( $time_zone )->subDay();

				return $dates;

			case 'Last_7_Days':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDays( 6 );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'This_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::MONDAY );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SUNDAY );
				} else {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::SUNDAY );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SATURDAY );
				}
				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;
				return $dates;

			case 'Last_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::MONDAY )->subDays( 7 );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SUNDAY )->subDays( 7 );
				} else {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::SUNDAY )->subDays( 7 );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SATURDAY )->subDays( 7 );
				}

				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;

				return $dates;

			case 'Last_30_Days':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDays( 29 );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'This_Month':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfMonth();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfMonth();

				return $dates;

			case 'Last_Month':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonth()->startOfMonth();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonth()->endOfMonth();

				return $dates;

			case 'This_Quarter':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfQuarter();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfQuarter();

				return $dates;

			case 'Last_Quarter':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonths( 3 )->startOfQuarter();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonths( 3 )->endOfQuarter();

				return $dates;

			case 'This_Year':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfYear();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfYear();

				return $dates;

			case 'Last_Year':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonths( 12 )->startOfYear();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonths( 12 )->endOfYear();

				return $dates;
		}
	}
	public static function get_dates( $query_type ) {
		$dates = array();
		switch ( $query_type ) {
			case 'Today':
				$dates['fromDate'] = gmdate( 'Y-m-d H:i:s' );
				$dates['endDate']  = gmdate( 'Y-m-d H:i:s' );

				return $dates;

			case 'Yesterday':
				$dates['fromDate'] = gmdate( 'Y-m-d H:i:s', strtotime( '-1 days' ) );
				$dates['endDate']  = gmdate( 'Y-m-d H:i:s', strtotime( '-1 days' ) );

				return $dates;

			case 'Last_7_Days':
				$dates['fromDate'] = gmdate( 'Y-m-d H:i:s', strtotime( '-6 days' ) );
				$dates['endDate']  = gmdate( 'Y-m-d H:i:s' );

				return $dates;

			case 'This_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					if ( 'Mon' !== gmdate( 'D' ) ) {
						$staticstart = gmdate( 'Y-m-d', strtotime( 'last Monday' ) );
					} else {
						$staticstart = gmdate( 'Y-m-d' );
					}

					if ( 'Sat' !== gmdate( 'D' ) ) {
						$staticfinish = gmdate( 'Y-m-d', strtotime( 'next Sunday' ) );
					} else {

						$staticfinish = gmdate( 'Y-m-d' );
					}
				} else {
					if ( 'Sun' !== gmdate( 'D' ) ) {
						$staticstart = gmdate( 'Y-m-d', strtotime( 'last Sunday' ) );
					} else {
						$staticstart = gmdate( 'Y-m-d' );
					}

					if ( 'Sat' !== gmdate( 'D' ) ) {
						$staticfinish = gmdate( 'Y-m-d', strtotime( 'next Saturday' ) );
					} else {

						$staticfinish = gmdate( 'Y-m-d' );
					}
				}
				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;
				return $dates;

			case 'Last_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$previous_week = strtotime( '-1 week +1 day' );
					$start_week    = strtotime( 'last monday midnight', $previous_week );
					$end_week      = strtotime( 'next sunday', $start_week );
				} else {
					$previous_week = strtotime( '-1 week +1 day' );
					$start_week    = strtotime( 'last sunday midnight', $previous_week );
					$end_week      = strtotime( 'next saturday', $start_week );
				}
				$start_week = gmdate( 'Y-m-d', $start_week );
				$end_week   = gmdate( 'Y-m-d', $end_week );

				$dates['fromDate'] = $start_week;
				$dates['endDate']  = $end_week;

				return $dates;

			case 'Last_30_Days':
				$dates['fromDate'] = gmdate( 'Y-m-d h:m:s', strtotime( '-29 days' ) );
				$dates['endDate']  = gmdate( 'Y-m-d h:m:s' );

				return $dates;

			case 'This_Month':
				$dates['fromDate'] = gmdate( 'Y-m-01' );
				$dates['endDate']  = gmdate( 'Y-m-t' );

				return $dates;

			case 'Last_Month':
				$dates['fromDate'] = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
				$dates['endDate']  = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) );

				return $dates;

			case 'This_Quarter':
				$current_month = gmdate( 'm' );
				$current_year  = gmdate( 'Y' );
				if ( $current_month >= 1 && $current_month <= 3 ) {
					$start_date = strtotime( '1-January-' . $current_year );  // timestamp or 1-Januray 12:00:00 AM
					$end_date   = strtotime( '31-March-' . $current_year );  // timestamp or 1-April 12:00:00 AM means end of 31 March
				} elseif ( $current_month >= 4 && $current_month <= 6 ) {
					$start_date = strtotime( '1-April-' . $current_year );  // timestamp or 1-April 12:00:00 AM
					$end_date   = strtotime( '30-June-' . $current_year );  // timestamp or 1-July 12:00:00 AM means end of 30 June
				} elseif ( $current_month >= 7 && $current_month <= 9 ) {
					$start_date = strtotime( '1-July-' . $current_year );  // timestamp or 1-July 12:00:00 AM
					$end_date   = strtotime( '30-September-' . $current_year );  // timestamp or 1-October 12:00:00 AM means end of 30 September
				} elseif ( $current_month >= 10 && $current_month <= 12 ) {
					$start_date = strtotime( '1-October-' . $current_year );  // timestamp or 1-October 12:00:00 AM
					$end_date   = strtotime( '31-December-' . ( $current_year ) );  // timestamp or 1-January Next year 12:00:00 AM means end of 31 December this year
				}

				$dates['fromDate'] = gmdate( 'Y-m-d', $start_date );
				$dates['endDate']  = gmdate( 'Y-m-d', $end_date );
				return $dates;

			case 'Last_Quarter':
				$current_month = gmdate( 'm' );
				$current_year  = gmdate( 'Y' );

				if ( $current_month >= 1 && $current_month <= 3 ) {
					$start_date = strtotime( '1-October-' . ( $current_year - 1 ) );  // timestamp or 1-October Last Year 12:00:00 AM
					$end_date   = strtotime( '31-December-' . ( $current_year - 1 ) );  // // timestamp or 1-January  12:00:00 AM means end of 31 December Last year
				} elseif ( $current_month >= 4 && $current_month <= 6 ) {
					$start_date = strtotime( '1-January-' . $current_year );  // timestamp or 1-Januray 12:00:00 AM
					$end_date   = strtotime( '31-March-' . $current_year );  // timestamp or 1-April 12:00:00 AM means end of 31 March
				} elseif ( $current_month >= 7 && $current_month <= 9 ) {
					$start_date = strtotime( '1-April-' . $current_year );  // timestamp or 1-April 12:00:00 AM
					$end_date   = strtotime( '30-June-' . $current_year );  // timestamp or 1-July 12:00:00 AM means end of 30 June
				} elseif ( $current_month >= 10 && $current_month <= 12 ) {
					$start_date = strtotime( '1-July-' . $current_year );  // timestamp or 1-July 12:00:00 AM
					$end_date   = strtotime( '30-September-' . $current_year );  // timestamp or 1-October 12:00:00 AM means end of 30 September
				}
				$dates['fromDate'] = gmdate( 'Y-m-d', $start_date );
				$dates['endDate']  = gmdate( 'Y-m-d', $end_date );
				return $dates;

			case 'This_Year':
				$dates['fromDate'] = gmdate( 'Y-01-01' );
				$dates['endDate']  = gmdate( 'Y-12-t' );

				return $dates;

			case 'Last_Year':
				$dates['fromDate'] = gmdate( 'Y-01-01', strtotime( '-1 year' ) );
				$dates['endDate']  = gmdate( 'Y-12-t', strtotime( '-1 year' ) );

				return $dates;
		}
	}

	public static function get_first_plugin_form() {
		$forms   = array();
		$plugins = apply_filters( 'fv_forms', $forms );

		$class = '\FormVibes\Integrations\\' . ucfirst( array_keys( $plugins )[0] );

		$plugin_forms = $class::get_forms( array_keys( $plugins )[0] );
		$plugin       = array_keys( $plugins )[0];

		$data = array(
			'formName'       => $plugin_forms,
			'selectedPlugin' => $plugin,
			'selectedForm'   => array_keys( $plugin_forms )[0],
		);

		return $data;
	}

	public static function get_settings() {
		$defaults = array(
			'ip'           => true,
			'userAgent'    => false,
			'debugMode'    => false,
			'exportReason' => false,
		);
		$settings = get_option( 'fvSettings' );

		$settings = wp_parse_args( $settings, $defaults );

		return $settings;
	}

	public static function get_entry_table_fields() {
		$entry_table_fields = array(
			'url',
			'user_agent',
			'fv_status',
			'captured',
		);
		// phpcs:ignore
		$entry_table_fields = apply_filters( 'formvibes/entry_table_fields', $entry_table_fields );

		return $entry_table_fields;
	}

	public static function prepare_table_columns( $columns, $plugin_name, $form_id, $type = 'submission' ) {

		$columns = array_filter($columns, function($column) {
            return ($column !== NULL && $column !== FALSE && $column !== "");
        });

		$key = array_search( 'fv-notes', $columns, true );
		if ( ( $key ) !== false ) {
			unset( $columns[ $key ] );
		}

		$saved_columns = get_option( 'fv-keys' );

		$col_label = 'Header';
		$col_key   = 'accessor';

		if ( $type === 'columns' ) {
			$col_label = 'alias';
			$col_key   = 'colKey';
		}

		if ( $saved_columns ) {
			if ( ! array_key_exists( $plugin_name . '_' . $form_id, $saved_columns ) ) {
				$cols = array();
				foreach ( $columns as $column ) {
					$cols[] = (object) array(
						$col_label => 'captured' === $column || 'datestamp' === $column ? 'Submission Date' : $column,
						$col_key   => $column,
						'visible'  => true,
					);
				}
				return $cols;
			}
		}

		$current_form_saved_columns = $saved_columns[ $plugin_name . '_' . $form_id ];

		if ( empty( $current_form_saved_columns ) ) {
			$cols = array();
			foreach ( $columns as $column ) {
				$cols[] = (object) array(
					$col_label => 'captured' === $column || 'datestamp' === $column ? 'Submission Date' : $column,
					$col_key   => $column,
					'visible'  => true,
				);
			}
			return $cols;
		}

		$cols = array();
		foreach ( $current_form_saved_columns as $column ) {
			if ( in_array( $column['colKey'], $columns, true ) ) {
				$cols[] = (object) array(
					$col_label => $column['alias'],
					$col_key   => $column['colKey'],
					'visible'  => $column['visible'],
				);
			}
		}

		foreach ( $columns as $column ) {
			$key = array_search( $column, array_column( $cols, $col_key ), true );
			if ( false === $key ) {
				$cols[] = (object) array(
					$col_label => $column,
					$col_key   => $column,
					'visible'  => true,
				);
			}
		}

		return $cols;
	}
}
