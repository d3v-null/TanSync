<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Deals with the extra fields
*/
class Tansync_UI_Extensions
{
	/**
	 * Parent class object
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	// *
	//  * The single instance of Tansync_UI_Extensions.
	//  * @var     object
	//  * @access  private
	//  * @since   1.0.0
	 
	// private static $_instance = null;	

	function __construct($parent)
	{
		// error_log("Tansync_UI_Extensions Constructor");
		$this->parent = $parent;
		$this->settings = $parent->settings;
		$this->synchronization = $parent->synchronization;
		// User Contact Methods
		add_action('admin_init', array($this, 'modify_user_edit_admin') );
		// My Account 
		add_action('init', array($this, 'modify_my_account'));
		// Edit My Account
		add_action('init', array($this, 'modify_edit_my_account'));		

		// add_action('init', array($this, 'modify_woocommerce_checkout'));
	}

	/**
	 * Main Tansync_UI_Extensions Instance
	 *
	 * Ensures only one instance of Tansync_UI_Extensions is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see TanSync()
	 * @return Main Tansync_UI_Extensions instance
	 */
	public static function instance ( $parent ) {
		return new self( $parent );
		// if ( is_null( self::$_instance ) ) {
		// 	self::$_instance = new self( $parent );
		// }
		// return self::$_instance;
	} // End instance()	

// TWO WAYS TO EDIT USER FIELDS: MY_PROFILE AND CONTACT_METHODS

	public function get_synced_fields(){
		$field_string = $this->settings->get_option('sync_field_settings', true);
		$fields = (array)json_decode($field_string);
		// if(WP_DEBUG) error_log("fields: ".serialize($fields));
		if($fields){
			return $fields;
		} else {
			return array();
		}
	}

	public function get_displayed_profile_fields(){
		$fields = $this->get_synced_fields();
		$filtered_fields = array_filter(
			$fields, 
			function ($field){
				$filter = isset($field->profile_display)?$field->profile_display:False;
				return $filter;
			}
		);
		// if(WP_DEBUG) error_log("displayed fields:".serialize($filtered_fields));
		return $filtered_fields;
	}

	public function get_modified_profile_fields(){
		$fields = $this->get_synced_fields();
		$filtered_fields = array_filter(
			$fields,
			function($field){
				$filter = isset($field->profile_modify)?$field->profile_modify:False;
				return $filter;
			}
		);
		// if(WP_DEBUG) error_log("modified fields: ".serialize($filtered_fields));
		return $filtered_fields;
	}

	public function get_contact_fields(){
		$fields = $this->get_synced_fields();
		$filtered_fields = array_filter(
			$fields,
			function($field){
				$filter = isset($field->contact_method)?$field->contact_method:False;
				// if(WP_DEBUG) error_log("filtering ".serialize($field)." | ".$filter);
				return $filter;
			}
		);
		// if(WP_DEBUG) error_log("contact fields: ".serialize($filtered_fields));
		return $filtered_fields;
	}

	public function get_user_edit_fields(){
		return array(
			'username',
			'user_email',
			'first_name',
			'role',
			'last_name',
			'nickname',
			'display_name',
			'description',
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_country',
			'billing_state',
			'billing_phone',
			'billing_email',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_postcode',
			'shipping_country',
			'shipping_state'			
		);
	}

