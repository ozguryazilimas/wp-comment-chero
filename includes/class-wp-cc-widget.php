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
        $widget_ops = array('classname' => 'wp-cc-widget', 'description' => __('Show a list of unread comments.', 'comment-chero'));
        $control_ops = array('width' => 350);
        $this->WP_Widget('comment_chero', __('Comment Chero', 'comment-chero'), $widget_ops, $control_ops);
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
        $title = apply_filters('widget_title', empty( $instance['title'] ) ? __('Unread Comments', 'comment-chero') : $instance['title'], $instance, $this->id_base);

        $login_user_limit = empty($instance['number_unread']) ? COMMENT_CHERO_SHOW_COUNT : (int) $instance['number_unread'];
        $poststats = comment_chero_post_statistics($login_user_limit, 0);

        $output .= $before_widget;
        if ($title) {
            $output .= $before_title . $title . $after_title;
        }

        $output .= display_unread_comments($poststats, true);

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
        $number_unread = empty($instance['number_unread']) ? COMMENT_CHERO_SHOW_COUNT : (int) $instance['number_unread'];
        $highlight = (isset($instance['highlight'])) ? true : false;
        $title_recent = (isset($instance['title_recent'])) ? strip_tags($instance['title_recent']) : __('Recent Comments', 'comment-chero');
        $number_recent = empty($instance['number_recent']) ? COMMENT_CHERO_SHOW_COUNT : (int) $instance['number_recent'];
        $show_recent = (isset($instance['show_recent'])) ? true : false;
        $show_text = (isset($instance['show_text'])) ? true : false;
        $custom_text = (isset($instance['custom_text'])) ? strip_tags($instance['custom_text']) : __('You must be logged in to view unread comments', 'comment-chero');


?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'comment-chero'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number_unread'); ?>"><?php _e('Number of unread comments to show (default 10):', 'comment-chero') ?></label>
            <input id="<?php echo $this->get_field_id('number_unread'); ?>" name="<?php echo $this->get_field_name('number_unread'); ?>" type="text" value="<?php echo $number_unread; ?>" size="3" />
        </p>
            <input class="checkbox" type="checkbox" <?php checked($highlight, true); ?> id="<?php echo $this->get_field_id('highlight'); ?>" name="<?php echo $this->get_field_name('highlight'); ?>" />
            <label for="<?php echo $this->get_field_id('highlight'); ?>"><?php _e('Highlight unread comments', 'comment-chero'); ?></label>
        </p>
        <p>
            <b><?php _e("Options for users who aren't logged in:", 'comment-chero') ?></b>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('title_recent'); ?>"><?php _e('Title for users who are not logged in:', 'comment-chero'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title_recent'); ?>" name="<?php echo $this->get_field_name('title_recent'); ?>" type="text" value="<?php echo esc_attr($title_recent); ?>" />
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_recent, true); ?> id="<?php echo $this->get_field_id('show_recent'); ?>" name="<?php echo $this->get_field_name('show_recent'); ?>" />
            <label for="<?php echo $this->get_field_id('show_recent'); ?>"><?php _e('Show recent comments if users are not logged in', 'comment-chero'); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number_recent'); ?>"><?php _e('Number of recent comments to show (default 10):', 'comment-chero') ?></label>
            <input id="<?php echo $this->get_field_id('number_recent'); ?>" name="<?php echo $this->get_field_name('number_recent'); ?>" type="text" value="<?php echo $number_recent; ?>" size="3" />
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_text, true); ?> id="<?php echo $this->get_field_id('show_text'); ?>" name="<?php echo $this->get_field_name('show_text'); ?>" />
            <label for="<?php echo $this->get_field_id('show_recent'); ?>"><?php _e('Show custom text if users are not logged in', 'comment-chero'); ?></label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('custom_text'); ?>"><?php _e('Custom Text:', 'comment-chero'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('custom_text'); ?>" name="<?php echo $this->get_field_name('custom_text'); ?>" type="text" value="<?php echo esc_attr($custom_text); ?>" />
        </p>
<?php
    }
}

