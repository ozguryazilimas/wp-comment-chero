<?php
if (!function_exists('wlcms_field_setting')) {
    function wlcms_field_setting($key = "", $default = false)
    {
        // ponytail: no $_POST['wlcms'] shadow here. It let unauthenticated POSTs
        // reflect arbitrary values into every admin render (reflected XSS). Live
        // preview persists nonce + manage_options-gated values via
        // WLCMS_Previewable::store_preview() and reads them back from the DB.
        return wlcms_db_field_setting($key, $default);
    }
}

if (!function_exists('wlcms_db_field_setting')) {
    function wlcms_db_field_setting($key = "", $default = false)
    {
        return wlcms()->Settings()->get($key, $default);
    }
}

if (!function_exists('wlcms_welcome_value')) {
    function wlcms_welcome_value($key = 0, $field = 'title', $default = false)
    {
        $welcome_panel = wlcms_field_setting('welcome_panel');

        return isset($welcome_panel[$key][$field]) ? $welcome_panel[$key][$field] : $default;
    }
}

if (!function_exists('wlcms_array_value')) {
    function wlcms_array_value($data = array(), $default = false)
    {
        return isset($data) ? $data : $default;
    }
}

if (!function_exists('wlcms_site_domain')) {
    function wlcms_site_domain()
    {
        $site = get_site_url();
        $scheme = '#^http(s)?://#';
        return preg_replace($scheme, "", $site);
    }
}

if (!function_exists('wlcms_sanitize_text_field')) {
    function wlcms_sanitize_text_field($value)
    {
        if (!is_array($value)) {
            return wp_kses_post($value);
        }

        foreach ($value as $key => $array_value) {
            $value[$key] = wlcms_sanitize_text_field($array_value);
        }
        return $value;
    }
}
if (!function_exists('wlcms_esc_html_deep')) {
    function wlcms_esc_html_deep($value)
    {
        if (!is_array($value)) {
            return esc_html($value);
        }

        foreach ($value as $key => $array_value) {
            $value[$key] = wlcms_esc_html_deep($array_value);
        }
        return $value;
    }
}

if (!function_exists('wlcms_esc_html_e')) {
    function wlcms_esc_html_e($value)
    {
        return wlcms_sanitize_text_field($value);
    }
}

if (!function_exists('wlcms_removeslashes')) {
    function wlcms_removeslashes($value)
    {
        return stripslashes_deep($value);
    }
}

if (!function_exists('wlcms_set_css')) {
    function wlcms_set_css($element, $props)
    {
        wlcms()->Admin_Script()->setCss($element, $props);
    }
}

if (!function_exists('wlcms_set_hidden_css')) {
    function wlcms_set_hidden_css($element)
    {
        wlcms()->Admin_Script()->set_CssHidden($element);
    }
}

if (!function_exists('wlcms_add_js')) {
    function wlcms_add_js($js)
    {
        wlcms()->Admin_Script()->appendJs($js);
    }
}

if (!function_exists('is_wlcms_super_admin')) {
    function is_wlcms_super_admin()
    {
        if (!current_user_can('manage_options')) return false;

        $enable_wlcms_admin = (bool) wlcms_field_setting('enable_wlcms_admin');
        if (!is_wlcms_admin() && $enable_wlcms_admin) return false;

        return true;
    }
}

if (!function_exists('is_wlcms_admin')) {
    function is_wlcms_admin()
    {
        return wlcms()->Admin_Menus()->has_visible_roles();
    }
}

if (!function_exists('wlcms_current_user_roles')) {
    function wlcms_current_user_roles()
    {
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $role = $roles[0] ?? '';

        /**
         * Allow overriding the "primary" role used by WLCMS when user has multiple roles.
         *
         * @param string   $role  Selected role (default: first role).
         * @param string[] $roles All user roles.
         * @param WP_User  $user  Current user.
         */
        return apply_filters('wlcms_primary_role', $role, $roles, $user);
    }
}

if (!function_exists('wlcms_select_roles')) {
    /**
     * add role select element
     *
     * @param array $args
     * @param string $selected
     * @return string
     */
    function wlcms_select_roles($args = array(), $selected = array())
    {

        global $wp_roles;

        $return = '<select name="' . $args['name'] . '[]" id="' . $args['name'] . '" multiple="multiple" class="' . $args['class'] . '">';
        $return .= '<option value=""> </option>';
        foreach ($wp_roles->role_names as $key => $title) {
            $selected_val = '';

            if (is_array($selected) && in_array($key, $selected)) {
                $selected_val = ' selected';
            }
            $return .= '<option value="' . esc_attr($key) . '" ' . $selected_val . '>' . esc_html($title) . '</option>';
        }
        $return .= '</select>';

        return $return;
    }
}

