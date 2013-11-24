<?php
/**
 * Represents the view for the administration dashboard.
 *
 * @package   wp-super-preload
 * @author    tokkonopapa
 * @license   GPL-2.0+
 * @link      http://tokkono.cute.coocan.jp/blog/slow/
 * @copyright 2013 tokkonopapa (tokkonopapa@gmail.com)
 */

if( ! class_exists( 'WP_Super_Preload_Admin' ) ) :

class WP_Super_Preload_Admin extends WP_Super_Preload {

	/**
	 * Slug of the plugin screen.
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Option name and group.
	 */
	private $option_name = array();
	private $option_slug = array();

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 */
	public function __construct( $file, $class ) {
		parent::__construct( $file, $class );

		// Check version and compatibility
		if ( version_compare( get_bloginfo( 'version' ), '3.1' ) < 0 || ! extension_loaded( 'curl' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		}

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Load admin JavaScript and setup for ajax.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );
		add_action( 'wp_ajax_super_preload', array( $this, 'super_preload_callback' ) );
		add_filter( 'exec_preload_hook', array( $this, 'post_preload' ), 10, 3 );

		// Set menu properties
		foreach ( self::$option_table as $key => $value ) {
			$this->option_name[] = $key;
			$this->option_slug[] = $key;
		}

		// Add plugin meta links @since 2.7
		add_filter( 'plugin_action_links_' . $this->plugin_base, array( $this, 'plugin_action_links' ), 10, 1 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_meta_links' ), 10, 2 );
	}

	public function __destruct () {
		parent::__destruct();
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance( $file ) {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance )
			self::$instance = new self( $file, __CLASS__ );

		return self::$instance;
	}

	/**
	 * Display notice
	 */
	public function admin_notice() {
		$info = $this->get_plugin_info();
		echo '<div class="error">';
		echo '<p>', sprintf( __( '%s: You need WordPress 3.1.0 or higher and PHP 5 with libcurl.', self::TEXTDOMAIN ), $info['Name'] ), '</p>';
		echo '</div>', "\n";
	}

	/**
	 * Register and enqueue admin-specific Stylesheet and JavaScript.
	 */
	public function register_admin_scripts( $hook ) {
		if ( ! isset( $this->plugin_screen_hook_suffix ) )
			return;

		// only on this plugin page
		$screen = get_current_screen();
		if ( $screen->id === $this->plugin_screen_hook_suffix ) {
			// Stylesheet
			$handle = $this->plugin_slug . '-admin-styles';
			wp_enqueue_style( $handle, plugins_url( 'css/admin.css', $this->plugin_file ), array(), $this->version );

			// JavaScript
			$handle = $this->plugin_slug . '-admin-script';
			wp_enqueue_script( $handle, plugins_url( 'js/admin.js', $this->plugin_file ), array( 'jquery' ), $this->version ); // @since 2.6

			// for Ajax
			wp_localize_script( $handle, 'WPSP', array( // @since r16
				'action' => 'super_preload',
				'url' => admin_url( 'admin-ajax.php' ),
				'token' => wp_create_nonce( $this->get_ajax_action() ),
			) );
		}
	}

	/**
	 * Add plugin links
	 */
	public function plugin_action_links( $links ) {
		return array_merge(
			array( sprintf( '<a href="options-general.php?page=%s">%s</a>', $this->plugin_slug, __( 'Settings' ) ) ),
			$links
		);
	}

	/**
	 * Add plugin meta links
	 */
	public function plugin_meta_links( $links, $file ) {
		if ( $file === $this->plugin_base ) {
			array_push(
				$links,
				'<a href="https://github.com/tokkonopapa/WP-Super-Preload">Contribute on GitHub</a>'
			);
		}
		return $links;
	}

	/**
	 * Get the name of ajax action
	 */
	private function get_ajax_action() {
		return $this->plugin_slug . '-ajax-action';
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 */
	public function admin_menu() {
		/**
		 * Add sub menu page to the Settings menu.
		 * @link http://codex.wordpress.org/Function_Reference/add_options_page
		 *
		 * add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
		 * @param string $page_title The text to be displayed in the title tags of the page when the menu is selected.
		 * @param string $menu_title The text to be used for the menu.
		 * @param string $capability The capability required for this menu to be displayed to the user.
		 * @param string $menu_slug The slug name to refer to this menu by (should be unique for this menu).
		 * @param string $function The function to be called to output the content for this page.
		 */
		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'WP Super Preload', self::TEXTDOMAIN ),
			__( 'WP Super Preload', self::TEXTDOMAIN ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Initializes the contents of admin page.
	 * This function is registered with the 'admin_init' hook.
	 */
	public function admin_init() {
		$this->render_settings();
	}

	/**
	 * Render the settings page for this plugin.
	 */
	public function render_page( $active_tab = 0 ) {
		$active_tab = 0;
?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<h2 class="nav-tab-wrapper">
		<a href="?page=<?php echo $this->plugin_slug; ?>&amp;tab=0" class="nav-tab <?php echo $active_tab == 0 ? 'nav-tab-active' : ''; ?>"><?php _e( 'Settings', self::TEXTDOMAIN ); ?></a>
	</h2>
	<form method="post" action="options.php">
<?php
		settings_fields( $this->option_slug[ $active_tab ] ); // @since 2.7.0
		do_settings_sections( $this->option_slug[ $active_tab ] ); // @since 2.7.0
		submit_button(); // @since 3.1
?>
	</form>
	<p id="preload_msg"><?php if ( WP_SUPER_PRELOAD_DEBUG ) echo "Total ", get_num_queries(), " queries, ", timer_stop(), " seconds, ", memory_get_usage(), "bytes."; ?></p>
</div>
<?php
	}

	/**
	 * Initializes the options page by registering the Sections, Fields, and Settings
	 */
	private function render_settings() {
		/*========================================*
		 * Settings
		 *========================================*/

		/**
		 * Register a setting and its sanitization callback.
		 * @link http://codex.wordpress.org/Function_Reference/register_setting
		 *
		 * register_setting( $option_group, $option_name, $sanitize_callback );
		 * @param string $option_group A settings group name.
		 * @param string $option_name The name of an option to sanitize and save.
		 * @param string $sanitize_callback A callback function that sanitizes the option's value.
		 * @since 2.7.0
		 */
		register_setting(
			$this->option_slug[0],
			$this->option_name[0],
			array( $this, 'sanitize_settings1' )
		);

		/*----------------------------------------*
		 * Options
		 *----------------------------------------*/
		$options = get_option( $this->option_name[0] );
		$fields = is_array( $options ) ? array_keys( $options ) : array();

		/**
		 * Add new section to a new page inside the existing page.
		 * @link http://codex.wordpress.org/Function_Reference/add_settings_section
		 *
		 * add_settings_section( $id, $title, $callback, $page );
		 * @param string $id String for use in the 'id' attribute of tags.
		 * @param string $title Title of the section.
		 * @param string $callback Function that fills the section with the desired content.
		 * @param string $page The menu page on which to display this section.
		 * @since 2.7.0
		 */
		/*----------------------------------------*
		 * Section of Basic Settings
		 *----------------------------------------*/
		$section = $this->plugin_slug . '-basics';
		add_settings_section(
			$section,
			__( 'Basic settings', self::TEXTDOMAIN ),
			NULL, // array( $this, 'render_section_basics' ),
			$this->option_slug[0]
		);

		/**
		 * Register a settings field to the settings page and section
		 * @link http://codex.wordpress.org/Function_Reference/add_settings_field
		 *
		 * add_settings_field( $id, $title, $callback, $page, $section, $args );
		 * @param string $id String for use in the 'id' attribute of tags.
		 * @param string $title Title of the field.
		 * @param string $callback Function responsible for rendering the option interface.
		 * @param string $page The menu page on which to display this field.
		 * @param string $section The section of the settings page in which to show the box.
		 * @param array $args Additional arguments that are passed to the $callback function.
		 * @since 2.7.0
		 */
		$field = 'synchronize_gc';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Trigger at the garbage collection WP-Cron event', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'select_gc',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'preload_freq';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Trigger at the scheduled WP-Cron event', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'select_freq',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
				'hh'     => sprintf( '%02d', intval( $options['preload_hh'] ) ),
				'mm'     => sprintf( '%02d', intval( $options['preload_mm'] ) ),
			)
		);

		$field = 'sitemap_urls';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'URLs of sitemap', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'textarea',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'additional_contents';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Additional contents', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'checkboxes',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'additional_pages';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'URLs of additional pages', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'textarea',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'max_pages';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Maximum number of pages', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'text',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'user_agent';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Additional UA string', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'text',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		/*----------------------------------------*
		 * Section of Advanced Settings
		 *----------------------------------------*/
		$section = $this->plugin_slug . '-advanced';
		add_settings_section(
			$section,
			__( 'Advanced settings', self::TEXTDOMAIN ),
			NULL, // array( $this, 'render_section_advanced' ),
			$this->option_slug[0]
		);

		$update = get_option( $this->option_name[1] );
		$field = 'split_preload';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Split preloading', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'checkbox',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
				'after'  => '&nbsp;<span>(&nbsp;' . sprintf( __( "next preloading will start from: %d", self::TEXTDOMAIN ), $update['next_preload'] ) . '&nbsp;)</span>',
			)
		);

