<?php

class WLCMS_Admin_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'), 9999);
        add_action('admin_init', array($this, 'init'), 9999);
        add_filter('mce_css', array($this, 'custom_editor_stylesheet'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('init', array($this, 'remove_admin_bar'));
        add_action("wp_ajax_wlcms_inital_search", [$this, "search_initial_pages"]);
        add_action("wp_ajax_wlcms_search_pages", [$this, "search_pages"]);
    }

    public function admin_init()
    {
        $this->remove_nag_messages();
        $this->remove_editor_wp_logo();
    }

    public function init()
    {
        $this->set_admin_css();
        $this->hide_screen_options();
    }

    public function remove_editor_wp_logo()
    {
        if (!wlcms_field_setting('hide_wordpress_logo_and_links')) {
            return;
        }

        $image = $this->get_editor_wp_logo();

        wlcms_set_hidden_css('.edit-post-header .edit-post-fullscreen-mode-close svg');
        wlcms_set_css('.editor-header__back-button:has(.wlcms_icon)', array('background' => '#000'));
        wlcms_add_js(' var wlcms_change_back = setInterval(function() {if(jQuery(".edit-post-fullscreen-mode-close .wlcms_icon").length == 0 ){ jQuery(".edit-post-fullscreen-mode-close").html("' . $image . '");}if(jQuery(".edit-post-fullscreen-mode-close_site-icon").length > 0){jQuery(".edit-post-fullscreen-mode-close_site-icon").remove();}}, 1000);');

    }

    private function get_editor_wp_logo()
    {
        $gutenberg_exit_icon = wlcms_field_setting('gutenberg_exit_icon');
        $admin_bar_logo = wlcms_field_setting('admin_bar_logo');

        if ($gutenberg_exit_icon) {
            $icon = "";
            if ($gutenberg_exit_icon == 'admin-bar-logo') {
                $icon = wlcms_field_setting('admin_bar_logo');
            } elseif ($gutenberg_exit_icon == 'custom-icon') {
                $icon = wlcms_field_setting('gutenberg_exit_custom_icon');
            } else {
                return '<span class=\"wlcms_icon dashicons dashicons-exit\"></span>';
            }

            return '<span id=\"wlcms_dashboard_logo\" class=\"wlcms_icon\"><img src=\"' . esc_url($icon) . '\" alt=\"\" /></span>';
        }

        if ($admin_bar_logo) {
            return '<span id=\"wlcms_dashboard_logo\" class=\"wlcms_icon\"><img src=\"' . esc_url($admin_bar_logo) . '\" alt=\"\" /></span>';
        }

        return '<span class=\"wlcms_icon wlcms_dashboard_exitdashicons dashicons-exit\"></span>';
    }

    public function remove_admin_bar()
    {

        if (!wlcms_field_setting('hide_admin_bar_all')) {
            return;
        }

        return $this->disable_admin_bar_menu();
    }

    private function disable_admin_bar_menu()
    {
        add_filter('show_admin_bar', '__return_false');
    }

    public function admin_menu()
    {
        if (!is_admin()) {
            return;
        }
    }

    public function hide_screen_options()
    {
        if (wlcms_field_setting('hide_screen_options')) {
            add_filter('screen_options_show_screen', '__return_false');
        }
    }

    private function set_admin_css()
    {

        if (wlcms_field_setting('hide_help_box')) {
            wlcms_set_hidden_css("#contextual-help-link-wrap");
        }

        if (!$admin_style = wlcms_field_setting('settings_custom_css_admin')) {
            return;
        }

        wlcms()->Admin_Script()->appendAdminCss($admin_style);
    }

    public function custom_editor_stylesheet($mce_css)
    {
        $mce_style = wlcms_field_setting('settings_custom_css_url');
        if (!$mce_style) {
            return $mce_css;
        }

        if (filter_var($mce_style, FILTER_VALIDATE_URL) === false) {
            $mce_style = get_stylesheet_directory_uri() . '/' . $mce_style;
        }

        $mce_css .= ',' . esc_url($mce_style);

        return $mce_css;
    }

    private function remove_nag_messages()
    {

        if (!wlcms_field_setting('hide_nag_messages')) {
            return;
        }

        remove_action('admin_notices', 'update_nag', 3);
        remove_action('admin_notices', 'maintenance_nag', 10);
        remove_action('network_admin_notices', 'update_nag', 3);
    }

    public function wp_version_check()
    {
        remove_action('init', 'wp_version_check');
    }
    public function search_initial_pages()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }

        $ids = [isset($_GET['q']) ? (int) $_GET['q'] : 0];
        $data = wlcms_get_pages_by_ids($ids);

        wp_send_json([
            'initials' => reset($data)
        ]);
        wp_die();
    }

    public function search_pages()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }

        $q = isset($_GET['q']) ? $_GET['q'] : '';
        wp_send_json([
            'results' => wlcms_search_pages($q),
            'pagination' => ['more' => false]
        ]);
        wp_die();
    }
}
