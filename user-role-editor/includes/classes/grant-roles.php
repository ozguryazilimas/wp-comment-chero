<?php
defined( 'ABSPATH' ) || exit;

/**
 * Project: User Role Editor plugin
 * Author: Vladimir Garagulya
 * Author email: support@role-editor.com
 * Author URI: https://www.role-editor.com
 * License: GPL v2+
 * 
 * Assign multiple roles to the list of selected users directly from the "Users" page
 * Grant/Revoke single role to/from selected users 
 */

class URE_Grant_Roles {

    const NO_ROLE_FOR_THIS_SITE = 'no-role-for-this-site';
    
    private $lib = null;
    private static $counter = 0;
    
    
    public function __construct() {
        
        $this->lib = URE_Lib::get_instance();
        
        add_action( 'load-users.php', array( $this, 'load' ) );                
                
    }
    // end of __construct()
    
    
    public function load() {
        
        add_action('restrict_manage_users', array($this, 'show_roles_manage_html') );
        add_action('admin_head', array(User_Role_Editor::get_instance(), 'add_css_to_users_page') );
        add_action('admin_enqueue_scripts', array($this, 'load_js') );        
        
    }
    // end of load()
            
    
    private static function validate_users( array $users ) {

        foreach ( $users as $user_id ) {
            if ( !is_numeric( $user_id ) ) {
                return false;
            }
            if ( !current_user_can( 'promote_user', $user_id ) ) {
                return false;
            }

            if ( is_multisite() && !is_user_member_of_blog( $user_id ) ) {
                return false;
            }

        }
        
        return true;
    }
    // end of validate_users()
    
    
    public static function add_role( array $users ) {
        
        $answer = URE_Lib::check_nonce();
        if ( $answer!==TRUE ) {
            return $answer;
        }

        if ( !current_user_can('promote_users') ) {            
             $answer = array('result'=>'error', 'message'=>esc_html__('Not enough permissions', 'user-role-editor') );
             return $answer;
        }
        
        if ( !self::validate_users( $users ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Can not edit user or invalid data at the users list', 'user-role-editor') );
            return $answer;
        }
        
        $lib = URE_Lib::get_instance();
        $role = $lib->get_request_var('role', 'post', 'string');
        if ( !self::validate_roles( array($role=>$role) ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Invalid role', 'user-role-editor') );
            return $answer;
        }
        
        $quantity = 0;
        foreach( $users as $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if (empty( $user ) ) {
                continue;
            }
            if ( empty( $user->roles ) || !in_array( $role, $user->roles ) ) {
                $user->add_role( $role );
                $quantity++;
            }
        }
        
        if ( $quantity>0 ) {
            // translators: template %d is a quantity of users to whom role was added
            $message = sprintf( esc_html__('Role added to %d user(s).', 'user-role-editor'), $quantity );
            $answer = array('result'=>'success', 'message'=>$message );
        } else {
            $answer = array('result'=>'error', 'message'=>esc_html__('Error: Role not added', 'user-role-editor') );
        }
        
        return $answer;
    }
    // end of add_role()
    
    
    private static function is_try_remove_admin_from_himself( $user_id, $role) {

        $result = false;
        
        $current_user = wp_get_current_user();
        $wp_roles = wp_roles();
        $role_caps = array_keys( $wp_roles->roles[$role]['capabilities'] );
      	$is_current_user = ( (int) $user_id === (int) $current_user->ID );
        $role_can_promote = in_array('promote_users', $role_caps);
        $can_manage_network = is_multisite() && current_user_can( 'manage_network_users' );

        // If the removed role has the `promote_users` cap and user is removing it from himself
        if ( $is_current_user && $role_can_promote && !$can_manage_network ) {
            $result = true;

            // Loop through the current user's roles.
            foreach ($current_user->roles as $_role) {
                $_role_caps = array_keys( $wp_roles->roles[$_role]['capabilities'] );
                // If the current user has another role that can promote users, it's safe to remove the role.  Else, the current user should to keep this role.                
                if ( ($role!==$_role) && in_array( 'promote_users', $_role_caps ) ) {
                    $result = false;
                    break;
                }
            }

        }
        
        return $result;
    }
    // end of is_try_remove_admin_from_himself()
    
    
    public static function revoke_role( array $users ) {
        
        $answer = URE_Lib::check_nonce();
        if ( $answer!==TRUE ) {
            return $answer;
        }

        if ( !current_user_can('promote_users') ) {            
             $answer = array('result'=>'error', 'message'=>esc_html__('Not enough permissions', 'user-role-editor') );
             return $answer;
        }
        
        if ( !self::validate_users( $users ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Can not edit user or invalid data at the users list', 'user-role-editor') );
            return $answer;
        }
        
        $lib = URE_Lib::get_instance();
        $role = $lib->get_request_var('role', 'post', 'string');
        if ( !self::validate_roles( array($role=>$role) ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Invalid role', 'user-role-editor') );
            return $answer;
        }
                 
        $quantity = 0;
        foreach( $users as $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if ( empty( $user ) ) {
                continue;
            }
            if ( self::is_try_remove_admin_from_himself( $user_id, $role ) ) {
                continue;
            }
            if ( is_array($user->roles) && in_array( $role, $user->roles ) ) {
                $user->remove_role( $role );
                $quantity++;
            }
        }
        
        if ( $quantity>0 ) {
            // translators: template %d is a quantity of users to whom role was added
            $message = sprintf( esc_html__('Role revoked from %d user(s).', 'user-role-editor'), $quantity );
            $answer = array('result'=>'success', 'message'=>$message );
        } else {
            $answer = array('result'=>'error', 'message'=>esc_html__('Error: Role not revoked', 'user-role-editor') );
        }        
        
        return $answer;
    }
    // end of revoke_role()


    private static function validate_roles( $roles ) {

        if ( !is_array( $roles ) ) {
            return false;
        }
        
        $lib = URE_Lib::get_instance();
        $editable_roles = $lib->get_all_editable_roles();
        $valid_roles = array_keys( $editable_roles );
        foreach( $roles as $role ) {
            if ( !in_array( $role, $valid_roles ) ) {
                return false;
            }
        }
        
        return true;        
    }
    // end of validate_roles()
        
    
    private static function grant_primary_role_to_user($user_id, $role) {
                        
        $user = get_user_by('id', $user_id);
        if (empty($user)) {
            return;
        }
                     
        if ($role===self::NO_ROLE_FOR_THIS_SITE) {
            $role = '';
        }
        $old_roles = $user->roles;  // Save currently granted roles to restore from them the bbPress roles later if there are any...
        $user->set_role($role); 
        
        $lib = URE_Lib::get_instance();
        $bbpress = $lib->get('bbpress');
        if (empty($bbpress)) {
            return;
        }
        
        $bbp_roles = $bbpress->extract_bbp_roles($old_roles);
        if (count($bbp_roles)>0) {  //  restore bbPress roles
            foreach($bbp_roles as $role) {
                $user->add_role($role);
            }        
        }        
        
    }
    // end of grant_primary_role_to_user()
            
    
    private static function grant_other_roles_to_user($user_id, $roles) {
                        
        $user = get_user_by('id', $user_id);
        if (empty($user)) {
            return;
        }
        
        $roles_list = array_values( $user->roles );
        $primary_role = array_shift( $roles_list );    // Get the 1st element from the roles array
        $lib = URE_Lib::get_instance();
        $bbpress = $lib->get( 'bbpress' );
        if ( empty( $bbpress ) ) {
            $bbp_roles = array();
        } else {
            $bbp_roles = $bbpress->extract_bbp_roles( $user->roles );
        }
        $user->remove_all_caps();
        $roles2 = ure_array_merge( array( $primary_role ), $bbp_roles, $roles );
        foreach( $roles2 as $role ) {
            $user->add_role( $role );
        }
        
    }
    // end of grant_other_roles_to_user()
    
    
    /**
     * Decide if primary role should be granted or left as it is
     * 
     * @param string $primary_role
     * @return boolean
     */
    private static function is_select_primary_role($primary_role) {
        
        if (empty($primary_role)) {
            return false;   // Primary role was not selected by user, leave an older one
        }
        
        $lib = URE_Lib::get_instance();
        if ($lib->is_super_admin()) {
            $select_primary_role = true;
        } else {
            $select_primary_role = apply_filters('ure_users_select_primary_role', true);
        }
        
        return $select_primary_role;
    }
    // end of is_select_primary_role()
    
    
    public static function grant_roles( array $users, string $primary_role, array $other_roles ) {

        $answer = URE_Lib::check_nonce();
        if ( $answer!==TRUE ) {
            return $answer;
        }

        if ( !current_user_can('promote_users') ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Not enough permissions', 'user-role-editor'));
            return $answer;
        }
                
        if ( !self::validate_users( $users ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Can not edit user or invalid data at the users list', 'user-role-editor') );
            return $answer;
        }

// Primary role       
        if ( !empty( $primary_role ) && ( $primary_role!==self::NO_ROLE_FOR_THIS_SITE ) && 
            !self::validate_roles( [$primary_role=>$primary_role] ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Invalid primary role', 'user-role-editor'));
            return $answer;
        }
                
        if ( self::is_select_primary_role( $primary_role ) ) {            
            foreach ( $users as $user_id ) {                
                self::grant_primary_role_to_user( $user_id, $primary_role );
            }            
        }
        
// Other roles                
        if ( !empty( $other_roles ) && !self::validate_roles( $other_roles ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Invalid data at the other roles list', 'user-role-editor'));
            return $answer;
        }
        
        if ( !empty( $other_roles ) ) {
            foreach( $users as $user_id ) {
                self::grant_other_roles_to_user( $user_id, $other_roles ); 
            }                
        }
        $answer = array('result'=>'success', 'message'=>esc_html__('Roles were granted to users successfully', 'user-role-editor'));
        
        return $answer;
    }
    // end of grant_roles()
    
    
    private static function get_user_roles( $user_id ) {
        
        $answer = array('primary_role'=>'', 'other_roles'=>array() );
        if ( empty($user_id) ) {            
            return $answer;
        }
    
        $user = get_user_by('id', $user_id);
        if ( empty( $user ) ) {
            return $answer;
        }
        
        $other_roles = array_values($user->roles);
        $primary_role = array_shift($other_roles);
        
        $answer = array('primary_role'=>$primary_role, 'other_roles'=>$other_roles );
        
        return $answer;
    }
    // end of get_user_roles()
        
    
    private static function select_primary_role_html( $primary_role ) {
        
        $lib = URE_Lib::get_instance();
        $select_primary_role = apply_filters('ure_users_select_primary_role', true);
        if (!$select_primary_role && !$lib->is_super_admin()) {
            return;
        }
?>        
        <span style="font-weight: bold;">
            <?php esc_html_e('Primary Role: ', 'user-role-editor');?> 
        </span>
        <select name="primary_role" id="primary_role">
<?php            
        // print the full list of roles with the primary one selected.
        wp_dropdown_roles( $primary_role );
        echo '<option value="'. esc_attr( self::NO_ROLE_FOR_THIS_SITE ) .'">' . esc_html__('&mdash; No role for this site &mdash;', 'user-role-editor') . '</option>'. PHP_EOL;
?>        
        </select>
        <hr/>
<?php        
    }
    // end of select_primary_role_html()
            
    
    private static function select_other_roles_html( $other_roles ) {
        $lib = URE_Lib::get_instance();
?>        
        <div id="other_roles_container">
            <span style="font-weight: bold;">
<?php          
        esc_html_e('Other Roles: ', 'user-role-editor');
?>        
        </span><br>
<?php        
        // Is PolyLang plugin active?
        $use_pll = function_exists('pll__');    
        
        $show_admin_role = $lib->show_admin_role_allowed();        
        $roles = $lib->get_all_editable_roles(); 
        foreach ($roles as $role_id => $role) {
            if (!$show_admin_role && $role_id==='administrator') {
                continue;
            }
            $selected = ( in_array( $role_id, $other_roles ) ) ? 'checked="checked"': '';
            $role_name = $use_pll ? pll__( $role['name'] ) : $role['name'];
            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $selected is one of two hardcoded literals set above, not user input.
            echo '<label for="wp_role_' . esc_attr( $role_id ) . '"><input type="checkbox"	id="wp_role_' . esc_attr( $role_id ) .
                 '" name="ure_roles[]" value="' . esc_attr( $role_id ) . '" '. $selected .'/>&nbsp;' .
            esc_html( $role_name ) .' ('. esc_html( $role_id ).')</label><br />'. PHP_EOL;
            // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        }
?>
        </div>
<?php        
    }
    // end of select_other_roles_html()
    
       
    public static function get_dialog_html() {
        
        if ( !current_user_can('promote_users') ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Not enough permissions', 'user-role-editor'));
            return $answer;
        }
        
        $lib = URE_Lib::get_instance();
        $user_id = $lib->get_request_var('user_id', 'post', 'int');
        $data = self::get_user_roles( $user_id );
        ob_start();
        self::select_primary_role_html( $data['primary_role'] );
        self::select_other_roles_html( $data['other_roles'] );
        $output = ob_get_clean();
        $answer = array('result'=>'success', 'message'=>'Grant roles dialog HTML', 'html'=>$output );
        
        return $answer;
    }
    // end of get_dialog_html()

    
    private function get_roles_options_list() {
        
        ob_start();
        wp_dropdown_roles();
        $output = ob_get_clean();
        
        return $output;
    }
    // end of get_roles_options_list()

        
    public function show_roles_manage_html( $which ) {
                      
        if ( !current_user_can( 'promote_users' ) ) {
            return;
        }
        $button_number =  ( $which==='bottom') ? '_2': '';
        $roles_options_list = self::get_roles_options_list();
        ob_start();
?>
        &nbsp;&nbsp;
        <input type="button" name="ure_grant_roles<?php echo esc_attr( $button_number );?>" id="ure_grant_roles<?php echo esc_attr( $button_number );?>" class="button"
             value="<?php esc_html_e('Grant Roles', 'user-role-editor');?>">
        &nbsp;&nbsp;
        <label class="screen-reader-text" for="ure_add_role<?php echo esc_attr( $button_number );?>"><?php esc_html_e( 'Add role&hellip;', 'user-role-editor' ); ?></label>
        <select name="ure_add_role<?php echo esc_attr( $button_number );?>" id="ure_add_role<?php echo esc_attr( $button_number );?>" style="display: inline-block; float: none;">
            <option value=""><?php esc_html_e( 'Add role&hellip;', 'user-role-editor' ); ?></option>
            <?php echo $roles_options_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML <option> markup from WP core's wp_dropdown_roles(), already safely generated. ?>
        </select>
        <input type="button" name="ure_add_role_button<?php echo esc_attr( $button_number );?>" id="ure_add_role_button<?php echo esc_attr( $button_number );?>" class="button"
             value="<?php esc_html_e('Add', 'user-role-editor');?>">
        &nbsp;&nbsp;
        <label class="screen-reader-text" for="ure_revoke_role<?php echo esc_attr( $button_number );?>"><?php esc_html_e( 'Revoke role&hellip;', 'user-role-editor' ); ?></label>
        <select name="ure_revoke_role<?php echo esc_attr( $button_number );?>" id="ure_revoke_role<?php echo esc_attr( $button_number );?>" style="display: inline-block; float: none;">
            <option value=""><?php esc_html_e( 'Revoke role&hellip;', 'user-role-editor' ); ?></option>
            <?php echo $roles_options_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML <option> markup from WP core's wp_dropdown_roles(), already safely generated. ?>
        </select>
	<input type="button" name="ure_revoke_role_button<?php echo esc_attr( $button_number );?>" id="ure_revoke_role_button<?php echo esc_attr( $button_number );?>" class="button"
             value="<?php esc_html_e('Revoke', 'user-role-editor');?>">

        
<?php
        if ( $which==='bottom' ) {
?>
            <div id="ure_grant_roles_dialog" class="ure-dialog">
                <div id="ure_grant_roles_container"></div>
            </div>
<?php
         URE_View::output_task_status_div();
        }
        $output = ob_get_clean();
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buffered markup this method just built above, each dynamic piece already escaped at output time.
    }
    // end of show_grant_roles_html()
    
    
    public function load_js() {
        global $wp_version;

        $show_wp_change_role = apply_filters('ure_users_show_wp_change_role', true);
        
        wp_enqueue_script('jquery-ui-dialog', '', array('jquery-ui-core','jquery-ui-button', 'jquery'), $wp_version, true );
        wp_register_script('ure-users-grant-roles', plugins_url('/js/users-grant-roles.js', URE_Core::get_plugin_full_path() ), array(), URE_Core::PLUGIN_VERSION, true );
        wp_enqueue_script('ure-users-grant-roles');
        wp_localize_script('ure-users-grant-roles', 'ure_users_grant_roles_data', array(
            'wp_nonce' => wp_create_nonce('user-role-editor'),
            'dialog_title'=> esc_html__('Grant roles to selected users', 'user-role-editor'),
            'select_users_first' => esc_html__('Select users to which you wish to grant roles!', 'user-role-editor'),
            'select_roles_first' => esc_html__('Select role(s) which you wish to grant!', 'user-role-editor'),
            'select_role_first' => esc_html__('Select role first!', 'user-role-editor'),
            'select_users_to_add_role' => esc_html__('Select users to which you wish to add role!', 'user-role-editor'),
            'select_users_to_revoke_role' => esc_html__('Select users from which you wish revoke role!', 'user-role-editor'),
            'show_wp_change_role' => $show_wp_change_role ? 1: 0
        ));
    }
    // end of load_js()
    
}
// end of URE_Grant_Roles class