		$field = 'requests_per_split';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Number of requests per split', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'text',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'parallel_requests';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Number of parallel requests', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'text',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'interval_in_msec';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Interval between parallel requests', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'text',
				'after'  => __( '[milliseconds]', self::TEXTDOMAIN ),
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => $options[ $field ],
			)
		);

		$field = 'preload_now';
		add_settings_field(
			$this->option_name[0] . "_$field",
			__( 'Test preloading', self::TEXTDOMAIN ),
			array( $this, 'render_field' ),
			$this->option_slug[0],
			$section,
			array(
				'type'   => 'button',
				'option' => $this->option_name[0],
				'field'  => $field,
				'value'  => __( 'Preload now', self::TEXTDOMAIN ),
			)
		);
	} // end render_settings

	/**
	 * Function that fills the section with the desired content.
	 * The function should echo its output.
	 */
//	public function render_section_basics() {
//		echo '<p>' . __( 'This is a explanation for section 1.', self::TEXTDOMAIN ) . '</p>';
//	}

//	public function render_section_advanced() {
//		echo '<p>' . __( 'This is a explanation for section 2.', self::TEXTDOMAIN ) . '</p>';
//	}

	/**
	 * Function that fills the field with the desired inputs as part of the larger form.
	 * The 'id' and 'name' should match the $id given in the add_settings_field().
	 *
	 * @param array $args A value to be given into the field.
	 * @link http://codex.wordpress.org/Function_Reference/checked
	 * esc_attr() @since 2.8.0
	 * esc_textarea() @since 3.1
	 */
	public function render_field( $args ) {
		// additional description
		if ( ! empty( $args['before'] ) )
			echo $args['before'], "\n";

		$id   = "${args['option']}_${args['field']}";
		$name = "${args['option']}[${args['field']}]";

		switch ( $args['type'] ) {
			case 'checkbox':
?>
<input type="checkbox" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="1"<?php checked( esc_attr( $args['value'] ) ); ?> />
<label for="<?php echo $id; ?>"><?php _e( 'Enable', self::TEXTDOMAIN ); ?></label>
<?php
				break;
			case 'checkboxes':
				echo '<ul id="additional-contents">';
				foreach ( $args['value'] as $key => $value ) {
					$title = ucfirst( str_replace( '_', ' ', $key ) );
?>
<li>
<input type="checkbox" id="<?php echo $id.'_'.$key; ?>" name="<?php echo $name.'['.$key.']'; ?>" value="1"<?php checked( esc_attr( $value ) ); ?> />
<label for="<?php echo $id.'_'.$key; ?>"><?php _e( $title, self::TEXTDOMAIN ); ?></label>
</li>
<?php
				}
				echo '</ul>';
				break;
			case 'text':
?>
<input type="text" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo esc_attr( $args['value'] ); ?>" />
<?php
				break;
			case 'textarea':
?>
<textarea id="<?php echo $id; ?>" name="<?php echo $name; ?>" wrap="off" cols="60" rows="2"><?php echo str_replace( " ", "\n", esc_textarea( $args['value'] ) ); ?></textarea>
<?php
				break;
			case 'select_gc':
?>
<select id="select_gc_event">
<?php
				$list = array(
					__( 'Disable', self::TEXTDOMAIN ) => 'disable',
					'WP Super Cache' => 'wp_cache_gc',
					'W3 Total Cache' => 'w3_pgcache_cleanup',
					'Hyper Cache'    => 'hyper_clean',
					'Quick Cache'    => 'ws_plugin__qcache_garbage_collector__schedule',
				);
				$sel = FALSE;
				foreach ( $list as $plugin => $event ) {
					$sel |= ($event === $args['value']);
?>
	<option value="<?php echo $event ?>"<?php selected( $event, $args['value'] ); ?>><?php echo $plugin; ?></option>
<?php
				}
?>
	<option value="<?php echo $sel === FALSE ? esc_attr( $args['value'] ) : ''; ?>"<?php selected( empty( $sel ), TRUE ); ?>><?php _e( 'Other Plugin', self::TEXTDOMAIN ); ?></option>
</select>
<input type="text" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo esc_attr( $args['value'] ); ?>" />
<?php
				$next = wp_next_scheduled( $args['value'] );
				if ( FALSE !== $next ) {
					echo '&nbsp;<span>(&nbsp;', __( "Next event at", self::TEXTDOMAIN ), '&nbsp;', date( 'd/M/Y H:i:s', intval( $next ) + WP_SUPER_PRELOAD_GMT_OFFSET ), "&nbsp;)</span>\n";
				}
				break;
			case 'select_freq':
?>
<select id="<?php echo $id; ?>" name="<?php echo $name; ?>">
<?php
				$list = array(
					__( 'Disable', self::TEXTDOMAIN ) => 'disable',
					__( 'Once Hourly' ) => 'hourly',
					__( 'Twice Daily' ) => 'twicedaily',
					__( 'Once Daily'  ) => 'daily',
				);
				foreach ( $list as $freq => $value ) {
?>
	<option value="<?php echo $value ?>"<?php selected( $value, $args['value'] ); ?>><?php echo $freq ?></option>
<?php
				}
?>
</select>&nbsp;
<span><?php _e( 'starting from', self::TEXTDOMAIN ); ?></span>&nbsp;
<input type="text" id="<?php echo $args['option']; ?>_preload_hh" name="<?php echo $args['option']; ?>[preload_hh]" value="<?php echo $args['hh']; ?>" size="2" maxlength="2" />&nbsp;:&nbsp;
<input type="text" id="<?php echo $args['option']; ?>_preload_mm" name="<?php echo $args['option']; ?>[preload_mm]" value="<?php echo $args['mm']; ?>" size="2" maxlength="2" />&nbsp;
<?php
				$next = wp_next_scheduled( self::EVENT_HOOK );
				if ( FALSE !== $next ) {
					echo '<span>(&nbsp;', __( "Next event at", self::TEXTDOMAIN ), '&nbsp;', date( 'd/M/Y H:i:s', intval( $next ) + WP_SUPER_PRELOAD_GMT_OFFSET ), "&nbsp;)</span>\n";
				} else {
					echo '<span>(&nbsp;', __( 'Current time', self::TEXTDOMAIN ), '&nbsp;', date( 'H:i', time() + WP_SUPER_PRELOAD_GMT_OFFSET ), "&nbsp;)</span>\n";
				}
				break;
			case 'button':
?>
<input type="button" class="button-secondary" id="<?php echo $args['field']; ?>" value="<?php echo $args['value']; ?>" />
<?php
				break;
		}

		// additional description
		if ( ! empty( $args['after'] ) )
			echo $args['after'], "\n";

	} // end render_field

	/**
	 * A callback function that validates the option's value
	 *
	 * @param string $option_name The name of option table.
	 * @param array $input The values to be validated.
	 *
	 * @link http://codex.wordpress.org/Data_Validation
	 * @link http://codex.wordpress.org/Function_Reference/sanitize_option
	 * @link http://codex.wordpress.org/Function_Reference/sanitize_text_field
	 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/sanitize_option_$option
	 * @link http://core.trac.wordpress.org/browser/tags/3.5/wp-includes/formatting.php
	 */
	private function sanitize_settings( $option_name, $input ) {
		// Default message and type for add_settings_error()
		$message = __( 'Settings has been successfully updated.', self::TEXTDOMAIN );
		$status = 'updated';

		/**
		 * Sanitize a string from user input or from the db
		 *
		 * check for invalid UTF-8,
		 * Convert single < characters to entity,
		 * strip all tags,
		 * remove line breaks, tabs and extra white space,
		 * strip octets.
		 *
		 * @since 2.9.0
		 * @example sanitize_text_field( $str );
		 * @param string $str
		 * @return string
		 */
		$output = get_option( $option_name );
		foreach ( $output as $key => $value ) {
			switch( $key ) {
				case 'split_preload':
					$output[ $key ] = isset( $input[ $key ] ) ?
						sanitize_text_field( $input[ $key ] ) : FALSE; // @since 2.9.0
					break;
				case 'additional_contents':
					foreach ( $output[ $key ] as $subkey => $subval ) {
						$output[ $key ][ $subkey ] = isset( $input[ $key ][ $subkey ] ) ?
							sanitize_text_field( $input[ $key ][ $subkey ] ) : FALSE;
					}
					break;
				case 'preload_freq':
					$output[ $key ] = 'disable';
					$list = array( 'hourly', 'twicedaily', 'daily' );
					foreach ( $list as $freq ) {
						if ( $freq === trim( $input[ $key ] ) ) {
							$output[ $key ] = $freq;
							break;
						}
					}
					break;
				case 'preload_hh':
				case 'preload_mm':
					if ( ! is_numeric( $input[ $key ] ) || intval( $input[ $key ] ) < 0 ) {
						$message = __( 'Some values must be an integer greater than or equal to zero.', self::TEXTDOMAIN );
						$status = 'error';
						break;
					}
					$output[ $key ] = isset( $input[ $key ] ) ?
						sanitize_text_field( trim( $input[ $key ] ) ) : $value;
					break;
				case 'max_pages':
				case 'requests_per_split':
				case 'parallel_requests':
				case 'interval_in_msec':
					if ( ! is_numeric( $input[ $key ] ) || intval( $input[ $key ] ) <= 0 ) {
						$message = __( 'Some values must be a natural number.', self::TEXTDOMAIN );
						$status = 'error';
						break;
					}
				default: // text
					$output[ $key ] = isset( $input[ $key ] ) ?
						sanitize_text_field( trim( $input[ $key ] ) ) : $value;
					break;
			}
		}

		// Register scheduled event
		self::deregister_schedule();
		self::register_schedule( $output );

		// Message at the top of page
		// @param string $setting: Slug title of the setting to which this error applies.
		// @param string $code: Slug-name to identify the error.
		// @param string $message: The formatted message text to display to the user.
		// @param string $type: The type of message it is. 'error' or 'updated'.
		// @link http://codex.wordpress.org/Function_Reference/add_settings_error
		// @since 3.0
		add_settings_error(
			$this->option_slug,
			'sanitize_' . $option_name,
			WP_SUPER_PRELOAD_DEBUG ? print_r( $output, TRUE ) : $message,
			$status
		);

		return $output;
	} // sanitize_settings

	/**
	 * A callback function that validates the option's value
	 */
	public function sanitize_settings1( $input = array() ) {
		return $this->sanitize_settings( $this->option_name[0], $input );
	}

	/**
	 * Ajax callback function
	 *
	 * @link http://codex.wordpress.org/AJAX_in_Plugins
	 * @link http://core.trac.wordpress.org/browser/trunk/wp-admin/admin-ajax.php
	 * @link http://codex.wordpress.org/Function_Reference/check_ajax_referer
	 */
	public function super_preload_callback() {
		// Check request origin, nonce, capability.
		if ( check_admin_referer( $this->get_ajax_action(), 'token' ) && // @since 2.5
		     current_user_can( 'manage_options' ) && ! empty($_POST) ) { // @since 2.0
			// Check parameter
			if ( 'fetch-now' === $_POST['mode'] || 'no-fetch' === $_POST['mode'] ) {
				$result = $this->exec_preload( $_POST['mode'] );
				echo htmlspecialchars( $result );
				die();
			}
		}

		// Forbidden
		status_header( 403 ); // @since 2.0.0
		@header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		echo __( 'Forbidden', self::TEXTDOMAIN );
		die();
	}

} // end class

endif; // class_exists
