<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Deals with Groups and roles integration
*/
class Tansync_Groups_Roles_Members
{
    public $parent = null;

    function __construct($parent)
    {
        // error_log("calling Tansync_Groups_Roles_Members -> __construct");
        $this->parent = $parent;

        // add_action('plugins_loaded', array(&$this, 'test_refresh_user'));
        // add_action('plugins_loaded', array(&$this, 'maybe_role_refresh'));

        add_action( 'profile_update', array(&$this, 'update_master_role'), 1, 1);
        add_action( 'profile_update', array(&$this, 'refresh_user'), 2, 1);
        add_action( 'edit_user_profile', array( &$this, 'edit_user_profile' ) );
        add_action( 'show_user_profile', array( &$this, 'show_user_profile' ) );

        add_filter( 'tansync_get_my_account_fields', array(&$this, 'add_master_field'));

        $this->master_role_field = $this->get_master_role_field();
    }

    // public function test_refresh_user(){
    //     $this->refresh_user(1);
    // }

    public function role_refresh(){
        $users = get_users();
        foreach ($users as $user) {
            error_log("refreshing user ".serialize($user->ID));
            $this->refresh_user($user->ID);
        }
        error_log("completed refresh");
    }

    // public function maybe_role_refresh(){
    //     $settings = $this->parent->settings;
    //     $refresh_roles = $settings->get_option('enable_role_refresh');
    //     error_log("refreshing roles: ".serialize($refresh_roles));
    //     if( $refresh_roles ){
    //         $return = $settings->set_option('refresh_roles', '');
    //         error_log("refresh_roles returned".serialize($return));
    //         $this->role_refresh();
    //     }
    // }


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
        // error_log("getting user master role ".$userid);
        $master_field = $this->get_master_role_field();
        // error_log(" -> master field: ".$master_field);
        $default_role = $this->get_default_role();
        // error_log(" -> default role: ".$default_role);
        $usermeta = get_user_meta($userid, $master_field, true);
        // error_log(" -> user meta value: ".serialize($usermeta));
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
        if(TANSYNC_DEBUG) error_log("user: $userid");
        $master_roles = explode('|', $master_role);
        if(TANSYNC_DEBUG) error_log("master_roles: ".serialize($master_roles));
        $mapping = $this->get_group_role_mapping();
        $expected_roles = [];
        $expected_groups = [];
        $expected_memberships = [];
        foreach ($master_roles as $role) {
            if(isset($mapping[$role])){
                $parameters = $mapping[$master_role];
                if( isset($parameters['roles'])){
                    $expected_roles = array_merge($expected_roles, $parameters['roles']); 
                }
                if( isset($parameters['groups'])){
                    $expected_groups = array_merge($expected_groups, $parameters['groups']); 
                }
                if( isset($parameters['memberships'])){
                    $expected_memberships = array_merge($expected_memberships, $parameters['memberships']);
                }
            }
        }

        if(TANSYNC_DEBUG) error_log("expected_groups: ".serialize($expected_groups));
        if (class_exists("Groups_User")){ //if groups is installed
            $guser = new Groups_User($userid);
            $groups = $guser->groups;
            if(TANSYNC_DEBUG) error_log("observed_groups: ".serialize($groups) );
            // $groups = $guser->group_ids;
            if($groups) foreach ($groups as $group) {
                $gid = $group->group_id;
                $gname = $group->name;
                if(TANSYNC_DEBUG) error_log(" -> group: $gname");
                if(in_array($gname, $expected_groups)){
                    if(TANSYNC_DEBUG) error_log(" --> was expected");
                    $expected_groups = array_diff($expected_groups, array($gname));
                } else {
                    if(TANSYNC_DEBUG) error_log(" --> was not expected");
                    if(TANSYNC_DEBUG) error_log(" ---> user_id: ".serialize($userid));
                    if(TANSYNC_DEBUG) error_log(" ---> group_id: ".serialize($gid));
                    $result = Groups_User_Group::delete($userid, $gid);
                    if(TANSYNC_DEBUG) error_log(" ---> result: ".serialize($result));
                }
            }
            if(TANSYNC_DEBUG) error_log("remaining groups: ".serialize($expected_groups));
            foreach ($expected_groups as $gname) {
                $group = Groups_Group::read_by_name($gname);
                if($group){
                    if(TANSYNC_DEBUG) error_log(" -> group exists: ".serialize($gname));
                    $gid = $group->group_id;
                    if(TANSYNC_DEBUG) error_log(" --> gid: ".serialize($gid));
                    Groups_User_Group::create(
                        array(
                            'group_id'  => $gid,
                            'user_id'   => $userid, 
                        )
                    );
                } else {
                    if(TANSYNC_DEBUG) error_log(" -> group doesn't exist: ".serialize($gname));
                }
            }
        }

