<?php

class WLCMS_Settings
{
    private $settings;

    function __construct()
    {
        $this->init_settings();
        add_filter('wp_kses_allowed_html', array($this, 'kses_allowed_html'), 10, 2);
        add_action('admin_init', array($this, 'init'));
        add_filter('plugin_action_links', array($this, 'plugin_settings'), 10, 2);
        add_action('wlcms_after_body', array($this, 'add_import_html'));
    }

    public function init()
    {
        // check or initiate import
        $this->import();

        if (!isset($_GET['wlcms-action'])) {
            return;
        }

        // check or initiate reset
        $this->reset_plugin();

        // check or initiate export
        $this->export();
    }
    public function plugin_settings($links, $file)
    {
        if (WLCMS_BASENAME == $file) {
            // Add your custom link
            $custom_link = '<a href="' . admin_url('options-general.php?page=wlcms-plugin.php&wlcms-action=reset&_wlcms_anonce=' . wp_create_nonce('wlcms-action-nonce')) . '"onclick="return confirm(\'Are you sure you want to reset?\')">Reset</a>';
            // Insert the custom link at the beginning of the array
            array_unshift($links, $custom_link);
        }
        return $links;
    }

    public function kses_allowed_html($tags, $context)
    {

        if ('post' === $context) {

            $tags['iframe'] = array(
                'align' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'name' => true,
                'src' => true,
                'id' => true,
                'class' => true,
                'style' => true,
                'scrolling' => true,
                'marginwidth' => true,
                'marginheight' => true,
                'allowfullscreen' => true,
                'mozallowfullscreen' => true,
                'webkitallowfullscreen' => true,
            );

            $tags['embed'] = array(
                'src' => true,
                'height' => true,
                'width' => true,
                'style' => true,
                'type' => true,
            );
        }

        return $tags;
    }

    public function get($key = "", $default = false)
    {
        if (!isset($this->settings[$key])) {
            return $default;
        }

        $value = wlcms_removeslashes($this->settings[$key]);
        if (empty($value) || is_null($value)) {
            return false;
        }

        if (is_array($value) && count($value) == 0) {
            return false;
        }

        return $value;
    }

    public function reset()
    {
        $this->settings = array();
    }

    public function setAll($value)
    {
        $this->settings = $value;
    }

    public function getAll()
    {
        return $this->settings;
    }

    public function set($key, $value)
    {
        $this->settings[$key] = $value;
    }

    public function remove($key)
    {
        if (isset($this->settings[$key])) {
            unset($this->settings[$key]);
        }
    }

    public function save()
    {
        update_option("wlcms_options", $this->settings);
    }

    public function store()
    {
        do_action('wlcms_before_saving', $this);
        $this->reset();
        $this->set('version', WLCMS_VERSION);

        foreach ($this->keys() as $key) {
            $setting_value = '';
            if (isset($_POST[$key])) {
                $setting_value = wlcms_kses($_POST[$key]);
            }
            $this->set($key, $setting_value);
        }

        $placeholder = ''; // use the same method used by preview wizard
        do_action('wlcms_save_addtional_settings', $this, $placeholder);

        $this->save();

        do_action('wlcms_after_saving', $this);

        WLCMS_Queue('Settings saved.');
        wp_redirect(wlcms()->admin_url());
        exit;
    }

    public function init_settings()
    {
        $settings = get_option("wlcms_options", false);

        if (!$settings) {
            $settings = $this->default_options();
        }

        $this->settings = $settings;
    }

    public function add_import_html()
    {
        wlcms()->admin_view('parts/import-settings');
    }

    public function import()
    {
        if (!is_wlcms_super_admin()) {
            return;
        }

        if (!isset($_POST['wlcms-settings_nonce'])) return;

        if (!is_admin() && !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['wlcms-settings']) && !isset($_FILES['import_file'])) {
            return;
        }

        if (!isset($_FILES['import_file'])) {
            return;
        }

        if ($_FILES['import_file']['size'] == 0 && $_FILES['import_file']['name'] == '') {
            return;
        }

        // check nonce
        if (!wp_verify_nonce($_POST['wlcms-settings_nonce'], 'wlcms-settings-action')) {

            WLCMS_Queue('Sorry, your nonce did not verify.', 'error');
            wp_redirect(wlcms()->admin_url());
            exit;
        }

        $import_field = 'import_file';
        $temp_file_raw = $_FILES[$import_field]['tmp_name'];
        $temp_file = esc_attr($temp_file_raw);
        $arr_file_type = $_FILES[$import_field];
        $uploaded_file_type = $arr_file_type['type'];
        $allowed_file_types = array('application/json');

        if (!in_array($uploaded_file_type, $allowed_file_types)) {
            WLCMS_Queue('Upload a valid .json file.', 'error');
            wp_redirect(wlcms()->admin_url());
            exit;
        }

        $settings = (array)json_decode(
            file_get_contents($temp_file),
            true
        );

        unlink($temp_file);

