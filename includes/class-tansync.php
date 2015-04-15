<?php

if ( ! defined( 'ABSPATH' ) ) exit;

define('TANSYNC_DEBUG', true);

class TanSync {

	/**
	 * The single instance of TanSync.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token = 'tansync';

		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions
		if ( is_admin() ) {
			$this->admin = new TanSync_Admin_API();
		}

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Add Actions and Filters
		$this->add_actions_filters();


	} // End __construct ()

	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '' ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new TanSync_Post_Type( $post_type, $plural, $single, $description );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new TanSync_Taxonomy( $taxonomy, $plural, $single, $post_types );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'tansync', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'tansync';

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main TanSync Instance
	 *
	 * Ensures only one instance of TanSync is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see TanSync()
	 * @return Main TanSync instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
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

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

// DERWENT

	public function modify_contact_methods($profile_fields) {

		$extra_fields = ($this->settings->get_option('extra_user_profile_fields'));
		if(WP_DEBUG) {
			error_log("Extra Profile Fields: ".serialize($extra_fields));
		}
		if($extra_fields){
			foreach (json_decode($extra_fields) as $field_slug => $field_label) {
				$label = $field_label?$field_label:$field_slug;
				if(WP_DEBUG) {
					error_log("$field_slug: $label");
				}
				$profile_fields[$field_slug] = $field_label;
			}
		}

		$remove_fields = ($this->settings->get_option('remove_user_profile_fields'));
		if(WP_DEBUG) {
			error_log("Remove Profile Fields: ".serialize($remove_fields));
		}
		if($remove_fields){
			foreach (json_decode($remove_fields) as $field_slug) {
				unset($profile_fields[$field_slug]);
			}
		}

		return $profile_fields;
	}

	public function add_my_account_extra_fields(){

		$extra_fields = ($this->settings->get_option('extra_user_profile_fields'));
		$user_id = get_current_user_id();
		
		if ($extra_fields and $user_id){
			// TODO: make this modifiable in settings
			echo "<h2>My Profile</h2>";
			// TODO: Make this a table
			echo "<p class='user-profile-fields'>";
			foreach (json_decode($extra_fields) as $field_slug => $field_label) {
				$label = $field_label?$field_label:$field_slug;
				$value = get_user_meta($user_id, $field_slug, true);
				echo "<strong class='profile-label'>".$label.":</strong> ";
				echo "<span class='profile-value'>".$value."</span>";
				echo "<br/>";
			}
			echo "</p>";
		}
	}

	private function get_current_user_roles(){
		global $Lasercommerce_Roles_Override;
        if(isset($Lasercommerce_Roles_Override) and is_array($Lasercommerce_Roles_Override)){
            $roles = $Lasercommerce_Roles_Override;
        } else {
            $current_user = wp_get_current_user();
            $roles = $current_user->roles;
        }
        return $roles;
	}

	private function evaluate_condition($type, $parameters){
		//TODO: actually evaluate conditions
		if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> evaluating condition: $type | ".serialize($parameters) );
		switch ($type) {
			case 'allowed_roles':
				if($parameters){
					if(gettype($parameters) == 'string') {
						$allowed_roles = array($parameters);
					} else {
						$allowed_roles = $parameters;
					}
					assert(is_array($allowed_roles));
					$current_roles = $this->get_current_user_roles();
					if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> current roles: ".serialize($current_roles));
					$intersect = array_intersect($current_roles, $allowed_roles);
					if(sizeof($intersect) == 0){
						if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> condition failed");
						return false;
					} else {
						if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> condition passed");
						return true;
					}
				}
				break;
			default:
				# code...
				break;
		}
		if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> condition passed by default");
		return true;
	}

	private function process_targeted_content_conditions($specs){
		if(WP_DEBUG and TANSYNC_DEBUG) error_log("\nProcessing Targeted Content Conditions | specs: ".serialize($specs));
		// assert($specs and is_array($specs));
		$slugs = array();
		foreach ($specs as $spec) {
			$slug = isset($spec->slug)?$spec->slug:"";
			if(WP_DEBUG and TANSYNC_DEBUG) error_log("-> processing slug: $slug");
			if(!$slug){
				if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> invalid slug: $slug");
			} 
			if( in_array($slug, array_keys($slugs))) {
				if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> already validated slug: $slug");
			}
			$conditions = isset($spec->conditions)?$spec->conditions:array();

			$passed = true;
			foreach (get_object_vars($conditions) as $type => $parameters) {
				$result = $this->evaluate_condition($type, $parameters);
				if(!$result) {
					$passed = false;
					break;
				}
			}
			if(!$passed) {
				if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> skipping $slug");
				continue;
			}

			$label = isset($spec->label)?$spec->label:$slug;
			if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> adding slug: $slug");
			$slugs[$slug] = $label;
		}
		return $slugs;
	}

	public function add_my_account_targeted_content(){
		$conditions_str = $this->settings->get_option('targeted_content_conditions');
		assert( gettype($conditions_str) == 'string' );
		$conditions = json_decode( $conditions_str );
		$slugs = $this->process_targeted_content_conditions($conditions);
		if($slugs and is_array($slugs)){
			//todo: make title modifiable in settings
			echo "<h2>My Resources</h2>";
			echo "<ul id='user_content'>";
			foreach ($slugs as $slug => $label) {
				// TODO output page urls and labels
				echo "<li>".$slug."</li>";
			}
			echo "</ul>";
		}


	}

	/**
	 * Adds Custom Actions and Filters 
	 */
	private function add_actions_filters () {
		add_filter('user_contactmethods', array($this, 'modify_contact_methods'));
		add_action('woocommerce_before_my_account', array($this, 'add_my_account_extra_fields'));
		add_action('woocommerce_before_my_account', array($this, 'add_my_account_targeted_content'));
	} 



}