        if(TANSYNC_DEBUG) error_log("expected_memberships: ".serialize($expected_memberships));
        if(class_exists("WC_Memberships")){
            $observed_memberships = wc_memberships_get_user_memberships($userid);
            if(TANSYNC_DEBUG) error_log("observed_memberships: ".serialize($observed_memberships));
            if($observed_memberships) foreach ($observed_memberships as $membership) {
                $membership_plan = $membership->get_plan();
                $membership_plan_slug = $membership_plan->get_slug();
                $membership_plan_name = $membership_plan->get_name();
                if(TANSYNC_DEBUG) error_log(" -> membership: ".$membership_plan_name);
                if(wc_memberships_is_user_active_member($userid, $membership_plan)){
                    if(TANSYNC_DEBUG) error_log(" --> User is active member");
                    if(in_array($membership_plan_slug, $expected_memberships)){
                        error_log(" --> was expected");
                        $expected_memberships = array_diff($expected_memberships, array($membership_plan_slug));
                        //TODO ENSURE STATUS IS ACTIVE
                    } else {
                        if(TANSYNC_DEBUG) error_log(" --> was not expected");
                        $result = $membership->cancel_membership( $note = __('Membership cancelled by TanSync', TANSYNC_DOMAIN) );
                        $membership->set_end_date(current_time( 'mysql', true ));
                        wp_delete_post($membership->get_id(), true);
                    }
                } else{
                    if(TANSYNC_DEBUG) error_log(" --> User is not active member");
                }
            }
            if(TANSYNC_DEBUG) error_log("remaining memberships:".serialize($expected_memberships));
            // foreach ($possible_membership_plans as $membership_plan) {
            //     $plan_slug = $membership_plan->get_slug();
            //     $plan_id = $membership_plan->get_id();
            //     $possible_plans[$plan_slug] = $plan_id;
            // }
            if($expected_memberships){
                $master_role_string = implode("|", $master_roles);
                $possible_membership_plans = wc_memberships_get_membership_plans();
                $possible_plans = array();
                foreach ($possible_membership_plans as $membership_plan) {
                    $possible_plans[$membership_plan->get_slug()] = $membership_plan;
                }
                foreach ($expected_memberships as $plan_slug) {
                    error_log("Plan: ".$plan_slug);
                    if( wc_memberships_is_user_member($userid, $plan_slug)){
                        if(TANSYNC_DEBUG) error_log(" -> user is a member ");
                        if( wc_memberships_is_user_member($userid, $plan_slug) ){
                            if(TANSYNC_DEBUG) error_log(" -> user is an active member ");
                            continue;
                        } else {
                            if(TANSYNC_DEBUG) error_log(" -> user is not an active member ");
                            $user_membership = wc_memberships_get_user_membership($userid, $plan_slug);
                            $user_membership->activate_membership( __('Activated by TanSync because of master roles',TANSYNC_DOMAIN).' '.$master_role_string );
                        }
                    } else {
                        if(TANSYNC_DEBUG) error_log(" -> user is not a member ");
                        if(in_array($plan_slug, array_keys($possible_plans))){
                            if(TANSYNC_DEBUG) error_log(" -> plan exists");
                            $plan = $possible_plans[$plan_slug];
                            $plan_id = $plan->get_id();

                            $data = apply_filters( 'wc_memberships_groups_import_membership_data', array(
                                'post_parent'    => $plan_id,
                                'post_author'    => $userid,
                                'post_type'      => 'wc_user_membership',
                                'post_status'    => 'wcm-active',
                                'comment_status' => 'open',
                            ), array(
                                'user_id'  => $userid,
                                // 'group_id' => $group_id,
                            ) );

                            $user_membership_id = wp_insert_post( $data );

                            if ( is_wp_error( $user_membership_id ) ) {
                                if(TANSYNC_DEBUG) error_log(" -> failed: error making post");
                                continue;
                            } else {
                                if(TANSYNC_DEBUG) error_log(" -> didn't fail making post");
                            } 

                            // update_post_meta( $user_membership_id, '_group_id', $group_id );
                            update_post_meta( $user_membership_id, '_start_date', current_time( 'mysql', true ) );

                            $end_date = '';

                            $user_membership = wc_memberships_get_user_membership( $user_membership_id );
                            $user_membership->set_end_date( $end_date );
                            $user_membership->add_note( __( 'Membership created by TanSync because of master roles', TANSYNC_DOMAIN) . ' ' . $master_role_string );
                        } else {
                            if(TANSYNC_DEBUG) error_log(" -> plan does not exist");
                        }
                    }
                }
            }
        }


