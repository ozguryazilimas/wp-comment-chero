<?php

class WLCMS_Admin_Dashboard extends WLCMS_Previewable
{

    private $is_dashboard_all_hidden;

    public function __construct()
    {
        //Check and set if it is a preview
        $this->check_preview();

        add_action('wp_dashboard_setup', array($this, 'dashboard_setup'), 999);
        add_action("wp_ajax_hide_vum_dashboard", array($this, "hide_vum_dashboard"));
        add_action("admin_init", array($this, "reset_welcome_dashboard"));
        add_action("init", array($this, "init"));
    }
    public function init()
    {

        $welcome_panels = wlcms_field_setting('welcome_panel');

        if (!$welcome_panels || !is_array($welcome_panels)) {
            return;
        }

        if (!count($welcome_panels) === 0) {
            return;
        }

        $pages = [];
        foreach ($welcome_panels as $panel) {
            if ($panel && is_array($panel) && isset($panel['template_type']) && $panel['template_type'] == 'page' && isset($panel['page_id_page'])) {
                $pages[] = (int)  $panel['page_id_page'];
            }
        }

        if (count($pages) > 0) {
            wlcms()->require_class("Welcome_Messages/Welcome_Messages_Page");
            $template = new Welcome_Messages_Page();
            $template->init($pages);
        }
    }

    public function dashboard_setup()
    {
        $this->reset_dashboard_style();
        $this->add_own_rss_panel();
        $this->dashboard_title();
        $this->set_welcome_metabox();

        //Old version setting 2.0.2
        if (!wlcms_field_setting('dashboard_role_stat')) {
            if (is_wlcms_admin()) return;
        } else {
            $dashboard_widgets_visibility_roles = wlcms_esc_html_deep(wlcms_field_setting('dashboard_widgets_visibility_roles'));
            if (!$dashboard_widgets_visibility_roles) return;
            if (!$this->has_current_user_role($dashboard_widgets_visibility_roles))
                return;
        }

        $this->set_dashboard_all_hidden();
        $this->remove_metaboxes();
        $this->remove_dashed_border();
    }

    private function reset_dashboard_style()
    {

        wlcms_set_css(
            '.wlcms-welcome-panel .elementor div,
            .wlcms-welcome-panel .elementor h1,
            .wlcms-welcome-panel .elementor h2,
            .wlcms-welcome-panel .elementor h3,
            .wlcms-welcome-panel .elementor h4,
            .wlcms-welcome-panel .elementor h5,
            .wlcms-welcome-panel .fl-builder-content p,
            .wlcms-welcome-panel p',
            array(
                'border' => '0',
                'font-size' => '100%',
                'font' => 'inherit',
                'line-height' => 'inherit',
                'vertical-align' => 'baseline',
                'color' => 'unset'
            )
        );

        wlcms_set_css(
            '.wlcms-welcome-panel .welcome-panel-content > h2',
            array(
                'width' => '95%',
                'padding' => '0 21px'
            )
        );
        wlcms_set_css(
            '.wlcms-welcome-panel .wlcms-welcome-content',
            array(
                'padding' => '20px'
            )
        );
        wlcms_set_css(
            '.wlcms-welcome-panel .welcome-panel-content',
            array(
                'max-width' => 'none!important',
                'margin-left' => '0!important',
                'justify-content' => 'flex-start',
                'min-height' => 'auto'
            )
        );

        wlcms_set_css(
            '.wlcms-welcome-panel .elementor-section-full_width',
            array(
                'width' => '100%!important',
                'left' => '0!important'
            )
        );

        wlcms_set_css('.wlcms-welcome-panel', array(
            'position' => 'relative',
            "border" => "1px solid #c3c4c7",
            "box-shadow" => "0 1px 1px rgb(0 0 0 / 4%)",
            "background" => "#fff",
            "font-size" => "13px",
            "line-height" => "1.7",
            "margin" => "16px 0"
        ));

        wlcms_set_css('.wlcms-welcome-panel .welcome-panel-close:before', array(
            "background" => "0 0",
            "color" => "#787c82",
            "content" => '"\f153"',
            "display" => "block",
            "font" => "normal 16px/20px dashicons",
            "speak" => "never",
            "height" => "20px",
            "text-align" => "center",
            "width" => "20px",
            "-webkit-font-smoothing" => "antialiased",
            "-moz-osx-font-smoothing" => "grayscale",
            "position" => "absolute",
            "top" => "8px",
            "left" => "0",
            "transition" => "all .1s ease-in-out",
        ));

        wlcms_set_css('.wlcms-welcome-panel, .wlcms-welcome-panel .welcome-panel-content', array(
            'padding' => '0!important',
        ));

        wlcms_set_css('.wlcms-welcome-panel a', array(
            "color" => "#2271b1",
        ));
        wlcms_set_css(
            '.wlcms-welcome-panel .welcome-panel-close',
            array(
                'top' => '0!important',
                'right' => '0!important',
                'background' => 'white!important',
                'z-index' => '1000',
                "position" => "absolute",
                "top" => "0",
                "right" => "0",
                "padding" => "10px 15px 10px 24px",
                "font-size" => "13px",
                "line-height" => "1.23076923",
                "text-decoration" => "none"
            )
        );
    }