        if (!$settings) {

            WLCMS_Queue('Nothing to import, please check your json file format.', 'error');
            wp_redirect(wlcms()->admin_url());
            exit;
        }

        // Validate the JSON schema: only accept keys the plugin actually
        // recognises so arbitrary options cannot be injected via import.
        $allowed_keys = array_merge($this->keys(), array('version'));
        $settings = array_intersect_key($settings, array_flip($allowed_keys));

        // Sanitize imported values the same way the regular save path does
        // (see store()), so untrusted JSON cannot smuggle in unescaped markup.
        $settings = wlcms_kses($settings);

        $this->setAll($settings);
        $this->save();

        WLCMS_Queue('Your Import has been completed.');

        wp_redirect(wlcms()->admin_url());
        exit;
    }


    public function export()
    {
        if (!is_wlcms_super_admin()) {
            return;
        }


        if (!isset($_GET['wlcms-action']) || (isset($_GET['wlcms-action']) && $_GET['wlcms-action'] != 'export')) {
            return;
        }

        if (!wp_verify_nonce($_REQUEST['_wlcms_anonce'], 'wlcms-action-nonce')) {

            WLCMS_Queue('Sorry, your nonce did not verify.', 'error');
            wp_redirect(wlcms()->admin_url());
            exit;
        }
        
        $settings = $this->getAll();

        if (!is_array($settings)) {
            $settings = array();
        }

        $settings = json_encode($settings);

        header('Content-disposition: attachment; filename=wlcms-settings.json');
        header('Content-type: application/json');
        echo $settings;
        exit;
    }

    public function reset_plugin()
    {
        global $wpdb;
        if (!current_user_can('install_plugins')) {
            return false;
        }

        if ($_GET['wlcms-action'] != 'reset') {
            return;
        }

        if (!wp_verify_nonce($_REQUEST['_wlcms_anonce'], 'wlcms-action-nonce')) {

            WLCMS_Queue('Sorry, your nonce did not verify.', 'error');
            wp_redirect(admin_url());
            exit;
        }


        delete_option("wlcms_options");
        $wpdb->get_results($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", 'wlcms_o_%'));

        WLCMS_Queue('Settings reset.');
        wp_redirect(wlcms()->admin_url());
        exit;
    }

    public function keys()
    {
        return array_keys($this->default_options());
    }

    public function get_default_option($key)
    {
        $settings = $this->default_options();
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    public function default_options()
    {

        $settings = array(
            'developer_icon' => '',
            'use_developer_icon_footer' => 1,
            'developer_icon_footer_url' => '',
            'developer_side_menu_image' => '',
            'developer_icon_admin_bar' => false,
            'developer_branding_footer' => false,
            'use_developer_side_menu_image' => false,
            'hide_wordpress_logo_and_links' => false,
            'hide_wp_version' => false,
            'admin_bar_logo' => '',
            'admin_bar_logo_width' => 15,
            'admin_bar_alt_text' => '',
            'admin_bar_howdy_text' => '',
            'admin_bar_url' => '',
            'side_menu_image' => '',
            'collapsed_side_menu_image' => '',
            'side_menu_link_url' => '',
            'side_menu_alt_text' => '',
            'gutenberg_exit_icon' => '',
            'gutenberg_exit_custom_icon' => '',
            'footer_image' => '',
            'footer_url' => '',
            'footer_html' => '',
            'dashboard_icon' => '',
            'dashboard_title' => 'Dashboard',
            'dashboard_role_stat' => false,
            'dashboard_widgets_visibility_roles' => array('administrator', 'editor', 'author', 'contributor', 'subscriber'),
            'dashboard_widgets' => array(),
            'hide_all_dashboard_panels' => false,
            'hide_at_a_glance' => false,
            'hide_activities' => false,
            'hide_recent_comments' => false,
            'hide_quick_press' => false,
            'hide_news_and_events' => false,
            'remove_empty_dash_panel' => false,
            'welcome_panel' => array(
                array(
                    'is_active' => false,
                    'show_title' => false,
                    'template_type' => 'html',
                    'visible_to' => array('administrator', 'editor', 'author', 'contributor', 'subscriber'),
                ), array(
                    'is_active' => false,
                    'show_title' => false,
                    'template_type' => 'html',
                    'visible_to' => array('administrator', 'editor', 'author', 'contributor', 'subscriber'),
                )
            ),
            'add_own_rss_panel' => false,
            'rss_feed_number_of_item' => 3,
            'show_post_content' => false,
            'rss_introduction' => '',
            'rss_logo' => '',
            'rss_title' => '',
            'wlcms_admin' => false,
            'admin_menus' => false,
            'enable_wlcms_admin' => false,
            'admin_bar_menus' => false,
            'hide_admin_bar_all' => false,
            'hide_help_box' => false,
            'hide_screen_options' => false,
            'hide_nag_messages' => false,
            'settings_custom_css_admin' => '',
            'settings_custom_css_url' => ''
        );
        return apply_filters('wlcms_setting_fields', $settings);
    }
}
