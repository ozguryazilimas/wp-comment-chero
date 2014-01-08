<script language="javascript" type="text/javascript">
//<![CDATA[
  function go_to_page() {
    var target_page = document.pagination_form.pagination_select.value;

    if (target_page != undefined && target_page != null) {
      document.location.href = target_page;
    }

    return false;
  }
//]]>
</script>

<?php
    global $wp_query, $current_user;
    $per_page = 100;
    $pagination_range = 3;
    $current_page = (get_query_var('paged') == 0) ? 1 : get_query_var('paged');
    $output = '';

    get_header();
    get_sidebar();

    if ($current_user->ID == '') {
        $output .= 'No comment chero';

    } else {
        $offset = ($current_page - 1) * $per_page;
        $unread_posts = comment_chero_post_statistics($per_page, $offset);
        $unread_post_count = comment_chero_post_with_comment_count();
        $pagination_count = (($unread_post_count - 1) / $per_page) + 1;
        $mark_all_unread = isset($_POST['mark_all_unread']);

        if ($mark_all_unread) {
            mark_all_as_read($current_user->ID);
        }

        // $output .= "<h3>" . sprintf(__("Post comments for %s", 'comment-chero'), $current_user->display_name) . "</h3>";
        // echo("<h3> current_page=" . $current_page . " </h3>");
        // echo("<br>");
        $output .='<div class="leftpane article-page content">
                        <article class="post-page cl">                
                            <div class="article-body">';
        $output .= '<hgroup><div class="cc_title">';
        $output .= '<h3 class="cc_page_title">' . __('All Comments', 'comment-chero') . '</h3>';
        $output .= '<form name="ccform" method="POST" action="commentchero">';
        $output .= '<input type="Submit" name="mark_all_unread" class="markasread" value="' . __('Mark all as read', 'comment-chero') . '">';
        $output .= '</form>';
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
    }

    get_footer();
?>
