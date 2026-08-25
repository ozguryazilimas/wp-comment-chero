<?php
/*
Plugin Name:        User Role Editor
Plugin URI:         https://www.role-editor.com
Description:        Change/add/delete WordPress user roles and capabilities.
Version:            4.66.1
Requires at least:  4.6
Requires PHP:       7.4
Author:             Vladimir Garagulya
Author URI:         https://www.role-editor.com
License:            GPL v2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:        user-role-editor
Domain Path:        /lang/
*/

/*
Copyright 2010-2026  Vladimir Garagulya  (email: support@role-editor.com)
*/

defined( 'ABSPATH' ) || exit;

/**
 * Centralized debug-only error logging, gated by WP_DEBUG.
 */
if ( ! function_exists( 'ure_log_error' ) ) {
    function ure_log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- sole error_log() call site; centralized debug-only logging, gated by WP_DEBUG above.
            error_log( $message );
        }
    }
}

// Registered here, before the class_exists() guard below, and as a plain
// function rather than a URE_Core callback: when this plugin is activated
// while the paired Pro plugin is already active, Pro's bootstrap has already
// defined URE_Loader/URE_Core by the time WordPress includes this file for
// the activation request, so the guard below returns early and
// URE_Core::init() (which used to register this hook) never runs in that
// request. Registering a class-method callback here would still happen to
// work today (both plugins define an identical URE_Core), but relies on that
// coincidence rather than any guarantee; a standalone function guarded by
// function_exists(), exactly like ure_log_error() above, has no such
// dependency and is safe to register unconditionally.
if ( ! function_exists( 'ure_prevent_dual_activation' ) ) {
    function ure_prevent_dual_activation() {
        $pro_plugin = 'user-role-editor-pro/user-role-editor-pro.php';
        if ( file_exists( WP_PLUGIN_DIR . '/' . $pro_plugin ) && is_plugin_active( $pro_plugin ) ) {
            deactivate_plugins( $pro_plugin );
        }
    }
}
register_activation_hook( __FILE__, 'ure_prevent_dual_activation' );

if ( class_exists('URE_Loader') ) {
    ure_log_error( 'URE Loader class is defined already. Stop execution' );
    return;
}
$ure_classes_dir = plugin_dir_path( __FILE__ ) .'includes/classes/';
$ure_loader_file = $ure_classes_dir .'loader.php';
if ( !file_exists( $ure_loader_file ) ) {
    ure_log_error( "File '{$ure_loader_file}' is not found." );
    return;
}
require_once $ure_loader_file;
if ( !class_exists('URE_Loader') ) {
    ure_log_error( "File '{$ure_loader_file}' loaded, but class 'URE_Loader' is not found." );
    return;
}


$ure_core_file = $ure_classes_dir .'core.php';
if ( !URE_Loader::load_file( $ure_core_file, 'URE_Core' ) ) {
    return;
}

URE_Core::init( __FILE__ );

