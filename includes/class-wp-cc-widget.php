<?php
/*
 * Copyright (c) 2013, Onur Küçük <onur@ozguryazilim.com.tr>
 * @license http://www.gnu.org/licenses/gpl-2.0.html  GPLv2
 */

/*
 * WP Commet Chero Widget Class
 */
class WP_Comment_Chero_Widget extends WP_Widget {
    function WP_Comment_Chero_Widget() {
        $widget_ops = array('classname' => 'wp-cc-widget', 'description' => __( 'Show a list of unread comments.', 'wp-cc') );
        $control_ops = array('width' => 350);
        $this->WP_Widget('comment_chero', __('Comment Chero', 'wp-cc'), $widget_ops, $control_ops);
    }


    function widget($args, $instance) {
        global $comments, $comment, $user_ID, $wpdb;

        $cache = wp_cache_get('widget_comment_chero', 'widget');

        if (!is_array($cache)) {
            $cache = array();
        }

        if (!isset($args['widget_id'])) {
            $args['widget_id'] = $this->id;
        }

        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return;
        }

        extract($args, EXTR_SKIP);

        $output = '';
        $title = apply_filters('widget_title', empty( $instance['title'] ) ? __( 'Unread Comments' ) : $instance['title'], $instance, $this->id_base);

        $login_user_limit = 10; // only get 10 comments for login user
        $poststats = comment_chero_post_statistics($login_user_limit);

        $output .= $before_widget;
        if ($title) {
            $output .= $before_title . $title . $after_title;
        }

        $output .= '<ul id="recentcomments">';
        $login_user_limit = $instance['number_unread'];

        if ($show_text) {
            $output.= '<li class="'.$rcclass.'">' . $custom_text . '</li>';
        }

        foreach ($poststats as $latestpost) {
            $post_id = $latestpost->post_id;
            $post_permalink = esc_url(get_permalink($post_id));
            $post_title =  get_the_title($post_id);

            if ($user_ID == '') {
                if($show_recent) {
                    $output .= '<li class="' . $rcclass . '">' .
                                    sprintf(_x('%1$s on %2$s', 'widgets'), get_comment_author_link(), '<a href="' . $post_permalink . '">' . $post_title . '</a>') .
                               '</li>';
                }
            } else {
                $rcclass = 'recentcomments';

                if ($latestpost->unread_comment_count > 0) {
                    // $rcclass .= " comment-chero-widget-unread";
                    $unreadclass = 'class="comment-chero-widget-unread"';
                    $unread_comment_status = " $latestpost->unread_comment_count yeni";
                } else {
                    $unread_comment_status = '';
                    $unreadclass = '';
                }

                $output .= '<li class="' . $rcclass . '">' .
                               sprintf(_x('%1$s', 'widgets'),  '<a href="' . $post_permalink . '" ' . $unreadclass . '>' . $post_title . '</a>') .
                               '(' . $latestpost->comment_count . ')' .
                               $unread_comment_status .
                           '</li>';
            }
        }

        if ($user_ID != '' && count($poststats) == 0) {
            $output .= '<li class="recentcomments">Okumadığınız yorum kalmamış ki...</li>';
        }

        $output .= '</ul>';
        $output .= $after_widget;
        echo $output;
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number_unread'] = $new_instance['number_unread'];
        $instance['highlight'] = $new_instance['highlight'];
        $instance['title_recent'] = strip_tags($new_instance['title_recent']);
        $instance['number_recent'] = $new_instance['number_recent'];
        $instance['show_recent'] = $new_instance['show_recent'];
        $instance['show_text'] = $new_instance['show_text'];
        $instance['custom_text'] = strip_tags($new_instance['custom_text']);
        update_option( 'comment-chero-highlight', $instance['highlight'], '', 'yes' );

