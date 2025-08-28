<?php

namespace AoDTechnologies\ContentVisibilityForDiviBuilder;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class ContentVisibilityForDiviBuilder {
	protected static $initialized = false;
	protected static $instance;

	protected static $wp_version;
	protected static $has_force_regenerate_templates;
	protected static $has_ET_Builder_Framework_Utility_Conditions;
	protected static $is_using_divi_builder_5;
	protected static $should_force_shortcode_manager_to_register_all_shortcodes = false;
	protected static $cvdb_et_pb_children = array();

	protected $underscore_text_domain;
	protected $show_rating_notice_option_key;
	protected $is_saving_cache = false;

	public static function get_version() {
		return '4.01';
	}

	public static function get_text_domain() {
		return 'content-visibility-for-divi-builder';
	}

	public static function get_name() {
		return __( 'Content Visibility For Divi Builder', self::get_text_domain() );
	}

	public static function init() {
		if (self::$initialized) {
			return;
		}

		self::$wp_version = get_bloginfo( 'version' );
		self::get_instance();

		self::$initialized = true;
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new ContentVisibilityForDiviBuilder();
		}

		return self::$instance;
	}

	public static function uninstall() {
		$underscore_text_domain = str_replace( '-', '_', self::get_text_domain() );

		delete_option( "{$underscore_text_domain}_authentication_tokens" );
	}

	public function __construct($actions_and_filters_priority = 10) {
		$this->underscore_text_domain = str_replace( '-', '_', self::get_text_domain() );
		$this->show_rating_notice_option_key = "{$this->underscore_text_domain}_show-rating-notice";

		add_action( 'plugins_loaded', array( $this, 'actions_and_filters' ), $actions_and_filters_priority );

		register_activation_hook( CVDB_PLUGIN, array( $this, 'activate' ) );
		register_deactivation_hook( CVDB_PLUGIN, array( $this, 'deactivate' ) );

		// Ensure builder is loaded for our API reference page
		if ( is_admin() ) {
			global $pagenow;

			if ( $pagenow === 'tools.php' && isset( $_GET['page'] ) ) {
				$plugin_page = plugin_basename( wp_unslash( $_GET['page'] ) );
				if ( $plugin_page === 'content-visibility-for-divi-builder-api-reference') {
					add_filter( 'et_builder_should_load_framework', '__return_true' );
					add_filter( 'et_should_load_shortcode_framework', '__return_true' );
					add_filter( 'et_builder_should_load_all_module_data', '__return_true' );

					self::$should_force_shortcode_manager_to_register_all_shortcodes = true;
				}
			}
		}
	}
	
	/**
	 * The code that runs during plugin activation.
	 */
	public function activate( $network_wide = false ) {
		self::maybe_force_regenerate_templates();

		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide === true ) {
			if ( version_compare( self::$wp_version, '4.6', '<' ) ) {
				foreach ( wp_get_sites() as $site ) {
					switch_to_blog( $site['blog_id'] );
					self::maybe_force_regenerate_templates();
					restore_current_blog();
				}
			} else {
				foreach ( get_sites( array(
					'fields' => 'ids'
				) ) as $site_id ) {
					switch_to_blog( $site_id );
					self::maybe_force_regenerate_templates();
					restore_current_blog();
				}
			}
		}
	}
	
	/**
	 * The code that runs during plugin deactivation.
	 */
	public function deactivate( $network_deactivating = false ) {
		self::maybe_force_regenerate_templates();

		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_deactivating === true ) {
			if ( version_compare( self::$wp_version, '4.6', '<' ) ) {
				foreach ( wp_get_sites() as $site ) {
					switch_to_blog( $site['blog_id'] );
					self::maybe_force_regenerate_templates();
					restore_current_blog();
				}
			} else {
				foreach ( get_sites( array(
					'fields' => 'ids'
				) ) as $site_id ) {
					switch_to_blog( $site_id );
					self::maybe_force_regenerate_templates();
					restore_current_blog();
				}
			}
		}
	}

	private static function maybe_force_regenerate_templates() {
		if ( self::$has_force_regenerate_templates ) {
			\et_pb_force_regenerate_templates();
		}
	}

	private function maybe_run_migrations() {
		$stored_version = get_option( "{$this->underscore_text_domain}_version" );
		
		// Migration code for already active plugins
		if (self::get_version() !== $stored_version) {
			update_option( "{$this->underscore_text_domain}_version", self::get_version() );
	
			self::maybe_force_regenerate_templates();
		}
	}

	public function no_op() {
		// Do nothing...
	}

	public function run_detections() {
		self::$has_force_regenerate_templates = function_exists( '\et_pb_force_regenerate_templates' );
		self::$has_ET_Builder_Framework_Utility_Conditions = class_exists( '\ET\Builder\Framework\Utility\Conditions' );
		self::$is_using_divi_builder_5 = function_exists( '\et_builder_d5_enabled' ) && \et_builder_d5_enabled();

		if ( self::$is_using_divi_builder_5 ) {
			// Flags the plugin as compatible with Divi 5 in the Divi 5 Migrator
			add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'no_op' ) );

			// Supports migrating visibility expression settings for any module in the Divi 5 Migrator
			add_filter( 'divi.conversion.moduleLibrary.conversionMap', array( $this, 'add_content_visibility_check_attribute_to_divi_5_migrator' ), 1337 );

			// Evaluates visibility expressions in Divi 5 blocks
			add_filter( 'block_type_metadata_settings', array( $this, 'hook_into_gutenberg_modules' ), 1337, 2 );
		}

		$this->maybe_run_migrations();
	}

	public function prevent_texturize_shortcodes( $tags ) {
		return array_diff( $tags, array( 'et_pb_text', 'et_pb_code', 'et_pb_fullwidth_code' ) );
	}

	public function actions_and_filters() {
		load_plugin_textdomain( self::get_text_domain(), false, basename( dirname( CVDB_PLUGIN ) ) . '/languages/' );

		add_action( 'init', array( $this, 'run_detections' ), 1337 );

		add_filter( "{$this->underscore_text_domain}_prevent_texturize_shortcodes", array( $this, 'prevent_texturize_shortcodes' ) );

		add_action( 'et_builder_modules_loaded', array( $this, 'detect_saving_cache' ), 0 );

		add_action( 'et_builder_ready', array( $this, 'hook_into_builder_shortcodes' ), 1337 );

		if ( is_admin() ) {
			add_filter( 'plugin_action_links_' . plugin_basename( CVDB_PLUGIN ), array( $this, 'plugin_action_links' ), 1337 );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 11 );

			add_action( 'admin_menu', array( $this, 'add_menu_items' ) );

			add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

			add_action( 'wp_ajax_' . self::get_text_domain() . '_dismiss-rating-notice', array( $this, 'ajax_dismiss_rating_notice' ) );
			add_action( 'wp_ajax_' . self::get_text_domain() . '_click-rating-link', array( $this, 'ajax_click_rating_link' ) );

			if ( current_user_can( 'manage_options' ) && get_user_option( $this->show_rating_notice_option_key ) === false ) {
				// TODO: Find a better way to detect when a user has actually used the features of this plugin
				update_user_option( get_current_user_id(), $this->show_rating_notice_option_key, '1' );
			}

			// // Ensure builder is loaded for our API reference page
			// global $pagenow;

			// if ( $pagenow === 'tools.php' && isset( $_GET['page'] ) ) {
			// 	$plugin_page = plugin_basename( wp_unslash( $_GET['page'] ) );
			// 	if ( $plugin_page === 'content-visibility-for-divi-builder-api-reference') {
			// 		add_filter( 'et_builder_should_load_framework', '__return_true' );
			// 		add_filter( 'et_builder_should_load_all_module_data', '__return_true' );
			// 	}
			// }
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	public function detect_saving_cache() {
		$this->is_saving_cache = apply_filters( 'et_builder_modules_is_saving_cache', false );
	}

	public static function evaluate_visibility_expression($expression, $type, $data) {
		$visibility = true;

		try {
			eval( '$visibility = ' . $expression . ';' );
		} catch (ParseError | Error $error) {
			global $wp;
			global $wp_filesystem;

			$attachment_file_name = wp_tempnam();
			try {
				$attachments = array();
				$error_message_format = "An error has been detected while evaluating a visibility expression.\nNOTE: This section/module will not be displayed until the error is corrected.\n\nPage URL:\n%1\$s\n\nVisibility expression:\n%2\$s\n\nError message:\n%3\$s";

				if ( $wp_filesystem->put_contents( $attachment_file_name, print_r( $data, true ), FS_CHMOD_FILE ) ) {
					$error_message_format .= "\n\nThe full Divi Section/Module $type is attached to this email for reference.";
					$attachments["full-divi-$type.txt"] = $attachment_file_name;
				}

				wp_mail( get_bloginfo( 'admin_email' ), '[' . get_bloginfo( 'name' ) . '] Content Visibility for Divi Builder - Visibility Expression Error Detected', sprintf( $error_message_format, home_url( add_query_arg( isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : array(), '', $wp->request ) ), $expression, $error->getMessage() ), array('Content-Type: text/plain; charset=UTF-8'), $attachments );
			} finally {
				$wp_filesystem->delete( $attachment_file_name, false, 'f' );
			}

			$visibility = false;
		}

		return $visibility;
	}

	public function hook_into_builder_shortcodes() {
		require_once plugin_dir_path( CVDB_PLUGIN ) . 'includes/cvdb-et-builder-element.class.php';

		$cvdb_tags = array();

		// Find the manager instance and use it to pre-populate potentially lazy-loaded shortcodes
		if ( class_exists( '\ET_Builder_Module_Shortcode_Manager' ) && isset( $GLOBALS['wp_filter']['pre_do_shortcode_tag'] ) ) {
			$manager = null;
			if ( version_compare( self::$wp_version, '4.7', '<' ) ) {
				foreach ( $GLOBALS['wp_filter']['pre_do_shortcode_tag'] as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \ET_Builder_Module_Shortcode_Manager ) {
							$manager = $callback['function'][0];
							break 2;
						}
					}
				}
			} else {
				foreach ( $GLOBALS['wp_filter']['pre_do_shortcode_tag']->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \ET_Builder_Module_Shortcode_Manager ) {
							$manager = $callback['function'][0];
							break 2;
						}
					}
				}
			}

			if ( $manager !== null ) {
				if ( self::$should_force_shortcode_manager_to_register_all_shortcodes ) {
					$manager->register_all_shortcodes();
				}

				$cvdb_tags = $manager->add_module_slugs( $cvdb_tags );
			}
		}

		foreach ( $GLOBALS['shortcode_tags'] as $tag => $func ){
			if ( is_array( $func ) && $func[0] instanceof \ET_Builder_Element ) {
				self::$cvdb_et_pb_children[$tag] = $func;
				remove_shortcode( $tag, $func );
				$cvdb_tags[] = $tag;
			}
		}

		$cvdb_tags = apply_filters( $this->underscore_text_domain . '_prevent_texturize_shortcodes', array_unique( $cvdb_tags ) );

		add_filter( 'no_texturize_shortcodes', function( $default_no_texturize_shortcodes ) use ( $cvdb_tags ) {
			return array_unique( array_merge( $default_no_texturize_shortcodes, $cvdb_tags ) );
		} );

		// Something temporary until a better solution is found
		if ( function_exists( '\et_pb_is_pagebuilder_used' ) && apply_filters( $this->underscore_text_domain . '_remove_wptexturize_from_builder_pages', true ) ) {
			add_filter( 'the_content', array( $this, 'remove_wptexturize_from_builder_content' ), 9 );
		}

		// TODO: Lazy-loaded callables will not be included here (unless we are on our API reference page)!
		do_action( $this->underscore_text_domain, self::$cvdb_et_pb_children );

		foreach ( self::$cvdb_et_pb_children as $tag => $func ) {
			new CVDB_ET_Builder_Element( $func, $tag, $this->underscore_text_domain, self::get_text_domain() );
		}

		if ( $this->is_saving_cache && method_exists( '\ET_Builder_Element', 'save_cache' ) ) {
			\ET_Builder_Element::save_cache();
		}

		add_action( 'et_builder_module_loaded', array( $this, 'override_lazy_loaded_builder_module' ), 10, 2 );
	}
	
	public function hook_into_gutenberg_modules( $settings, $metadata ) {
		if ( !isset( $settings['render_callback'] ) ) {
			return $settings;
		}

		$render_callback = $settings['render_callback'];
		$settings['render_callback'] = function( $block_attributes, $content, \WP_Block $block ) use ( $render_callback ) {
			$path_parts = array( 'module', 'cvdb', 'contentVisibilityCheck', 'desktop', 'value' );

			$cvdb_content_visibility_check = $block->attributes;
			foreach ( $path_parts as $key ) {
				if ( !is_array( $cvdb_content_visibility_check ) || !isset( $cvdb_content_visibility_check[$key] ) ) {
					return apply_filters(
						"{$this->underscore_text_domain}_block_render_callback",
						call_user_func( $render_callback, $block_attributes, $content, $block ),
						$block_attributes,
						$content,
						$block,
						$render_callback
					);
				}

				$cvdb_content_visibility_check = $cvdb_content_visibility_check[$key];
			}

			if ( !is_string( $cvdb_content_visibility_check ) ) {
				return apply_filters(
					"{$this->underscore_text_domain}_block_render_callback",
					call_user_func( $render_callback, $block_attributes, $content, $block ),
					$block_attributes,
					$content,
					$block,
					$render_callback
				);
			}

			$visibility = self::evaluate_visibility_expression( $cvdb_content_visibility_check, 'block', $block );

			if ( !$visibility ) {
				return '';
			}

			return apply_filters(
				"{$this->underscore_text_domain}_block_render_callback",
				call_user_func( $render_callback, $block_attributes, $content, $block ),
				$block_attributes,
				$content,
				$block,
				$render_callback
			);
		};

		return $settings;
	}

	public function remove_wptexturize_from_builder_content( $content ) {
		if ( \et_pb_is_pagebuilder_used( get_the_ID() ) ) {
			remove_filter( 'the_content', 'wptexturize' );
		}

		return $content;
	}

	public function override_lazy_loaded_builder_module( $tag, $module ) {
		$func = $GLOBALS['shortcode_tags'][$tag];
		self::$cvdb_et_pb_children[$tag] = $func;
		remove_shortcode( $tag, $func );
		new CVDB_ET_Builder_Element( $func, $tag, $this->underscore_text_domain, self::get_text_domain() );
	}

	public function enqueue_scripts() {
		if ( self::$is_using_divi_builder_5 ) {
			if ( did_action( 'divi_visual_builder_initialize' ) && self::$has_ET_Builder_Framework_Utility_Conditions && \ET\Builder\Framework\Utility\Conditions::is_vb_app_window() ) {
				// Adds the Content Visibility field to Divi 5's editor
				wp_enqueue_script( self::get_text_domain() . '_gutenberg-filters', plugins_url( '/js/gutenberg-filters.js', CVDB_PLUGIN ), array( 'divi-vendor-wp-hooks' ), self::get_version() );
			}
		}
	}

	public function add_content_visibility_check_attribute_to_divi_5_migrator($moduleLibraryConversionMap) {
		$attributeMapValue = 'module.cvdb.contentVisibilityCheck.*';

		foreach ( $moduleLibraryConversionMap as &$conversionMap ) {
			if ( isset( $conversionMap['attributeMap'] ) ) {
				$conversionMap['attributeMap']['cvdb_content_visibility_check'] = $attributeMapValue;
			} else {
				$conversionMap['attributeMap'] = array(
					'cvdb_content_visibility_check' => $attributeMapValue,
				);
			}
		}

		return $moduleLibraryConversionMap;
	}

	public function enqueue_admin_scripts() {
		wp_enqueue_style( self::get_text_domain() . '_admin_styles', plugins_url( '/css/admin-styles.css', CVDB_PLUGIN ), array(), self::get_version() );
		wp_enqueue_script( self::get_text_domain() . '_admin-script', plugins_url( '/js/admin.js' , CVDB_PLUGIN ), array( 'jquery' ), self::get_version() );
		wp_localize_script( self::get_text_domain() . '_admin-script', 'cvdbAdminScript', array( 
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'textDomain' => self::get_text_domain()
		) );

		if ( wp_script_is( 'et_pb_admin_js' ) ) {
			wp_enqueue_script( self::get_text_domain() . '_builder_js_fixes', plugins_url( '/js/builder-fixes.js' , CVDB_PLUGIN ), array( 'jquery', 'et_pb_admin_js' ), self::get_version(), true );
		}
	}

	public function plugin_action_links( $links ) {
		$rate_text = _x( 'Rate', 'verb: They were asked to rate their ability at different driving maneuvers', self::get_text_domain() );

		return array_merge( $links, array(
			/* translators: 1: The translated text for "Rate" as a verb (e.g. They were asked to rate their ability at different driving maneuvers) 2: A "heart" emoji at the end of the translated text, or smiley for older WordPress versions */
			sprintf( '<a class="' . self::get_text_domain() . '-rating-link" href="https://wordpress.org/support/view/plugin-reviews/' . self::get_text_domain() . '?rate=5#postform" target="_blank">' . __( '%1$s this plugin %2$s', self::get_text_domain() ) . '</a>', $rate_text, ( function_exists( 'wp_staticize_emoji' ) ? wp_staticize_emoji( '❤' ) : translate_smiley( array( ':)' ) ) ) )
		) );
	}

	public function ajax_dismiss_rating_notice() {
		update_user_option( get_current_user_id(), $this->show_rating_notice_option_key, '0' );

		exit;
	}

	public function ajax_click_rating_link() {
		wp_redirect( 'https://wordpress.org/support/view/plugin-reviews/' . self::get_text_domain() . '?rate=5#postform' );

		exit;
	}

	public function add_menu_items() {
		add_submenu_page(
			'tools.php',
			__( self::get_name() . ' API Reference', self::get_text_domain() ),
			__( self::get_name() . ' API Reference', self::get_text_domain() ),
			'manage_options',
			self::get_text_domain() . '-api-reference',
			array( $this, 'render_api_reference_page' )
		);
	}

	public function render_api_reference_page() {
		if ( version_compare( self::$wp_version, '3.8', '<' ) ) {
			screen_icon( 'edit-pages' );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
?>
<div class="wrap">
<h1><?php _e( self::get_name() . ' API Reference', self::get_text_domain() ); ?></h1>
<h2 class="title"><?php _e( 'The Basics', self::get_text_domain() ); ?></h2>
<p><?php _e( self::get_name() . ' provides several WordPress actions and filters to allow customization/extension of installed Divi Builder modules.', self::get_text_domain() ); ?></p>
<p><?php _e( 'The following tabs provide detailed information about these actions and filters, along with a list of module-specific actions and filters for those modules currently installed on the system. Enjoy!', self::get_text_domain() ); ?></p>
<h2 class="nav-tab-wrapper"><?php
		$all_tabs = array(
			'general' => __( 'General Actions and Filters', self::get_text_domain() ),
			'specific' => __( 'Legacy Divi Module-Specific Actions and Filters', self::get_text_domain() ),
			'available' => __( 'Currently Available Legacy Divi Module-Specific Actions and Filters', self::get_text_domain() ),
		);
		foreach ( $all_tabs as $tab_key => $tab_caption ) {
			$active = $tab == $tab_key ? ' nav-tab-active' : '';
?>
<a class="nav-tab<?php echo $active; ?>" href="?page=<?php echo self::get_text_domain() . '-api-reference' ?>&tab=<?php echo $tab_key; ?>"><?php echo $tab_caption; ?></a><?php
		}
?>
</h2><?php
		if ( $tab === 'general' ) {
?>
<h2 class="title"><?php /* translators: 1: the WordPress action string, 2: the WordPress action parameter list */ printf( __( 'Action: %1$s<br>Parameters:<br>%2$s', self::get_text_domain()), '<code>content_visibility_for_divi_builder</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: Array of Legacy Divi Module shortcode callables; array keys are the callables&#8217; shortcode tags', self::get_text_domain()), '<code>$cvdb_et_pb_children</code>' ) ); ?></h2>
<p><?php _e( 'Useful for enumerating available Legacy Divi Module shortcode tags.', self::get_text_domain() ); ?></p>
<hr>
<h2 class="title"><?php /* translators: 1: the WordPress filter string, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain()), '<code>content_visibility_for_divi_builder_prevent_texturize_shortcodes</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: Array of available Legacy Divi Module shortcodes', self::get_text_domain() ), '<code>$cvdb_tags</code>' ) ); ?></h2>
<p><?php _e( 'Will by default pass all Legacy Divi Module shortcodes to the "no_texturize_shortcodes" standard WordPress filter. You may use this filter to remove some or all of the Modules from the array merged into "no_texturize_shortcodes".', self::get_text_domain() ); ?></p>
<hr>
<h2 class="title"><?php /* translators: 1: the WordPress filter string, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain()), '<code>content_visibility_for_divi_builder_remove_wptexturize_from_builder_pages</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: boolean indicating whether wptexturize should be removed from the "the_content" built-in WordPress filter for any post_type using Divi Builder; defaults to true', self::get_text_domain() ), '<code>$remove_wptexturize</code>' ) ); ?></h2>
<p><?php _e( 'You may return false in this filter to allow wp_texturize on "the_content" filter for Legacy Divi Builder post_types.', self::get_text_domain() ); ?></p><?php
			if ( self::$is_using_divi_builder_5 ) {
?>
<hr>
<h2 class="title"><?php /* translators: 1: the WordPress filter string pattern, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain()), '<code>content_visibility_for_divi_builder_block_render_callback</code>', /* translators: 1: the first parameter's variable name, 2: the second parameter's variable name, 3: the third parameter's variable name, 4: the fourth parameter's variable name, 5: the fifth parameter's variable name, 6: the sixth parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: The HTML to output for this Block&#8217;s render callback<br>&nbsp; &nbsp; %2$s: The attributes of the block (first standard Gutenberg render callback parameter)<br>&nbsp; &nbsp; %3$s: the content of the block (second standard Gutenberg render callback parameter)<br>&nbsp; &nbsp; %4$s: the WP_Block instance (third standard Gutenberg render callback parameter)<br>&nbsp; &nbsp; %5$s: The function that is called by default to handle this block (the original render callback)', self::get_text_domain() ), '<code>$result</code>', '<code>$block_attributes</code>', '<code>$content</code>', '<code>$block</code>', '<code>$render_callback</code>' ) ); ?></h2>
<p><?php _e( 'Executes within each Divi 5 Block&#8217;s render callback, allowing you to modify the output.<br>You can target specific blocks by inspecting the $block parameter&#8217;s name property, e.g.:<br><code>if ( $block->name === \'divi/text\' ) { /* ... */ }</code>', self::get_text_domain() ); ?></p><?php
			}
		} else if ( $tab === 'specific' ) {
?>
<h2 class="title"><?php /* translators: 1: the WordPress action string pattern, 2: the WordPress action parameter list */ printf( __( 'Action: %1$s<br>Parameters:<br>%2$s', self::get_text_domain()), '<code>content_visibility_for_divi_builder_setup_&lt;module_shortcode&gt;</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: The Module&#8217;s class instance', self::get_text_domain() ), '<code>$et_pb_element</code>' ) ); ?></h2>
<p><?php _e( 'Executes for each Legacy Divi Module, allowing you to use it as necessary (e.g. modify the class instance&#8217;s public variables)', self::get_text_domain() ); ?></p>
<hr>
<h2 class="title"><?php /* translators: 1: the WordPress filter string pattern, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain()), '<code>content_visibility_for_divi_builder_shortcode_&lt;module_shortcode&gt;</code>', /* translators: 1: the first parameter's variable name, 2: the second parameter's variable name, 3: the third parameter's variable name, 4: the fourth parameter's variable name, 5: the fifth parameter's variable name, 6: the sixth parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: The HTML to output for this Module&#8217;s shortcode<br>&nbsp; &nbsp; %2$s: The attributes of the shortcode (first standard WordPress shortcode callback parameter)<br>&nbsp; &nbsp; %3$s: the content of the shortcode (second standard WordPress shortcode callback parameter)<br>&nbsp; &nbsp; %4$s: the function_name of the shortcode (third standard WordPress shortcode callback parameter)<br>&nbsp; &nbsp; %5$s: The Module&#8217;s class instance<br>&nbsp; &nbsp; %6$s: The function name that is called by default on the Module&#8217;s class instance to handle this shortcode', self::get_text_domain() ), '<code>$result</code>', '<code>$atts</code>', '<code>$content</code>', '<code>$function_name</code>', '<code>$et_pb_element</code>', '<code>$et_pb_shortcode_callback</code>' ) ); ?></h2>
<p><?php _e( 'Executes within each Legacy Divi Module&#8217;s shortcode handler, allowing you to modify the output.', self::get_text_domain() ); ?></p><?php
		} else if ( $tab === 'available' ) {
			// We only care if self::$cvdb_et_pb_children is sorted while displaying this page...
			uasort( self::$cvdb_et_pb_children, function($a, $b) {
				return strcmp( $a[0]->name, $b[0]->name );
			} );

			foreach ( self::$cvdb_et_pb_children as $tag => $func ) {
			$et_pb_element = $func[0];
?>
	<h2 class="title"><?php printf( /* translators: 1: the Module's name */ __( 'Legacy Divi Module Name: %1$s', self::get_text_domain() ), $et_pb_element->name ); ?></h2>
	<h3>&nbsp; &nbsp; <?php printf( /* translators: 1: the Module-Specific action */ __( 'Action: %1$s', self::get_text_domain() ), "<code>content_visibility_for_divi_builder_setup_$tag</code>" ); ?></h3>
	<h3>&nbsp; &nbsp; <?php printf( /* translators: 1: the Module-Specific filter */ __( 'Filter: %1$s', self::get_text_domain() ), "<code>content_visibility_for_divi_builder_shortcode_$tag</code>" ); ?></h3>
	<hr><?php
			}
		}
