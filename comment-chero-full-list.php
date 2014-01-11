<?php
    global $wp_query, $current_user;
    $per_page = 100;
    $pagination_range = 3;
    $current_page = (get_query_var('paged') == 0) ? 1 : get_query_var('paged');
    $output = '';

    get_header();
    get_sidebar();


    $offset = ($current_page - 1) * $per_page;
    $unread_posts = comment_chero_post_statistics($per_page, $offset);
    $unread_post_count = comment_chero_post_with_comment_count();
    $pagination_count = (($unread_post_count - 1) / $per_page) + 1;

    $output .='<div class="leftpane article-page content">
                    <article class="post-page cl">
                        <div class="article-body">';
    $output .= '<hgroup><div class="cc_title">';
    $output .= '<h3 class="cc_page_title">' . __('All Comments', 'comment-chero') . '</h3>';

    if ($current_user->ID != '') {
        $output .= '<input type="Submit" name="mark_all_read" class="markasread" value="' . __('Mark all as read', 'comment-chero') . '">';
    }

    $output .= '</div></hgroup>';
    $output .= '<div class="cc_full_list">';
    $output .= display_unread_comments($unread_posts, false);
    $output .= '</div>';

    // Pagination start
    $output .= '<div class="cc_navigation clearfix">';
    // $output .= '<span>' . sprintf(__("Total page count: %s", 'comment-chero'), $unread_post_count) . '</span>';
    $output .= '<div id="cc_page_numbers"><ul>';
    $output .= '<li><a href="?paged=1">' . __('First page', 'comment-chero') . '</li>';

    if ($current_page > 1) {
        $output .= '<li><a href="?paged=' . ($current_page - 1) . '"><<</a></li>';
    }

    for ($i = $current_page - $pagination_range; $i <= $current_page + $pagination_range; $i++) {
        if ($i >= 1 && $i <= $pagination_count) {
            if ($current_page == $i) {
                $output .= '<li class="active_page"><a>' . $i . '</a></li>';
            } else {
                $output .= '<li><a href="?paged=' . $i . '">' . $i . '</a></li>';
            }
        }
    }

    if ($current_page < $pagination_count - 1) {
        $output .= '<li><a href="?paged=' . ($current_page + 1) . '">>></a></li>';
    }

    // pull down select
    $output .= '<li><form name="pagination_form" class="dropdownPage">
        <select name="pagination_select" onChange="go_to_page();">';

    for($i = 1; $i <= $pagination_count; $i++) {
        $output .= '<option value="?paged=' . $i . '"' . ($i == $current_page ? ' selected="selected"' : '') . '>' . $i . '</option>';
    }

    $output .= '</select></form></li>';

    // go to last page, a.k.a. last entry in pagination
    $output .= '<li><a href="?paged=' . $pagination_count . '">' . __('Last page', 'comment-chero') . '</li>';
    $output .= '</ul></div>';
    $output .= '</div></article></div>';

    echo $output;


    get_footer();
?>

<script language="javascript" type="text/javascript">
//<![CDATA[

      function go_to_page() {
        var target_page = document.pagination_form.pagination_select.value;

        if (target_page != undefined && target_page != null) {
          document.location.href = target_page;
        }

        return false;
      }

    jQuery(document).ready(function() {

<?php
    if ($current_user->ID != '') {
        echo "var ajaxpath = '" . get_option('siteurl') . "/wp-admin/admin-ajax.php';";
        echo "\n";
?>
    jQuery('input[name=mark_all_read]').on('click', function() {
        jQuery.post(
            ajaxpath,
            {
                'action': 'comment_chero_mark_all_read'
            },
            function(response) {
                // console.log('The server responded: ' + response);
                window.location.reload(true);
            }
        );

        return false;
      });
    });
<?php
    }
?>

//]]>
</script>