        return $instance;
    }

    function form($instance) {
        global $wp_cc;

        $instance_name = strip_tags($instance['instance']);

        $title = (isset($instance['title'])) ? strip_tags($instance['title']) : '';
        $number_unread = empty($instance['number_unread']) ? 10 : (int) $instance['number_unread'];
        $highlight = (isset($instance['highlight'])) ? true : false;
        $title_recent = (isset($instance['title_recent'])) ? strip_tags($instance['title_recent']) : 'Recent Comments' ;
        $number_recent = empty($instance['number_recent']) ? 10 : (int) $instance['number_recent'];
        $show_recent = (isset($instance['show_recent'])) ? true : false;
        $show_text = (isset($instance['show_text'])) ? true : false;
        $custom_text = (isset($instance['custom_text'])) ? strip_tags($instance['custom_text']) : 'You must be logged in to view unread comments';


?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-cc'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number_unread'); ?>">Number of unread comments to show (0 for all):</label>
            <input id="<?php echo $this->get_field_id('number_unread'); ?>" name="<?php echo $this->get_field_name('number_unread'); ?>" type="text" value="<?php echo $number_unread; ?>" size="3" />
        </p>
            <input class="checkbox" type="checkbox" <?php checked($highlight, true); ?> id="<?php echo $this->get_field_id('highlight'); ?>" name="<?php echo $this->get_field_name('highlight'); ?>" />
            <label for="<?php echo $this->get_field_id('highlight'); ?>"><?php _e('Highlight unread comments', 'wp-rc'); ?></label>
        </p>
        <p>
            <b>Options for users who aren't logged in:</b>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('title_recent'); ?>"><?php _e('Title for users who are not logged in:', 'wp-cc'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title_recent'); ?>" name="<?php echo $this->get_field_name('title_recent'); ?>" type="text" value="<?php echo esc_attr($title_recent); ?>" />
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_recent, true); ?> id="<?php echo $this->get_field_id('show_recent'); ?>" name="<?php echo $this->get_field_name('show_recent'); ?>" />
            <label for="<?php echo $this->get_field_id('show_recent'); ?>"><?php _e('Show recent comments if users are not logged in', 'wp-rc'); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number_recent'); ?>">Number of recent comments to show (0 for all):</label>
            <input id="<?php echo $this->get_field_id('number_recent'); ?>" name="<?php echo $this->get_field_name('number_recent'); ?>" type="text" value="<?php echo $number_recent; ?>" size="3" />
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_text, true); ?> id="<?php echo $this->get_field_id('show_text'); ?>" name="<?php echo $this->get_field_name('show_text'); ?>" />
            <label for="<?php echo $this->get_field_id('show_recent'); ?>"><?php _e('Show custom text if users are not logged in', 'wp-rc'); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('custom_text'); ?>"><?php _e('Custom Text:', 'wp-cc'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('custom_text'); ?>" name="<?php echo $this->get_field_name('custom_text'); ?>" type="text" value="<?php echo esc_attr($custom_text); ?>" />
        </p>

<?php
    }
}

function comment_chero_post_statistics($postcount) {
    /*
     * Returns array of objects containing
     *
     * [post_id] => 12345
     * [comment_count] => 203
     * [latest_comment] => 2013-05-04 12:43:16
     * [unread_comment_count] => 3
     *
     * TODO: Move everything into one sql if it runs faster
     *       Make this stuff smarter
     *
     */

    global $user_ID, $wpdb, $comment_chero_db_post_reads, $comments, $comment;

    $post_ids = array();
    $unread_comments = array();

    $postlistquery = $wpdb->prepare("SELECT comment_post_ID as post_id,
                                            count(comment_post_ID) as comment_count,
                                            max(comment_date_gmt) as latest_comment
                                     FROM $wpdb->comments
                                     GROUP BY comment_post_ID
                                     ORDER BY max(comment_date_gmt) DESC
                                     LIMIT $postcount");

    $postlistquery_result = $wpdb->get_results($postlistquery);

    foreach($postlistquery_result as $plqr) {
        array_push($post_ids, $plqr->post_id);
    }

    $post_sql_array = implode(", ", $post_ids);
    $unread_comment_query = $wpdb->prepare("SELECT count(*) as unread_count,comment_post_ID
                                            FROM $wpdb->comments
                                            WHERE comment_post_ID in ($post_sql_array)
                                                AND
                                                    user_id != $user_ID
                                                AND
                                                    comment_date_gmt >= IFNULL(
                                                                          (
                                                                            SELECT read_time
                                                                            FROM $comment_chero_db_post_reads
                                                                            WHERE user_id=$user_ID
                                                                                AND
                                                                                  post_id=comment_post_ID
                                                                          ),
                                                                        0)
                                            GROUP BY comment_post_ID");

    $unread_comment_query_result = $wpdb->get_results($unread_comment_query);

    foreach($unread_comment_query_result as $u) {
        $unread_comments[$u->comment_post_ID] = $u->unread_count;
    }

    foreach($postlistquery_result as $p) {
        $p->unread_comment_count = $unread_comments[$p->post_id];
    }


    return $postlistquery_result;
}


function comment_chero_init() {
    register_widget('WP_Comment_Chero_Widget');
}

?>
