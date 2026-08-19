<?php
if ( !defined('AME_ROOT_DIR') ) {
	define('AME_ROOT_DIR', dirname(dirname(__FILE__)));
}
if ( !defined('WS_AME_USE_BUNDLES') ) {
	/**
	 * If set to true, the plugin will use Webpack bundles when available. If set
	 * to false, it will load individual JS files instead.
	 */
	define('WS_AME_USE_BUNDLES', true);
}

if ( !defined('WS_AME_INTERNAL_VERSION') ) {
	define('WS_AME_INTERNAL_VERSION', 2024.001);
}

$thisDirectory = dirname(__FILE__);
require_once $thisDirectory . '/shadow_plugin_framework.php';
require_once $thisDirectory . '/role-utils.php';
require_once $thisDirectory . '/ame-utils.php';
require_once $thisDirectory . '/ame-collections.php';
require_once $thisDirectory . '/ame-option.php';
require_once $thisDirectory . '/actors.php';
require_once $thisDirectory . '/menu-item.php';
require_once $thisDirectory . '/menu.php';
require_once $thisDirectory . '/auto-versioning.php';

//The AJAX wrapper could be independent or installed as a Composer dependency.
if ( file_exists($thisDirectory . '/../ajax-wrapper/AjaxWrapper.php') ) {
	require_once $thisDirectory . '/../ajax-wrapper/AjaxWrapper.php';
}

//Composer autoloader.
if ( file_exists($thisDirectory . '/../vendor/autoload.php') ) {
	require_once $thisDirectory . '/../vendor/autoload.php';
}

require_once $thisDirectory . '/AmeAutoloader.php';

$wsAmeFreeAutoloader = new YahnisElsts\AdminMenuEditor\AmeAutoloader([
	//Customizable library
	'YahnisElsts\\AdminMenuEditor\\Customizable\\' => AME_ROOT_DIR . '/customizables',
	//Script dependency wrapper
	'YahnisElsts\\WpDependencyWrapper\\v1\\'       => AME_ROOT_DIR . '/includes/wp-dependency-wrapper',
	//AJAX wrapper
	'YahnisElsts\\AjaxActionWrapper\\v2\\'         => AME_ROOT_DIR . '/includes/ajax-wrapper-v2/src',
	//General utilities
	'YahnisElsts\\AdminMenuEditor\\Utils\\'        => AME_ROOT_DIR . '/includes/utils',
	//Wire DSL
	'YahnisElsts\\AdminMenuEditor\\WireDSL\\'       => AME_ROOT_DIR . '/shared-dsl/server',
]);
$wsAmeFreeAutoloader->register();
require_once $thisDirectory . '/../customizables/constants.php';

require_once $thisDirectory . '/module.php';
require_once $thisDirectory . '/persistent-module.php';
require_once $thisDirectory . '/shortcodes.php';

if ( file_exists($thisDirectory . '/../extras/pro-dependencies.php') ) {
	require_once $thisDirectory . '/../extras/pro-dependencies.php';
}

if ( !class_exists('WPMenuEditor', false) ) {
	require_once $thisDirectory . '/menu-editor-core.php';
}
