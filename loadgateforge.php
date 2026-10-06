<?php
/**
 * Plugin Name:       LoadGate Forge
 * Plugin URI:        https://github.com/gunjanjaswal/LoadGate-Forge
 * Description:       Stop selected plugins from loading on specific front-end URLs. Lighter pages, set from one screen, fully reversible, with admin and REST always left untouched.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Gunjan Jaswal
 * Author URI:        https://www.gunjanjaswal.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       loadgateforge
 *
 * @package LoadGateForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOADGATEFORGE_VERSION', '1.0.0' );
define( 'LOADGATEFORGE_SELF', 'loadgateforge/loadgateforge.php' );

/**
 * Path to the mu-plugins directory.
 *
 * @return string
 */
function loadgateforge_mu_dir() {
	return defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
}

/**
 * Full path to the installed mu loader.
 *
 * @return string
 */
function loadgateforge_mu_target() {
	return loadgateforge_mu_dir() . '/loadgateforge-mu.php';
}

/**
 * Copy the bundled loader into mu-plugins.
 *
 * @return bool True on success.
 */
function loadgateforge_install_mu() {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! WP_Filesystem() ) {
		return false;
	}

	$dir = loadgateforge_mu_dir();
	if ( ! $wp_filesystem->is_dir( $dir ) ) {
		if ( ! $wp_filesystem->mkdir( $dir, FS_CHMOD_DIR ) ) {
			return false;
		}
	}

	$source  = plugin_dir_path( __FILE__ ) . 'mu-loader.php';
	$content = $wp_filesystem->get_contents( $source );
	if ( false === $content || '' === $content ) {
		return false;
	}

	$ok = $wp_filesystem->put_contents( loadgateforge_mu_target(), $content, FS_CHMOD_FILE );
	if ( $ok ) {
		update_option( 'loadgateforge_mu_version', LOADGATEFORGE_VERSION, false );
	}

	return (bool) $ok;
}

/**
 * Remove the mu loader.
 */
function loadgateforge_remove_mu() {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( WP_Filesystem() ) {
		$target = loadgateforge_mu_target();
		if ( $wp_filesystem->exists( $target ) ) {
			$wp_filesystem->delete( $target );
		}
	}

	delete_option( 'loadgateforge_mu_version' );
}

register_activation_hook( __FILE__, 'loadgateforge_install_mu' );
register_deactivation_hook( __FILE__, 'loadgateforge_remove_mu' );

/**
 * Keep the mu loader in sync on version changes, and flag if it is missing.
 */
function loadgateforge_maybe_sync_mu() {
	global $wp_filesystem;

	$installed = get_option( 'loadgateforge_mu_version' );
	$missing   = true;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( WP_Filesystem() ) {
		$missing = ! $wp_filesystem->exists( loadgateforge_mu_target() );
	}

	if ( $missing || LOADGATEFORGE_VERSION !== $installed ) {
		loadgateforge_install_mu();
	}
}
add_action( 'admin_init', 'loadgateforge_maybe_sync_mu' );

/**
 * Show a warning if the loader could not be installed.
 */
function loadgateforge_admin_notice() {
	global $wp_filesystem;

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( WP_Filesystem() && $wp_filesystem->exists( loadgateforge_mu_target() ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	printf(
		/* translators: %s: path to the mu-plugins directory. */
		esc_html__( 'LoadGate Forge could not install its loader into %s. Make that folder writable and reactivate the plugin, otherwise rules will not take effect.', 'loadgateforge' ),
		'<code>' . esc_html( loadgateforge_mu_dir() ) . '</code>'
	);
	echo '</p></div>';
}
add_action( 'admin_notices', 'loadgateforge_admin_notice' );

/**
 * Register the settings page.
 */
function loadgateforge_admin_menu() {
	add_options_page(
		__( 'LoadGate Forge', 'loadgateforge' ),
		__( 'LoadGate Forge', 'loadgateforge' ),
		'manage_options',
		'loadgateforge',
		'loadgateforge_render_page'
	);
}
add_action( 'admin_menu', 'loadgateforge_admin_menu' );

/**
 * List of togglable active plugins (excluding LoadGate Forge itself).
 *
 * @return array<string,string> Map of plugin file to display name.
 */
function loadgateforge_candidate_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all    = get_plugins();
	$active = (array) get_option( 'active_plugins', array() );
	$list   = array();

	foreach ( $active as $file ) {
		if ( LOADGATEFORGE_SELF === $file ) {
			continue;
		}
		$list[ $file ] = isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] : $file;
	}

	return $list;
}

