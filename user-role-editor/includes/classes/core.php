<?php
/*
 * Project: User Role Editor
 * General Helper class
 * Author: Vladimir Garagulya
 * Author email: support@role-editor.com
 * Author URI: https://www.role-editor.com
 * License: GPL v2+
 *
 */

defined( 'ABSPATH' ) || exit;

class URE_Core {
    const PLUGIN_VERSION = '4.66.1';
    const PLUGIN_SLUG = 'user-role-editor';
    const PLUGIN_NAME = 'User Role Editor';
    const TEXT_DOMAIN = 'user-role-editor';
    const REQUIRED_PHP_VERSION = '7.4';
    const REQUIRED_WORDPRESS_VERSION = '4.6';

    protected static bool $initialized = false;    
    private static ?string $plugin_url = null;
    private static ?string $plugin_dir = null;
    private static ?string $plugin_base_name = null;
    private static ?string $plugin_file     = null;
    private static ?string $plugin_full_path = null;
    private static ?string $plugin_parent_page = null;

    private static $logger = null; // Allow injection    
    
    
    
    /**
     * Set custom logger (for testing purpose)
     */
    public static function set_logger(?callable $callback): void {
        
        if ( is_callable( $callback) ) {
            self::$logger = $callback;
        }
        
    }
    
    private static function log(string $message): void {
        if ( self::$logger!==null ) {
            $callback = self::$logger;
            if ( is_callable( $callback) ) {
                call_user_func( self::$logger, $message );
            }
            return;
        }
        
        ure_log_error( '[URE Loader] ' . $message );
    }
    
    
    public static function set_plugin_full_path( $plugin_full_path ) {
        
        self::$plugin_full_path = $plugin_full_path;
        
    }
    // end of set_plugin_full_path()
    
    
    public static function get_plugin_url(): string {
        
        if ( self::$plugin_url === null ) {
            self::$plugin_url = plugin_dir_url( self::get_plugin_full_path() );
        }
        
        return self::$plugin_url;
        
    }
    // end of get_plugin_url()
    
    
    public static function get_plugin_dir(): string {
        
        if ( self::$plugin_dir === null ) {
            self::$plugin_dir = plugin_dir_path( self::get_plugin_full_path() );
        }
        return self::$plugin_dir;
    }
    // end of get_plugin_dir()
    

    public static function get_plugin_base_name(): string {
        
        if ( self::$plugin_base_name === null ) {
            self::$plugin_base_name = plugin_basename( self::get_plugin_full_path() );
        }
        
        return self::$plugin_base_name;
        
    }
    // end of get_plugin_base_name()


    public static function get_plugin_file(): string {
        
        if (self::$plugin_file === null) {
            self::$plugin_file = basename( self::get_plugin_full_path() );
        }
        
        return self::$plugin_file;
        
    }
    // end of get_plugin_file()
    

    public static function get_plugin_full_path(): string {
        
        if ( self::$plugin_full_path === null ) {
            // A bootstrap class should not guess its own location.
            throw new RuntimeException(
                'URE_Core must be initialized correctly before use.'
            );
        }
        
        return self::$plugin_full_path;
        
    }    
    // end of get_plugin_full_path()

    
    public static function get_plugin_parent_page(): string {
        
        if (self::$plugin_parent_page === null) {
            self::$plugin_parent_page = is_network_admin() ? 'network/users.php' : 'users.php';
        }
        
        return self::$plugin_parent_page;
        
    }
    // end of get_plugin_file()


    private static function deactivate_with_notice( string $message ): void {

        deactivate_plugins( self::get_plugin_full_path() );

        if ( URE_Loader::load_file( self::get_plugin_dir() .'includes/classes/admin-notice.php' ) ) {
            $notice = new URE_Admin_Notice('warning', $message );
            $notice->register();
        }
    }
    // end of deactivate_with_notice()

    
    /**
     * Check product version and stop execution if product version is not compatible with required one
     * @param string $version1
     * @param string $version2
     * @param string $error_message
     * @return void
     */    
    public static function version_compare( $version1, $version2, $message ) {
        if ( version_compare($version1, $version2, '<') ) {
             self::log( $message );
             self::deactivate_with_notice( $message );
             return false;
        }
        
        return true;
    }
    // end of version_compare()
    
    
    public static function valid_php_version() {
        $message = sprintf('User Role Editor requires PHP %s or newer. Do not activate it until update your server PHP version.', 
                            self::REQUIRED_PHP_VERSION );
        if ( !self::version_compare( PHP_VERSION, self::REQUIRED_PHP_VERSION, $message ) ) {
            return false;
        }
        
        return true;
    }
    // end of php_version_compare()
    
    
    public static function valid_wordpress_version() {
        global $wp_version;
        
        $message = sprintf('User Role Editor requires WordPress %s or newer. Do not activate it until update your WordPress installation.', 
                            self::REQUIRED_WORDPRESS_VERSION );                    
        if ( !self::version_compare( $wp_version, self::REQUIRED_WORDPRESS_VERSION, $message ) ) {
            return false;
        }
        
        return true;
    }
    // end of wordpress_version_compare()

         
    
