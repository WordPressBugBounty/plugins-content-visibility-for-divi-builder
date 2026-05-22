<?php

namespace AoDTechnologies\ContentVisibilityForDiviBuilder;

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
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

	protected static $underscore_text_domain;
	protected static $validation_option_key;

	protected $show_rating_notice_option_key;
	protected $is_saving_cache = false;

	public static function get_version() {
		return '5.00';
	}

	public static function get_text_domain() {
		return 'content-visibility-for-divi-builder';
	}

	public static function get_name() {
		return __( 'Content Visibility For Divi Builder', self::get_text_domain() );
	}

	public static function get_underscore_text_domain() {
		return self::$underscore_text_domain;
	}

	public static function init() {
		if (self::$initialized) {
			return;
		}

		self::$wp_version = get_bloginfo( 'version' );
		self::$underscore_text_domain = str_replace( '-', '_', self::get_text_domain() );
		self::$validation_option_key = self::$underscore_text_domain . '_expression_validation_enabled';
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
		delete_option( self::$underscore_text_domain . '_authentication_tokens' );
	}

	public function __construct($actions_and_filters_priority = 10) {
		require_once plugin_dir_path( CVDB_PLUGIN ) . 'includes/security-scanner.class.php';
		SecurityScanner::init();

		$this->show_rating_notice_option_key = self::$underscore_text_domain . '_show_rating_notice';

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
		$stored_version = get_option( self::$underscore_text_domain . '_version' );

		if ( $stored_version === false ) {
			// New install — validation on by default
			update_option( self::$validation_option_key, '1' );
		} else if ( version_compare( $stored_version, '5.00', '<' ) ) {
			// Upgrade — validation pending, don't overwrite if already set
			if ( get_option( self::$validation_option_key ) === false ) {
				update_option( self::$validation_option_key, '0' );
			}

			// Rename the per-user rating-notice key from the legacy mixed-case form
			// (`<udt>_show-rating-notice`) to the standardized fully-underscored form
			// (`<udt>_show_rating_notice`). Single SQL UPDATE migrates every user at once.
			global $wpdb;
			$old_key = self::$underscore_text_domain . '_show-rating-notice';
			$new_key = self::$underscore_text_domain . '_show_rating_notice';
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s",
				$new_key,
				$old_key
			) );
		}

		// Migration code for already active plugins
		if (self::get_version() !== $stored_version) {
			update_option( self::$underscore_text_domain . '_version', self::get_version() );

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

		add_filter( self::$underscore_text_domain . '_prevent_texturize_shortcodes', array( $this, 'prevent_texturize_shortcodes' ) );

		add_action( 'et_builder_modules_loaded', array( $this, 'detect_saving_cache' ), 0 );

		add_action( 'et_builder_ready', array( $this, 'hook_into_builder_shortcodes' ), 1337 );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			add_filter( 'plugin_action_links_' . plugin_basename( CVDB_PLUGIN ), array( $this, 'plugin_action_links' ), 1337 );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 11 );

			add_action( 'admin_menu', array( $this, 'add_menu_items' ) );

			add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

			add_action( 'admin_init', array( $this, 'handle_validation_toggle' ) );

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

	public static function is_eval_available() {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}
		try {
			$cached = ( \cvdb_eval_expression( '42' ) === 42 );
		} catch ( \Throwable $e ) {
			$cached = false;
		}
		return $cached;
	}

	public static function get_allowed_callables() {
		return apply_filters( self::$underscore_text_domain . '_allowed_callables', array(
			// WP conditional tags — pure read-only context queries (default allowlist).
			// Site admins extend this list via the filter to opt in custom helpers.
			'is_user_logged_in', 'current_user_can', 'is_admin', 'is_super_admin',
			'is_singular', 'is_single', 'is_page', 'is_home', 'is_front_page',
			'is_archive', 'is_category', 'is_tag', 'is_author', 'is_search',
			'is_404', 'is_attachment', 'is_post_type_archive', 'is_tax',
			'is_main_query', 'is_feed', 'is_rtl', 'wp_is_mobile',
			'has_tag', 'has_term', 'has_category', 'in_category',
			'get_current_user_id', 'get_the_ID', 'comments_open', 'pings_open',
		) );
	}

	public static function normalize_callable_name( $name ) {
		$name = (string) $name;
		// Strip leading literal `namespace\` keyword (case-insensitive on the keyword)
		if ( strncasecmp( $name, 'namespace\\', 10 ) === 0 ) {
			$name = substr( $name, 10 );
		}
		// Strip leading `\` (fully-qualified marker)
		$name = ltrim( $name, '\\' );
		// Class & function names are case-insensitive in PHP — lowercase for stable matching
		return strtolower( $name );
	}

	/**
	 * Pre-process a token stream so multi-token namespaced names emitted by
	 * the PHP 7.x tokenizer (e.g. `\Foo\Bar` → 4 tokens) collapse into a
	 * single synthetic `T_STRING` carrying the full qualified name. PHP 8.x
	 * already emits single tokens (T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED,
	 * T_NAME_RELATIVE) so most chains pass through; this only fires on PHP 7
	 * or on rare residual `T_NS_SEPARATOR T_STRING` patterns.
	 */
	public static function merge_namespace_tokens( $tokens ) {
		$result = array();
		$count = count( $tokens );
		$i = 0;
		while ( $i < $count ) {
			$tok = $tokens[ $i ];
			$is_array_tok = is_array( $tok );
			$is_ns_sep = $is_array_tok && $tok[0] === T_NS_SEPARATOR;
			$is_namespace_kw = $is_array_tok && $tok[0] === T_NAMESPACE;
			$is_string = $is_array_tok && $tok[0] === T_STRING;

			if ( !( $is_ns_sep || $is_namespace_kw || $is_string ) ) {
				$result[] = $tok;
				$i++;
				continue;
			}

			$start = $i;
			$name = '';
			$line = $is_array_tok && isset( $tok[2] ) ? $tok[2] : 0;

			if ( $is_namespace_kw ) {
				$name = 'namespace';
				$i++;
				if ( $i >= $count || ! is_array( $tokens[ $i ] ) || $tokens[ $i ][0] !== T_NS_SEPARATOR ) {
					// `namespace` keyword used standalone — emit as-is, will fail token allowlist anyway
					$result[] = $tokens[ $start ];
					$i = $start + 1;
					continue;
				}
				$name .= '\\';
				$i++;
			} elseif ( $is_ns_sep ) {
				$name = '\\';
				$i++;
			}

			if ( $i >= $count || ! is_array( $tokens[ $i ] ) || $tokens[ $i ][0] !== T_STRING ) {
				$result[] = $tokens[ $start ];
				$i = $start + 1;
				continue;
			}

			$name .= $tokens[ $i ][1];
			$i++;

			while ( $i + 1 < $count
				&& is_array( $tokens[ $i ] ) && $tokens[ $i ][0] === T_NS_SEPARATOR
				&& is_array( $tokens[ $i + 1 ] ) && $tokens[ $i + 1 ][0] === T_STRING ) {
				$name .= '\\' . $tokens[ $i + 1 ][1];
				$i += 2;
			}

			if ( $i === $start + 1 && $is_string ) {
				// Unchanged plain T_STRING — emit original
				$result[] = $tokens[ $start ];
			} else {
				$result[] = array( T_STRING, $name, $line );
			}
		}
		return $result;
	}

	public static function get_blocked_functions() {
		return apply_filters( self::$underscore_text_domain . '_blocked_functions', array(
			// Command execution
			'exec', 'system', 'shell_exec', 'passthru', 'proc_open', 'popen', 'pcntl_exec',
			// Code execution
			'eval', 'assert', 'create_function', 'call_user_func', 'call_user_func_array',
			'preg_replace_callback', 'array_map', 'array_filter', 'array_walk', 'array_walk_recursive',
			'usort', 'uasort', 'uksort', 'register_shutdown_function', 'register_tick_function', 'ob_start',
			// File I/O
			'file_put_contents', 'fwrite', 'fputs', 'fopen', 'unlink', 'rename', 'copy', 'mkdir', 'rmdir',
			'chmod', 'chown', 'chgrp', 'symlink', 'link', 'tmpfile', 'tempnam', 'touch',
			'file_get_contents', 'file', 'readfile', 'fread', 'fgets', 'fgetc', 'fpassthru',
			'highlight_file', 'show_source', 'php_strip_whitespace',
			// Network
			'curl_init', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
			'stream_socket_client', 'mail', 'wp_mail', 'wp_remote_get', 'wp_remote_post',
			'wp_remote_request', 'wp_remote_head', 'wp_safe_remote_get', 'wp_safe_remote_post',
			'wp_safe_remote_request', 'wp_safe_remote_head', 'download_url',
			// WP write operations
			'wp_insert_user', 'wp_create_user', 'wp_delete_user', 'wp_update_user',
			'wp_set_auth_cookie', 'wp_set_current_user', 'wp_set_password', 'wp_logout',
			'wp_insert_post', 'wp_update_post', 'wp_delete_post', 'wp_trash_post',
			'update_option', 'delete_option', 'add_option',
			'update_user_meta', 'delete_user_meta', 'add_user_meta',
			'update_post_meta', 'delete_post_meta', 'add_post_meta',
			// Output/info
			'header', 'setcookie', 'setrawcookie', 'phpinfo', 'php_uname', 'getenv', 'putenv',
			'ini_set', 'ini_alter', 'ini_restore', 'dl',
			'get_defined_functions', 'get_defined_vars', 'get_defined_constants',
		) );
	}

	public static function validate_expression( $expression ) {
		$blocked_functions = self::get_blocked_functions();
		$allowed_callables = array_map( array( __CLASS__, 'normalize_callable_name' ), self::get_allowed_callables() );

		$allowed_tokens = apply_filters( self::$underscore_text_domain . '_allowed_tokens', array(
			T_STRING,
			T_LNUMBER, T_DNUMBER, T_CONSTANT_ENCAPSED_STRING,
			T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR,
			T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL,
			T_IS_GREATER_OR_EQUAL, T_IS_SMALLER_OR_EQUAL, T_COALESCE,
			T_DOUBLE_COLON, T_OBJECT_OPERATOR,
			T_NS_SEPARATOR, T_ARRAY, T_DOUBLE_ARROW, T_WHITESPACE,
		) );

		// PHP 8+ token constants
		if ( defined( 'T_NULLSAFE_OPERATOR' ) ) {
			$allowed_tokens[] = T_NULLSAFE_OPERATOR;
		}
		if ( defined( 'T_NAME_QUALIFIED' ) ) {
			$allowed_tokens[] = T_NAME_QUALIFIED;
		}
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
			$allowed_tokens[] = T_NAME_FULLY_QUALIFIED;
		}
		if ( defined( 'T_NAME_RELATIVE' ) ) {
			$allowed_tokens[] = T_NAME_RELATIVE;
		}

		$allowed_chars = apply_filters( self::$underscore_text_domain . '_allowed_chars', array(
			'(', ')', ',', '!', '<', '>', '+', '-', '*', '/', '%', '.', '?', ':', '[', ']',
		) );

		$tokens = token_get_all( '<?php ' . $expression );
		array_shift( $tokens ); // drop the opening <?php

		// Drop whitespace tokens — they're never significant for what we check
		$tokens = array_values( array_filter( $tokens, function( $t ) {
			return ! ( is_array( $t ) && $t[0] === T_WHITESPACE );
		} ) );

		// Collapse PHP 7's multi-token namespaced names into single synthetic T_STRING tokens
		$tokens = self::merge_namespace_tokens( $tokens );

		$count = count( $tokens );
		$prev_significant_token = null;

		// Token types that introduce a callable name
		$name_token_types = array( T_STRING );
		if ( defined( 'T_NAME_QUALIFIED' ) )       $name_token_types[] = T_NAME_QUALIFIED;
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) $name_token_types[] = T_NAME_FULLY_QUALIFIED;
		if ( defined( 'T_NAME_RELATIVE' ) )        $name_token_types[] = T_NAME_RELATIVE;

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( is_array( $token ) ) {
				$token_type = $token[0];
				$token_value = $token[1];

				if ( !in_array( $token_type, $allowed_tokens, true ) ) {
					return sprintf( 'Disallowed token type: %s ("%s")', token_name( $token_type ), $token_value );
				}

				$is_name = in_array( $token_type, $name_token_types, true );
				$next = $i + 1 < $count ? $tokens[ $i + 1 ] : null;

				if ( $is_name ) {
					// Static method call: <Name> :: <Name> (
					if ( is_array( $next ) && $next[0] === T_DOUBLE_COLON ) {
						$method_tok = $i + 2 < $count ? $tokens[ $i + 2 ] : null;
						$after = $i + 3 < $count ? $tokens[ $i + 3 ] : null;
						if ( is_array( $method_tok ) && $method_tok[0] === T_STRING && $after === '(' ) {
							$callable = $token_value . '::' . $method_tok[1];
							$normalized = self::normalize_callable_name( $callable );
							if ( in_array( $normalized, $blocked_functions, true ) ) {
								return sprintf( 'Blocked function: %s', $callable );
							}
							if ( !in_array( $normalized, $allowed_callables, true ) ) {
								return sprintf( 'Unknown callable: %s — not on the allowlist. Contact the site administrator if it should be added.', $callable );
							}
							$prev_significant_token = $tokens[ $i + 2 ];
							$i += 2;
							continue;
						}
						// Class::CONSTANT (no parens) — read-only access; allowed. Walk through.
						$prev_significant_token = $token;
						continue;
					}

					// Function call: <Name> (
					if ( $next === '(' ) {
						$normalized = self::normalize_callable_name( $token_value );
						if ( in_array( $normalized, $blocked_functions, true ) ) {
							return sprintf( 'Blocked function: %s', $token_value );
						}
						if ( !in_array( $normalized, $allowed_callables, true ) ) {
							return sprintf( 'Unknown callable: %s — not on the allowlist. Contact the site administrator if it should be added.', $token_value );
						}
						$prev_significant_token = $token;
						continue;
					}

					// Bare name — only allowed if it's a literal or the second part of Class::X
					$preceded_by_double_colon = is_array( $prev_significant_token ) && $prev_significant_token[0] === T_DOUBLE_COLON;
					$lower_value = strtolower( $token_value );
					if ( $preceded_by_double_colon || $lower_value === 'true' || $lower_value === 'false' || $lower_value === 'null' ) {
						$prev_significant_token = $token;
						continue;
					}
					return sprintf( 'Unknown identifier: %1$s — must be a function call (e.g. %1$s()), static method, class constant, or a true/false/null literal.', $token_value );
				}

				// Instance method call: -> <Name> (
				$is_object_op = $token_type === T_OBJECT_OPERATOR ||
					( defined( 'T_NULLSAFE_OPERATOR' ) && $token_type === T_NULLSAFE_OPERATOR );
				if ( $is_object_op ) {
					$name_tok = $next;
					$after = $i + 2 < $count ? $tokens[ $i + 2 ] : null;
					if ( is_array( $name_tok ) && $name_tok[0] === T_STRING && $after === '(' ) {
						return sprintf( 'Instance method call ->%s() cannot be allowlisted — rewrite as a static helper', $name_tok[1] );
					}
				}

				if ( $token_type === T_CONSTANT_ENCAPSED_STRING ) {
					$prev_significant_token = $token;
					continue;
				}

				$prev_significant_token = $token;
			} else {
				if ( !in_array( $token, $allowed_chars, true ) ) {
					return sprintf( 'Disallowed character: "%s"', $token );
				}

				// Anything-as-callable: when `(` follows a value that isn't a name token, the
				// thing being called is the result of an expression and can't be tied to an
				// allowlistable callable. Catches:
				//   'phpinfo'()              — string-as-callable
				//   ('phpinfo')()            — parenthesized string
				//   ('php' . 'info')()       — concatenation result
				//   func()()                 — chained call (call returns callable, then call)
				//   ['phpinfo'][0]()         — array literal indexed then called
				if ( $token === '(' && $prev_significant_token !== null ) {
					if ( is_array( $prev_significant_token ) && $prev_significant_token[0] === T_CONSTANT_ENCAPSED_STRING ) {
						return sprintf( 'String used as callable: %s', $prev_significant_token[1] );
					}
					if ( $prev_significant_token === ')' ) {
						return 'Call invocation on a non-name expression — `(...)()` cannot be allowlisted; rewrite as a static helper';
					}
					if ( $prev_significant_token === ']' ) {
						return 'Call invocation on an array element — `[...]()` cannot be allowlisted; rewrite as a static helper';
					}
				}

				$prev_significant_token = $token;
			}
		}

		return true;
	}

	public static function evaluate_visibility_expression($expression, $type, $data) {
		$visibility = true;

		$validation_enabled = get_option( self::$validation_option_key );
		if ( $validation_enabled === '1' ) {
			$validation_result = self::validate_expression( $expression );
			if ( $validation_result !== true ) {
				global $wp;
				global $wp_filesystem;

				try {
					$error_message_format = "A visibility expression has been BLOCKED by expression validation.\nThe content will be shown by default (not hidden).\n\nPage URL:\n%1\$s\n\nVisibility expression:\n%2\$s\n\nValidation error:\n%3\$s";

					wp_mail( get_bloginfo( 'admin_email' ), '[' . get_bloginfo( 'name' ) . '] Content Visibility for Divi Builder - Blocked Expression Detected', sprintf( $error_message_format, home_url( add_query_arg( isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : array(), '', isset( $wp->request ) ? $wp->request : '' ) ), $expression, $validation_result ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
				} catch ( \Exception $e ) {
					// Silently fail if email cannot be sent
				}

				return $visibility;
			}
		}

		try {
			$visibility = (bool) \cvdb_eval_expression( $expression );
		} catch (\ParseError | \Error $error) {
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

		$cvdb_tags = apply_filters( self::$underscore_text_domain . '_prevent_texturize_shortcodes', array_unique( $cvdb_tags ) );

		add_filter( 'no_texturize_shortcodes', function( $default_no_texturize_shortcodes ) use ( $cvdb_tags ) {
			return array_unique( array_merge( $default_no_texturize_shortcodes, $cvdb_tags ) );
		} );

		// Something temporary until a better solution is found
		if ( function_exists( '\et_pb_is_pagebuilder_used' ) && apply_filters( self::$underscore_text_domain . '_remove_wptexturize_from_builder_pages', true ) ) {
			add_filter( 'the_content', array( $this, 'remove_wptexturize_from_builder_content' ), 9 );
		}

		// TODO: Lazy-loaded callables will not be included here (unless we are on our API reference page)!
		do_action( self::$underscore_text_domain, self::$cvdb_et_pb_children );

		foreach ( self::$cvdb_et_pb_children as $tag => $func ) {
			new CVDB_ET_Builder_Element( $func, $tag, self::$underscore_text_domain, self::get_text_domain() );
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
						self::$underscore_text_domain . '_block_render_callback',
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
					self::$underscore_text_domain . '_block_render_callback',
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
				self::$underscore_text_domain . '_block_render_callback',
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
		new CVDB_ET_Builder_Element( $func, $tag, self::$underscore_text_domain, self::get_text_domain() );
	}

	public function enqueue_scripts() {
		if ( self::$is_using_divi_builder_5 ) {
			if ( did_action( 'divi_visual_builder_initialize' ) && self::$has_ET_Builder_Framework_Utility_Conditions && \ET\Builder\Framework\Utility\Conditions::is_vb_app_window() ) {
				// Adds the Content Visibility field to Divi 5's editor
				wp_enqueue_script( self::get_text_domain() . '_gutenberg-filters', plugins_url( '/js/gutenberg-filters.js', CVDB_PLUGIN ), array( 'divi-vendor-wp-hooks', 'divi-vendor-react', 'divi-field-library', 'wp-api-fetch' ), self::get_version(), true );
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
		wp_enqueue_script( self::get_text_domain() . '_admin-script', plugins_url( '/js/admin.js' , CVDB_PLUGIN ), array( 'jquery', 'wp-api-fetch' ), self::get_version() );
		wp_localize_script( self::get_text_domain() . '_admin-script', 'cvdbAdminScript', array(
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

	public function register_rest_routes() {
		register_rest_route( 'cvdb/v1', '/notices/rating/dismiss', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => function() {
				$current_user_id = get_current_user_id();

				return $current_user_id !== 0 && current_user_can( 'edit_user', $current_user_id );
			},
			'callback'            => array( $this, 'rest_dismiss_rating_notice' ),
		) );
	}

	public function rest_dismiss_rating_notice() {
		update_user_option( get_current_user_id(), $this->show_rating_notice_option_key, '0' );
		return new \WP_REST_Response( null, 204 );
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
			'security' => __( 'Expression Validation', self::get_text_domain() ),
			'validation-filters' => __( 'Expression Validation Filters', self::get_text_domain() ),
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
		} else if ( $tab === 'security' ) {
			$validation_enabled = get_option( self::$validation_option_key );
?>
<h2 class="title"><?php _e( 'Expression Validation', self::get_text_domain() ); ?></h2>
<p><?php _e( 'Expression validation prevents potentially dangerous PHP code from being executed via visibility expressions. When enabled, all expressions are checked against an allowlist of safe tokens and a denylist of dangerous functions before evaluation.', self::get_text_domain() ); ?></p>

<h3><?php _e( 'Status', self::get_text_domain() ); ?></h3>
<?php if ( $validation_enabled === '1' ) { ?>
<p><strong style="color: #46b450;"><?php _e( 'Expression validation is active.', self::get_text_domain() ); ?></strong></p>
<p><?php _e( 'Use the scanner below to check your content for expressions that would be blocked by validation.', self::get_text_domain() ); ?></p>
<?php } else { ?>
<p><strong style="color: #dc3232;"><?php _e( 'Expression validation is not yet enabled.', self::get_text_domain() ); ?></strong></p>
<p><?php _e( 'Use the scanner below to check your content for expressions that would be blocked, then enable validation when ready.', self::get_text_domain() ); ?></p>
<?php } ?>

<?php SecurityScanner::render_scanner_section(); ?>

<?php if ( $validation_enabled !== '1' ) { ?>
<hr>
<h3><?php _e( 'Enable Validation', self::get_text_domain() ); ?></h3>
<p><?php _e( 'Once you have reviewed the scan results and resolved any flagged expressions, enable validation to protect your site. You can disable it again later if something unexpected breaks.', self::get_text_domain() ); ?></p>
<form method="post">
	<?php wp_nonce_field( self::get_text_domain() . '_enable_validation', '_cvdb_validation_nonce' ); ?>
	<input type="hidden" name="cvdb_enable_validation" value="1">
	<?php submit_button( __( 'Enable Validation', self::get_text_domain() ), 'primary', 'submit', true ); ?>
</form>
<?php } else { ?>
<hr>
<h3><?php _e( 'Disable Validation', self::get_text_domain() ); ?></h3>
<p><?php _e( 'If validation is causing unexpected behavior on your site, you can disable it temporarily while you investigate. While disabled, expressions are evaluated as-is — anything dangerous in your content will run.', self::get_text_domain() ); ?></p>
<form method="post" style="background:#fcf0f1;border-left:4px solid #d63638;padding:12px 16px;max-width:560px;">
	<p style="margin-top:0;"><?php printf( __( 'Type %s in the field below to confirm:', self::get_text_domain() ), '<code>DISABLE</code>' ); ?></p>
	<?php wp_nonce_field( self::get_text_domain() . '_disable_validation', '_cvdb_validation_nonce' ); ?>
	<input type="hidden" name="cvdb_disable_validation" value="1">
	<input type="text" name="cvdb_disable_confirmation" pattern="DISABLE" required autocomplete="off" placeholder="DISABLE" style="width:200px;font-family:monospace;text-transform:uppercase;" />
	<?php submit_button( __( 'Disable Validation', self::get_text_domain() ), 'delete', 'submit', false ); ?>
</form>
<?php
			}
		} else if ( $tab === 'validation-filters' ) {
?>
<h2 class="title"><?php _e( 'Expression Validation Filters', self::get_text_domain() ); ?></h2>
<p><?php _e( 'These filters let you adjust what the expression validator and the content scanner consider safe. Three of them affect both runtime evaluation and the scanner; one only affects scanner warnings. All accept and must return an array of strings (or token type constants for <code>allowed_tokens</code>).', self::get_text_domain() ); ?></p>
<hr>

<h2 class="title"><?php /* translators: 1: filter name, 2: parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain() ), '<code>content_visibility_for_divi_builder_blocked_functions</code>', sprintf( __( '&nbsp; &nbsp; %1$s: Array of lowercase function names that are blocked when present in any expression. Returning an unfiltered call to one of these names from an expression produces a hard validation error.', self::get_text_domain() ), '<code>$blocked_functions</code>' ) ); ?></h2>
<p><?php _e( 'Affects both runtime evaluation and the scanner. Use this to add organization-specific dangerous functions to the denylist. Note: <strong>removing</strong> entries weakens the security posture — only do so if you have audited the function in question.', self::get_text_domain() ); ?></p>
<p><?php _e( 'Example: block calls to a custom helper that performs writes.', self::get_text_domain() ); ?></p>
<pre><code>add_filter( 'content_visibility_for_divi_builder_blocked_functions', function( $names ) {
    $names[] = 'mytheme_force_login';
    return $names;
} );</code></pre>
<hr>

<h2 class="title"><?php /* translators: 1: filter name, 2: parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain() ), '<code>content_visibility_for_divi_builder_allowed_tokens</code>', sprintf( __( '&nbsp; &nbsp; %1$s: Array of PHP tokenizer type constants (e.g. <code>T_STRING</code>, <code>T_LNUMBER</code>) that are permitted to appear in expressions.', self::get_text_domain() ), '<code>$allowed_tokens</code>' ) ); ?></h2>
<p><?php _e( 'Affects both runtime evaluation and the scanner. Tokens not in this list cause validation to fail with "Disallowed token type". The default list permits identifiers, literals, comparison/logical operators, namespacing, and array syntax — but excludes things like <code>T_VARIABLE</code> (no <code>$vars</code>) and assignment operators.', self::get_text_domain() ); ?></p>
<hr>

<h2 class="title"><?php /* translators: 1: filter name, 2: parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain() ), '<code>content_visibility_for_divi_builder_allowed_chars</code>', sprintf( __( '&nbsp; &nbsp; %1$s: Array of single-character tokens (e.g. <code>(</code>, <code>)</code>, <code>,</code>) that are permitted to appear in expressions.', self::get_text_domain() ), '<code>$allowed_chars</code>' ) ); ?></h2>
<p><?php _e( 'Affects both runtime evaluation and the scanner. Characters not in this list cause validation to fail with "Disallowed character". The default list excludes characters that enable side effects (e.g. <code>;</code>, <code>=</code>, <code>$</code>, <code>` </code>).', self::get_text_domain() ); ?></p>
<hr>

<h2 class="title"><?php /* translators: 1: filter name, 2: parameter list */ printf( __( 'Filter: %1$s<br>Parameters:<br>%2$s', self::get_text_domain() ), '<code>content_visibility_for_divi_builder_allowed_callables</code>', sprintf( __( '&nbsp; &nbsp; %1$s: Array of lowercase callable names that the scanner considers known-safe and will not flag with a "custom callable" warning.', self::get_text_domain() ), '<code>$allowed_callables</code>' ) ); ?></h2>
<p><?php _e( 'Affects only the scanner output, not runtime evaluation. The validator can\'t inspect the body of a user-defined function or method, so any callable that isn\'t on this list (and isn\'t already blocked) is surfaced as a warning prompting manual review. Adding your own well-audited helpers here suppresses those warnings on subsequent scans.', self::get_text_domain() ); ?></p>
<p><?php _e( 'Example: trust your theme\'s helper after auditing it.', self::get_text_domain() ); ?></p>
<pre><code>add_filter( 'content_visibility_for_divi_builder_allowed_callables', function( $names ) {
    $names[] = 'mytheme_should_be_visible';
    return $names;
} );</code></pre>
<?php
		}
?>
</div><?php
	}

	public function render_admin_notices() {
		if ( get_user_option( $this->show_rating_notice_option_key ) === '1' ) {
			$rating_link = '<a href="' . esc_url( 'https://wordpress.org/support/view/plugin-reviews/' . self::get_text_domain() . '?rate=5#postform' ) . '" target="_blank" rel="noopener">' . _x( 'rating', 'present participle: I enjoyed rating the awesome WordPress plugin', self::get_text_domain() ) . '</a>';
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

		$validation_enabled = get_option( self::$validation_option_key );
		if ( $validation_enabled === '0' && current_user_can( 'manage_options' ) ) {
			$security_tab_url = admin_url( 'tools.php?page=' . self::get_text_domain() . '-api-reference&tab=security' );
?>
<div class="notice notice-warning"><p><?php
			printf(
				__( '<strong>%1$s:</strong> Expression validation is not yet enabled. Please review the <a href="%2$s">Expression Validation</a> tab to scan your content and enable validation.', self::get_text_domain() ),
				esc_html( self::get_name() ),
				esc_url( $security_tab_url )
			);
?></p></div>
<?php
		}

		if ( !self::is_eval_available() ) {
?>
<div class="notice notice-error">
	<p><strong><?php echo esc_html( self::get_name() ); ?>:</strong> <?php _e( 'PHP <code>eval()</code> appears to be disabled on this host (commonly via the Suhosin extension or a custom hardening policy). Visibility expressions cannot be evaluated until <code>eval()</code> is re-enabled. Contact your hosting provider, or remove this plugin if <code>eval()</code> cannot be restored.', self::get_text_domain() ); ?></p>
</div>
<?php
		}
	}

	public function handle_validation_toggle() {
		$enable  = isset( $_POST['cvdb_enable_validation'] )  && $_POST['cvdb_enable_validation']  === '1';
		$disable = isset( $_POST['cvdb_disable_validation'] ) && $_POST['cvdb_disable_validation'] === '1';
		if ( !$enable && !$disable ) {
			return;
		}

		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce_action = $enable
			? self::get_text_domain() . '_enable_validation'
			: self::get_text_domain() . '_disable_validation';
		if ( !isset( $_POST['_cvdb_validation_nonce'] ) || !wp_verify_nonce( $_POST['_cvdb_validation_nonce'], $nonce_action ) ) {
			return;
		}

		if ( $disable ) {
			$confirmation = isset( $_POST['cvdb_disable_confirmation'] ) ? trim( wp_unslash( $_POST['cvdb_disable_confirmation'] ) ) : '';
			if ( $confirmation === 'DISABLE' ) {
				update_option( self::$validation_option_key, '0' );
			}
		} else {
			update_option( self::$validation_option_key, '1' );
		}

		wp_safe_redirect( admin_url( 'tools.php?page=' . self::get_text_domain() . '-api-reference&tab=security' ) );
		exit;
	}

}

ContentVisibilityForDiviBuilder::init();