        if(TANSYNC_DEBUG) error_log("expected_roles: ".serialize($expected_roles));
        $user = new WP_User($userid);
        $roles = $user->roles;
        // $expected_roles = ['administrator'];
        if(TANSYNC_DEBUG) error_log("roles: ".serialize($roles) );
        foreach ($roles as $role) {
            if(TANSYNC_DEBUG) error_log(" -> role: $role");
            if(in_array($role, $expected_roles)){
                if(TANSYNC_DEBUG) error_log(" --> expected");
                $expected_roles = array_diff($expected_roles, array($role));
            } else {
                if(TANSYNC_DEBUG) error_log(" --> unexpected");
                $user->remove_role($role);
            }
        }
        if(TANSYNC_DEBUG) error_log("remaining roles: ".serialize($expected_roles));
        foreach ($expected_roles as $role) {
            if($role and $GLOBALS['wp_roles']->is_role( $role )){
                if(TANSYNC_DEBUG) error_log(" -> is role: ".serialize($role));
                $user->add_role($role);
            } else {
                if(TANSYNC_DEBUG) error_log(" -> not role: ".serialize($role));
            }
        }
    }

    public function add_master_field($fields){
        $master_role_field = $this->get_master_role_field();
        if($master_role_field){
            $fields[] = $master_role_field;
        }
        return $fields;
    }
    
    public function output_master_role_admin($user) {
        if( is_admin()){
            error_log(" -> is_admin");
            $master_role_field = $this->get_master_role_field();
            $master_role = $this->get_user_master_role($user->ID);
            $output = '<h3>' . __( 'Master role',TANSYNC_DOMAIN ) . '</h3>';
            $output .= '<table class="form-table">';
            $output .= '<tbody>';
            $output .= '<tr class="'.$master_role_field.'-wrap">';
            $output .= '<th><label for="'.$master_role_field.'">'.__('Master Role', TANSYNC_DOMAIN).' </label></th>';
            $output .= '<td><input type="text" name="'.$master_role_field.'" id="'.$master_role_field.'" value="'.$master_role.'" class="regular-text ltr"></td>';
            $output .= '</tr>';
            $output .= '</tbody>';
            $output .= '<table>';
            echo $output;
        } else {
            error_log(" -> not is_admin");
        }        
    }

    public function edit_user_profile ($user) {
        // error_log("calling edit_user_profile");
        $this->output_master_role_admin($user);
    }

    public function show_user_profile ($user) {
        // error_log("calling show_user_profile");
        $this->output_master_role_admin($user);
    }

    public function update_master_role($user) {
        error_log("calling update_master_role");
        error_log(" -> master_role_field:".$this->master_role_field);
        $post_filtered = filter_input_array( INPUT_POST );

        if(isset($post_filtered[$this->master_role_field])){
            error_log("-> post is set ");
            $master_role = $post_filtered[$this->master_role_field];
            if(is_array($master_role)) $master_role = $master_role[0];
            if(is_string($master_role)){
                update_user_meta($user, $this->master_role_field, $master_role);
            }
        }
    }

    /**
     * Main Tansync_Groups_Roles_Members Instance
     *
     * Ensures only one instance of Tansync_Groups_Roles_Members is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see TanSync because of master role( );
     * @return Main Tansync_Groups_Roles_Members instance
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