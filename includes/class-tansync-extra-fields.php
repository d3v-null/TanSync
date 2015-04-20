<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Deals with the extra fields
*/
class Tansync_Extra_fields
{
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

	function __construct($parent)
	{
		$this->parent = $parent;
		$this->settings = $parent->settings;
		// User Contact Methods
		add_action('admin_init', array($this, 'modify_user_edit_admin') );
		// My Account 
		add_action('init', array($this, 'modify_my_account'));
		// Edit My Account
		add_action('init', array($this, 'modify_edit_my_account'));		
	}

// TWO WAYS TO EDIT USER FIELDS: MY_PROFILE AND CONTACT_METHODS

	public function sync_user($user_id){
		//TODO: This
		if(WP_DEBUG and TANSYNC_DEBUG) error_log("syncing user: ".$user_id);
		update_user_meta($user_id, 'last_update', time());
	}

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
			'first_name',
			'last_name',
			'nickname',
			'display_name',
			'description',
		);
	}

	public function get_my_account_fields(){
		return array(
			'first_name',
			'last_name',
			'display_name',
			'email'
		);
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
		foreach ($extra_fields as $slug => $params) {
			if(in_array($slug, $core_fields)) continue;
			$label = isset($params->label)?$params->label:$slug;
			// if(WP_DEBUG and TANSYNC_DEBUG) {
			// 	error_log("$slug: $label");
			// }			
			$profile_fields[$slug] = $label;
		}
		return $profile_fields;
	}

	public function modify_user_edit_admin(){
		global $pagenow;
		if(WP_DEBUG and TANSYNC_DEBUG) error_log("pagenow: ".serialize($pagenow));
		// User-Edit Contact Methods
		if($pagenow == "user-edit.php"){
			add_filter('user_contactmethods', array($this, 'modify_contact_fields'));
			// do_action( 'edit_user_profile_update', $user_id );
			// add_filter('edit_user_profile_update', array(&$this, 'sync_user'));
			// add_filter('personal_options_update', array(&$this, 'sync_user'));
			add_filter('profile_update', array(&$this, 'sync_user'));
		} elseif ($pagenow == "profile.php" ) {	
			add_filter('user_contactmethods', array($this, 'modify_contact_fields'));
			// do_action( 'personal_options_update', $user_id );
			// add_filter('personal_options_update', array(&$this, 'sync_user'));
			// add_filter('edit_user_profile_update', array(&$this, 'sync_user'));
			add_filter('profile_update', array(&$this, 'sync_user'));
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
				if(WP_DEBUG and TANSYNC_DEBUG) error_log("in woocommerce_save_account_details closure | user_id: $user_id");
				$current_user = get_user_by( 'id', $user_id);
				foreach($extra_fields as $slug => $params){
					$default = isset($params->default)?$params->default:'';
					$value = (isset($_POST[$slug]) and !empty($_POST[$slug]))?wc_clean($_POST[$slug]):$default;
					if(WP_DEBUG and TANSYNC_DEBUG) {
						error_log(" -> slug:$slug ");
						error_log(" -> default:$default");
						error_log(" -> value:$value");
					}
					update_user_meta($user_id, $slug, $value);
				}		
			},
			0
		);
		add_action( 'woocommerce_save_account_details', array(&$this, 'sync_user'),	999 );

	}
}