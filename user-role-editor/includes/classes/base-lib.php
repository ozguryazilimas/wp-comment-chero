<?php
defined( 'ABSPATH' ) || exit;

/*
 * General stuff for usage at WordPress plugins
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * 
 */

/**
 * This class contains general stuff for usage at WordPress plugins and must be extended by child class
 */
class URE_Base_Lib {

    protected static $instance = null; // object exemplar reference  
    protected $options_id = ''; // identifire to save/retrieve plugin options to/from wp_option DB table
    protected $options = array(); // plugin options data
    protected $multisite = false;
    protected $active_for_network = false;
    protected $main_blog_id = 0;

    
    public static function get_instance( $options_id = '') {
        if ( self::$instance===null ) {        
            self::$instance = new URE_Base_Lib( $options_id );
        }
        
        return self::$instance;
    }
    // end of get_instance()
        

    /**
     * class constructor
     * @param string $options_id  to save/retrieve plugin options to/from wp_option DB table
     */
    protected function __construct( $options_id ) {

        $this->multisite = function_exists( 'is_multisite' ) && is_multisite();
        if ( $this->multisite ) {
            // get Id of the 1st (main) blog
            $this->main_blog_id = $this->get_main_site();
        }

        $this->init_options( $options_id );

    }
    // end of __construct()

    
    public function get( $property_name ) {
        
        if ( !property_exists( $this, $property_name ) ) {
            ure_log_error( 'Lib class does not have such property '. $property_name );
            return null;
        }
        
        return $this->$property_name;
    }
    // end of get_property()
    
    
    public function set( $property_name, $property_value ) {
        
        if ( !property_exists( $this, $property_name ) ) {
            ure_log_error( 'Lib class does not have such property '. $property_name );
            return;
        }
        
        $this->$property_name = $property_value;
    }
    // end of get_property()
    

    public function get_main_site() {
        global $current_site;
        
        $blog_id = is_object( $current_site ) ? $current_site->blog_id : null;
        
        return $blog_id;
    }
    // end of get_main_site()

    
    /**
     * get current options for this plugin
     */
    protected function init_options( $options_id ) {
        
        $this->options_id = $options_id;
        $this->options = get_option( $options_id, array() );
        
    }
    // end of init_options()

    /**
     * Return HTML formatted message
     * 
     * @param string $message   message text
     * @param string $error_style message div CSS style
     */
    public function show_message( $message, $error_style = false ) {

        if ( $message ) {
            if ( $error_style ) {
                echo '<div id="message" class="notice notice-warning is-dismissible">';
            } else {
                echo '<div id="message" class="notice notice-success is-dismissible">';
            }
            // wp_kses_post(), not esc_html(), as some messages legitimately include inline tags such as <br/> or <em>.
            echo '<p>'. wp_kses_post( $message ) . '</p></div>';
        }
    }
    // end of show_message()
    
    
    public static function filter_string_var( $raw_str ) {

        $value = sanitize_text_field( wp_unslash( $raw_str ) );

        return $value;
    }
    // end of filter_string_var()

