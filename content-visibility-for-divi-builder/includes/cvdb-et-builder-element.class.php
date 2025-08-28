<?php

namespace AoDTechnologies\ContentVisibilityForDiviBuilder;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class CVDB_ET_Builder_Element extends \ET_Builder_Element {
	private $wrapped_element;
	private $wrapped_element_shortcode_callback;
	private $tag;
	private $underscore_text_domain;
	private $text_domain;
	private $visibility_field_definition;

	public function __construct($func, $tag, $underscore_text_domain, $text_domain) {
		$this->wrapped_element = $func[0];
		$this->wrapped_element_shortcode_callback = $func[1];
		$this->tag = $tag;
		$this->underscore_text_domain = $underscore_text_domain;
		$this->text_domain = $text_domain;

		$parent_properties = array_intersect(array_keys( get_class_vars( '\ET_Builder_Element' ) ), array_keys( get_object_vars( $this->wrapped_element ) ));
		foreach ( $parent_properties as $parent_property ) {
			unset( $this->$parent_property );
		}

		$this->visibility_field_definition = array(
			'label' => __( 'Content Visibility', $this->text_domain ),
			'type'  => 'text',
			'option_category' => 'layout',
			'tab_slug' => 'custom_css',
			'toggle_slug' => 'visibility',
			'description' => __( 'Enter a boolean expression which evaluates to true when you want to display this element, or leave blank to always display it.', $this->text_domain ),
			'priority' => 1337,
		);

		if ( method_exists( $this->wrapped_element, '_set_fields_unprocessed' ) ) {
			if ( !isset( $this->wrapped_element->fields_unprocessed['cvdb_content_visibility_check'] ) ) {
				$this->wrapped_element->_set_fields_unprocessed( array(
					'cvdb_content_visibility_check' => $this->visibility_field_definition
				) );
			}
		} else if ( !isset( $this->wrapped_element->fields_unprocessed['cvdb_content_visibility_check'] ) ) {
			$this->wrapped_element->fields_unprocessed['cvdb_content_visibility_check'] = $this->visibility_field_definition;

			if ( property_exists( $this->wrapped_element, 'whitelisted_fields' ) ) {
				$this->wrapped_element->whitelisted_fields['cvdb_content_visibility_check'] = array();
			}
		}

		// Even though we already added the Content Visibility field above, this is needed
		// by Divi 5 to ensure that Divi 4 modules in backwards compatibility mode will
		// display the Content Visibility field within the Divi 5 shortcode module
		add_filter( "et_pb_all_fields_unprocessed_{$this->wrapped_element->slug}", array( $this, 'cvdb_add_field_for_shortcode_definitions' ), 1337 );

		do_action( "{$this->underscore_text_domain}_setup_{$this->tag}", $this->wrapped_element );

		add_shortcode( $this->tag, array( $this, 'cvdb_execute' ) );
	}

	public function cvdb_add_field_for_shortcode_definitions($fields) {
		$fields['cvdb_content_visibility_check'] = $this->visibility_field_definition;

		return $fields;
	}

	public function cvdb_execute( $atts, $content, $function_name, $parent_address = '', $global_parent = '', $global_parent_type = '', $parent_type = '', $theme_builder_area = '' ) {
		if ( !isset( $atts['cvdb_content_visibility_check'] ) || trim( $atts['cvdb_content_visibility_check'] ) === '' ) {
			return apply_filters( "{$this->underscore_text_domain}_shortcode_{$this->tag}", call_user_func( array( $this->wrapped_element, $this->wrapped_element_shortcode_callback ), $atts, $content, $function_name, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area ), $atts, $content, $function_name, $this->wrapped_element, $this->wrapped_element_shortcode_callback );
		}

		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen();

			if ( $current_screen !== null && $current_screen->action === '' && $current_screen->id === $current_screen->post_type ) {
				return apply_filters( "{$this->underscore_text_domain}_shortcode_{$this->tag}", call_user_func( array( $this->wrapped_element, $this->wrapped_element_shortcode_callback ), $atts, $content, $function_name, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area ), $atts, $content, $function_name, $this->wrapped_element, $this->wrapped_element_shortcode_callback );
			}
		}

		if ( isset( $_REQUEST['action'] ) ) {
			switch ( sanitize_text_field( $_REQUEST['action'] ) ) {
				case 'et_fb_retrieve_builder_data':
				case 'et_fb_get_saved_templates':
					return apply_filters( "{$this->underscore_text_domain}_shortcode_{$this->tag}", call_user_func( array( $this->wrapped_element, $this->wrapped_element_shortcode_callback ), $atts, $content, $function_name, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area ), $atts, $content, $function_name, $this->wrapped_element, $this->wrapped_element_shortcode_callback );
				default:
					break;
			}
		}

		if ( function_exists( '\et_fb_is_enabled' ) && \et_fb_is_enabled() ) {
			return apply_filters( "{$this->underscore_text_domain}_shortcode_{$this->tag}", call_user_func( array( $this->wrapped_element, $this->wrapped_element_shortcode_callback ), $atts, $content, $function_name, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area ), $atts, $content, $function_name, $this->wrapped_element, $this->wrapped_element_shortcode_callback );
		}

		$visibility = ContentVisibilityForDiviBuilder::evaluate_visibility_expression( str_replace( array( '%22', '%5D' ), array( '"', ']' ), $atts['cvdb_content_visibility_check'] ), 'shortcode', $et_pb_element );

		if ( !$visibility ) {
			return '';
		}

		return apply_filters( "{$this->underscore_text_domain}_shortcode_{$this->tag}", call_user_func( array( $this->wrapped_element, $this->wrapped_element_shortcode_callback ), $atts, $content, $function_name, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area ), $atts, $content, $function_name, $this->wrapped_element, $this->wrapped_element_shortcode_callback );
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
