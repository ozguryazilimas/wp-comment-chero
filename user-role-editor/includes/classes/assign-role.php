<?php
defined( 'ABSPATH' ) || exit;

/**
 * Project: User Role Editor plugin
 * Author: Vladimir Garagulya
 * Author email: support@role-editor.com
 * Author URI: https://www.role-editor.com
 * Greetings: some ideas and code samples for long running cron job was taken from the "Broken Link Checker" plugin (Janis Elst).
 * License: GPL v2+
 * 
 * Assign role to the users without role stuff
 */
class URE_Assign_Role {
    
    const MAX_USERS_TO_PROCESS = 50;

    private static $counter = 0;    
    
    protected $lib = null;
    private $quick_count = true;
    
    
    function __construct() {
        
        $this->lib = URE_Lib::get_instance();
        $this->quick_count = $this->count_quick_or_thoroughly();
    }
    // end of __construct()


    public function create_no_rights_role() {        
        
        $role_id = 'no_rights';
        $role_name = 'No rights';
                
        $wp_roles = wp_roles();        
        if ( isset( $wp_roles->roles[$role_id] ) ) {
            return;
        }
        
        add_role( $role_id, $role_name, array() );
        
    }
    // end of create_no_rights_role()          
    
    
    private function count_quick_or_thoroughly() {
        
        $quick_count = true;
        if ( defined('URE_COUNT_USERS_WITHOUT_ROLE_THOROUGHLY') && URE_COUNT_USERS_WITHOUT_ROLE_THOROUGHLY ) {
            $quick_count = false;
        } elseif ( $this->lib->is_pro() ) {
            $count_thoroughly = $this->lib->get_option( 'count_users_without_role_thoroughly', false );
            if ( $count_thoroughly ) {
                $quick_count = false;
            }
        }
        
        $quick_count = apply_filters('ure_count_users_without_role_quick', $quick_count );
        
        return $quick_count;
    }
    // end of count_quick_or_thoroughly()
    
    
    private function get_thorougly_where_condition() {
        global $wpdb;

        
        $id = get_current_blog_id();
        $blog_prefix = $wpdb->get_blog_prefix( $id );
        $meta_key = $blog_prefix .'capabilities';        
        $where = $wpdb->prepare(
            "WHERE NOT EXISTS (SELECT user_id FROM `". $wpdb->usermeta ."` ".
                                "WHERE user_id=users.ID AND meta_key=%s) OR ".
                                    "EXISTS (SELECT user_id FROM `". $wpdb->usermeta ."` ".
                                              "WHERE user_id=users.ID AND meta_key=%s AND ".
                                                "(meta_value='a:0:{}' OR meta_value IS NULL))",
            $meta_key, $meta_key);
                                    
        return $where;                            
    }
    // end of get_thoroughly_where_condition()


    private function get_quick_query_part2() {
        global $wpdb;

        $id = get_current_blog_id();
        $blog_prefix = $wpdb->get_blog_prefix($id);
        $meta_key = $blog_prefix .'capabilities';
        $query = $wpdb->prepare(
                "FROM `". $wpdb->usermeta ."` usermeta ".
                   "INNER JOIN `". $wpdb->users ."` users ON usermeta.user_id=users.ID ".
                      "WHERE usermeta.meta_key=%s AND ".
                            "(usermeta.meta_value = 'a:0:{}' OR usermeta.meta_value is NULL)",
                    $meta_key);
                                    
        return $query;                            
    }
    // end of get_quick_query_part2()    
    
    
    private function get_users_count_query() {
        global $wpdb;
                
        if ( $this->quick_count ) {
            $part2 = $this->get_quick_query_part2();
            $query = "SELECT COUNT(DISTINCT usermeta.user_id) ". $part2;
        } else {            
            $where = $this->get_thorougly_where_condition();
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->users is the site's own table name (not user input); %i placeholder needs WP 6.2+ but plugin supports WP 4.4+.
            $query = "SELECT count(ID) FROM {$wpdb->users} users" . ' ' . $where;
        }

        return $query;
    }
    // end of get_users_count_query()
    
    
    public function count_users_without_role() {
        
        global $wpdb;
    
        $users_quant = get_transient('ure_users_without_role');
        if (empty($users_quant)) {
            $query = $this->get_users_count_query();
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $query is assembled from $wpdb->prepare()'d fragments and internal table names, see get_users_count_query(); no WP_User_Query equivalent exists for "users with zero roles / an empty roles array" (the thing this counts).
            $users_quant = $wpdb->get_var( $query );
            set_transient('ure_users_without_role', $users_quant, 15 );
        }
        
        return $users_quant;
    }
    // end of count_users_without_role()
        
    
    public function get_users_without_role() {        
        global $wpdb;
        
        $top_limit = self::MAX_USERS_TO_PROCESS;
        
        if ( $this->quick_count ) {            
            $part2 = $this->get_quick_query_part2();
            $query = "SELECT DISTINCT usermeta.user_id {$part2}
                        LIMIT 0, {$top_limit}";
        } else {
            $where = $this->get_thorougly_where_condition();
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->users is the site's own table name (not user input); %i placeholder needs WP 6.2+ but plugin supports WP 4.4+.
            $query = "SELECT users.ID FROM {$wpdb->users} users" .
                        ' '. $where .
                        'LIMIT 0, '. $top_limit;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $query is assembled from $wpdb->prepare()'d fragments and internal table names, see above; no WP_User_Query equivalent exists for "users with zero roles / an empty roles array" (the thing this fetches).
        $users0 = $wpdb->get_col( $query );

        return $users0;
    }
    // end of get_users_without_role()
    
    
    public function show_html() {
        
      $users_quant = $this->count_users_without_role();
      if ((int) $users_quant === 0) {
          return;
      }
      $button_number =  (self::$counter>0) ? '_2': '';
      
?>          
        &nbsp;&nbsp;<input type="button" name="move_from_no_role<?php echo esc_attr( $button_number );?>" id="move_from_no_role<?php echo esc_attr( $button_number );?>" class="button"
                        value="Without role (<?php echo esc_attr( $users_quant );?>)" onclick="ure_move_users_from_no_role_dialog()">
<?php
    if ( self::$counter===0 ) {
?>
        <div id="move_from_no_role_dialog" class="ure-dialog">
            <div id="move_from_no_role_content" style="padding: 10px;"></div>                
        </div>
<?php
        self::$counter++;
    }
        
    }
    // end of show_html()
       
}
// end of URE_Assign_Role class