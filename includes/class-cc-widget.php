<?php
/**
 * Copyright (c) 2013, Onur Küçük <onur@ozguryazilim.com.tr>
 * @license http://www.gnu.org/licenses/gpl-2.0.html  GPLv2
 */

/**
 * WP Unread Comments Widget Class
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
        $title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Unread Comments' ) : $instance['title'], $instance, $this->id_base );

        $order='asc';
        $from_time = 10; // ignore comments older than 10 days ago
        $number = 10; // only get 10 comments for login user

        $postlistquery = "SELECT comment_post_ID as post_id, count(comment_post_ID) as comment_count, max(comment_date) as latest_comment
                          FROM $wpdb->comments
                          GROUP BY comment_post_ID
                          ORDER BY max(comment_date) DESC
                          LIMIT $number;";

        $postlist = $wpdb->get_results($postlistquery);

        $output .= $before_widget;
        if ( $title ) {
            $output .= $before_title . $title . $after_title;
        }

        $output .= '<ul id="recentcomments">';
        // $postarray=array();
        $commentarray=array();
        $number = $instance['number_unread'];

        if ($show_text) {
            $output.= '<li class="'.$rcclass.'">' . $custom_text . '</li>';
        }

        /*
        if ( $comments ) {
            foreach ( (array) $comments as $comment) {
                if($user_ID=='') {
                    if($show_recent) {
                        $output.='<li class="'.$rcclass.'">' . sprintf(_x('%1$s on %2$s', 'widgets'), get_comment_author_link(), '<a href="' . esc_url( get_comment_link($comment->comment_ID) ) . '">' . get_the_title($comment->comment_post_ID) . '</a>') . '</li>';
                    }
                } else {
                    $rcclass='recentcomments';
                    $post_id = $comment->comment_post_ID;
                    $post_key = 'wuc_post_id'.$post_id;
                    $ts_a = strtotime(get_user_meta( $user_ID, $post_key, true ));

                    $comment_time = strtotime($comment->comment_date_gmt);
                    //if(!in_array($post_id, $postarray))
                    //{
                    //    array_push($postarray, $post_id);
                    array_push($commentarray, '<li class="'.$rcclass.'">' . 
                        sprintf(_x('%1$s', 'widgets'),  '<a href="' . esc_url( get_comment_link($comment->comment_ID) ) . '">' . get_the_title($comment->comment_post_ID) . '</a>') . '</li>');
                    //}
                    if($number>0 && count($commentarray) >= $number) break;
                }
            }
        }
        */

        foreach ($postlist as $latestpost) {
            $post_id = $latestpost->post_id;
            $post_permalink = esc_url(get_permalink($post_id));
            $post_title =  get_the_title($post_id);

            if($user_ID=='') {
                if($show_recent) {
                    $output.='<li class="'.$rcclass.'">' . sprintf(_x('%1$s on %2$s', 'widgets'), get_comment_author_link(), '<a href="' . $post_permalink . '">' . $post_title . '</a>') . '</li>';
                }
            } else {
                $rcclass='recentcomments';
                $post_key = 'wuc_post_id'.$post_id;
                // $ts_a = strtotime(get_user_meta( $user_ID, $post_key, true ));
                $comment_time = strtotime($latestpost->latest_comment);

                // TODO: Currently using JOIN causes a 0.12 sec query to become a 1.20 sec query as we need to do custom
                // string CONCAT in sql. Convert the SQL to a sane one when we switch to our own table
                // comment_date > $latestpost->latest_comment
                $unread_comment_query = "SELECT count(*)
                                  FROM $wpdb->comments
                                  WHERE comment_post_ID=$post_id
                                        AND
                                        comment_date >= IFNULL(
                                                            (SELECT meta_value
                                                             FROM $wpdb->usermeta
                                                             WHERE user_id=$user_ID
                                                                   AND
                                                                   $wpdb->usermeta.meta_key=CONCAT('wuc_post_id', comment_post_ID)
                                                            ),
                                                        0)
                                 ";
                $unread_comment_count = $wpdb->get_var($unread_comment_query);

                if ($unread_comment_count > 0) {
                    // $unread_found_class = 'class="comment-chero-widget-unread"';
                    // $rcclass .= " comment-chero-widget-unread";
                    $unreadclass = ' class="comment-chero-widget-unread"';
                    $unread_comment_status = " $unread_comment_count yeni";
                } else {
                    // $unread_found_class = '';
                    $unread_comment_status = '';
                    $unreadclass = '';
                }

                array_push($commentarray, '<li class="'.$rcclass.'">' .
                    sprintf(_x('%1$s', 'widgets'),  '<a href="' . $post_permalink . '"' . $unreadclass . '>' . $post_title . '</a>') . '(' . $latestpost->comment_count . ')' . $unread_comment_status .  '</li>');
                // if($number>0 && count($commentarray) >= $number) break;
            }
        }

        if ($user_ID !='') {
            for ($i = count($commentarray) - 1; $i >=0 ; $i--) {
                $output .= $commentarray[$i];
            }
            if(count($commentarray)==0) $output.='<li class="recentcomments">Okumadığınız yorum kalmamış ki...</li>';

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
        global $wp_uc;

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

function comment_chero_init() {
    register_widget('WP_Comment_Chero_Widget');
}

?>
