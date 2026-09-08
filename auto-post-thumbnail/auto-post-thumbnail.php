<?php
/**
 * Plugin Name: Auto Featured Image - Auto Post Thumbnail
 * Plugin URI: https://themeisle.com/plugins/auto-featured-image
 * Description: Automatically sets the Featured Image from the first image in a post — for any post type. Generate images from post titles or search for images natively in Elementor, Gutenberg, and Classic Editor.
 * Version: 5.0.6
 * Requires PHP: 7.4
 * Author: Themeisle <contact@themeisle.com>
 * Author URI: https://themeisle.com
 * Text Domain: auto-post-thumbnail
 * WordPress Available:  yes
 * Requires License:    no
 * Domain Path: /languages
 *
 * @package AutoPostThumbnail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Check premium plugin compatibility.
 *
 * - Old pro (<=1.5.0): deactivates pro, shows error notice, returns true (free continues).
 * - Compatible pro (>1.5.0): deactivates free, shows info notice, returns false (free stops).
 * - No pro found: returns true (free continues).
 *
 * @return bool Whether the free plugin should continue loading.
 */
function wpapt_check_premium_compatibility() {
	$premium_paths = [
		'auto-post-thumbnail-premium/auto-post-thumbnail-premium.php',
	];

	// Also check same-directory path (dev environment).
	$current_dir = basename( __DIR__ );
	$dev_path    = $current_dir . '/auto-post-thumbnail-premium.php';
	if ( ! in_array( $dev_path, $premium_paths, true ) ) {
		$premium_paths[] = $dev_path;
	}

	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	foreach ( $premium_paths as $premium_path ) {
		$premium_file = WP_PLUGIN_DIR . '/' . $premium_path;

		if ( ! file_exists( $premium_file ) ) {
			continue;
		}

		// Check if premium plugin is active (handles both single site and multisite).
		$is_active = false;
		if ( is_multisite() ) {
			$active_plugins = get_site_option( 'active_sitewide_plugins', [] );
			$is_active      = isset( $active_plugins[ $premium_path ] );
		}
		if ( ! $is_active ) {
			$active_plugins = get_option( 'active_plugins', [] );
			$is_active      = in_array( $premium_path, $active_plugins, true );
		}

		if ( ! $is_active ) {
			continue;
		}

		$plugin_data     = get_plugin_data( $premium_file, false, false );
		$premium_version = $plugin_data['Version'] ?? '0.0.0';

		if ( version_compare( $premium_version, '1.5.0', '<=' ) ) {
			// Old incompatible pro — deactivate it.
			if ( ! defined( 'WAPT_PLUGIN_THROW_ERROR' ) ) {
				define( 'WAPT_PLUGIN_THROW_ERROR', true );
			}

			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			deactivate_plugins( $premium_path );

			$notice = function () {
				echo '<div class="notice notice-error"><p>' . wp_kses_post(
					sprintf(
						/* translators: %1$s: plugin name, %2$s: required plugin. */
						__( 'The %1$s plugin has been deactivated and needs to be updated to restore its functionality, as version 1.5.0 or lower is incompatible with %2$s.', 'auto-post-thumbnail' ),
						'<strong>' . __( 'Auto Featured Image Premium', 'auto-post-thumbnail' ) . '</strong>',
						'<strong>' . __( 'Auto Featured Image', 'auto-post-thumbnail' ) . '</strong>'
					)
				) . '</p></div>';
			};

			add_action( 'admin_notices', $notice );
			add_action( 'network_admin_notices', $notice );

			return true;
		}

		// Compatible pro is active — free should not load.
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( plugin_basename( __FILE__ ) );

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(
					__( 'Auto Featured Image (Free) has been deactivated because Auto Featured Image Pro is already active. No action is needed.', 'auto-post-thumbnail' )
				) . '</p></div>';
			}
		);

		return false;
	}

	return true;
}

if ( ! wpapt_check_premium_compatibility() ) {
	return;
}


define( 'WAPT_FREE_PATH', __FILE__ );

require_once 'bootstrap.php';
