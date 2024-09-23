<?php

/**
 * @since             1.0.0
 * @package           content_visibility_for_divi_builder
 *
 * @wordpress-plugin
 * Plugin Name:       Content Visibility for Divi Builder
 * Description:       Allows Sections and Modules to be displayed/hidden based on the outcome of a PHP boolean expression.
 * Version:           3.22
 * Author:            AoD Technologies LLC
 * Author URI:        http://www.aod-tech.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       content-visibility-for-divi-builder
 * Domain Path:       /languages
 *
 * Content Visibility for Divi Builder is free software: you
 * can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software
 * Foundation, either version 2 of the License, or any later version.
 *
 * Content Visibility for Divi Builder is distributed in the
 * hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Content Visibility for Divi Builder. If not, see
 * http://www.gnu.org/licenses/gpl-2.0.txt.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function activate_content_visibility_for_divi_builder_single_site( $is_network = FALSE ) {
	if ( function_exists( 'et_pb_force_regenerate_templates' ) ) {
		et_pb_force_regenerate_templates();
	}
}

function deactivate_content_visibility_for_divi_builder_single_site( $is_network = FALSE ) {
	if ( function_exists( 'et_pb_force_regenerate_templates' ) ) {
		et_pb_force_regenerate_templates();
	}
}

/**
 * The code that runs during plugin activation.
 */
function activate_content_visibility_for_divi_builder( $network_wide = FALSE ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide === TRUE ) {
		activate_content_visibility_for_divi_builder_single_site( true );

		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '4.6', '<' ) ) {
			foreach ( wp_get_sites() as $site ) {
				switch_to_blog( $site['blog_id'] );
				activate_content_visibility_for_divi_builder_single_site();
				restore_current_blog();
			}
		} else {
			foreach ( get_sites( array(
				'fields' => 'ids'
			) ) as $site_id ) {
				switch_to_blog( $site_id );
				activate_content_visibility_for_divi_builder_single_site();
				restore_current_blog();
			}
		}
	} else {
		activate_content_visibility_for_divi_builder_single_site();
	}
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_content_visibility_for_divi_builder( $network_deactivating = FALSE ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_deactivating === TRUE ) {
		deactivate_content_visibility_for_divi_builder_single_site( true );

		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '4.6', '<' ) ) {
			foreach ( wp_get_sites() as $site ) {
				switch_to_blog( $site['blog_id'] );
				deactivate_content_visibility_for_divi_builder_single_site();
				restore_current_blog();
			}
		} else {
			foreach ( get_sites( array(
				'fields' => 'ids'
			) ) as $site_id ) {
				switch_to_blog( $site_id );
				deactivate_content_visibility_for_divi_builder_single_site();
				restore_current_blog();
			}
		}
	} else {
		deactivate_content_visibility_for_divi_builder_single_site();
	}
}

register_activation_hook( __FILE__, 'activate_content_visibility_for_divi_builder' );
register_deactivation_hook( __FILE__, 'deactivate_content_visibility_for_divi_builder' );

