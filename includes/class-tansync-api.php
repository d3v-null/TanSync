<?php

// /**
//  * WP_REST_Controller class.
//  */
// if ( ! class_exists( 'WP_REST_Controller' ) ) {
// 	require_once WP_PLUGIN_DIR . '/rest_api/lib/endpoints/class-wp-rest-controller.php' ;
// }
//
// /**
//  * WP_REST_Users_Controller class.
//  */
// if ( ! class_exists( 'WP_REST_Users_Controller' ) ) {
// 	require_once WP_PLUGIN_DIR . '/rest_api/lib/endpoints/class-wp-rest-users-controller.php';
// }
//
// class Tansync_REST_Users_Controller extends WP_REST_Users_Controller{
//   public function __construct() {
//     $this->namespace = 'wp/v2';
//     $this->rest_base = 'tansync_users';
//   }
//   /**
//    * Update the values of additional fields added to a data object.
//    *
//    * @param array  $object
//    * @param WP_REST_Request $request
//    */
//   protected function update_additional_fields_for_object( $object, $request ) {
//
//     $additional_fields = $this->get_additional_fields();
//
//     foreach ( $additional_fields as $field_name => $field_options ) {
//
//       if ( ! $field_options['update_callback'] ) {
//         continue;
//       }
//
//       // Don't run the update callbacks if the data wasn't passed in the request.
//       if ( ! isset( $request[ $field_name ] ) ) {
//         continue;
//       }
//
//       $response = call_user_func( $field_options['update_callback'], $request[ $field_name ], $object, $field_name, $request, $this->get_object_type() );
//
//       if(is_wp_error($response)){
//         return $response;
//       }
//     }
//   }
// }

/**
* Helper class for creating XMLRPC functions to interface with sync
*/
class Tansync_API
{

