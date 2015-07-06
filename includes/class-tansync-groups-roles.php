<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Deals with Groups and roles integration
*/
class Tansync_Groups_Roles
{
    public $parent = null;

    function __construct($parent)
    {
        // error_log("calling Tansync_Groups_Roles -> __construct");
        $this->parent = $parent;

        // add_action('plugins_loaded', array(&$this, 'test_refresh_user'));
        add_action('plugins_loaded', array(&$this, 'role_refresh'));
    }

    public function test_refresh_user(){
        $userid = 1;
        $this->refresh_user(1);
    }

    public function role_refresh(){
        $users = get_users();
        foreach ($users as $user) {
            $this->refresh_user($user->ID);
        }
    }

    public function maybe_role_refresh(){
        $settings = $this->parent->settings;
        $refresh_roles = $settings->get_option('refresh_roles');
        if( $refresh_roles ){
            $this->role_refresh();
            $settings->set_option('refresh_roles', false);
        }
    }


    public function get_group_role_mapping(){
        $settings = $this->parent->settings;
        $mapping_json = $settings->get_option('group_role_mapping');
        $mapping = json_decode($mapping_json, true);
        if($mapping){
            return $mapping;
        } else {
            return array();
        }
    }

    public function get_master_role_field(){
        $default = "act_role";
        $settings = $this->parent->settings;
        $role_field = $settings->get_option('role_field');
        if($role_field){
            return $role_field;
        } else {
            return $default;
        }
    }

    public function get_default_role(){
        //TODO: This
        $default = "RN";
        $settings = $this->parent->settings;
        $default_role = $settings->get_option('default_role');
        return "RN";
    }

    public function get_user_master_role($userid){
        $master_field = $this->get_master_role_field();
        $default_role = $this->get_default_role();
        $usermeta = get_user_meta($userid, $master_field, true);
        if($usermeta){
            return $usermeta;
        } else {
            return $default_role;
        }
    }

    public function refresh_user($userid, $master_role=null){
        if(!$master_role){
            $master_role = $this->get_user_master_role($userid);
        }
        if(WP_DEBUG and TANSYNC_DEBUG) error_log("user: $userid");
        $master_roles = explode('|', $master_role);
        if(WP_DEBUG and TANSYNC_DEBUG) error_log("master_roles: ".serialize($master_roles));
        $mapping = $this->get_group_role_mapping();
        $expected_roles = [];
        $expected_groups = [];
        foreach ($master_roles as $role) {
            if(isset($mapping[$role])){
                $parameters = $mapping[$master_role];
                if( isset($parameters['roles'])){
                    $expected_roles = array_merge($expected_roles, $parameters['roles']); 
                }
                if( isset($parameters['groups'])){
                    $expected_groups = array_merge($expected_groups, $parameters['groups']); 
                }
            }
        }
        if(WP_DEBUG and TANSYNC_DEBUG) error_log("expected_groups: ".serialize($expected_groups));
        if (class_exists("Groups_User")){ //if groups is installed
            $guser = new Groups_User($userid);
            $groups = $guser->groups;
            if(WP_DEBUG and TANSYNC_DEBUG) error_log("groups: ".serialize($groups) );
            // $groups = $guser->group_ids;
            if($groups) foreach ($groups as $group) {
                $gid = $group->id;
                $gname = $group->name;
                if(WP_DEBUG and TANSYNC_DEBUG) error_log(" -> group: $gname");
                if(in_array($gname, $expected_groups)){
                    if(WP_DEBUG and TANSYNC_DEBUG) error_log(" --> was expected");
                    $expected_groups = array_diff($expected_groups, array($gname));
                } else {
                    if(WP_DEBUG and TANSYNC_DEBUG) error_log(" --> was not expected");
                    Groups_User_Group::delete($userid, $gname);
                }
            }
            if(WP_DEBUG and TANSYNC_DEBUG) error_log("remaining groups: ".serialize($expected_groups));
            foreach ($expected_groups as $gname) {
                $group = Groups_Group::read_by_name($gname);
                if($group){
                    if(WP_DEBUG and TANSYNC_DEBUG) error_log(" -> group exists: ".serialize($gname));
                    $gid = $group->group_id;
                    if(WP_DEBUG and TANSYNC_DEBUG) error_log(" --> gid: ".serialize($gid));
                    Groups_User_Group::create(
                        array(
                            'group_id'  => $gid,
                            'user_id'   => $userid, 
                        )
                    );
                } else {
                    if(WP_DEBUG and TANSYNC_DEBUG) error_log(" -> group doesn't exist: ".serialize($gname));
                }
            }
        }
        if(WP_DEBUG and TANSYNC_DEBUG) error_log("expected_roles: ".serialize($expected_roles));
        $user = new WP_User($userid);
        $roles = $user->roles;
        // $expected_roles = ['administrator'];
        if(WP_DEBUG and TANSYNC_DEBUG) error_log("roles: ".serialize($roles) );
        foreach ($roles as $role) {
            if(WP_DEBUG and TANSYNC_DEBUG) error_log(" -> role: $role");
            if(in_array($role, $expected_roles)){
                if(WP_DEBUG and TANSYNC_DEBUG) error_log(" --> expected");
                $expected_roles = array_diff($expected_roles, array($role));
            } else {
                if(WP_DEBUG and TANSYNC_DEBUG) error_log(" --> unexpected");
                $user->remove_role($role);
            }
        }
        if(WP_DEBUG and TANSYNC_DEBUG) error_log("remaining roles: ".serialize($expected_roles));
        foreach ($expected_roles as $role) {
            if($role and $GLOBALS['wp_roles']->is_role( $role )){
                if(WP_DEBUG and TANSYNC_DEBUG) error_log(" -> is role: ".serialize($role));
                $user->add_role($role);
            } else {
                if(WP_DEBUG and TANSYNC_DEBUG) error_log(" -> not role: ".serialize($role));
            }
        }
    }
    
    /**
     * Main Tansync_Groups_Roles Instance
     *
     * Ensures only one instance of Tansync_Groups_Roles is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see TanSync()
     * @return Main Tansync_Groups_Roles instance
     */
    public static function instance ( $parent ) {
        return new self( $parent );
        // if ( is_null( self::$_instance ) ) {
        //     self::$_instance = new self( $parent );
        // }
        // return self::$_instance;
    } // End instance()

}





?>