?>
</div><?php
	}

	public function render_admin_notices() {
		if ( get_user_option( $this->show_rating_notice_option_key ) === '1' ) {
			$rating_link = '<a href="' . esc_url( admin_url( 'admin-ajax.php?action=' . self::get_text_domain() . '_click-rating-link' ) ) . '" target="_blank">' . _x( 'rating', 'present participle: I enjoyed rating the awesome WordPress plugin', self::get_text_domain() ) . '</a>';
?>
<div id="<?php echo esc_attr( self::get_text_domain() ); ?>_rating-notice" class="notice notice-info is-dismissible" style="position: relative;"><p><?php
			/* translators: 1: The plugin's name 2: The translated text for "rating" in the present participle (e.g. I enjoyed rating the awesome WordPress plugin) 3: A "smiley" emoticon at the end of the translated text */
			printf( __( 'If you like what %1$s helps you to do, please take a moment to consider %2$s it. And don\'t worry, we won\'t keep asking once you dismiss this notice. %3$s', self::get_text_domain() ), self::get_name(), $rating_link, translate_smiley( array( ':)' ) ) );
?></p><?php
			if ( version_compare( self::$wp_version, '4.2', '<' ) ) {?>
<div class="notice-dismiss" style="position: absolute; top: 0; right: 0; padding: 8px; cursor: pointer;" data-cvdb="true"><img src="<?php echo esc_url( admin_url( 'images/no.png' ) ); ?>" alt="X"></div>
<?php
			}
?></div>
<?php
		}
	}
}

ContentVisibilityForDiviBuilder::init();