/**
 * Save the submitted rules.
 */
function loadgateforge_handle_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'loadgateforge' ) );
	}
	check_admin_referer( 'loadgateforge_save' );

	$enabled = ( isset( $_POST['loadgateforge_enabled'] ) && '1' === $_POST['loadgateforge_enabled'] ) ? '1' : '';
	update_option( 'loadgateforge_enabled', $enabled, false );

	$candidates = loadgateforge_candidate_plugins();
	$allowed    = array( 'contains', 'prefix', 'exact' );
	$rules      = array();

	$paths = isset( $_POST['rule_path'] ) ? (array) wp_unslash( $_POST['rule_path'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per element below.

	foreach ( $paths as $i => $raw_path ) {
		if ( isset( $_POST['rule_remove'][ $i ] ) ) {
			continue;
		}

		$path = sanitize_text_field( $raw_path );
		$path = trim( $path );
		if ( '' === $path ) {
			continue;
		}

		// Accept a full URL and reduce it to a path.
		if ( false !== strpos( $path, '://' ) ) {
			$parsed = wp_parse_url( $path, PHP_URL_PATH );
			$path   = is_string( $parsed ) ? $parsed : $path;
		}

		$match = 'contains';
		if ( isset( $_POST['rule_match'][ $i ] ) ) {
			$candidate_match = sanitize_key( wp_unslash( $_POST['rule_match'][ $i ] ) );
			if ( in_array( $candidate_match, $allowed, true ) ) {
				$match = $candidate_match;
			}
		}

		$plugins = array();
		if ( isset( $_POST['rule_plugins'][ $i ] ) && is_array( $_POST['rule_plugins'][ $i ] ) ) {
			$selected = array_map( 'sanitize_text_field', wp_unslash( $_POST['rule_plugins'][ $i ] ) );
			foreach ( $selected as $plugin_file ) {
				if ( isset( $candidates[ $plugin_file ] ) ) {
					$plugins[] = $plugin_file;
				}
			}
		}

		if ( empty( $plugins ) ) {
			continue;
		}

		$rules[] = array(
			'path'    => $path,
			'match'   => $match,
			'plugins' => $plugins,
		);
	}

	update_option( 'loadgateforge_rules', $rules, true );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'        => 'loadgateforge',
				'loadgateforge_msg' => 'saved',
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}
add_action( 'admin_post_loadgateforge_save', 'loadgateforge_handle_save' );

/**
 * Render one rule block.
 *
 * @param int                   $index      Row index.
 * @param array                 $rule       Rule data (path, match, plugins).
 * @param array<string,string>  $candidates Available plugins.
 */
function loadgateforge_render_rule( $index, $rule, $candidates ) {
	$path    = isset( $rule['path'] ) ? $rule['path'] : '';
	$match   = isset( $rule['match'] ) ? $rule['match'] : 'contains';
	$plugins = isset( $rule['plugins'] ) ? (array) $rule['plugins'] : array();
	?>
	<div class="loadgateforge-rule" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;margin:0 0 16px;max-width:760px;">
		<p>
			<label>
				<strong><?php esc_html_e( 'On URLs where the path', 'loadgateforge' ); ?></strong>
				<select name="rule_match[<?php echo esc_attr( $index ); ?>]">
					<option value="contains" <?php selected( $match, 'contains' ); ?>><?php esc_html_e( 'contains', 'loadgateforge' ); ?></option>
					<option value="prefix" <?php selected( $match, 'prefix' ); ?>><?php esc_html_e( 'starts with', 'loadgateforge' ); ?></option>
					<option value="exact" <?php selected( $match, 'exact' ); ?>><?php esc_html_e( 'is exactly', 'loadgateforge' ); ?></option>
				</select>
				<input type="text" name="rule_path[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $path ); ?>" placeholder="/shop/" class="regular-text" />
			</label>
		</p>
		<p><strong><?php esc_html_e( 'do not load these plugins:', 'loadgateforge' ); ?></strong></p>
		<div style="columns:2;max-width:720px;">
			<?php foreach ( $candidates as $file => $name ) : ?>
				<label style="display:block;margin:0 0 6px;">
					<input type="checkbox" name="rule_plugins[<?php echo esc_attr( $index ); ?>][]" value="<?php echo esc_attr( $file ); ?>" <?php checked( in_array( $file, $plugins, true ) ); ?> />
					<?php echo esc_html( $name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<p>
			<label style="color:#b32d2e;">
				<input type="checkbox" name="rule_remove[<?php echo esc_attr( $index ); ?>]" value="1" />
				<?php esc_html_e( 'Remove this rule when I save', 'loadgateforge' ); ?>
			</label>
		</p>
	</div>
	<?php
}

/**
 * Render the settings page.
 */
function loadgateforge_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$candidates = loadgateforge_candidate_plugins();
	$rules      = get_option( 'loadgateforge_rules', array() );
	$rules      = is_array( $rules ) ? $rules : array();
	$enabled    = get_option( 'loadgateforge_enabled', '1' );
	$msg        = isset( $_GET['loadgateforge_msg'] ) ? sanitize_key( wp_unslash( $_GET['loadgateforge_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'LoadGate Forge', 'loadgateforge' ); ?></h1>

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rules saved.', 'loadgateforge' ); ?></p></div>
		<?php endif; ?>

		<p class="description" style="max-width:760px;">
			<?php esc_html_e( 'Pick plugins that should not load on certain front-end URLs. Matching runs before WordPress loads plugins, so it only sees the URL, not the page template. Admin, login, REST and cron requests are never affected, and nothing here is permanent.', 'loadgateforge' ); ?>
		</p>

		<?php if ( empty( $candidates ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No other active plugins to manage yet.', 'loadgateforge' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'loadgateforge_save' ); ?>
			<input type="hidden" name="action" value="loadgateforge_save" />

			<p>
				<label>
					<input type="checkbox" name="loadgateforge_enabled" value="1" <?php checked( '1', $enabled ); ?> />
					<strong><?php esc_html_e( 'Apply rules on the front end', 'loadgateforge' ); ?></strong>
					<span class="description"><?php esc_html_e( '(uncheck as a quick off switch)', 'loadgateforge' ); ?></span>
				</label>
			</p>

			<h2><?php esc_html_e( 'Rules', 'loadgateforge' ); ?></h2>

			<?php
			$index = 0;
			foreach ( $rules as $rule ) {
				loadgateforge_render_rule( $index, $rule, $candidates );
				$index++;
			}
			// One blank row to add a new rule.
			loadgateforge_render_rule( $index, array(), $candidates );
			?>

			<p class="description" style="max-width:760px;">
				<?php esc_html_e( 'Fill in the blank rule to add another. Disabling a plugin that renders a page will break that page, so test the URL after saving.', 'loadgateforge' ); ?>
			</p>

			<?php submit_button( __( 'Save rules', 'loadgateforge' ) ); ?>
		</form>

		<hr style="max-width:760px;margin:28px 0 12px;" />
		<p class="description" style="max-width:760px;">
			<?php
			printf(
				/* translators: 1: Ko-fi support link, 2: developer contact email link. */
				esc_html__( 'Built by Gunjan Jaswal. Enjoying LoadGate Forge? %1$s, or %2$s.', 'loadgateforge' ),
				'<a href="' . esc_url( 'https://ko-fi.com/gunjanjaswal' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'buy me a coffee on Ko-fi', 'loadgateforge' ) . '</a>',
				'<a href="' . esc_url( 'mailto:hello@gunjanjaswal.me' ) . '">' . esc_html__( 'contact the developer', 'loadgateforge' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Settings, support and contact links on the plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function loadgateforge_action_links( $links ) {
	$settings = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=loadgateforge' ) ),
		esc_html__( 'Settings', 'loadgateforge' )
	);
	array_unshift( $links, $settings );

	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://ko-fi.com/gunjanjaswal' ),
		esc_html__( 'Support on Ko-fi', 'loadgateforge' )
	);
	$links[] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( 'mailto:hello@gunjanjaswal.me' ),
		esc_html__( 'Contact developer', 'loadgateforge' )
	);

	return $links;
}
add_filter( 'plugin_action_links_' . LOADGATEFORGE_SELF, 'loadgateforge_action_links' );
