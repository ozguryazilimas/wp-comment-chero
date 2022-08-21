<?php
/*
Plugin Name: Comment Chero
Plugin URI: http://www.ozguryazilim.com.tr
Description: This plugin displays unread comments in a sidebar widget and can highlight unread comments in comment lists.
Version: 1.3.2
Author: Onur Küçük
Author URI: http://www.delipenguen.net
License: GPL2
*/

/*  Copyright (C) 2013, Onur Küçük

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('COMMENT_CHERO_SHOW_COUNT', 10);

if (!defined('COMMENT_CHERO_CUSTOM_COMMENT_PAGINATION')) {
  define('COMMENT_CHERO_CUSTOM_COMMENT_PAGINATION', FALSE);
}

require_once(dirname(__FILE__) . '/includes/class-wp-cc-widget.php');
$wp_cc = new WP_Comment_Chero_Widget();

global $comment_chero_db_version;
$comment_chero_db_version = "1.0";

global $wpdb;
global $comment_chero_db_post_reads;
$comment_chero_db_post_reads = $wpdb->prefix . "comment_chero_post_reads";

$installed_version = get_option('comment_chero_db_version');
global $installed_version;

//widget
add_action('widgets_init', 'comment_chero_init');

// timestamp functions - activate these 2 calls if you don't call them in your template / theme
//add_action('get_header', 'comment_chero_get_time');
//add_action('get_footer', 'comment_chero_set_time');

// register the functions
add_action('comment_chero_get_time', 'comment_chero_get_time');
add_action('comment_chero_set_time', 'comment_chero_set_time');

// add the action to every comment
add_filter('comment_class', 'comment_chero_unread_class', 10);

// add css
// add_action('init', 'comment_chero_add_css');

register_activation_hook(__FILE__, 'comment_chero_init_db');
add_action('plugins_loaded', 'comment_chero_update_db_check');


function comment_chero_init_db() {
  global $comment_chero_db_version, $comment_chero_db_post_reads, $installed_version, $wpdb;

  if ($installed_version != $comment_chero_db_version) {
    $sql = "CREATE TABLE $comment_chero_db_post_reads (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        read_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id (post_id, user_id)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option("comment_chero_db_version", $comment_chero_db_version);
  }
}

function comment_chero_update_db_check() {
  global $comment_chero_db_version, $installed_version;

  if (get_site_option('comment_chero_db_version' ) != $comment_chero_db_version) {
    comment_chero_init_db();
  }
}

function comment_chero_add_assets() {
  // enqueue WordPress CSS hook
  wp_register_style('comment-chero', plugins_url('css/comment-chero.css', __FILE__));
  wp_enqueue_style('comment-chero');
}
add_action('wp_enqueue_scripts', 'comment_chero_add_assets');

// Update cookie when an user reads a post
function comment_chero_get_time() {
  global $wp_query, $wpdb,$user_ID;
  $post_id = $wp_query->post->ID;

  $_SESSION['comment_chero_post_id'] = $post_id;
  $_SESSION['comment_chero_post_time'] = current_time('mysql', 1);
}

// Update db that the user read the post
function comment_chero_set_time() {
  global $wpdb, $user_ID, $comment_chero_db_post_reads;

  if ($user_ID != '') {
    $post_id = $_SESSION['comment_chero_post_id'];
    $post_time = $_SESSION['comment_chero_post_time'];

    $post_read_query = "INSERT INTO $comment_chero_db_post_reads
                        (post_id,user_id,read_time)
                        VALUES ($post_id, $user_ID, '$post_time')
                        ON DUPLICATE KEY UPDATE read_time='$post_time';";

    $success = $wpdb->query($post_read_query);
  }
}

// Adds the unread class to every matched comment
function comment_chero_unread_class($classes = array()) {
  global $comment, $wpdb, $user_ID, $comment_chero_db_post_reads, $wp_cc;
  $highlight = get_option('comment-chero-highlight');

  if ($highlight) {
    $post_id = $comment->comment_post_ID;

    $post_read_query = "SELECT read_time FROM $comment_chero_db_post_reads
                        WHERE post_id=$post_id
                              AND
                              user_id=$user_ID;";

    $ts_a = strtotime($wpdb->get_var($post_read_query));
    $comment_time = strtotime($comment->comment_date_gmt);

    if ($comment_time > $ts_a) {
      $classes [] = 'comment_chero_comment_unread';
    }
  }

  return $classes;
}

function comment_chero_init() {
  // load_plugin_textdomain('comment-chero', false, dirname(plugin_basename(__FILE__)) . '/languages' );
  load_plugin_textdomain('comment-chero', false, basename(dirname(__FILE__)) . '/languages' );
  register_widget('WP_Comment_Chero_Widget');
}

add_action('template_redirect', 'cc_custom_page_template_redirect');
function cc_custom_page_template_redirect() {
  global $wp_query;

  if ($wp_query->query_vars['name'] == 'commentchero') {
    $wp_query->is_404 = false;
    status_header(200);
    include(ABSPATH . 'wp-content/plugins/comment-chero/comment-chero-full-list.php');
    exit;
  }
}

add_action('wp_ajax_comment_chero_mark_all_read', 'comment_chero_mark_all_read');
function comment_chero_mark_all_read() {
  global $wpdb, $user_ID;
  $success = mark_all_as_read($user_ID);
  die($success);
}

?>
