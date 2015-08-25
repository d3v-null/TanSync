<?php

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TANSYNC_EGRESS', 0);
define( 'TANSYNC_INGRESS', 1);
define( 'TANSYNC_VIRGIN', 0);
define( 'TANSYNC_PENDING', 1);
define( 'TANSYNC_COMPLETE', 2);


function tansync_set_html_content_type() {
    return 'text/html';
}

/**
* Deals with the extra fields
*/
class Tansync_Synchronization{
    
    // *
    //  * The single instance of Tansync_Synchronization.
    //  * @var     object
    //  * @access  private
    //  * @since   1.0.0
     
    // private static $_instance = null;

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

    private $tabledata = array(
        'columns' => array(
            'id'=>'mediumint(9) NOT NULL AUTO_INCREMENT',
            'user_id'=>'int NOT NULL',
            'direction' => 'int NOT NULL',
            'status' => 'int NOT NULL',
            'time' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL',
            'changed' => 'text NOT NULL',
            'data' => 'text NOT NULL',
        ),
        'unique key' => 'UNIQUE KEY id (id)'
    );

    function __construct($parent)
    {
        // if(TANSYNC_DEBUG) error_log("calling Tansync_Synchronization -> __construct");

        $this->parent = $parent;
        $this->settings = $parent->settings;

        add_action( 'admin_init', array(&$this, 'store_initial_userdata'), 999);

        add_action( 'profile_update', array(&$this, 'handle_profile_update'), 1, 2);

        add_action( 'user_register', array(&$this, 'handle_user_register'), 1, 1 );

        add_action( 'plugins_loaded', array(&$this, 'update_report_email'), 1 );
    }


    public function install_tables(){
        if(TANSYNC_DEBUG) error_log("calling Tansync_Synchronization -> install_tables");
        global $wpdb;
        $update_table_name = $wpdb->prefix . $this->update_table_suffix ;
        $charset_collate = $wpdb->get_charset_collate();


        // $sql = "CREATE TABLE IF NOT EXISTS $update_table_name (
        //         id mediumint(9) NOT NULL AUTO_INCREMENT,
        //         user_id int NOT NULL,
        //         time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        //         data text NOT NULL,
        //         UNIQUE KEY id (id)
        //     ) $charset_collate;";

        // error_log("SQL 1: ".serialize($sql));

        $sql = "CREATE TABLE $update_table_name (";
        foreach ($this->tabledata['columns'] as $col => $params) {
            $sql .= $col . ' ' . $params . ', ';
        }
        $sql .= $this->tabledata['unique key'];
        $sql .= ") $charset_collate;";

        if(TANSYNC_DEBUG) error_log("SQL 2: ".serialize($sql));

        $wpdb->query($sql);
    }

    public function uninstall_tables(){
        if(TANSYNC_DEBUG) error_log("calling Tansync_Synchronization -> uninstall_tables");
        global $wpdb;
        $update_table_name = $wpdb->prefix . $this->update_table_suffix ;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "DROP TABLE $update_table_name;";

        $wpdb->query($sql); 
    }

    public function validate_install(){
        if(TANSYNC_DEBUG) error_log("calling Tansync_Synchronization -> validate_install");
        global $wpdb;
        $update_table_name = $wpdb->prefix . $this->update_table_suffix ;
        $sql = $wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s",$update_table_name);
        $table = $wpdb->get_row($sql, ARRAY_A);
        // error_log("table: ".serialize($table));
        if(!is_array($table) or empty($table)){
            //table does not exist, create it
            $this->install_tables();
        } else{
            // TODO: IF TABLE IS INVALID
            $sql = $wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s",$update_table_name);
            $columns = $wpdb->get_results($sql, ARRAY_A);
            // error_log("columns: ".serialize($columns));
            foreach ($this->tabledata['columns'] as $colname => $params) {
                // error_log("looking for ".serialize($colname));
                $column_present = false;
                foreach ($columns as $column) {
                    // error_log("column: ".serialize($column));
                    if (isset($column['COLUMN_NAME'])){
                        if( $column['COLUMN_NAME'] == $colname){
                            $column_present = true;
                        }
                    }
                }
                if(!$column_present){
                    if(TANSYNC_DEBUG) error_log("problem with tansync database, reinstalling");
                    $this->uninstall_tables();
                    $this->install_tables();
                    break;
                }
            }
        }

    }

