<?php
/**
 * Site Health WP-CLI Commands
 */

// Make sure the file is not directly accessible.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Class Site_Health_WP_CLI
	 */
	class Site_Health_WP_CLI {
		/**
		 * See the sites debug information.
		 *
		 * ## EXAMPLES
		 *
		 * wp site-health debug
		 */
		public function debug( $args, $assoc_args ) {
			if ( ! class_exists( 'WP_Debug_Data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			}

			$info  = WP_Debug_Data::debug_data();
			$sizes = WP_Debug_Data::get_sizes();

			$wordpress_size = 'wordpress_size: ' . $sizes['wordpress_size']['debug'];
			$themes_size    = 'themes_size: ' . $sizes['themes_size']['debug'];
			$plugins_size   = 'plugins_size: ' . $sizes['plugins_size']['debug'];
			$uploads_size   = 'uploads_size: ' . $sizes['uploads_size']['debug'];
			$database_size  = 'database_size: ' . $sizes['database_size']['debug'];
			$total_size     = 'total_size: ' . $sizes['total_size']['debug'];

			$results = WP_Debug_Data::format( $info, 'debug' );
			$results = str_replace( 'wordpress_size: loading...', $wordpress_size, $results );
			$results = str_replace( 'themes_size: loading...', $themes_size, $results );
			$results = str_replace( 'plugins_size: loading...', $plugins_size, $results );
			$results = str_replace( 'uploads_size: loading...', $uploads_size, $results );
			$results = str_replace( 'database_size: loading...', $database_size, $results );
			$results = str_replace( 'total_size: loading...', $total_size, $results );

			$results = str_replace( '`', '', $results );

			WP_CLI::line( $results );
		}

		/**
		 * See the sites status.
		 *
		 * ## EXAMPLES
		 *
		 * wp site-health status
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Render the output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 *   - yaml
		 * ---
		 */
		public function status( $args, $assoc_args ) {
			$wp_site_health = WP_Site_Health::get_instance();

			$tests = $wp_site_health::get_tests();

			$results = array();

			$site_status = array(
				'good'        => 0,
				'recommended' => 0,
				'critical'    => 0,
			);

			// Direct tests.
			foreach ( $tests['direct'] as $test ) {
				if ( is_callable( array( $wp_site_health, 'get_test_' . $test['test'] ) ) ) {
					$test_result = call_user_func( array( $wp_site_health, 'get_test_' . $test['test'] ) );
				}

				if ( is_callable( $test['test'] ) ) {
					$test_result = call_user_func( $test['test'] );
				}

				$results[] = array(
					'test'        => $test['label'],
					'type'        => wp_kses( $test_result['badge']['label'], array() ),
					'status'      => wp_kses( $test_result['status'], array() ),
					'description' => html_entity_decode( wp_kses( $test_result['description'], array() ) ),
				);
			}

			foreach ( $tests['async'] as $key => $test ) {
				if ( is_callable( array( $wp_site_health, 'get_test_' . $key ) ) ) {
					$test_result = call_user_func( array( $wp_site_health, 'get_test_' . $key ) );
				}

				if ( is_callable( $test['test'] ) ) {
					$test_result = call_user_func( $test['test'] );
				}

				$results[] = array(
					'test'        => $test['label'],
					'type'        => wp_kses( $test_result['badge']['label'], array() ),
					'status'      => wp_kses( $test_result['status'], array() ),
					'description' => html_entity_decode( wp_kses( $test_result['description'], array() ) ),
				);
			}

			// Gather test status counts.
			foreach ( $results as $result ) {
				if ( 'critical' === $result['status'] ) {
					$site_status['critical']++;
				} elseif ( 'recommended' === $result['status'] ) {
					$site_status['recommended']++;
				} else {
					$site_status['good']++;
				}
			}

			// Remove passed tests.
			foreach ( $results as $key => $result ) {
				if ( 'good' === $result['status'] ) {
					unset( $results[ $key ] );
				}
			}

			// Output information.
			$status_results = 'Passed Tests: ' . $site_status['good'] . ', Critical Issues: ' . $site_status['critical'] . ', Recommended Improvements: ' . $site_status['recommended'];

			WP_CLI::line( $status_results );

			if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
				WP_CLI\Utils\format_items( 'json', $results, array( 'test', 'type', 'status', 'description' ) );
			} elseif ( WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'csv' ) {
				WP_CLI\Utils\format_items( 'csv', $results, array( 'test', 'type', 'status', 'description' ) );
			} elseif ( WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'yaml' ) {
				WP_CLI\Utils\format_items( 'yaml', $results, array( 'test', 'type', 'status', 'description' ) );
			} else {
				WP_CLI\Utils\format_items( 'table', $results, array( 'test', 'type', 'status', 'description' ) );
			}
		}
	}

	WP_CLI::add_command( 'site-health', 'Site_Health_WP_CLI' );
}
