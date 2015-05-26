<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class TanSync_Settings {

	/**
	 * The single instance of TanSync_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		$this->parent = $parent;

		$this->base = 'tsync_';

		// Initialise settings
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->parent->file ) , array( $this, 'add_settings_link' ) );
	}

	/**
	 * Initialise settings
	 * @return void
	 */
	public function init_settings () {
		$this->settings = $this->settings_fields();

	}

	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item () {
		$page = add_options_page( __( 'TanSync', 'tansync' ) , __( 'TanSync', 'tansync' ) , 'manage_options' , $this->parent->_token . '_settings' ,  array( $this, 'settings_page' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
	}

	/**
	 * Load settings JS & CSS
	 * @return void
	 */
	public function settings_assets () {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below
		wp_enqueue_style( 'farbtastic' );
    	wp_enqueue_script( 'farbtastic' );

    	// We're including the WP media scripts here because they're needed for the image upload field
    	// If you're not including an image upload then you can leave this function call out
    	wp_enqueue_media();

    	wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0' );
    	wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array 		Modified links
	 */
	public function add_settings_link ( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'tansync' ) . '</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields () {

		// $settings['User Fields'] = array(
		// 	'title'				=> __( 'Additional User Fields', 'tansync' ),
		// 	'description'		=> __( 'Describes the fields added to the user profile screens', 'tansync' ),
		// 	'fields'			=> array(
			// 	array(
			// 		'id' 			=> 'text_field',
			// 		'label'			=> __( 'Some Text' , 'tansync' ),
			// 		'description'	=> __( 'This is a standard text field.', 'tansync' ),
			// 		'type'			=> 'text',
			// 		'default'		=> '',
			// 		'placeholder'	=> __( 'Placeholder text', 'tansync' )
			// 	),
			// 	array(
			// 		'id' 			=> 'password_field',
			// 		'label'			=> __( 'A Password' , 'tansync' ),
			// 		'description'	=> __( 'This is a standard password field.', 'tansync' ),
			// 		'type'			=> 'password',
			// 		'default'		=> '',
			// 		'placeholder'	=> __( 'Placeholder text', 'tansync' )
			// 	),
			// 	array(
			// 		'id' 			=> 'secret_text_field',
			// 		'label'			=> __( 'Some Secret Text' , 'tansync' ),
			// 		'description'	=> __( 'This is a secret text field - any data saved here will not be displayed after the page has reloaded, but it will be saved.', 'tansync' ),
			// 		'type'			=> 'text_secret',
			// 		'default'		=> '',
			// 		'placeholder'	=> __( 'Placeholder text', 'tansync' )
			// 	),
			// 	array(
			// 		'id' 			=> 'text_block',
			// 		'label'			=> __( 'A Text Block' , 'tansync' ),
			// 		'description'	=> __( 'This is a standard text area.', 'tansync' ),
			// 		'type'			=> 'textarea',
			// 		'default'		=> '',
			// 		'placeholder'	=> __( 'Placeholder text for this textarea', 'tansync' )
			// 	),
			// 	array(
			// 		'id' 			=> 'single_checkbox',
			// 		'label'			=> __( 'An Option', 'tansync' ),
			// 		'description'	=> __( 'A standard checkbox - if you save this option as checked then it will store the option as \'on\', otherwise it will be an empty string.', 'tansync' ),
			// 		'type'			=> 'checkbox',
			// 		'default'		=> ''
			// 	),
			// 	array(
			// 		'id' 			=> 'select_box',
			// 		'label'			=> __( 'A Select Box', 'tansync' ),
			// 		'description'	=> __( 'A standard select box.', 'tansync' ),
			// 		'type'			=> 'select',
			// 		'options'		=> array( 'drupal' => 'Drupal', 'joomla' => 'Joomla', 'wordpress' => 'WordPress' ),
			// 		'default'		=> 'wordpress'
			// 	),
			// 	array(
			// 		'id' 			=> 'radio_buttons',
			// 		'label'			=> __( 'Some Options', 'tansync' ),
			// 		'description'	=> __( 'A standard set of radio buttons.', 'tansync' ),
			// 		'type'			=> 'radio',
			// 		'options'		=> array( 'superman' => 'Superman', 'batman' => 'Batman', 'ironman' => 'Iron Man' ),
			// 		'default'		=> 'batman'
			// 	),
			// 	array(
			// 		'id' 			=> 'multiple_checkboxes',
			// 		'label'			=> __( 'Some Items', 'tansync' ),
			// 		'description'	=> __( 'You can select multiple items and they will be stored as an array.', 'tansync' ),
			// 		'type'			=> 'checkbox_multi',
			// 		'options'		=> array( 'square' => 'Square', 'circle' => 'Circle', 'rectangle' => 'Rectangle', 'triangle' => 'Triangle' ),
			// 		'default'		=> array( 'circle', 'triangle' )
			// 	),
			// array(
			// 	'id' 			=> 'number_field',
			// 	'label'			=> __( 'A Number' , 'tansync' ),
			// 	'description'	=> __( 'This is a standard number field - if this field contains anything other than numbers then the form will not be submitted.', 'tansync' ),
			// 	'type'			=> 'number',
			// 	'default'		=> '',
			// 	'placeholder'	=> __( '42', 'tansync' )
			// ),
			// array(
			// 	'id' 			=> 'colour_picker',
			// 	'label'			=> __( 'Pick a colour', 'tansync' ),
			// 	'description'	=> __( 'This uses WordPress\' built-in colour picker - the option is stored as the colour\'s hex code.', 'tansync' ),
			// 	'type'			=> 'color',
			// 	'default'		=> '#21759B'
			// ),
			// array(
			// 	'id' 			=> 'an_image',
			// 	'label'			=> __( 'An Image' , 'tansync' ),
			// 	'description'	=> __( 'This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an imge the thumbnail will display above these buttons.', 'tansync' ),
			// 	'type'			=> 'image',
			// 	'default'		=> '',
			// 	'placeholder'	=> ''
			// ),
			// array(
			// 	'id' 			=> 'multi_select_box',
			// 	'label'			=> __( 'A Multi-Select Box', 'tansync' ),
			// 	'description'	=> __( 'A standard multi-select box - the saved data is stored as an array.', 'tansync' ),
			// 	'type'			=> 'select_multi',
			// 	'options'		=> array( 'linux' => 'Linux', 'mac' => 'Mac', 'windows' => 'Windows' ),
			// 	'default'		=> array( 'linux' )
			// )
		// );

		$settings['Sync'] = array(
			'title'				=> __( 'Sync Settings', 'tansync' ),
			'description'		=> __( 'Extra User Fields that are synced with a remote target', 'tansync' ),
			'fields'			=> array(
				array(
					'id' 			=> 'sync_field_settings',
					'label'			=> __( 'Synchrnoised Field Settings' , 'tansync' ),
					'description'	=> __( 'Enter user fields that are read from external source and whether this field is modified by WordPress', 'tansync' ),
					'type'			=> 'textarea',
					'default'		=> '',
					'placeholder'	=> __( '{"<field_id_1>":<sync_params_1>, "<field_id_2>":<sync_params_2>, ...}', 'tansync' )
				),	
				array(
					'id'			=> 'sync_email_enable',
					'label'			=> 'Enable Synchronization Email',
					'description'	=> __( 'Enables the service that emails staff regularly about user account changes', 'tansync'),
					'type'			=> 'checkbox',
					'default'		=> 'on'
				),
				array(
					'id'			=> 'sync_email_interval',
					'label'			=> 'Synchronization Email Interval',
					'description'	=> __( 'Enter the Interval (in seconds) that the plugin checks for updated users', 'tansync'),
					'type'			=> 'number',
					'default'		=> 300
				),
				array(
					'id'			=> 'sync_email_to',
					'label'			=> __( 'Synchronize Email Address', 'tansync'),
					'description'	=> __( 'Enter the email to which the synchronization messages are sent', 'tansync'),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> 'user@example.com'
				)
			)
		);

		$settings['Targeted Content'] = array(
			'title'				=> __( 'Targeted Content', 'tansync'),
			'description'		=> __( 'Settings to determine how targeted content is displayed to the user'),
			'fields'			=> array(
				array(
					'id'			=> 'targeted_content_conditions',
					'label'			=> __('Targeted Content Conditions'),
					'description'	=> __('Enter a list of page slugs and the conditions required to display those pages to the user', 'tansync'),
					'type'			=> 'textarea',
					'default'		=> '',
					'placeholder'	=> __( '[{"slug":"page_slug_1", "Conditions":<page_conditions_1>}, {"slug":"page_slug_2", "condtions":<page_conditions_2>}]'),
				),
			)
		);

		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings () {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section != $section ) continue;

				// Add section to page
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page
					add_settings_field( $field['id'], $field['label'], array( $this->parent->admin, 'display_field' ), $this->parent->_token . '_settings', $section, array( 'field' => $field, 'prefix' => $this->base ) );
				}

				if ( ! $current_section ) break;
			}
		}
	}

	public function settings_section ( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html;
	}

	/**
	 * Load settings page content
	 * @return void
	 */
	public function settings_page () {

		// Build page HTML
		$html = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
			$html .= '<h2>' . __( 'TanSync Settings' , 'tansync' ) . '</h2>' . "\n";

			$tab = '';
			if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
				$tab .= $_GET['tab'];
			}

			// Show page tabs
			if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

				$html .= '<h2 class="nav-tab-wrapper">' . "\n";

				$c = 0;
				foreach ( $this->settings as $section => $data ) {

					// Set tab class
					$class = 'nav-tab';
					if ( ! isset( $_GET['tab'] ) ) {
						if ( 0 == $c ) {
							$class .= ' nav-tab-active';
						}
					} else {
						if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) {
							$class .= ' nav-tab-active';
						}
					}

					// Set tab link
					$tab_link = add_query_arg( array( 'tab' => $section ) );
					if ( isset( $_GET['settings-updated'] ) ) {
						$tab_link = remove_query_arg( 'settings-updated', $tab_link );
					}

					// Output tab
					$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

					++$c;
				}

				$html .= '</h2>' . "\n";
			}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings' , 'tansync' ) ) . '" />' . "\n";
				$html .= '</p>' . "\n";
			$html .= '</form>' . "\n";
		$html .= '</div>' . "\n";

		echo $html;
	}

	/**
	 * Main TanSync_Settings Instance
	 *
	 * Ensures only one instance of TanSync_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see TanSync()
	 * @return Main TanSync_Settings instance
	 */
	public static function instance ( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * gets the specified option
	 */
	public function get_option( $option_slug ) {
		// if(WP_DEBUG) error_log("getting option $option_slug");
		if ( is_string($this->base) and is_string($option_slug) ) {
			return get_option( $this->base . $option_slug );
		} else {
			return null;
		}
	}

	/**
	 * gets the specified option
	 */
	public function set_option( $option_slug, $value ) {
		// if(WP_DEBUG) error_log("getting option $option_slug");
		if ( is_string($this->base) and is_string($option_slug) ) {
			return update_option( $this->base . $option_slug, $value );
		} else {
			return null;
		}
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
	} // End __wakeup()

}
