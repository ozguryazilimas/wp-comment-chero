<?php
/**
 * Bootstrap file for plugin compatibility checks.
 *
 * @package AutoPostThumbnail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WAPT_PLUGIN_ACTIVE' ) ) {
	return;
}

define( 'WAPT_PATH', defined( 'WAPT_PRO_PATH' ) ? WAPT_PRO_PATH : WAPT_FREE_PATH );
define( 'WAPT_PLUGIN_ACTIVE', true );
define( 'WAPT_PLUGIN_VERSION', '5.0.4' );
define( 'WAPT_PLUGIN_FILE', WAPT_PATH );
define( 'WAPT_ABSPATH', __DIR__ );
define( 'WAPT_PLUGIN_BASENAME', plugin_basename( WAPT_PATH ) );
define( 'WAPT_PLUGIN_SLUG', dirname( plugin_basename( WAPT_PATH ) ) );
define( 'WAPT_PRODUCT_SLUG', basename( dirname( WAPT_PATH ) ) );
define( 'WAPT_PLUGIN_URL', plugins_url( '', WAPT_PATH ) );
define( 'WAPT_PLUGIN_DIR', __DIR__ );


require_once WAPT_PLUGIN_DIR . '/vendor/autoload.php';

if ( defined( 'WAPT_PRO_PATH' ) && class_exists( 'AutoPostThumbnailPro\Loader' ) ) {
	( new AutoPostThumbnailPro\Loader() )->run();
}

( new AutoPostThumbnail\Loader() )->run();
