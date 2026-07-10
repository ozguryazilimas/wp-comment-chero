<?php
/*
Plugin Name: White Label CMS
Plugin URI: https://www.videousermanuals.com/white-label-cms/?utm_campaign=wlcms&utm_medium=plugin&utm_source=readme-txt
Description:  A plugin that allows you to brand WordPress CMS as your own
Version: 2.7.14
Author: www.videousermanuals.com
Author URI: https://www.videousermanuals.com/?utm_campaign=wlcms&utm_medium=plugin&utm_source=readme-txt
Text Domain: white-label-cms
Domain Path: /languages
 */


if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('WLCMS_VERSION', '2.7.14');
define("WLCMS_DIR", plugin_dir_path(__FILE__));
define("WLCMS_ASSETS_URL", plugin_dir_url(__FILE__) . 'assets/');
define("WLCMS_BASENAME", plugin_basename(__FILE__));
define("WLCMS_ASSETS_DIR", WLCMS_DIR . 'assets/');
define("WLCMS_SCREEN_ID", 'settings_page_wlcms-plugin');


include_once(WLCMS_DIR . 'includes/classes/I18n.php');
include_once(WLCMS_DIR . 'includes/classes/Loader.php');
include_once(WLCMS_DIR . 'includes/Functions.php');

global $wlcms;

if (!function_exists('wlcms')) :
    function wlcms()
    {
        global $wlcms;

        $wlcms = WLCMS_Loader::getInstance();

        return $wlcms;
    }
endif;

wlcms();