    private function set_dashboard_all_hidden()
    {
        $this->is_dashboard_all_hidden = (bool) $this->get_settings('hide_all_dashboard_panels');
    }

    public function is_dashboard_all_hidden()
    {
        return $this->is_dashboard_all_hidden;
    }

    /**
     * Change Dashboard H1 Title by using js
     *
     * @return void
     */
    private function dashboard_title()
    {
        global $wp_version;

        $dashboard_title = '';
        if ($icon = $this->get_settings('dashboard_icon')) {
            $dashboard_title .= '<span id=\"wlcms_dashboard_logo\"><img src=\"' . esc_url($icon) . '\" alt=\"\" /></span>';

            wlcms_set_css('.index-php #wlcms_dashboard_logo img', array('vertical-align' => 'middle', 'padding-right' => '10px'));
        }

        if ($title = $this->get_settings('dashboard_title')) {
            $dashboard_title .= '<span id=\"wlcms_dashboard_title\">' . esc_attr($title) . '</span>';
        }

        if (version_compare($wp_version, '3.8-beta', '>=')) {
            wlcms_add_js('jQuery(".index-php #wpbody-content .wrap h1:eq(0)").html("' . $dashboard_title . '")');
            return;
        }

        wlcms_add_js('jQuery("#icon-index").html("' . $dashboard_title . '")');

        return;
    }

