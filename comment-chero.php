<?php
/*
Plugin Name: Comment Chero
Plugin URI: http://www.ozguryazilim.com.tr
Description: This plugin displays unread comments in a sidebar widget and can highlight unread comments in comment lists. Influenced by wp-unread-comments plugin which had serious performance issues.
Version: 0.0.1
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

    require_once(dirname(__FILE__) . '/includes/class-cc-widget.php');
    $wp_uc = new WP_Comment_Chero_Widget();

    //widget
    add_action('widgets_init', 'comment_chero_init');

    // timestamp functions - comment out these 2 calls if you place the actions in your template
    //add_action('get_header', 'wuc_get_time');
    //add_action('get_footer', 'wuc_set_time');

    // register the functions
    add_action('wuc_get_time', 'wuc_get_time');
    add_action('wuc_set_time', 'wuc_set_time');

    // Add the action to every comment
    add_filter('comment_class', 'wuc_unread_class', 10);

    //add css
    add_action('init', 'comment_chero_add_css');

    function comment_chero_add_css() {
        // enqueue WordPress CSS hook
        wp_enqueue_style('comment-chero', get_option('siteurl').'/wp-content/plugins/comment-chero/css/comment-chero.css');
    }

    // Update cookie when an user reads a post
    function wuc_get_time() {
        global $wp_query, $wpdb,$user_ID;
        $post_id = $wp_query->post->ID;
        $post_key = 'wuc_post_id'.$post_id;
        $_SESSION['wuc_post_key'] = $post_key;
        $_SESSION[$post_key] = current_time('mysql', 1);
    }

    function wuc_set_time(){
        global $wpdb, $user_ID;
        $post_key=$_SESSION['wuc_post_key'];
        update_user_meta( $user_ID, $post_key, $_SESSION[$post_key] );
    }

    // Adds the unread class to every matched comment
    function wuc_unread_class($classes = array()) {
        global $comment, $wpdb, $user_ID, $wp_uc;
        $highlight = get_option( 'comment-chero-highlight' );

        if ($highlight) {
            $post_id = $comment->comment_post_ID;
            $post_key = 'wuc_post_id'.$post_id;
            $ts_a = strtotime(get_user_meta( $user_ID, $post_key, true ));
            $comment_time = strtotime($comment->comment_date_gmt);

            if ($comment_time > $ts_a) {
                $classes [] = 'comment-chero';
            }
        }

        return $classes;
    }

?>
