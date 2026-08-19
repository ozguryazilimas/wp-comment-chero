<?php
/*
Plugin Name: Admin Menu Editor
Plugin URI: http://w-shadow.com/blog/2008/12/20/admin-menu-editor-for-wordpress/
Description: Lets you directly edit the WordPress admin menu. You can re-order, hide or rename existing menus, add custom menus and more. 
Version: 1.15.2
Author: Janis Elsts
Author URI: http://w-shadow.com/blog/
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
This plugin may include third-party libraries and other content that is licensed under various
GPL-compatible licenses. In such cases, the relevant license will usually be stated at the top
of the source code file or in "readme.txt", "license.txt" or a similar file located in the same
directory as the content.
*/

if ( include(dirname(__FILE__) . '/includes/version-conflict-check.php') ) {
	return;
}

//Load the plugin
require_once dirname(__FILE__) . '/includes/basic-dependencies.php';
global $wp_menu_editor;
$wp_menu_editor = new WPMenuEditor(__FILE__, 'ws_menu_editor');