    /**
     * Returns value by name from GET/POST/REQUEST. Minimal type checking is provided
     * Note: the returned string is sanitized for safe storage/comparison (sanitize_text_field),
     * NOT for direct HTML output - escape it (esc_html/esc_attr/etc) at the point of output.
     *
     * @param string $var_name  Variable name to return
     * @param string $request_type  type of request to process get/post/request (default)
     * @param string $var_type  variable type to provide value checking
     * @return mix variable value from request
     */
    public function get_request_var( $var_name, $request_type = 'request', $var_type = 'string') {

        $result = 0;
        $request_type = strtolower( $request_type );
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- generic read-only accessor used by ~40 call sites, some behind nonce-gated AJAX/form handlers and some plain GET-based UI reads (e.g. user-role-editor.php's page-routing 'object' param) with no nonce to check; nonce verification is each mutating caller's responsibility (see URE_Ajax_Processor::valid_nonce(), URE_Lib::check_nonce()). Sanitization happens in filter_string_var() -> sanitize_text_field( wp_unslash() ), which WPCS cannot trace through a wrapper call.
        switch ( $request_type ) {
            case 'get': {
                if ( isset( $_GET[$var_name] ) ) {
                    $result = self::filter_string_var( $_GET[$var_name] );
                }
                break;
            }
            case 'post': {
                if ( isset( $_POST[$var_name] ) ) {
                    if ( $var_type!=='checkbox') {
                        $result = self::filter_string_var( $_POST[$var_name] );
                    } else {
                        $result = 1;
                    }
                }
                break;
            }
            case 'request': {
                if ( isset( $_REQUEST[$var_name] ) ) {
                    $result = self::filter_string_var( $_REQUEST[$var_name] );
                }
                break;
            }
            default: {
                $result = -1;   //  Wrong request type value, possible mistake in a function call
            }
        }
        // phpcs:enable

        if ( $result && $var_type === 'int' && !is_numeric( $result ) ) {
            $result = 0;
        }

        return $result;
    }
    // end of get_request_var()

    
    /**
     * returns option value for option with name in $option_name
     */
    public function get_option( $option_name, $default = false ) {

        if ( isset( $this->options[$option_name] ) ) {
            $value = $this->options[$option_name];
        } else {
            $value = $default;
        }
        $value = apply_filters('ure_get_option_'. $option_name, $value );
        
        return $value;
    }
    // end of get_option()

    
    /**
     * puts option value according to $option_name option name into options array property
     */
    public function put_option( $option_name, $option_value, $flush_options = false ) {

        if ( !is_array( $this->options ) ) {
            $this->options = array();
        }        
        $this->options[$option_name] = $option_value;
        if ( $flush_options ) {
            $this->flush_options();
        }
    }
    // end of put_option()
    

    /**
     * Delete URE option with name option_name
     * @param string $option_name
     * @param bool $flush_options
     */
    public function delete_option( $option_name, $flush_options = false ) {
        if ( array_key_exists( $option_name, $this->options ) ) {
            unset( $this->options[$option_name] );
            if ( $flush_options ) {
                $this->flush_options();
            }
        }
    }
    // end of delete_option()

    
    /**
     * Saves options array into WordPress database wp_options table
     */
    public function flush_options() {

        update_option( $this->options_id, $this->options );
    }
    // end of flush_options()
    

    public function get_current_url() {
        global $wp;
        
        $current_url = esc_url_raw( add_query_arg( $wp->query_string, '', home_url( $wp->request ) ) );

        return $current_url;
    }
    // end of get_current_url()

    
    /**
     * Returns comma separated list from the first $items_count element of $full_list array
     * 
     * @param array $full_list
     * @param int $items_count
     * @return string
     */
    public function get_short_list_str( $full_list, $items_count=3 ) {
     
        if ( empty( $full_list ) || !is_array( $full_list ) ) {
            return '...';
        }
        
        $short_list = array(); $i = 0;
        foreach($full_list as $item) {            
            if ( $i>=$items_count ) {
                break;
            }
            $short_list[] = $item;
            $i++;
        }
        
        $str = implode(', ', $short_list );
        if ( $items_count<count( $full_list ) ) {
            $str .= ', ...';
        }
        
        return $str;
    }    
    //  end of get_short_list_str()
    
    
    /**
     * Prepare the list of integer or string values for usage in SQL query IN (val1, val2, ... , valN) claster
     * @global wpdb $wpdb
     * @param string $list_type: allowed values 'int', 'string'
     * @param array $list_values: array of integers or strings
     * @return string - comma separated values (CSV)
     */
    public static function esc_sql_in_list( $list_type, $list_values ) {
        global $wpdb;
        
        if ( empty( $list_values ) || !is_array( $list_values ) || count( $list_values )===0 ) {
            return '';
        }

        if ( $list_type==='int' ) {
            $placeholder = '%d';   //  Integer
        } else {
            $placeholder = '%s';   // String
        }
        
        $placeholders = array_fill( 0, count( $list_values ), $placeholder );
        $str = implode(',', $placeholders );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $str contains only %d/%s placeholders built above, no raw input; passed straight to prepare().
        $result = $wpdb->prepare( $str, $list_values );

        return $result;
    }
    // end of esc_sql_in_list()
    
    
    /**
     * Returns the array of multi-site WP sites/blogs IDs for the current network
     * @return array
     */
    public function get_blog_ids() {

        if ( !$this->multisite ) {
            return null;
        }
        
        $network = get_current_site();

        $blog_ids = get_sites( array(
            'network_id' => $network->id,
            'fields'     => 'ids',
        ) );

        return $blog_ids;
    }
    // end of get_blog_ids()
    
        
    /**
     * Prevent cloning of the instance of the *Singleton* instance.
     *
     * @return void
     */
    public function __clone() {
        throw new \Exception('Do not clone a singleton instance.');
    }
    // end of __clone()
    
    /**
     * Prevent unserializing of the *Singleton* instance.
     *
     * @return void
     */
    public function __wakeup() {
        throw new \Exception('Do not unserialize a singleton instance.');
    }
    // end of __wakeup()

}
// end of URE_Base_Lib class