if (!function_exists('wlcms_select_pages')) {
    /**
     * add role select element
     *
     * @param array $args
     * @param string $selected
     * @return string
     */
    function wlcms_select_pages($args = array(), $selected = '', $query = false)
    {

        if (!$query) {
            $post_type = array(
                'post_type' => 'page',
                'posts_per_page' => '-1',
                'post_status' => 'publish'
            );
        }
        $pages = get_posts($query);

        $return = '<select name="' . $args['name'] . '" id="' . $args['name'] . '" class="' . $args['class'] . '">';
        $return .= '<option value=""> </option>';
        if ($pages) :
            foreach ($pages as $page) {
                $selected_val = '';
                $key = $page->ID;
                $title = $page->post_title;

                if ($selected == $key) {
                    $selected_val = ' selected';
                }
                $return .= '<option value="' . esc_attr($key) . '" ' . $selected_val . '>' . esc_html($title) . '</option>';
            }
        endif;
        $return .= '</select>';

        return $return;
    }
}

if (!function_exists('wlcms_kses')) {
    function wlcms_kses($value, $callback = 'wp_kses_post')
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = wlcms_kses($item, $callback);
            }
        } elseif (is_object($value)) {
            $object_vars = get_object_vars($value);
            foreach ($object_vars as $property_name => $property_value) {
                $value->$property_name = wlcms_kses($property_value, $callback);
            }
        } else {
            $value = call_user_func($callback, $value);
        }

        return $value;
    }
}

if (!function_exists('vum_fix_json')) {
    function vum_fix_json($matches)
    {
        return "s:" . strlen($matches[2]) . ':"' . $matches[2] . '";';
    }
}

if (!function_exists('wlcms_form_upload_field')) {
    /**
     * Upload image field generator
     *
     * @param string $label
     * @param string $key
     * @param string $help
     * @return string
     */
    function wlcms_form_upload_field($label = '', $key = '', $help = '')
    {
        $html = '<label>' . $label . '</label>
                <div class="wlcms-upload-thumbnail">';

        $key_setting = wlcms_field_setting($key);
        if ($key_setting) {
            $html .= '<img src="' . esc_url($key_setting) . '" alt="" /><span class="dashicons dashicons-dismiss wlcms-remove-img"></span>';
        }

        $html .= '</div>
                    <div class="wlcms-input">
                        <input type="text" name="' . $key . '" class="wlcms-upload-input" value="' . esc_url($key_setting) . '" />
                        <a href="#" class="wlcms_upload">Upload</a>
                    </div>
                <div class="wlcms-help">' . $help . '</div>';

        return $html;
    }
}

if (!function_exists('wlcms_is_elementor_active')) {
    function wlcms_is_elementor_active()
    {
        if (!version_compare(PHP_VERSION, '5.4', '>=')) {
            return false;
        }
        return (function_exists('_is_elementor_installed') && _is_elementor_installed()) || defined('ELEMENTOR_VERSION');
    }
}

if (!function_exists('wlcms_is_beaver_builder_active')) {
    function wlcms_is_beaver_builder_active()
    {
        if (!version_compare(PHP_VERSION, '5.4', '>=')) {
            return false;
        }
        return class_exists('FLBuilder');
    }
}

if (!function_exists('wlcms_has_pagebuilder')) {
    function wlcms_has_pagebuilder()
    {
        return (wlcms_is_beaver_builder_active() || wlcms_is_elementor_active());
    }
}

if (!function_exists('wlcms_css_metrics')) {
    function wlcms_css_metrics($value = 'auto')
    {
        if ($value == 'auto') return $value;

        if (strpos($value, '%') !== false) return $value;

        if (strpos($value, 'px') !== false) return $value;

        return $value . 'px';
    }
}

if (isset($_GET['preview_section']) && $_GET['preview_section'] == 'login') {
    if (!function_exists('wp_clear_auth_cookie')) {

        /**
         * Multisite login hack to avoid redirecting to the dashboard while in preview mode
         * By adding &reauth=1 from the param WordPress will act as force show login form.
         * This will replace the pluggable function wp_clear_auth_cookie to avoid removing of cookies
         * @return void
         */
        function wp_clear_auth_cookie()
        {
            return;
        }
    }
}


if (!function_exists('wlcms_get_pages_by_ids')) {
    function wlcms_get_pages_by_ids(array $ids)
    {
        $query = new \WP_Query([
            'post__in' => $ids,
            'post_type' => ['page'],
            'posts_per_page' => -1
        ]);

        $result = [];
        if ($query->have_posts()) {

            while ($query->have_posts()) : $query->the_post();
                $result[] = ['id' => get_the_ID(), 'text' => get_the_title()];
            endwhile;
        }

        wp_reset_postdata();

        return $result;
    }
}


if (!function_exists('wlcms_search_pages')) {
    function wlcms_search_pages($q = '')
    {
        $query = new \WP_Query([
            's' => wlcms_sanitize_text_field($q),
            'post_type' => ['page'],
            'posts_per_page' => 50
        ]);
        $result = [];
        if ($query->have_posts()) {

            while ($query->have_posts()) : $query->the_post();
                $result[] = ['id' => get_the_ID(), 'text' => get_the_title()];
            endwhile;
        }
        wp_reset_postdata();

        return $result;
    }
}
