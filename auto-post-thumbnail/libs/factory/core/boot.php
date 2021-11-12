<?php
/**
 * Factory Plugin
 *
 * @author        @author        Alex Kovalev <alex.kovalevv@gmail.com>, repo: https://github.com/alexkovalevv
 * @since         1.0.0
 * @package       core
 * @copyright (c) 2018, Webcraftic Ltd
 *
 */

// Exit if accessed directly
if( !defined('ABSPATH') ) {
	exit;
}

if( defined('FACTORY_450_LOADED') ) {
	return;
}

define('FACTORY_450_LOADED', true);

define('FACTORY_450_VERSION', '4.5.0');

define('FACTORY_450_DIR', dirname(__FILE__));
define('FACTORY_450_URL', plugins_url(null, __FILE__));

load_plugin_textdomain('wbcr_factory_450', false, dirname(plugin_basename(__FILE__)) . '/langs');

#comp merge
require_once(FACTORY_450_DIR . '/includes/functions.php');

require_once(FACTORY_450_DIR . '/includes/entities/class-factory-paths.php');
require_once(FACTORY_450_DIR . '/includes/entities/class-factory-support.php');

require_once(FACTORY_450_DIR . '/includes/class-factory-requests.php');
require_once(FACTORY_450_DIR . '/includes/class-factory-options.php');
require_once(FACTORY_450_DIR . '/includes/class-factory-plugin-base.php');
require_once(FACTORY_450_DIR . '/includes/class-factory-migrations.php');
require_once(FACTORY_450_DIR . '/includes/class-factory-notices.php');

// ASSETS
require_once(FACTORY_450_DIR . '/includes/assets-managment/class-factory-assets-list.php');
require_once(FACTORY_450_DIR . '/includes/assets-managment/class-factory-script-list.php');
require_once(FACTORY_450_DIR . '/includes/assets-managment/class-factory-style-list.php');

// PREMIUM
require_once(FACTORY_450_DIR . '/includes/premium/class-factory-license-interface.php');
require_once(FACTORY_450_DIR . '/includes/premium/class-factory-provider-abstract.php');
require_once(FACTORY_450_DIR . '/includes/premium/class-factory-manager.php');

// UPDATES
require_once(FACTORY_450_DIR . '/includes/updates/repositories/class-factory-repository-abstract.php');
require_once(FACTORY_450_DIR . '/includes/updates/repositories/class-factory-wordpress.php');
require_once(FACTORY_450_DIR . '/includes/updates/repositories/class-factory-github.php');
require_once(FACTORY_450_DIR . '/includes/updates/class-factory-upgrader.php');
require_once(FACTORY_450_DIR . '/includes/updates/class-factory-premium-upgrader.php');

require_once(FACTORY_450_DIR . '/includes/class-factory-plugin-abstract.php');

require_once(FACTORY_450_DIR . '/includes/activation/class-factory-activator.php');
require_once(FACTORY_450_DIR . '/includes/activation/class-factory-update.php');
#endcomp

add_action('admin_enqueue_scripts', function () {
	wp_enqueue_script('wfactory-450-core-general', FACTORY_450_URL . '/assets/js/core-general.js', [
		'jquery'
	], FACTORY_450_VERSION);
	wp_enqueue_script('wfactory-450-core-components', FACTORY_450_URL . '/assets/js/core-components.js', [
		'jquery',
		'wfactory-450-core-general'
	], FACTORY_450_VERSION);
});
