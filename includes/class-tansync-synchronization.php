<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Deals with the extra fields
*/
class Tansync_Synchronization{
    
    /**
     * The single instance of Tansync_Synchronization.
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null;

    /**
     * Parent class object
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $parent = null;

    /**
     * Settings class object
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $settings = null;

    public $update_table_suffix = 'tansync_updates';

    function __construct($parent)
    {
        $this->parent = $parent;
        $this->settings = $parent->settings;

        // do_action( 'profile_update', $user_id, $old_user_data );
        add_action( 'profile_update', array(&$this, 'handle_profile_update') );
        // do_action( 'user_register', $user_id );
        add_action( 'user_register', array(&$this, 'handle_user_register') );
    }

    public function install_tables(){
        error_log("calling Tansync_Synchronization -> install_tables");
        global $wpdb;
        $update_table_name = $wpdb->prefix . $this->update_table_suffix ;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $update_table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                data text NOT NULL,
                UNIQUE KEY id (id)
            ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function validate_install(){
        error_log("calling Tansync_Synchronization -> validate_install");
        global $wpdb;
        $update_table_name = $wpdb->prefix . $this->update_table_suffix ;
        $sql = $wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s",$update_table_name);
        $table = $wpdb->get_row($sql, ARRAY_A);
        error_log("table: ".serialize($table));
        if(!is_array($table) or empty($table)){
            //table does not exist, create it
            $this->install_tables();
        } else{
            // TODO: IF TABLE IS INVALID
            // {
                //delete table and install it again

                //TODO: DELETE SQL

                //$this->install_tables;
            // }
        }

    }

    public function get_ingress_updates(){
        //TODO: 
        // SELECT * FROM 
        // global $wpdb
        // $sql = $wpdb->prepare( 'SELECT sync_id, ')
        // $results = wpdb->get_results( 'SELECT * FROM ')
    }

    public function get_egress_updates(){
        //TODO: 
    }

    public function handle_profile_update($user_id, $old_user_data=null){
        error_log("USER PROFILE UPDATE".serialize($user_id));
        $this->user_sync($user_id);
        
    }

    public function handle_user_register($user_id){
        error_log("USER REGISTRATION: ".serialize($user_id));
        $this->user_sync($user_id);
    }

    public function user_sync($user_id){
        error_log("TRIGGER SYNC: ".serialize($user_id));
        // if(WP_DEBUG and TANSYNC_DEBUG) error_log("syncing user: ".$user_id);
        //TODO: THIS
        // checks for pending ingress updates

        // puts the changes in a csv file
    }

    public function process_pending_egress(){

    }

    public function process_pending_ingress(){
        // load pending ingress from file

        foreach( $pending_ingress as $userdata ){

        }
    }

    public function user_update($user_id, $data){
        update_user_meta($user_id, 'last_update', time());
    }

    /**
     * Main Tansync_Synchronization Instance
     *
     * Ensures only one instance of Tansync_Synchronization is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see TanSync()
     * @return Main Tansync_Synchronization instance
     */
    public static function instance ( $parent ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $parent );
        }
        return self::$_instance;
    } // End instance()

}