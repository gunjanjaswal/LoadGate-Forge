<?php
/**
 * LoadGate must-use loader.
 *
 * Installed automatically into wp-content/mu-plugins/ by the LoadGate plugin.
 * It runs before regular plugins load, so it can decide which plugins to skip
 * for the current front-end request. Do not edit by hand; the plugin overwrites
 * it on update. Deactivating LoadGate removes it.
 *
 * LoadGate-MU-Version: 1.0.0
 *
 * @package LoadGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'loadgate_mu_filter_active_plugins' ) ) {
	/**
	 * Remove selected plugins from the active list for matching front-end URLs.
	 *
	 * @param mixed $plugins Active plugins list.
	 * @return mixed
	 */
	function loadgate_mu_filter_active_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}

		// Only ever touch plain front-end GET requests.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return $plugins;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return $plugins;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return $plugins;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $plugins;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method ) {
			return $plugins;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri ) {
			return $plugins;
		}

		// Never run for admin, login, REST or cron endpoints.
		if ( false !== strpos( $uri, '/wp-admin/' )
			|| false !== strpos( $uri, 'wp-login.php' )
			|| false !== strpos( $uri, '/wp-json/' )
			|| false !== strpos( $uri, 'wp-cron.php' ) ) {
			return $plugins;
		}

		if ( '1' !== get_option( 'loadgate_enabled', '1' ) ) {
			return $plugins;
		}

		$rules = get_option( 'loadgate_rules', array() );
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return $plugins;
		}

		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			$path = $uri;
		}

		$disable = array();
		foreach ( $rules as $rule ) {
			if ( empty( $rule['path'] ) || empty( $rule['plugins'] ) || ! is_array( $rule['plugins'] ) ) {
				continue;
			}

			$needle = $rule['path'];
			$match  = isset( $rule['match'] ) ? $rule['match'] : 'contains';

			if ( 'exact' === $match ) {
				$hit = ( rtrim( $path, '/' ) === rtrim( $needle, '/' ) );
			} elseif ( 'prefix' === $match ) {
				$hit = ( 0 === strpos( $path, $needle ) );
			} else {
				$hit = ( false !== strpos( $path, $needle ) );
			}

			if ( $hit ) {
				foreach ( $rule['plugins'] as $plugin_file ) {
					$disable[ $plugin_file ] = true;
				}
			}
		}

		if ( empty( $disable ) ) {
			return $plugins;
		}

		// LoadGate must never disable itself.
		unset( $disable['loadgate/loadgate.php'] );

		$kept = array();
		foreach ( $plugins as $plugin_file ) {
			if ( isset( $disable[ $plugin_file ] ) ) {
				continue;
			}
			$kept[] = $plugin_file;
		}

		return array_values( $kept );
	}
}

add_filter( 'option_active_plugins', 'loadgate_mu_filter_active_plugins' );