    /**
     * The single instance of Tansync_API.
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null;

    function __construct()
    {
        if(TANSYNC_DEBUG) error_log("calling Tansync_API -> __construct");
        $this->parent = TanSync::instance();
        $this->settings = $this->parent->settings;
        $this->synchronization = $this->parent->synchronization;
        // add_filter( 'xmlrpc_methods', array(&$this, 'new_xmlrpc_methods'), 0, 1);
        add_action( 'rest_api_init', array(&$this, 'register_rest_methods') );
        add_action( 'wp_json_server_before_serve', array(&$this, 'on_wp_json_server_before_serve') );
    }

    function wp_json_server_before_serve(){
        // if (defined('XMLRPC_REQUEST') and XMLRPC_REQUEST){
        //     $this->synchronization->cancel_queued_updates();
        // }
        if (defined('JSON_REQUEST') and JSON_REQUEST){
            $this->synchronization->cancel_queued_updates();
        }
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
        if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_field | user:".serialize($user_id));
        if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_field | key:".serialize($key));
        if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_field | newVal:".serialize($newVal));

        // check key is in sync_field_settings
        $sync_field_settings = $this->settings->get_sync_settings();
        $errors = array();
        // if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_field | sync_field_settings:".serialize($sync_field_settings));
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

    function construct_api_error($message, $code=null){
        if(is_null($code)){
            $code = 'general';
        }
        if(!is_string($code)){
            $code = strval($code);
        }
        $data = date('Y-m-d H:i:s');
        if(TANSYNC_DEBUG) error_log("Tansync_API->construct_api_error | code:".serialize($code));
        if(TANSYNC_DEBUG) error_log("Tansync_API->construct_api_error | message:".serialize( $message));
        $error = new WP_Error($message, $code, $data);
        // $error->add_data($data, $code);
        return $error;
    }

    function update_user_fields($user_id, $fields){
        $user = $this->get_user_strict($user_id);
        if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | USERID:".serialize($user_id));
        if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | FIELDS:".serialize($fields));

        $sync_field_settings = $this->settings->get_sync_settings();
        $core_updates = array();
        $meta_updates = array();
        $errors = array();
        // if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | SETTINGS:".serialize($sync_field_settings));
        foreach ($fields as $key => $newVal) {
            if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | KEY: $key | newVal:".serialize($newVal) );
            if(!is_string($key) ){
                return $this->construct_api_error(
                    "Key is not a string: ".serialize($key),
                    "key_inv"
                );
            }
            if(!isset($sync_field_settings[$key])){
                return $this->construct_api_error(
                    "Unrecognized key: ".serialize($key),
                    "key_unkn"
                );
            }
            $key_settings = (array)$sync_field_settings[$key];
            if(!isset($key_settings['sync_ingress']) or !$key_settings['sync_ingress']){
                return $this->construct_api_error(
                    "Key is not ingress: ".serialize($key),
                    "key_not_ingress"
                );
            }
            if(isset($key_settings['core']) and $key_settings['core']){
                // wp_update_user( array($key => $newVal) );
                $core_updates[$key] = $newVal;
            } else {
                // update_user_meta( $user_id, $key, $newVal );
                $meta_updates[$key] = $newVal;
            }
        }
        if($core_updates){
            $core_updates['ID'] = $user_id;
            if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | CORE_UPDATES:".serialize($core_updates));
            $response = wp_update_user($core_updates);
            if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | CORE_RETURN:".serialize($response));
            if(is_wp_error($response)){
                return $this->construct_api_error(
                    $response->get_error_message(),
                    $response->get_error_code()
                );
            }
            if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | CORE COMPLETE");
        }
        if($meta_updates){
            if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | META_UPDATES:".serialize($meta_updates));
            foreach ($meta_updates as $key => $value) {
                if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | META UPDATE KEY:".serialize($key));
                $response = update_user_meta( $user_id, $key, $value );
                if(is_wp_error($response)){
                    return $this->construct_api_error(
                        $response->get_error_message(),
                        $response->get_error_code()
                    );
                }
            }
            if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | META COMPLETE:".serialize($meta_updates));
        }
        if(TANSYNC_DEBUG) error_log("Tansync_API->update_user_fields | RETURNING:".serialize($errors));
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

    // function test_xmlrpc($args)
    // {
    //     if(TANSYNC_DEBUG) error_log("calling Tansync_API -> test_xmlrpc");
    //     global $wp_xmlrpc_server;
    //     $wp_xmlrpc_server->escape( $args );
    //
    //     if(TANSYNC_DEBUG) error_log("Tansync_API | args:".serialize($args));
    //     $blog_id  = $args[0];
    //     $username = $args[1];
    //     $password = $args[2];
    //
    //     if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
    //         return $wp_xmlrpc_server->error;
    //
    //     $user_id  = isset($args[3])?$args[3]:null;
    //     $user_key = isset($args[4])?$args[4]:null;
    //     $user_val = isset($args[5])?$args[5]:null;
    //     $old_val  = isset($args[6])?$args[6]:null;
    //
    //     try {
    //         $return = $this->update_user_field($user_id, $user_key, $user_val, $old_val);
    //         return "Success: ".serialize($return);
    //     } catch(Exception $e){
    //         return "Failed up update: ".serialize($e);
    //     }


        // global $wp_xmlrpc_server;
        // $wp_xmlrpc_server->escape( $args );

        // $blog_id  = $args[0];
        // $username = $args[1];
        // $password = $args[2];

        // if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
        //     return $wp_xmlrpc_server->error;

        // return $user->ID;
    // }

    // function xmlrpc_update_user_fields($args){
    //     if(TANSYNC_DEBUG) error_log("calling Tansync_API -> xmlrpc_update_user_fields");
    //     global $wp_xmlrpc_server;
    //     $wp_xmlrpc_server->escape( $args );
    //     $blog_id  = $args[0];
    //     $username = $args[1];
    //     $password = $args[2];
    //
    //     if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
    //         return $wp_xmlrpc_server->error;
    //
    //     $user_id  = isset($args[3])?$args[3]:null;
    //     if(TANSYNC_DEBUG) error_log("Tansync_API->xmlrpc_update_user_fields | user:".serialize($user_id));
    //     $fields_json_base64 = isset($args[4])?$args[4]:null;
    //     if(TANSYNC_DEBUG) error_log("Tansync_API->xmlrpc_update_user_fields | fields_json_base64:".serialize($fields_json_base64));
    //     if(TANSYNC_DEBUG) error_log("Tansync_API->xmlrpc_update_user_fields | fields_json:".serialize(base64_decode($fields_json_base64)));
    //
    //     $return_obj = array("error_status" => "pass");
    //     try {
    //         $fields_json = base64_decode($fields_json_base64);
    //         $fields = json_decode($fields_json);
    //         $return = $this->update_user_fields($user_id, $fields);
    //
    //         if(!empty($return)){
    //             $return_obj['error_status'] = "partial";
    //             $return_obj['errors'] = $return;
    //         }
    //         return json_encode($return_obj);
    //     } catch(Exception $e){
    //         if(TANSYNC_DEBUG) error_log("Tansync_API->xmlrpc_update_user_fields | failed to update:".serialize($e->getMessage()));
    //         $return_obj['error_status'] = "fail";
    //         $return_obj['errors'] = array($e->getMessage());
    //         return json_encode($return_obj);
    //     }
    // }
    //
    // function new_xmlrpc_methods( $methods ) {
    //     if(TANSYNC_DEBUG) error_log("calling Tansync_API -> new_xmlrpc_methods");
    //     $methods['tansync.test_xmlrpc'] = array($this, 'test_xmlrpc');
    //     $methods['tansync.update_user_fields'] = array($this, 'xmlrpc_update_user_fields');
    //     return $methods;
    // }

    function handle_json_update_user_fields( $value, $object, $field_name ) {
        if ( ! $value || ! is_string( $value ) ) {
            if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | no value");
            return;
        }

        $user_id = $object->ID;
        $fields_json_base64 = $value;
        if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | user:".serialize($user_id));
        if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | fields_json_base64:".serialize($fields_json_base64));

        $fields_json = base64_decode($fields_json_base64);
        if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | fields_json:".serialize($fields_json));
        $fields = json_decode($fields_json);
        $response = $this->update_user_fields($user_id, $fields);
        if(is_wp_error($response)){
          if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | handling:".serialize($response));
          $this->handle_json_request_custom_error($response);
        }
        // $response_obj = array();
        // if(!empty($response)){
        //   $response_obj['error_status'] = "partial";
        //   $response_obj['errors'] = $response;
        // }
        // return json_encode($response_obj);
        // try {
        // } catch(Exception $e) {
        //     if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | failed to update:".serialize($e->getMessage()));
        //     $return_obj['error_status'] = "fail";
        //     $return_obj['errors'] = array($e->getMessage());
        //     if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_update_user_fields | returning:".serialize($return_obj));
        //     return json_encode($return_obj);
        // }
    }

    function handle_json_get_user_last_error( $object, $field_name, $request ) {
        if(TANSYNC_DEBUG) error_log("Tansync_API->handle_json_get_user_last_error | handling:".serialize($object));
        $user_id = $object['id'];
        return get_user_meta($user_id, 'tansync_last_error');
    }

    function handle_json_request_custom_error( $error ){
        // I can't believe i actually have to hook on to this fucking filter:

        // /**
        //  * Filter user data returned from the REST API.
        //  *
        //  * @param WP_REST_Response $response  The response object.
        //  * @param object           $user      User object used to create response.
        //  * @param WP_REST_Request  $request   Request object.
        //  */
        // return apply_filters( 'rest_prepare_user', $response, $user, $request );