    // It's excuted on plugin uninstall via WordPress->Plugins->Delete
    public static function uninstall() {
    
        $uninstall = new URE_Uninstall;
        $uninstall->act();

    }
    // end of uninstall()        
    
    
    public static function define_files() {

        $includes_dir = URE_Core::get_plugin_dir() . 'includes/';
        $classes_dir = $includes_dir . 'classes/';
        
        URE_Loader::add_def( $classes_dir . 'admin-notice.php', 'URE_Admin_Notice' );
        URE_Loader::add_def( $classes_dir . 'base-lib.php', 'URE_Base_Lib' );
        URE_Loader::add_def( $classes_dir . 'lib.php', 'URE_Lib' );
        URE_Loader::add_def( $includes_dir . 'misc-support-stuff.php', null );
        URE_Loader::add_def( $classes_dir . 'task-queue.php', 'URE_Task_Queue' );
        URE_Loader::add_def( $classes_dir . 'own-capabilities.php', 'URE_Own_Capabilities' );
        URE_Loader::add_def( $classes_dir . 'bbpress.php', 'URE_bbPress' );
        URE_Loader::add_def( $classes_dir . 'assign-role.php', 'URE_Assign_Role' );
        URE_Loader::add_def( $classes_dir . 'grant-roles.php', 'URE_Grant_Roles' );
        URE_Loader::add_def( $classes_dir . 'user-other-roles.php', 'URE_User_Other_Roles' );
        URE_Loader::add_def( $classes_dir . 'protect-admin.php', 'URE_Protect_Admin' );
        URE_Loader::add_def( $classes_dir . 'ajax-processor.php', 'URE_Ajax_Processor' );
        URE_Loader::add_def( $classes_dir . 'screen-help.php', 'URE_Screen_Help' );
        URE_Loader::add_def( $classes_dir . 'known-js-css-compatibility-issues.php', 'URE_Known_JS_CSS_Compatibility_Issues' );
        URE_Loader::add_def( $classes_dir . 'role-additional-options.php', 'URE_Role_Additional_Options' );
        URE_Loader::add_def( $classes_dir . 'capability.php', 'URE_Capability' );
        URE_Loader::add_def( $classes_dir . 'woocommerce-capabilities.php', 'URE_WooCommerce_Capabilities' );
        URE_Loader::add_def( $classes_dir . 'capabilities-groups-manager.php', 'URE_Capabilities_Groups_Manager' );
        URE_Loader::add_def( $classes_dir . 'capabilities.php', 'URE_Capabilities' );
        URE_Loader::add_def( $classes_dir . 'view.php', 'URE_View' );
        URE_Loader::add_def( $classes_dir . 'role-view.php', 'URE_Role_View' );
        URE_Loader::add_def( $classes_dir . 'user-view.php', 'URE_User_View' );
        URE_Loader::add_def( $classes_dir . 'editor.php', 'URE_Editor' );
        URE_Loader::add_def( $classes_dir . 'tools.php', 'URE_Tools' );
        URE_Loader::add_def( $classes_dir . 'settings.php', 'URE_Settings' );
        URE_Loader::add_def( $classes_dir . 'uninstall.php', 'URE_Uninstall' );
        URE_Loader::add_def( $classes_dir . 'user-role-editor.php', 'User_Role_Editor' );
        
    }
    // end of define_files()

    public static function init( $plugin_full_path ) {
                        
        if ( self::$initialized ) {
            // Allow URE to initialize one time only
            return;
        }

        self::$initialized = true;
        
        self::set_plugin_full_path( $plugin_full_path );

        if ( !self::valid_php_version() ) {
            return;
        }
        if ( !self::valid_wordpress_version() ) {
            return;
        }
                
        self::define_files();
        URE_Loader::load_files();
        
        // Uninstall hook
        register_uninstall_hook( URE_Core::get_plugin_full_path(), [ 'URE_Core', 'uninstall' ] );
        
        if ( ! isset( $GLOBALS['user_role_editor'] ) ) {
            $GLOBALS['user_role_editor'] = User_Role_Editor::get_instance();
        }        
    }
    // end of init()
                    
}
// end of class URE_Core