	public function get_my_account_fields(){
		$fields = array(
			'first_name',
			'last_name',
			'display_name',
			'email',
		);

		apply_filters( 'tansync_get_my_account_fields', $fields	);

		return $fields;
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

	public function modify_contact_fields($profile_fields) {
		$extra_fields = $this->get_contact_fields();
		$core_fields = $this->get_user_edit_fields();
		// error_log("contact fields: ");
		foreach ($extra_fields as $slug => $params) {
			if(in_array($slug, $core_fields)) continue;
			$label = isset($params->label)?$params->label:$slug;
			// error_log(" -> $slug: $label");
			$profile_fields[$slug] = $label;
		}
		return $profile_fields;
	}

	public function modify_user_edit_admin(){
		global $pagenow;
		// error_log("pagenow: ".serialize($pagenow));
		// User-Edit Contact Methods
		if(in_array($pagenow, array("user-edit.php", "profile.php"))){
			// $this->synchronization->process_pending_updates();
			$this->filter_acui_columns();
			add_filter('user_contactmethods', array($this, 'modify_contact_fields'));
		}
	}

	public function display_my_account_fields(){
		$extra_fields = $this->get_displayed_profile_fields();
		$user_id = get_current_user_id();
		
		if ($extra_fields and $user_id){
			// TODO: make this modifiable in settings
			echo "<h2>My Profile</h2>";
			echo "<p class='user-profile-fields'>";
			// TODO: Add class
			echo "<table>";
			echo "<tbody>";
			foreach ($extra_fields as $slug => $params) {
				$label = isset($params->label)?$params->label:$slug;
				$value = get_user_meta($user_id, $slug, true);
				echo "<tr>";
				echo "<th class='profile-label'>".$label."</th> ";
				echo "<td class='profile-value'>".$value."</td>";
				echo "</tr>";
			}
			echo "</tbody>";
			echo "</table>";
			echo "</p>";
		}
	}

	private function evaluate_condition($type, $parameters){
		//TODO: actually evaluate conditions
		// if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> evaluating condition: $type | ".serialize($parameters) );
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
					// if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> current roles: ".serialize($current_roles));
					$intersect = array_intersect($current_roles, $allowed_roles);
					if(sizeof($intersect) == 0){
						// if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> condition failed");
						return false;
					} else {
						// if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> condition passed");
						return true;
					}
				}
				break;
			default:
				# code...
				break;
		}
		// if(WP_DEBUG and TANSYNC_DEBUG) error_log("---> condition passed by default");
		return true;
	}

	private function process_targeted_content_conditions($specs){
		// if(WP_DEBUG and TANSYNC_DEBUG) error_log("\nProcessing Targeted Content Conditions | specs: ".serialize($specs));
		// assert($specs and is_array($specs));
		$slugs = array();
		foreach ($specs as $spec) {
			$slug = isset($spec->slug)?$spec->slug:"";
			// if(WP_DEBUG and TANSYNC_DEBUG) error_log("-> processing slug: $slug");
			if(!$slug){
				// if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> invalid slug: $slug");
			} 
			if( in_array($slug, array_keys($slugs))) {
				// if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> already validated slug: $slug");
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
				// if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> skipping $slug");
				continue;
			}

			$label = isset($spec->label)?$spec->label:$slug;
			// if(WP_DEBUG and TANSYNC_DEBUG) error_log("--> adding slug: $slug");
			$slugs[$slug] = $label;
		}
		return $slugs;
	}

	public function add_my_account_targeted_content(){
		$conditions_str = $this->settings->get_option('targeted_content_conditions');
		if( gettype($conditions_str) != 'string' ){
			return;
		}
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


	public function modify_my_account(){
		add_action('woocommerce_before_my_account', array($this, 'display_my_account_fields'));
		add_action('woocommerce_before_my_account', array($this, 'add_my_account_targeted_content'));
	}

	public function modify_edit_my_account(){
		$modified_fields = $this->get_modified_profile_fields();
		$core_fields = $this->get_my_account_fields();
		$extra_fields = array();
		foreach($modified_fields as $key => $params){
			if(!in_array($key, $core_fields)){
				$extra_fields[$key] = $params;
			}
		}

		// do_action( 'woocommerce_edit_account_form' ); 
		add_action(
			'woocommerce_edit_account_form',
			function() use ($extra_fields){
				$user_id = get_current_user_id();
				foreach ($extra_fields as $slug => $params) {
					$label = isset($params->label)?$params->label:$slug;
					$value = get_user_meta($user_id, $slug, true);
?>
	<p class="form-row form-row-wide">
		<label for="<?php echo $slug; ?>"><?php _e( $label, 'lasercommerce' ); ?></label>
		<input type="text" class="input-text" name="<?php echo $slug; ?>" id="<?php echo $slug; ?>" value="<?php echo esc_attr( $value ); ?>" />
	</p>
<?php
				}
			}
		);
		// do_action_ref_array( 'user_profile_update_errors', array( &$errors, $update, &$user ) );
		add_action(
			'user_profile_update_errors',
			function($ref_array) use ($extra_fields){
				//todo: validate fields
			}
		);
		// do_action( 'woocommerce_save_account_details', $user->ID );
		add_action(
			'woocommerce_save_account_details',
			function($user_id) use ($extra_fields){
				// if(WP_DEBUG and TANSYNC_DEBUG) error_log("in woocommerce_save_account_details closure | user_id: $user_id");
				$current_user = get_user_by( 'id', $user_id);
				foreach($extra_fields as $slug => $params){
					$default = isset($params->default)?$params->default:'';
					$value = (isset($_POST[$slug]) and !empty($_POST[$slug]))?wc_clean($_POST[$slug]):$default;
					if(WP_DEBUG and TANSYNC_DEBUG) {
						// error_log(" -> slug:$slug ");
						// error_log(" -> default:$default");
						// error_log(" -> value:$value");
					}
					update_user_meta($user_id, $slug, $value);
				}		
			},
			0
		);
		// add_action( 'woocommerce_save_account_details', array(&$this, 'sync_user'),	999 );

	}

	public function sync_user($userid){
		error_log("called sync_user ".serialize($userid));
		$synchronization = $this->parent->synchronization;
		$synchronization->queue_update($user_id);
	}

	public function filter_acui_columns(){
		$acui_columns = get_option("acui_columns"); 
		// error_log("acui_columns: ".serialize($acui_columns));
		// error_log(" -> acui is_array ". is_array($acui_columns));
		// error_log(" -> acui not empty ". !empty($acui_columns));
		if(is_array($acui_columns) && !empty($acui_columns)){
			// error_log("made it this far");
			$new_columns = array();
			$extra_fields = array_keys($this->get_contact_fields());
			$core_fields = $this->get_user_edit_fields();
			// error_log("extra fields: ".serialize($extra_fields));
			// error_log("core fields: ".serialize($core_fields));
			foreach ($acui_columns as $key => $column) {
				// error_log("evaluating key, col".serialize($key).serialize($column));
				if(in_array($column, $extra_fields)) {
					// error_log('removing column because extra '.$column);
					continue;
				}
				if(in_array($column, $core_fields)) {
					// error_log('removing column because core '.$column);
					continue;
				}
				if(in_array($column, $new_columns)) {
					// error_log('removing column because not unique '.$column);
					continue;
				}
				array_push($new_columns, $column);
			}
			update_option("acui_columns", $new_columns);
		}
	}
}