        add_filter(
            'rest_prepare_user',
            function($response, $user, $request) use ($error){
                Tansync_API::poison_rest_prepare_user($response, $user, $request, $error);
            },
            10,
            3
        );
    }

    static function poison_rest_prepare_user($response, $user, $request, $error){
      if(TANSYNC_DEBUG) error_log("calling Tansync_API -> poison_rest_prepare_user");
      // if(TANSYNC_DEBUG) error_log("Tansync_API -> poison_rest_prepare_user: request".serialize($request));
      // if(TANSYNC_DEBUG) error_log("Tansync_API -> poison_rest_prepare_user: request".serialize($request->get_route()));
      // if(TANSYNC_DEBUG) error_log("Tansync_API -> poison_rest_prepare_user: request".serialize($request['tansync_updated_fields']));
      if(TANSYNC_DEBUG) error_log("Tansync_API -> poison_rest_prepare_user: error".serialize($error));
      // $new_response = new WP_REST_Response( $data );
      // return Tansync_API::errorToResponse($error);
      $user_id = $user->ID;
      update_user_meta($user_id, 'tansync_last_error', serialize($error));
      return $error;
      // return $response;
    }

    static function errorToResponse($error){
      $error_data = $error->get_error_data();
      if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
        $status = $error_data['status'];
      } else {
        $status = 500;
      }

      $data = array();
      foreach ( (array) $error->errors as $code => $messages ) {
        foreach ( (array) $messages as $message ) {
          $data[] = array( 'code' => $code, 'message' => $message );
        }
      }
      $response = new WP_REST_Response( $data, $status );
      if(TANSYNC_DEBUG) error_log("Tansync_API -> errorToResponse: ".serialize($response->get_data()));
      return $response;
    }

    function register_rest_methods() {
        if(TANSYNC_DEBUG) error_log("calling Tansync_API -> register_rest_methods");

        $this->synchronization->cancel_queued_updates();

        // $controller = new Tansync_REST_Users_Controller;
        // $controller->register_routes();

        register_rest_field(
            'user',
            'tansync_updated_fields',
            array(
                'get_callback'     => null,
                'update_callback'  => array($this, 'handle_json_update_user_fields'),
                'schema'           => null,
            )
        );

        register_rest_field(
            'user',
            'tansync_last_error',
            array(
                'get_callback'     => array($this, 'handle_json_get_user_last_error'),
                'update_callback'  => null,
                'schema'           => null,
            )
        );


    }

    /**
     * Main Tansync_API Instance
     *
     * Ensures only one instance of Tansync_API is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Main Tansync_API instance
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