call_user_func( function() {
	$text_domain = 'content-visibility-for-divi-builder';
	$plugin_key = str_replace( '-', '_', $text_domain );

	$wp_version = get_bloginfo( 'version' );

	$version = '3.22';
	$stored_version = get_option( 'content_visibility_for_divi_builder_version' );

	$is_network = function_exists( 'is_multisite' ) && is_multisite() && network_site_url() === site_url();

	// Migration code for already active plugins
	if ($version !== $stored_version) {
		update_option( 'content_visibility_for_divi_builder_version', $version );

		add_action( 'init', function() {
			if ( function_exists( 'et_pb_force_regenerate_templates' ) ) {
				et_pb_force_regenerate_templates();
			}
		} );
	}

	add_action( 'admin_enqueue_scripts', function() use ( $text_domain, $version ) {
		wp_enqueue_style( "{$text_domain}_admin_styles", plugins_url( '/css/admin-styles.css', __FILE__ ), array(), $version );
		wp_enqueue_script( "{$text_domain}_admin-script", plugins_url( '/js/admin.js' , __FILE__ ), array( 'jquery' ), $version );
		wp_localize_script( "{$text_domain}_admin-script", 'cvdbAdminScript', array( 
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'textDomain' => $text_domain
		) );

		if ( wp_script_is( 'et_pb_admin_js' ) ) {
			wp_enqueue_script( "{$text_domain}_builder_js_fixes", plugins_url( '/js/builder-fixes.js' , __FILE__ ), array( 'jquery', 'et_pb_admin_js' ), $version, true );
		}
	}, 11 );

	add_action( 'plugins_loaded', function() use ( $text_domain ) {
		load_plugin_textdomain( $text_domain, false, basename( dirname( __FILE__ ) ) . '/languages/' );

		add_filter( 'content_visibility_for_divi_builder_prevent_texturize_shortcodes', function ( $medb_tags ) use ( $text_domain ) {
			foreach( $medb_tags as $tag ) {
				add_filter( 'content_visibility_for_divi_builder_shortcode_' . $tag, function( $result, $atts, $content, $function_name, $et_pb_element, $et_pb_shortcode_callback ) {
					if ( !isset( $atts['cvdb_content_visibility_check'] ) || trim( $atts['cvdb_content_visibility_check'] ) === '' ) {
						return $result;
					}

					if ( is_admin() && function_exists( 'get_current_screen' ) ) {
						$current_screen = get_current_screen();

						if ( $current_screen !== null && $current_screen->action === '' && $current_screen->id === $current_screen->post_type ) {
							return $result;
						}
					}

					if ( isset( $_REQUEST['action'] ) ) {
						switch ( sanitize_text_field( $_REQUEST['action'] ) ) {
							case 'et_fb_retrieve_builder_data':
							case 'et_fb_get_saved_templates':
								return $result;
							default:
								break;
						}
					}

					if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
						return $result;
					}

					$visibility = true;

					eval( '$visibility = ' . str_replace( array( '%22', '%5D' ), array( '"', ']' ), $atts['cvdb_content_visibility_check'] ) . ';' );

					if ( !$visibility ) {
						$result = '';
					}

					return $result;
				}, 1337, 6 );
			}

			return array_diff( $medb_tags, array( 'et_pb_text', 'et_pb_code', 'et_pb_fullwidth_code' ) );
		} );
	} );

	add_action( 'init', function() use ( $text_domain, $wp_version, $is_network ) {
		if ( is_admin() ) {
			$show_rating_notice_option_key = $text_domain . '_show-rating-notice';

			if ( get_user_option( $show_rating_notice_option_key ) === '1' ) {
				add_action( 'admin_notices', function() use ( $text_domain, $wp_version ) {
					$rating_link = '<a href="' . esc_url( admin_url( 'admin-ajax.php?action=' . $text_domain . '_click-rating-link' ) ) . '" target="_blank">' . _x( 'rating', 'present participle: I enjoyed rating the awesome WordPress plugin', $text_domain ) . '</a>';
?>
<div id="content-visibility-for-divi-builder_rating-notice" class="notice notice-info is-dismissible" style="position: relative;"><p><?php
					/* translators: 1: The translated text for "rating" in the present participle (e.g. I enjoyed rating the awesome WordPress plugin) 2: A "smiley" emoticon at the end of the translated text */
					printf( __( 'If you like what Content Visibility for Divi Builder helps you to do, please take a moment to consider %1$s it. And don\'t worry, we won\'t keep asking once you dismiss this notice. %2$s', $text_domain ), $rating_link, translate_smiley( array( ':)' ) ) );
?></p><?php
					if ( version_compare( $wp_version, '4.2', '<' ) ) {?>
<div class="notice-dismiss" style="position: absolute; top: 0; right: 0; padding: 8px; cursor: pointer;" data-cvdb="true"><img src="<?php echo esc_url( admin_url( 'images/no.png' ) ); ?>" alt="X"></div>
<?php
					}
?></div>
<?php
				} );
			}

			add_action( 'wp_ajax_' . $text_domain . '_dismiss-rating-notice', function() use ( $show_rating_notice_option_key ) {
				update_user_option( get_current_user_id(), $show_rating_notice_option_key, '0' );

				exit;
			} );

			add_action( 'wp_ajax_' . $text_domain . '_click-rating-link', function() use ( $text_domain ) {
				wp_redirect( "https://wordpress.org/support/view/plugin-reviews/$text_domain?rate=5#postform" );

				exit;
			} );

			if ( $GLOBALS['pagenow'] === 'plugins.php' ) { 
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) use ( $text_domain ) {
					$rate_text = _x( 'Rate', 'verb: They were asked to rate their ability at different driving maneuvers', $text_domain );

					return array_merge( $links, array(
						/* translators: 1: The translated text for "Rate" as a verb (e.g. They were asked to rate their ability at different driving maneuvers) 2: A "heart" emoji at the end of the translated text, or smiley for older WordPress versions */
						sprintf( '<a class="' . $text_domain . '-rating-link" href="https://wordpress.org/support/view/plugin-reviews/' . $text_domain . '?rate=5#postform" target="_blank">' . __( '%1$s this plugin %2$s', $text_domain ) . '</a>', $rate_text, ( function_exists( 'wp_staticize_emoji' ) ? wp_staticize_emoji( '❤' ) : translate_smiley( array( ':)' ) ) ) )
					) );
				}, 1337, 2 );
			} else if ( current_user_can( 'manage_options' ) && get_user_option( $show_rating_notice_option_key ) === FALSE ) {
				// TODO: Find a better way to detect when a user has actually used the features of this plugin
				update_user_option( get_current_user_id(), $show_rating_notice_option_key, '1' );
			}
		}
	} );

	$is_saving_cache = false;
	add_action( 'et_builder_modules_loaded', function() use ( &$is_saving_cache ) {
		$is_saving_cache = apply_filters( 'et_builder_modules_is_saving_cache', false );
	}, 0 );

	// Module Extender for Divi Builder functionality
	$medb_et_pb_children  = array();
	add_action( 'et_builder_ready', function() use ( $text_domain, $plugin_key, $wp_version, &$is_saving_cache, &$medb_et_pb_children ) {
		if ( !class_exists( 'MEDB_ET_Builder_Element' ) ) {
			class MEDB_ET_Builder_Element extends ET_Builder_Element {
				private $wrapped_element;
				private $wrapped_element_shortcode_callback;
				private $tag;
				private $plugin_key;
				private $text_domain;

				public function __construct($func, $tag, $plugin_key, $text_domain) {
					$this->wrapped_element = $func[0];
					$this->wrapped_element_shortcode_callback = $func[1];
					$this->tag = $tag;
					$this->plugin_key = $plugin_key;
					$this->text_domain = $text_domain;

					$parent_properties = array_intersect(array_keys( get_class_vars( 'ET_Builder_Element' ) ), array_keys( get_object_vars( $this->wrapped_element ) ));
					foreach ( $parent_properties as $parent_property ) {
						unset( $this->$parent_property );
					}

					$visibility_field_definition = array(
						'label' => __( 'Content Visibility', $this->text_domain ),
						'type'  => 'text',
						'option_category' => 'layout',
						'tab_slug' => 'custom_css',
						'toggle_slug' => 'visibility',
						'description' => __( 'Enter a boolean expression which evaluates to true when you want to display this element, or leave blank to always display it.', $this->text_domain ),
						'priority' => 1,
					);

					if ( method_exists( $this->wrapped_element, '_set_fields_unprocessed' ) ) {
						if ( !isset( $this->wrapped_element->fields_unprocessed['cvdb_content_visibility_check'] ) ) {
							$this->wrapped_element->_set_fields_unprocessed( array(
								'cvdb_content_visibility_check' => $visibility_field_definition
							) );
						}
					} else if ( !isset( $this->wrapped_element->fields_unprocessed['cvdb_content_visibility_check'] ) ) {
						$this->wrapped_element->fields_unprocessed['cvdb_content_visibility_check'] = $visibility_field_definition;

						if ( property_exists( $this->wrapped_element, 'whitelisted_fields' ) ) {
							$this->wrapped_element->whitelisted_fields['cvdb_content_visibility_check'] = array();
						}
					}

					do_action( $this->plugin_key . '_setup_' . $this->tag, $this->wrapped_element );

					add_shortcode( $this->tag, array( $this, 'medb_execute' ) );
				}

				public function medb_execute( $atts, $content, $function_name, $parent_address = '', $global_parent = '', $global_parent_type = '', $parent_type = '', $theme_builder_area = '' ) {
					return apply_filters( $this->plugin_key . '_shortcode_' . $this->tag, call_user_func( array( $this->wrapped_element, $this->wrapped_element_shortcode_callback ), $atts, $content, $function_name, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area ), $atts, $content, $function_name, $this->wrapped_element, $this->wrapped_element_shortcode_callback );
				}

				public function __call($name, $args) {
					if ( $this->wrapped_element !== null ) {
						$result = call_user_func_array( array(
							$this->wrapped_element,
							$name
						), $args );
					}
				}

				public function &__get($name) {
					$result = null;
					if ( $this->wrapped_element !== null ) {
						$result = $this->wrapped_element->$name;
					}
					return $result;
				}

				public function __set($name, $value) {
					if ( $this->wrapped_element !== null ) {
						$this->wrapped_element->$name = $value;
					}
				}

				public function __isset($name) {
					$result = false;
					if ( $this->wrapped_element !== null ) {
						$result = isset( $this->wrapped_element->$name );
					}
					return $result;
				}

				public function __unset($name) {
					if ( $this->wrapped_element !== null ) {
						unset( $this->wrapped_element->$name );
					}
				}
			}
		}

		$medb_tags = array();

		// Find the manager instance and use it to pre-populate potentially lazy-loaded shortcodes
		if ( class_exists( 'ET_Builder_Module_Shortcode_Manager' ) && isset( $GLOBALS['wp_filter']['pre_do_shortcode_tag'] ) ) {
			$manager = null;
			if ( version_compare( $wp_version, '4.7', '<' ) ) {
				foreach ( $GLOBALS['wp_filter']['pre_do_shortcode_tag'] as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof ET_Builder_Module_Shortcode_Manager ) {
							$manager = $callback['function'][0];
							break 2;
						}
					}
				}
			} else {
				foreach ( $GLOBALS['wp_filter']['pre_do_shortcode_tag']->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof ET_Builder_Module_Shortcode_Manager ) {
							$manager = $callback['function'][0];
							break 2;
						}
					}
				}
			}

			if ( $manager !== null ) {
				$medb_tags = $manager->add_module_slugs( $medb_tags );
			}
		}

		foreach ( $GLOBALS['shortcode_tags'] as $tag => $func ) {
			if ( is_array( $func ) && $func[0] instanceof ET_Builder_Element ) {
				$medb_et_pb_children[$tag] = $func;
				remove_shortcode( $tag, $func );
				$medb_tags[] = $tag;
			}
		}

		$medb_tags = apply_filters( $plugin_key . '_prevent_texturize_shortcodes', array_unique( $medb_tags ) );

		add_filter( 'no_texturize_shortcodes', function( $default_no_texturize_shortcodes ) use ( $medb_tags ) {
			return array_unique( array_merge( $default_no_texturize_shortcodes, $medb_tags ) );
		} );

		// Something temporary until a better solution is found
		if ( function_exists( 'et_pb_is_pagebuilder_used' ) && apply_filters( $plugin_key . '_remove_wptexturize_from_builder_pages', true ) ) {
			add_filter( 'the_content', function( $content ) {
				if ( et_pb_is_pagebuilder_used( get_the_ID() ) ) {
					remove_filter( 'the_content', 'wptexturize' );
				}
				return $content;
			}, 9 );
		}

		// TODO: Lazy-loaded callables will not be included here!
		do_action( $plugin_key, $medb_et_pb_children );

		foreach ( $medb_et_pb_children as $tag => $func ) {
			new MEDB_ET_Builder_Element( $func, $tag, $plugin_key, $text_domain );
		}

		if ( $is_saving_cache && method_exists( 'ET_Builder_Element', 'save_cache' ) ) {
			ET_Builder_Element::save_cache();
		}

		add_action( 'et_builder_module_loaded', function( $tag, $module ) use ( $text_domain, $plugin_key ) {
			$func = $GLOBALS['shortcode_tags'][$tag];
			$medb_et_pb_children[$tag] = $func;
			remove_shortcode( $tag, $func );
			new MEDB_ET_Builder_Element( $func, $tag, $plugin_key, $text_domain );
		}, 10, 2 );
	}, 1337 );

	add_action( 'admin_menu', function() use ( $text_domain, &$medb_et_pb_children, $wp_version ) {
		$medb_text_domain = 'module-extender-for-divi-builder';
		add_submenu_page(
			'tools.php',
			__( 'Module Extender for Divi Builder API Reference', $text_domain ),
			__( 'Module Extender API Reference', $text_domain ),
			'manage_options',
			$medb_text_domain . '-api-reference',
			function() use ( $text_domain, $medb_text_domain, &$medb_et_pb_children, $wp_version ) {
				if ( version_compare( $wp_version, '3.8', '<' ) ) {
					screen_icon( 'edit-pages' );
				}
				$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
?>
<div class="wrap">
	<h1><?php _e( 'Module Extender for Divi Builder API Reference', $text_domain ); ?></h1>
	<h2 class="title"><?php _e( 'The Basics', $text_domain ); ?></h2>
	<p><?php _e( 'Module Extender for Divi Builder provides several WordPress actions and filters to allow customization/extension of installed Divi Builder modules.', $text_domain ); ?></p>
	<p><?php _e( 'The following tabs provide detailed information about these actions and filters, along with a list of module-specific actions and filters for those modules currently installed on the system. Enjoy!', $text_domain ); ?></p>
	<h2 class="nav-tab-wrapper"><?php
				$all_tabs = array(
					'general' => __( 'General Actions and Filters', $text_domain ),
					'specific' => __( 'Module-Specific Actions and Filters', $text_domain ),
					'available' => __( 'Currently Available Module-Specific Actions and Filters', $text_domain ),
				);
				foreach ( $all_tabs as $tab_key => $tab_caption ) {
					$active = $tab == $tab_key ? ' nav-tab-active' : '';
?>
		<a class="nav-tab<?php echo $active; ?>" href="?page=<?php echo $medb_text_domain . '-api-reference' ?>&tab=<?php echo $tab_key; ?>"><?php echo $tab_caption; ?></a><?php
				}
?>
	</h2><?php
				if ( $tab === 'general' ) {
?>
		<h2 class="title"><?php /* translators: 1: the WordPress action string, 2: the WordPress action parameter list */ printf( __( 'Action: %1$s<br>Parameters:<br>%2$s', $text_domain), '<code>content_visibility_for_divi_builder</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: Array of Module shortcode callables; array keys are the callables&#8217; shortcode tags', $text_domain), '<code>$medb_et_pb_children</code>' ) ); ?></h2>
		<p><?php _e( 'Useful for enumerating available Module shortcode tags.', $text_domain ); ?></p>
		<hr>
		<h2 class="title"><?php /* translators: 1: the WordPress filter string, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', $text_domain), '<code>content_visibility_for_divi_builder_prevent_texturize_shortcodes</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: Array of available Module shortcodes', $text_domain ), '<code>$medb_tags</code>' ) ); ?></h2>
		<p><?php _e( 'Will by default pass all Module shortcodes to the "no_texturize_shortcodes" standard WordPress filter. You may use this filter to remove some or all of the Modules from the array merged into "no_texturize_shortcodes".', $text_domain ); ?></p>
		<hr>
		<h2 class="title"><?php /* translators: 1: the WordPress filter string, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', $text_domain), '<code>content_visibility_for_divi_builder_remove_wptexturize_from_builder_pages</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: boolean indicating whether wptexturize should be removed from the "the_content" built-in WordPress filter for any post_type using Divi Builder; defaults to true', $text_domain ), '<code>$remove_wptexturize</code>' ) ); ?></h2>
		<p><?php _e( 'You may return false in this filter to allow wp_texturize on "the_content" filter for Divi Builder post_types.', $text_domain ); ?></p><?php
				} else if ( $tab === 'specific' ) {
?>
		<h2 class="title"><?php /* translators: 1: the WordPress action string pattern, 2: the WordPress action parameter list */ printf( __( 'Action: %1$s<br>Parameters:<br>%2$s', $text_domain), '<code>content_visibility_for_divi_builder_setup_&lt;module_shortcode&gt;</code>', /* translators: 1: the first parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: The Module&#8217;s class instance', $text_domain ), '<code>$et_pb_element</code>' ) ); ?></h2>
		<p><?php _e( 'Executes for each Module, allowing you to use it as necessary (e.g. modify the class instance&#8217;s public variables)', $text_domain ); ?></p>
		<hr>
		<h2 class="title"><?php /* translators: 1: the WordPress filter string pattern, 2: the WordPress filter parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', $text_domain), '<code>content_visibility_for_divi_builder_shortcode_&lt;module_shortcode&gt;</code>', /* translators: 1: the first parameter's variable name, 2: the second parameter's variable name, 3: the third parameter's variable name, 4: the fourth parameter's variable name, 5: the fifth parameter's variable name, 6: the sixth parameter's variable name */ sprintf( __( '&nbsp; &nbsp; %1$s: The HTML to output for this Module&#8217;s shortcode<br>&nbsp; &nbsp; %2$s: The attributes of the shortcode (first standard WordPress shortcode callback parameter)<br>&nbsp; &nbsp; %3$s: the content of the shortcode (second standard WordPress shortcode callback parameter)<br>&nbsp; &nbsp; %4$s: the function_name of the shortcode (third standard WordPress shortcode callback parameter)<br>&nbsp; &nbsp; %5$s: The Module&#8217;s class instance<br>&nbsp; &nbsp; %6$s: The function name that is called by default on the Module&#8217;s class instance to handle this shortcode', $text_domain ), '<code>$result</code>', '<code>$atts</code>', '<code>$content</code>', '<code>$function_name</code>', '<code>$et_pb_element</code>', '<code>$et_pb_shortcode_callback</code>' ) ); ?></h2>
		<p><?php _e( 'Executes within each Module&#8217;s shortcode handler, allowing you to modify the output.', $text_domain ); ?></p><?php
				} else if ( $tab === 'available' ) {
					foreach ( $medb_et_pb_children as $tag => $func ) {
					$et_pb_element = $func[0];
?>
			<h2 class="title"><?php printf( /* translators: 1: the Module's name */ __( 'Module Name: %1$s', $text_domain ), $et_pb_element->name ); ?></h2>
			<h3>&nbsp; &nbsp; <?php printf( /* translators: 1: the Module-Specific action */ __( 'Action: %1$s', $text_domain ), "<code>content_visibility_for_divi_builder_setup_$tag</code>" ); ?></h3>
			<h3>&nbsp; &nbsp; <?php printf( /* translators: 1: the Module-Specific filter */ __( 'Filter: %1$s', $text_domain ), "<code>content_visibility_for_divi_builder_shortcode_$tag</code>" ); ?></h3>
			<hr><?php
					}
				}
?>
</div><?php
			}
		);

		add_action( 'load-tools_page_' . $medb_text_domain . '-api-reference', function() use ( $medb_text_domain ) {
			global $pagenow;

			$screen = get_current_screen();
			if ( $pagenow === 'tools.php' && $screen->id === 'tools_page_' . $medb_text_domain . '-api-reference' && function_exists( 'et_builder_should_load_framework' ) && !et_builder_should_load_framework() ) {
				if ( file_exists( ET_BUILDER_DIR . 'layouts.php' ) ) {
					if ( !class_exists( 'ET_Builder_Element' ) ) {
						require ET_BUILDER_DIR . 'layouts.php';
						require ET_BUILDER_DIR . 'class-et-builder-element.php';
						require ET_BUILDER_DIR . 'class-et-global-settings.php';
	
						do_action( 'et_builder_framework_loaded' );
	
						et_builder_init_global_settings();
						et_builder_add_main_elements();
					}
				} else {
					if ( !class_exists( 'ET_Builder_Plugin_Compat_Base' ) ) {
						require ET_BUILDER_DIR . 'class-et-builder-plugin-compat-base.php';
						require ET_BUILDER_DIR . 'class-et-builder-plugin-compat-loader.php';
						require ET_BUILDER_DIR . 'class-et-builder-settings.php';
					}

					if ( file_exists( ET_BUILDER_DIR . 'class-et-builder-theme-compat-base.php' ) && !class_exists( 'ET_Builder_Theme_Compat_Base' ) ) {
						require ET_BUILDER_DIR . 'class-et-builder-theme-compat-base.php';
						require ET_BUILDER_DIR . 'class-et-builder-theme-compat-handler.php';
					}

					
					if ( file_exists( ET_BUILDER_DIR . 'class-et-builder-value.php' ) ) {
						require ET_BUILDER_DIR . 'class-et-builder-value.php';
					}

					if ( file_exists( ET_BUILDER_DIR . 'class-et-builder-element.php' ) && !class_exists( 'ET_Builder_Element' ) ) {
						require ET_BUILDER_DIR . 'class-et-builder-element.php';
					}

					foreach (array(
						'ab-testing.php',
						'class-et-builder-post-feature-base.php',
						'class-et-builder-global-feature-base.php',
						'class-et-builder-module-features.php',
						'class-et-builder-google-fonts-feature.php',
						'class-et-builder-dynamic-assets-feature.php',
						'class-et-builder-module-shortcode-manager.php'
					) as $file) {
						if ( file_exists( ET_BUILDER_DIR . $file ) ) {
							require ET_BUILDER_DIR . $file;
						}
					}

					do_action( 'et_builder_framework_loaded' );

					add_filter( 'et_builder_should_load_all_module_data', '__return_true' );

					if ( !did_action( 'wp_loaded' ) ) {
						add_action( 'wp_loaded', 'et_builder_init_global_settings', apply_filters( 'et_pb_load_global_settings_priority', 9 )  );
						add_action( 'wp_loaded', 'et_builder_add_main_elements', apply_filters( 'et_pb_load_main_elements_priority', 10 ) );
					} else {
						et_builder_init_global_settings();
						et_builder_add_main_elements();
					}
				}
			}
		} );
	} );
 } );
