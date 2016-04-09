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


    // // helper method for xmlrpc to update user field
    function update_user_field($user_id, $key, $newVal, $oldVal = null){
        // check $user_id is valid
        $user = get_user_by("ID", $user_id);
        if(!$user) throw new Exception("Error Processing Request: invalid user_id", 1);
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | user:".serialize($user_id));
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | key:".serialize($key));
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | newVal:".serialize($newVal));

        // check key is in sync_field_settings
        $sync_field_settings = $this->settings->get_sync_settings();
        if(TANSYNC_DEBUG) error_log("TanSync_XMLRPC->update_user_field | sync_field_settings:".serialize($sync_field_settings));
        if(isset($sync_field_settings[$key])){
            $key_settings = (array)$sync_field_settings[$key];
            // check if key can be used on ingress sync
            if(isset($key_settings['sync_ingress']) and $key_settings['sync_ingress']){
                // update user core or user meta depending on sync_field_settings
                if(isset($key_settings['core']) and $key_settings['core']){
                    wp_update_user( array($key => $newVal) );
                } else {
                    if($oldVal){
                        update_user_meta( $user_id, $key, $newVal, $oldVal );
                    } else {
                        update_user_meta( $user_id, $key, $newVal );
                    }
                }
            } else {
                throw new Exception("Error Processing Request: field not ingress", 1);
            }
        } else {
            throw new Exception("Error Processing Request: invalid key", 1);
        }
    }

    // function update_user_fields($user_id, $fields){

    // }

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
            $this->update_user_field($user_id, $user_key, $user_val, $old_val);
            return "Success";
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

    function new_xmlrpc_methods( $methods ) {
        if(TANSYNC_DEBUG) error_log("calling Tansync_XMLRPC -> new_xmlrpc_methods");
        $methods['tansync.test_xmlrpc'] = array($this, 'test_xmlrpc');
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