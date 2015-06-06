<?php
/*
 * Plugin Name: TanSync
 * Version: 1.0
 * Plugin URI: http://www.hughlashbrooke.com/
 * Description: Aids the synchronization of the User Database
 * Author: Derwent
 * Author URI: http://laserphile.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: tansync
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define('TANSYNC_DEBUG', false);
define( 'TANSYNC_DOMAIN', 'tansync');

// Load plugin class files
require_once( 'includes/class-tansync.php' );
require_once( 'includes/class-tansync-settings.php' );
require_once( 'includes/class-tansync-ui-extensions.php' );
require_once( 'includes/class-tansync-synchronization.php' );

// Load plugin libraries
require_once( 'includes/lib/class-tansync-admin-api.php' );
require_once( 'includes/lib/class-tansync-post-type.php' );
require_once( 'includes/lib/class-tansync-taxonomy.php' );

/**
 * Returns the main instance of TanSync to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object TanSync
 */
function TanSync () {
	error_log("Tansync function");

    $instance = TanSync::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = TanSync_Settings::instance( $instance );
	}

    $instance->synchronization = TanSync_Synchronization::instance( $instance );
    // if ( 1 or is_null( $instance->synchronization ) ) {
    // }

    $instance->extra_fields = Tansync_UI_Extensions::instance( $instance );
    // if ( 1 or is_null( $instance->extra_fields ) ) {
    // }

	return $instance;
}

TanSync();