    public function get_userdata($userid){
        $usermeta = get_user_meta( $userid );
        if($usermeta){
            // error_log("usermeta ".serialize($usermeta));           
        } else {
            $usermeta = array();
        }
        $userdata = get_userdata( $userid );
        if($userdata){
            if ( $userdata instanceof stdClass ) {
                $userdata = get_object_vars( $userdata );
            } elseif ( $userdata instanceof WP_User ) {
                $userdata = $userdata->to_array();
            }
            $userdata = array_merge( $usermeta, $userdata) ;
        } else {
            $userdata = $usermeta;
        }
        // if(TANSYNC_DEBUG) error_log("userdata ".serialize($userdata));
        return $userdata;
    }


    public function store_initial_userdata(){
        global $user_id;
        if(isset($user_id) and $user_id){
            if(TANSYNC_DEBUG) error_log("user_id set and true");
            $_user_id = $user_id;
        } else {
            if(TANSYNC_DEBUG) error_log("user_id not set or false");
            if(function_exists('wp_reset_vars')){
                wp_reset_vars( array( 'user_id' ) ); }
            else {
                if(TANSYNC_DEBUG) error_log("wp_reset_vars DNE");
            }
            if(isset($user_id) and $user_id){
                if(TANSYNC_DEBUG) error_log("user_id set and true");
                $_user_id = $user_id;
            } else {
                $_user_id = get_current_user_id();
            }
        } 
        if($_user_id){
            if(TANSYNC_DEBUG) error_log("user id found:".serialize($_user_id));
            $this->initial_userdata = $this->get_userdata($_user_id);
        } else {
            if(TANSYNC_DEBUG) error_log("user id not found");
            $this->initial_userdata = array();
        }
    }

    // public function get_ingress_updates(){
    //     //TODO: 
    //     // SELECT * FROM 
    //     // global $wpdb
    //     // $sql = $wpdb->prepare( 'SELECT sync_id, ')
    //     // $results = wpdb->get_results( 'SELECT * FROM ')
    // }

    // public function get_egress_updates(){
    //     //TODO: 
    // }


    public function handle_profile_update($userid, $old_userdata=null){
        if(TANSYNC_DEBUG) error_log("USER PROFILE UPDATE".serialize($userid));
        $this->queue_update($userid);
    }

    public function handle_user_register($userid){
        if(TANSYNC_DEBUG) error_log("USER REGISTRATION: ".serialize($userid));
        $this->queue_update($userid);
    }

    public function get_synced_fields(){
        $sync_settings_json = $this->settings->get_option("sync_field_settings");
        $sync_settings = json_decode($sync_settings_json);
        $synced_fields = array();
        if($sync_settings){
            foreach (get_object_vars($sync_settings) as $key => $value) {
                if( isset($value->sync_egress) and $value->sync_egress ){
                    $label = $key;
                    if(isset($value->label)) {$label = $value->label; }
                    if(isset($value->sync_label)) {$label = $value->sync_label;}
                    $synced_fields[$key] = $label;
                }
            }
        } else {
            if(TANSYNC_DEBUG) error_log("TANSYNC: sync settings configured incorrectly");
        }

        return $synced_fields;
    }

