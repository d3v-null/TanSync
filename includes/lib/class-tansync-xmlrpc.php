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
        $this->parent = TanSync::instance();
        $this->settings = $this->parent->settings;
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
            self::$_instance = new self( $file, $version );
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