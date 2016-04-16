<?php

/**
* Helper class for creating XMLRPC functions to interface with sync
*/
class Tansync_XMLRPC
{
    
    /**
     * The single instance of TanSync_XMLRPC.
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null;

    function __construct()
    {
        if(TANSYNC_DEBUG) error_log("calling Tansync_XMLRPC -> __construct");
        $this->parent = TanSync::instance();
        $this->settings = $this->parent->settings;
        add_filter( 'xmlrpc_methods', array(&$this, 'new_xmlrpc_methods'), 0, 1);
    }


    function get_user_strict($user_id){
        $user = get_user_by("ID", $user_id);
        if(!$user) throw new Exception("Error Processing Request: invalid user_id", 1);
        return $user;
    }

    // // helper method for xmlrpc to update user field
    function update_user_field($user_id, $key, $newVal, $oldVal = null){
        // check $user_id is valid
        $user = $this->get_user_strict($user_id);
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | user:".serialize($user_id));
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | key:".serialize($key));
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | newVal:".serialize($newVal));

        // check key is in sync_field_settings
        $sync_field_settings = $this->settings->get_sync_settings();
        $errors = array();
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | sync_field_settings:".serialize($sync_field_settings));
        if($key and isset($sync_field_settings[$key])){
            $key_settings = (array)$sync_field_settings[$key];
            // check if key can be used on ingress sync
            if(isset($key_settings['sync_ingress']) and $key_settings['sync_ingress']){
                // update user core or user meta depending on sync_field_settings
                if(isset($key_settings['core']) and $key_settings['core']){
                    wp_update_user( array('id' => $user_id, $key => $newVal) );
                } else {
                    if($oldVal){
                        update_user_meta( $user_id, $key, $newVal, $oldVal );
                    } else {
                        update_user_meta( $user_id, $key, $newVal );
                    }
                }
            } else {
                $errors[$key] = "Field not Ingress";
            }
        } else {
            $errors[$key] = "Invalid Key";
        }
        return json_encode($errors);
    }

    function update_user_fields($user_id, $fields){
        $user = $this->get_user_strict($user_id);
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_fields | USERID:".serialize($user_id));

        $sync_field_settings = $this->settings->get_sync_settings();
        $core_updates = array();
        $meta_updates = array();
        $errors = array();
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_fields | SETTINGS:".serialize($sync_field_settings));
        foreach ($fields as $key => $newVal) {
            if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_fields | KEY: $key | newVal:".serialize($newVal) );
            if(isset($sync_field_settings[$key])){
                $key_settings = (array)$sync_field_settings[$key];
                if(isset($key_settings['sync_ingress']) and $key_settings['sync_ingress']){
                    if(isset($key_settings['core']) and $key_settings['core']){
                        // wp_update_user( array($key => $newVal) );
                        $core_updates[$key] = $newVal;
                    } else {
                        update_user_meta( $user_id, $key, $newVal );
                        $meta_updates[$key] = $newVal;
                    }
                } else {
                    $errors[$key] = "Field not Ingress";
                }
            } else {
                $errors[$key] = "Invalid key";
            }
        }
        if($core_updates){
            $core_updates['ID'] = $user_id;
            if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_fields | CORE_UPDATES:".serialize($core_updates));
            $return = wp_update_user($core_updates);
            if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_fields | CORE_RETURN:".serialize($return));
            if($return != $user_id){
                $errors['core'] = $return;
            }
        }
        return serialize($errors);

    }

// function mynamespace_getUserID( $args ) {
//     global $wp_xmlrpc_server;
//     $wp_xmlrpc_server->escape( $args );

//     $blog_id  = $args[0];
//     $username = $args[1];
//     $password = $args[2];

//     if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
//         return $wp_xmlrpc_server->error;

//     return $user->ID;    
// }

    function test_xmlrpc($args)
    {
        if(TANSYNC_DEBUG) error_log("calling Tansync_XMLRPC -> test_xmlrpc");
        global $wp_xmlrpc_server;
        $wp_xmlrpc_server->escape( $args );

        if(TANSYNC_DEBUG) error_log("Tansync_XMLRPC | args:".serialize($args));
        $blog_id  = $args[0];
        $username = $args[1];
        $password = $args[2];

        if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
            return $wp_xmlrpc_server->error;

        $user_id  = isset($args[3])?$args[3]:null;
        $user_key = isset($args[4])?$args[4]:null;
        $user_val = isset($args[5])?$args[5]:null;
        $old_val  = isset($args[6])?$args[6]:null;

        try {
            $return = $this->update_user_field($user_id, $user_key, $user_val, $old_val);
            return "Success: ".serialize($return);
        } catch(Exception $e){
            return "Failed up update: ".serialize($e);
        }


        // global $wp_xmlrpc_server;
        // $wp_xmlrpc_server->escape( $args );

        // $blog_id  = $args[0];
        // $username = $args[1];
        // $password = $args[2];

        // if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
        //     return $wp_xmlrpc_server->error;

        // return $user->ID;            
    }

    function xmlrpc_update_user_fields($args){
        if(TANSYNC_DEBUG) error_log("calling Tansync_XMLRPC -> xmlrpc_update_user_fields");
        global $wp_xmlrpc_server;
        $wp_xmlrpc_server->escape( $args );
        $blog_id  = $args[0];
        $username = $args[1];
        $password = $args[2];

        if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
            return $wp_xmlrpc_server->error;

        $user_id  = isset($args[3])?$args[3]:null;
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->xmlrpc_update_user_fields | user:".serialize($user_id));
        $fields_json_base64 = isset($args[4])?$args[4]:null;
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->xmlrpc_update_user_fields | fields_json_base64:".serialize($fields_json_base64));
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->xmlrpc_update_user_fields | fields_json:".serialize(base64_decode($fields_json_base64)));
        $fields = json_decode(base64_decode($fields_json_base64));

        try {
            $return = $this->update_user_fields($user_id, $fields);
            return "Success: ".serialize($return);
        } catch(Exception $e){
            return "Failed up update: ".serialize($e);
        }



    }

    function new_xmlrpc_methods( $methods ) {
        if(TANSYNC_DEBUG) error_log("calling Tansync_XMLRPC -> new_xmlrpc_methods");
        $methods['tansync.test_xmlrpc'] = array($this, 'test_xmlrpc');
        $methods['tansync.update_user_fields'] = array($this, 'xmlrpc_update_user_fields');
        return $methods;   
    }

    /**
     * Main TanSync_XMLRPC Instance
     *
     * Ensures only one instance of TanSync_XMLRPC is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Main TanSync_XMLRPC instance
     */
    public static function instance () {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( );
        }
        return self::$_instance;
    } // End instance ()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone () {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
    } // End __clone ()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup () {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
    } // End __wakeup ()    
}