    public function queue_update($userid){
        if(TANSYNC_DEBUG) error_log("TRIGGER SYNC: ".serialize($userid));
        // checks for pending ingress updates
        $this->modified_user = $userid;

        add_action("shutdown", function() use ($userid){
            $userdata = $this->get_userdata($userid);
            if(isset($this->initial_userdata)){
                $userdata_old = $this->initial_userdata;
            } else {
                $userdata_old = array();
            }
            
            // filter only sync'd fields
            $syncdata = array();
            $changed = array();
            $syncfields = $this->get_synced_fields();
            // if(TANSYNC_DEBUG) error_log("userdata: ");
            foreach ($syncfields as $key => $label) {
                if (isset($userdata[$key])){
                    $syncdata[$label] = $userdata[$key];
                    // if(TANSYNC_DEBUG) error_log(" => $key|$label NEW: ".serialize($userdata[$key]));
                    if(isset($userdata_old[$key])){
                        // if(TANSYNC_DEBUG) error_log(" => $key|$label OLD: ".serialize($userdata_old[$key]));
                        if($userdata[$key] == $userdata_old[$key]){
                            // if(TANSYNC_DEBUG) error_log("value $key has not changed");
                            continue;
                        }
                    }
                    $changed[$label] = $userdata[$key];
                }
            }

            $userstring = json_encode($syncdata);
            $changestring = json_encode($changed);

            global $wpdb;

            $update_table_name = $wpdb->prefix . $this->update_table_suffix ;

            $wpdb->insert(
                $update_table_name, 
                array( 
                    'user_id' => $userid,
                    'direction' => TANSYNC_EGRESS,
                    'status' => TANSYNC_VIRGIN,
                    'changed' => $changestring,
                    'data' => $userstring
                ),
                array(
                    'user_id' => '%d',
                    'direction' => '%d',
                    'status' => '%d',
                    'changed' => '%s',
                    'data' => '%s'
                )
            );        
        });
    }

    public function get_update_interval(){
        $interval = $this->settings->get_option('sync_email_interval');
        if(!$interval and isset($this->settings->settings['Sync']['fields']['sync_email_interval']['default'])){
            $interval = $this->settings->settings['Sync']['fields']['sync_email_interval']['default'];
        }
        // error_log("interval is ".$interval);
        return $interval;        
    }

    public function get_unsyncd_updates($time_from = null, $time_to = null){
        $_prodecure = "TANSYNC_SYNCHRONIZATION_GET: ";
        if(TANSYNC_DEBUG) error_log($_prodecure."start");

        if(!$time_to){
            $time_to = time();
        }
        if(!$time_from){
            $time_from = 0;
        }
        if( $time_from >= $time_to ){
            return array();
        }

        global $wpdb;

        $update_table_name = $wpdb->prefix . $this->update_table_suffix ;
        $sql = $wpdb->prepare(
            "
                SELECT ud.user_id, ud.data, ud.changed
                FROM 
                    $update_table_name ud
                    INNER JOIN(
                        SELECT um.ID, MAX(um.time)
                        FROM $update_table_name  um
                        WHERE 
                            UNIX_TIMESTAMP(um.time) BETWEEN %s AND %s
                            AND (
                                um.status = %d
                                OR um.status = %d
                            )
                        GROUP BY um.user_id
                    ) um
                    ON um.id = ud.id
            ",
            $time_from,
            $time_to,
            TANSYNC_VIRGIN,
            TANSYNC_PENDING
        );
        // error_log("SQL: ".serialize($sql));
        $updates = $wpdb->get_results(
            $sql,
            ARRAY_A
        );

        if(TANSYNC_DEBUG) error_log($_prodecure."returning ".serialize($updates));

        return $updates;
    }

    // public function cron_add_tansync_update_interval( $schedules ){
    //     // error_log("calling Tansync_Synchronization -> cron_add_tansync_update_interval");
        
    //     $interval = $this->get_update_interval();

    //     $schedules['tansync_update'] = array(
    //         'interval' => $interval,
    //         'display' => __( 'Tansync Update Interval', TANSYNC_DOMAIN)
    //     );

    //     return $schedules;
    // }

