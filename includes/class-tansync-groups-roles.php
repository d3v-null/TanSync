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
        error_log("calling Tansync_Synchronization -> __construct");

        $this->parent = $parent;

    }

    public function get_group_role_mapping(){
        $settings = $this->parent->settings;
        $mapping_json = $settings->get_option('group_role_mapping');
        $mapping = json_decode($mapping_json);
        if($mapping){
            return get_object_vars($mapping);
        } else {
            return array();
        }
    }

    public function get_user_master_role($userid){
        $usermeta = get_user_meta($userid)

    }

    public function refresh_user($userid, $master_role=null){
        if(!$master_role){
            $master_role = $this->get_user_master_role($userid);
        }
        $mapping = get_group_role_mapping();
        $parameters = $mapping[$master_role]
        if (class_exists("Groups_User")){ //if groups is installed
            $user = 
        }
    }
}





?>