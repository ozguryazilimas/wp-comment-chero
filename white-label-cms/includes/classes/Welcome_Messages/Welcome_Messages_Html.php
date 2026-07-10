<?php
class Welcome_Messages_Html
{
    private $key;
    private $settings;
    public function process($settings, $key)
    {
        $this->key = $key;
        $this->settings = $settings;

        if (isset($this->settings['is_fullwidth']) && $this->settings['is_fullwidth'] == 1) {
            add_action('in_admin_header', array($this, 'welcome_panel'));
            return;
        }

        wp_add_dashboard_widget(
            'custom_vum_widget' . $key,
            isset($this->settings['title']) ? esc_html($this->settings['title']) : '&nbsp;',
            array($this, 'welcome_description'),
            null,
            array('desc' => $this->template())
        );
    }

    public function welcome_description($post, $callback_args)
    {
        echo $callback_args['args']['desc'];
    }

    public function welcome_panel()
    { ?>
        <div id="welcome-panel<?php echo $this->key ?>" data-welcome_key="<?php echo $this->key ?>" class="wlcms-welcome-panel">
            <?php
            if (isset($this->settings['dismissible'])) :
            ?><a class="welcome-panel-close" href="#" aria-label="Dismiss the welcome panel">Dismiss</a>
            <?php endif ?>
            <div class="welcome-panel-content welcome-panel-content<?php echo $this->key ?>" style="padding-bottom:20px">
                <?php if (isset($this->settings['title'])) : ?>
                    <h2><?php echo esc_html($this->settings['title']) ?></h2>
                <?php endif; ?>
                <div class="wlcms-welcome-content">
                    <?php echo $this->template(); ?>
                </div>
            </div>
        </div>
<?php
        $welcome = sprintf(";jQuery('#welcome-panel%1\$d').insertBefore('#dashboard-widgets-wrap');jQuery('#welcome-panel%1\$d').show();", $this->key);
        wlcms_add_js($welcome);
    }

    public function template()
    {
        return isset($this->settings['description']) ? wpautop(wp_kses_post($this->settings['description'])) : '';
    }
}