function display_unread_comments($poststats, $show_more) {
        global $user_ID;
        $output = '<ul id="recentcomments">';
        $rcclass = '';

        if ($show_text) {
            $output.= '<div class="'.$rcclass.'">' . $custom_text . '</div>';
        }

        foreach ($poststats as $latestpost) {
            $post_id = $latestpost->post_id;
            $post_permalink = esc_url(get_permalink($post_id));
            $post_title =  get_the_title($post_id);
            if($show_more){
                $count = strlen($post_title);
                if($count >= 21)
                    $post_title = mb_substr($post_title, 0,21)."...";
            }
            else{
                $count = strlen($post_title);
                if($count >= 69)
                    $post_title = mb_substr($post_title, 0,69)."...";
            }
            if ($user_ID == '' && $show_recent) {
                $output .= '<div class="' . $rcclass . '">' .
                                sprintf(_x('%1$s on %2$s', 'widgets'), get_comment_author_link(), '<a href="' . $post_permalink . '">' . $post_title . '</a>') .
                           '</div>';
            } else {
                $rcclass = 'recentcomments';

                if ($latestpost->unread_comment_count > 0) {
                    $unreadclass = 'class="comment_chero_widget_unread"';
                    $unread_comment_status = ' ' . sprintf(__("%d", 'comment-chero'),  $latestpost->unread_comment_count);
                } else {
                    $unread_comment_status = '';
                    $unreadclass = 'class="comment_chero_widget_read"';
                }

                if(!empty($unread_comment_status)) {
                    $output .= '<li class="' . $rcclass . '">' .
                        sprintf(_x('%1$s', 'widgets'),  '<div class="commentUnread"><a href="' . $post_permalink . '" ' . $unreadclass . '>' . $post_title . '</a>') .
                        '<span class="allUnreadComment" title="'.__('Unread Comments', 'comment-chero').'">'.$unread_comment_status .'</span></div>'.
                        '<span class="unreadAllComment" title="'.__('All Comments', 'comment-chero').'">' . $latestpost->comment_count . '</span>' .
                    '</li>';
                } else {
                    $output .= '<li class="' . $rcclass . '">' .
                        sprintf(_x('%1$s', 'widgets'),  '<div class="commentRead"><a href="' . $post_permalink . '" ' . $unreadclass . '>' . $post_title . '</a></div>') .
                        '<span class="readAllComment" title="'.__('All Comments', 'comment-chero').'">' . $latestpost->comment_count . '</span>' .
                    '</li>';
                }
            }
        }

        if ($user_ID != '') {
            if ($show_more) {
                $output .= '<a href="/commentchero" class="comment_chero_widget_unread" style="float: right;">' . __('more', 'comment-chero') . '</a>';
            }

            if (count($poststats) == 0) {
                $output .= '<li class="recentcomments">' . __('You don\'t have any unread comments...', 'comment-chero') . '</li>';
            }
        }

        $output .= '</ul>';

        return $output;
}

function comment_chero_post_statistics($postcount, $offset) {
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


    $sql_limit = ($postcount > 0) ? "LIMIT $postcount" : '';
    $sql_offset = ($offset && $offset > 0) ? "OFFSET $offset" : '';


    $postlistquery = $wpdb->prepare("SELECT comment_post_ID as post_id,
                                            count(comment_post_ID) as comment_count,
                                            max(comment_date_gmt) as latest_comment
                                     FROM $wpdb->comments
                                     GROUP BY comment_post_ID
                                     ORDER BY max(comment_date_gmt) DESC
                                     $sql_limit
                                     $sql_offset");

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

function comment_chero_post_with_comment_count() {
    global $wpdb;

    $postlistquery = $wpdb->prepare("SELECT COUNT(DISTINCT(comment_post_ID))
                                     FROM $wpdb->comments");

    $postlistquery_result = $wpdb->get_var($postlistquery);
    return $postlistquery_result;
}

function mark_all_as_read($user_id) {
    global $wpdb, $comment_chero_db_post_reads;

    $post_time = current_time('mysql', 1);

    $mark_as_read_query_str = "INSERT INTO $comment_chero_db_post_reads (post_id, user_id, read_time)
                                   SELECT DISTINCT ID, '$user_id', '$post_time'
                                       FROM $wpdb->posts
                                       WHERE post_type IN ('page', 'post')
                               ON DUPLICATE KEY UPDATE read_time='$post_time';";

    $mark_as_read_query = $wpdb->prepare($mark_as_read_query_str);
    $success = $wpdb->query($mark_as_read_query);
}

?>