    public function update_report_email(){
        $_prodecure = "TANSYNC_SYNCHRONIZATION_EMAIL: ";
        if(TANSYNC_DEBUG) error_log($_prodecure."start");

        //FUCK Cron, I'm doing this manually

        $last_run = $this->settings->get_option('update_report_last_run');
        if($last_run){
            if(TANSYNC_DEBUG) error_log($_prodecure."last_run set as".serialize($last_run));
        } else {
            if(TANSYNC_DEBUG) error_log($_prodecure."last_run not set");
            $last_run = 0;
        }

        $interval = $this->get_update_interval();
        if($interval){
            if(TANSYNC_DEBUG) error_log($_prodecure."interval set as ".serialize($interval));
        } else {
            if(TANSYNC_DEBUG) error_log($_prodecure."interval not set");
        }

        $now = time();
        if($now){
            if(TANSYNC_DEBUG) error_log($_prodecure."now set as ".serialize($now));
        } else {
            if(TANSYNC_DEBUG) error_log($_prodecure."now not set");
        }
        if($last_run + $interval < $now){
            if(TANSYNC_DEBUG) error_log($_prodecure."time for an email");
            $time_from  = $last_run;
            $time_to    = $now;
            $updates = $this->get_unsyncd_updates($time_from, $time_to);
            if($updates and !empty($updates)){
                if(TANSYNC_DEBUG) error_log($_prodecure."updates to report");

                $email_recipient = $this->settings->get_option('sync_email_to');
                if($email_recipient){
                    if(TANSYNC_DEBUG) error_log($_prodecure."email_recipient set as ".serialize($email_recipient));
                } else {
                    if(TANSYNC_DEBUG) error_log($_prodecure."email_recipient not set");
                }
                if($email_recipient){
                    $email_message = "UPDATES: \n";
                    $email_message .= "<table>";
                    $email_message .= "<tr><td>UserID</td><td>Changes</td><td>Data</td></tr>";
                    $no_updates = true;
                    foreach($updates as $update){
                        if(TANSYNC_DEBUG) error_log($_prodecure."analysing update ".serialize($update));

                        if ($update['changed'] == '[]'){
                            if(TANSYNC_DEBUG) error_log($_prodecure."no change detected");
                            continue;
                        } else {
                            if(TANSYNC_DEBUG) error_log($_prodecure."change detected");
                            $no_updates = false;
                        }
                        $email_message .= "<tr>";
                        $email_message .= "<td>".$update['user_id']."</td>";
                        $email_message .= "<td>".$update['changed']."</td>";
                        $email_message .= "<td>".$update['data']."</td>";
                        $email_message .= "</tr>";
                    }

                    if($no_updates) {
                        if(TANSYNC_DEBUG) error_log($_prodecure."there were no updates");
                        return;
                    }

                    $email_message .= "</table>";
                    // $email_message .= "<p>";
                    // $email_message .= "<strong>synced fields: </strong>";
                    // $email_message .= serialize($this->get_synced_fields());
                    // $email_message .= "</p>";
                    $email_message .= "</html>";

                    if(TANSYNC_DEBUG) error_log($_prodecure."update report email firing: ");
                    if(TANSYNC_DEBUG) error_log($_prodecure." -> recipient: ". $email_recipient);
                    if(TANSYNC_DEBUG) error_log($_prodecure." -> message: ". $email_message);

                    add_filter( 'wp_mail_content_type', 'tansync_set_html_content_type' );

                    wp_mail( $email_recipient, "tansync user updates", $email_message);

                    remove_filter( 'wp_mail_content_type', 'tansync_set_html_content_type' );

                }
                $this->settings->set_option('update_report_last_run', $now);
            }
        } else {
            if(TANSYNC_DEBUG) error_log($_prodecure."not time for an email");
        }
        if(TANSYNC_DEBUG) error_log($_prodecure."finish");
    }

    // public function schedule_email_cron(){

    //     error_log("calling Tansync_Synchronization -> schedule_email_cron");

    //     $enabled = $this->settings->get_option('sync_email_enable');
    //     $next = wp_next_scheduled('tansync_cron_report_update');
    //     if(!$enabled){
    //         if($next){
    //             wp_clear_scheduled_hook( 'tansync_cron_report_update' );
    //         }
    //         return;
    //     } else {
    //         $interval = $this->get_update_interval();
    //         error_log("schedule_email_cron is rescheduling");

    //         wp_clear_scheduled_hook( 'tansync_cron_report_update' );
    //         global $tansync_cron_report_runs;
    //         $tansync_cron_report_runs = 0;

    //         $now = time();

    //         error_log("now: ".serialize($now));

    //         wp_schedule_event(
    //             $now + $interval, 
    //             'tansync_update', 
    //             'tansync_cron_report_update', 
    //             array(
    //                 $now
    //             )
    //         );
    //     }
    // }

    // public function process_pending_egress(){

    // }

    // public function process_pending_ingress(){
    //     // load pending ingress from file

    //     foreach( $pending_ingress as $userdata ){

    //     }
    // }

    public function user_update($userid, $data){
        update_user_meta($userid, 'last_update', time());
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
        return new self( $parent );
        // if ( is_null( self::$_instance ) ) {
        //     self::$_instance = new self( $parent );
        // }
        // return self::$_instance;
    } // End instance()

}