    /**
     * Removed Wordpress Dashboard metaboxes if set to true
     *
     * @return void
     */
    private function remove_metaboxes()
    {
        global $wp_meta_boxes;

        $wlcms_widgets = wlcms_esc_html_deep($this->get_settings('dashboard_widgets'));

        if (!(isset($wp_meta_boxes['dashboard']) && is_array($wp_meta_boxes['dashboard']))) {
            return;
        }

        foreach ($wp_meta_boxes['dashboard'] as $section_key => $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach ($section as $position_key => $position) {
                if (!is_array($position)) {
                    continue;
                }
                foreach ($position as $widget_id => $widget) {
                    if ($this->is_wlcms_widget($widget_id)) {
                        continue;
                    } elseif (!$this->is_dashboard_all_hidden()) {
                        if ($wlcms_widgets && is_array($wlcms_widgets) && count($wlcms_widgets) > 0) {
                            if (!in_array($widget_id, $wlcms_widgets)) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }
                    unset($wp_meta_boxes['dashboard'][$section_key][$position_key][$widget_id]);
                }
            }
        }
    }

    private function is_wlcms_widget($dashboard_key = false)
    {
        if (!$dashboard_key) {
            return false;
        }

        if (strpos($dashboard_key, 'custom_vum_widget') !== false) {
            return true;
        }

        return in_array($dashboard_key, $this->excluded_widgets());
    }

    private function excluded_widgets()
    {
        return apply_filters('wlcms_exclude_dashboard_metaboxes', array('wlcms_rss_box'));
    }

    /**
     * Add Own Welcome panel if set to true
     *
     * @return void
     */
    private function set_welcome_metabox()
    {

        $user_id = get_current_user_id();
        if ($user_id == 0) {
            return false;
        }

        $welcome_panels = wlcms_field_setting('welcome_panel');

        if (!$welcome_panels || !is_array($welcome_panels)) {
            return;
        }

        if (!count($welcome_panels) === 0) {
            return;
        }


        $admin_Dashboard_Welcome_Message = wlcms()->require_class("Admin_Dashboard_Welcome_Message");
        $admin_Dashboard_Welcome_Message = new Admin_Dashboard_Welcome_Message();

        foreach ($welcome_panels as $key => $welcome_panel) {
            if (!$this->is_welcome_panel_visible($welcome_panel) || !is_array($welcome_panel)) {
                continue;
            }

            $welcome_content_hidden = get_user_meta($user_id, 'vum_hide_dashboard' . $key, true);

            if ($welcome_content_hidden && isset($welcome_panel['dismissible'])) {

                continue;
            }

            $admin_Dashboard_Welcome_Message->set($welcome_panel, $key);
            $admin_Dashboard_Welcome_Message->handle();
        }

        $admin_Dashboard_Welcome_Message->make_welcome_panel($welcome_panels);

        wlcms_set_css('.index-php .wlcms-welcome-panel-content', array('margin' => '13px', 'padding-bottom' => '25px!important'));
    }

    private function is_welcome_panel_visible($setting)
    {

        if (!isset($setting['is_active'])) {
            return false;
        }

        if ($setting['is_active'] != '1') {
            return false;
        }

        if (
            !isset($setting['visible_to'])
            || (isset($setting['visible_to']) && !$setting['visible_to'])
        )
            return false;

        $roles = $setting['visible_to'];

        return $this->has_current_user_role($roles);
    }

    private function has_current_user_role($roles)
    {
        if (!is_array($roles)) {
            return false;
        }

        $user_role = wlcms_current_user_roles();

        return in_array($user_role, $roles);
    }

    /**
     * Remove Dashboard Border Line if set to true
     *
     * @return void
     */
    private function remove_dashed_border()
    {
        if (!$this->get_settings('remove_empty_dash_panel')) {
            return;
        }

        wlcms_set_css('body.index-php #dashboard-widgets .postbox-container .empty-container', array('border' => '0', 'visibility' => 'hidden'));
    }

    /**
     * Remove Wordpress Core News and Event if set to troe
     *
     * @return void
     */
    public function remove_news_and_events()
    {
        if (!$this->get_settings('hide_news_and_events')) {
            return;
        }

        remove_meta_box('dashboard_primary', 'dashboard', 'core');

        wlcms_add_js(';jQuery("#community-events").remove();jQuery(".community-events-footer").remove();jQuery("#dashboard_primary h2 span").remove();');
    }

    /**
     * Add RSS Dashboard Metabox
     *
     * @return void
     */
    public function add_own_rss_panel()
    {
        if (!$this->get_settings('add_own_rss_panel')) {
            return;
        }

        if (!$this->get_settings('rss_feed_address')) {
            return;
        }


        $title = '';
        $this->get_settings('rss_title');

        if ($logo = $this->get_settings('rss_logo')) {
            $title .= '<img src="' . esc_url($logo) . '" height="16" width="16" alt="Logo" style="padding-right:5px;vertical-align:bottom;"/> ';
        }

        if ($rss_title = $this->get_settings('rss_title')) {
            $title .= $rss_title;
        }

        wp_add_dashboard_widget(
            'wlcms_rss_box',
            !empty($title) ? $title : '&nbsp;',
            array($this, 'rss_box')
        );
    }

    /**
     * Display RSS Metabox to the dashboard
     * called by wp_add_dashboard_widget
     * 
     * @return void
     */
    public function rss_box()
    {
        include_once(ABSPATH . WPINC . '/feed.php');

        $num_items = $this->get_settings('rss_feed_number_of_item');
        $introduction = $this->get_settings('rss_introduction');
        $show_post_content = $this->get_settings('show_post_content');
        $url = $this->get_settings('rss_feed_address');

        if ($introduction) {
            echo '<p>' . wp_kses_post($introduction) . '</p>';
        }

        $rss = fetch_feed($url);

        if ($error = is_wp_error($rss)) {
            echo '<div class="warning-text">' . esc_html($rss->get_error_message()) . '</div>';

            wlcms_set_css(
                '.index-php .warning-text',
                array('color' => 'rgba(240, 116, 95, 0.808)', 'display' => 'block')
            );
            return;
        }

        $maxitems = $rss->get_item_quantity($num_items);
        $rss_items = $rss->get_items(0, $maxitems);

        if ($maxitems == 0) {
            echo 'No items.';
            return;
        }

        $rss_list = '<ul>';

        foreach ($rss_items as $item) :

            $rss_list .= sprintf(
                '<li><strong><a href="%s" title="Posted %s" target="_blank">%s</a> </strong> <br />',
                esc_url($item->get_permalink()),
                $item->get_date('j F Y | g:i a'),
                esc_html($item->get_title())
            );

            if ($show_post_content) :
                $rss_list .= wp_kses_post($item->get_content());

            endif;
            $rss_list .= '</li>';

        endforeach;

        $rss_list .= '</ul>';

        echo $rss_list;
    }

    public function hide_vum_dashboard()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], "vum_hide_dashboard_nonce")) {
            exit("No naughty business please");
        }
        $user_id = get_current_user_id();
        if ($user_id == 0) {
            return false;
        }

        $key = sanitize_text_field($_POST['key']);
        update_user_meta($user_id, 'vum_hide_dashboard' . $key, 1);
        echo json_encode(array('type' => 'success'));
        exit;
    }

    public function reset_welcome_dashboard()
    {
        if (!isset($_GET['wlcms-action'])) return;
        
        if ($_GET['wlcms-action'] !== 'reset-welcome-dashboard') return;

        if (!isset($_GET['dashboard'])) return;

        if (!is_wlcms_super_admin()) return;
        
        $key = sanitize_text_field($_GET['dashboard']);

        delete_metadata('user', 0, 'vum_hide_dashboard' . $key, '', true);

        WLCMS_Queue('Welcome dashboard message successfully reset.');
    }

    public function widgets()
    {
        global $wp_meta_boxes;

        if (isset($wp_meta_boxes['dashboard']) && is_array($wp_meta_boxes['dashboard'])) {
            return $wp_meta_boxes['dashboard'];
        }

        require_once ABSPATH . '/wp-admin/includes/dashboard.php';

        set_current_screen('dashboard');
        //remove wlcms hook
        remove_action('wp_dashboard_setup', array($this, 'dashboard_setup'), 999);
        wp_dashboard_setup();
        //re-apply wlcms hook
        add_action('wp_dashboard_setup', array($this, 'dashboard_setup'), 999);
        set_current_screen(get_current_screen());
        return $wp_meta_boxes['dashboard'];
    }
}
