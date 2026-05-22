<?php

namespace AoDTechnologies\ContentVisibilityForDiviBuilder;

if ( !defined( 'WPINC' ) ) {
	die;
}

class SecurityScanner {
	const FINDINGS_META_KEY = '_content_visibility_for_divi_builder_validation_findings';

	private static $reflection_available = null;

	public static function is_reflection_available() {
		if ( self::$reflection_available !== null ) {
			return self::$reflection_available;
		}
		if (
			!extension_loaded( 'Reflection' ) ||
			!class_exists( '\ReflectionFunction', false ) ||
			!class_exists( '\ReflectionMethod', false )
		) {
			self::$reflection_available = false;
			return false;
		}
		try {
			// Smoke test: construct a Reflection on a function that always exists,
			// and call the methods we actually use. If anything throws (disable_classes,
			// other host hardening), treat Reflection as unavailable.
			$probe = new \ReflectionFunction( 'print_r' );
			$probe->getFileName();
			$probe->getStartLine();
			self::$reflection_available = true;
		} catch ( \Throwable $e ) {
			self::$reflection_available = false;
		}
		return self::$reflection_available;
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_scanner_script' ), 12 );

		// Publish gate
		add_filter( 'rest_pre_insert_post', array( __CLASS__, 'gate_rest_publish' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'gate_classic_publish' ), 10, 2 );

		// Save-time analysis + edit-screen notice (always-on backstop, regardless of validation toggle)
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'render_findings_notice' ) );
	}

	public static function on_save_post( $post_id, $post, $update ) {
		// Skip autosaves and revisions — only act on user-initiated saves of the live post
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( !is_object( $post ) || !isset( $post->post_content ) ) {
			return;
		}

		// Fast path: skip the expensive analysis when there are no cvdb markers in content at all
		if ( strpos( $post->post_content, 'cvdb_content_visibility_check' ) === false
			&& strpos( $post->post_content, 'contentVisibilityCheck' ) === false ) {
			delete_post_meta( $post_id, self::FINDINGS_META_KEY );
			return;
		}

		$errors = self::find_validation_errors( $post->post_content );
		if ( empty( $errors ) ) {
			delete_post_meta( $post_id, self::FINDINGS_META_KEY );
		} else {
			update_post_meta( $post_id, self::FINDINGS_META_KEY, $errors );
		}
	}

	public static function render_findings_notice() {
		global $pagenow;
		if ( $pagenow !== 'post.php' ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( ! $post_id || !current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$findings = get_post_meta( $post_id, self::FINDINGS_META_KEY, true );
		if ( empty( $findings ) || !is_array( $findings ) ) {
			return;
		}

		$is_strict = self::validation_enabled();
		$class = $is_strict ? 'notice-error' : 'notice-warning';
		$headline = $is_strict
			? sprintf(
				_n(
					'%d visibility expression failed validation and will be blocked at runtime:',
					'%d visibility expressions failed validation and will be blocked at runtime:',
					count( $findings ),
					ContentVisibilityForDiviBuilder::get_text_domain()
				),
				count( $findings )
			)
			: sprintf(
				_n(
					'%d visibility expression would be blocked when validation is enabled:',
					'%d visibility expressions would be blocked when validation is enabled:',
					count( $findings ),
					ContentVisibilityForDiviBuilder::get_text_domain()
				),
				count( $findings )
			);
		?>
		<div class="notice <?php echo esc_attr( $class ); ?>">
			<p><strong><?php _e( 'Content Visibility for Divi Builder', ContentVisibilityForDiviBuilder::get_text_domain() ); ?>:</strong> <?php echo esc_html( $headline ); ?></p>
			<ul style="margin-left:24px;list-style:disc;">
				<?php foreach ( $findings as $e ) :
					$module = $e['module_name'] !== '' ? $e['module_name'] : '(unknown module)';
					if ( $e['admin_label'] !== '' ) {
						$module .= ' "' . $e['admin_label'] . '"';
					}
				?>
				<li>
					<code><?php echo esc_html( $e['expression'] ); ?></code>
					<em style="color:#666;">in <?php echo esc_html( $module ); ?></em>
					<br>→ <?php echo esc_html( $e['error'] ); ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Find every expression in $content that fails validation. Returns an array
	 * of { expression, type, module_name, admin_label, error } entries — empty
	 * if everything validates.
	 */
	private static function find_validation_errors( $content ) {
		if ( !is_string( $content ) || $content === '' ) {
			return array();
		}
		$errors = array();
		foreach ( self::extract_expressions_from_content( $content ) as $expr ) {
			$result = ContentVisibilityForDiviBuilder::validate_expression( $expr['expression'] );
			if ( $result !== true ) {
				$errors[] = array(
					'expression'  => $expr['expression'],
					'type'        => $expr['type'],
					'module_name' => isset( $expr['module_name'] ) ? $expr['module_name'] : '',
					'admin_label' => isset( $expr['admin_label'] ) ? $expr['admin_label'] : '',
					'error'       => $result,
				);
			}
		}
		return $errors;
	}

	private static function validation_enabled() {
		return get_option( ContentVisibilityForDiviBuilder::get_underscore_text_domain() . '_expression_validation_enabled' ) === '1';
	}

	private static function format_publish_error_message( $errors ) {
		$lines = array();
		$lines[] = sprintf(
			_n(
				'%d visibility expression failed validation and is blocking the save:',
				'%d visibility expressions failed validation and are blocking the save:',
				count( $errors ),
				ContentVisibilityForDiviBuilder::get_text_domain()
			),
			count( $errors )
		);
		$lines[] = '';
		foreach ( $errors as $e ) {
			$module = $e['module_name'] !== '' ? $e['module_name'] : '(unknown module)';
			if ( $e['admin_label'] !== '' ) {
				$module .= ' "' . $e['admin_label'] . '"';
			}
			$lines[] = '• ' . $e['expression'];
			$lines[] = '    in ' . $module;
			$lines[] = '    → ' . $e['error'];
			$lines[] = '';
		}
		$lines[] = __( 'No changes were written — your existing post is unchanged. Fix or remove the offending expressions and save again.', ContentVisibilityForDiviBuilder::get_text_domain() );
		return implode( "\n", $lines );
	}

	private static function format_publish_error_html( $errors ) {
		$text_domain = ContentVisibilityForDiviBuilder::get_text_domain();
		$out  = '<h1>' . esc_html__( 'Save blocked by Content Visibility validation', $text_domain ) . '</h1>';
		$out .= '<p>' . esc_html( sprintf(
			_n(
				'%d visibility expression failed validation. Your existing post has NOT been changed — use your browser\'s Back button to return to the editor, fix the expression(s), and save again.',
				'%d visibility expressions failed validation. Your existing post has NOT been changed — use your browser\'s Back button to return to the editor, fix the expressions, and save again.',
				count( $errors ),
				$text_domain
			),
			count( $errors )
		) ) . '</p>';
		$out .= '<ul style="margin-left:24px;list-style:disc;">';
		foreach ( $errors as $e ) {
			$module = $e['module_name'] !== '' ? $e['module_name'] : '(unknown module)';
			if ( $e['admin_label'] !== '' ) {
				$module .= ' "' . $e['admin_label'] . '"';
			}
			$out .= '<li><code>' . esc_html( $e['expression'] ) . '</code>'
				. ' <em style="color:#666;">' . sprintf( esc_html__( 'in %s', $text_domain ), esc_html( $module ) ) . '</em>'
				. '<br>&rarr; ' . esc_html( $e['error'] ) . '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * REST publish gate — fires for Gutenberg/Divi 5 VB and any REST API client.
	 * Returning WP_Error blocks the publish; the editor surfaces the message in its standard error UI.
	 */
	public static function gate_rest_publish( $prepared_post, $request ) {
		if ( !self::validation_enabled() || !is_object( $prepared_post ) ) {
			return $prepared_post;
		}

		// Resolve the EFFECTIVE status & content after this update. On partial updates,
		// only the fields the client sent are present on $prepared_post — fall back to the
		// existing stored post for whatever's missing.
		$existing = !empty( $prepared_post->ID ) ? get_post( $prepared_post->ID ) : null;

		$status = isset( $prepared_post->post_status )
			? $prepared_post->post_status
			: ( $existing ? $existing->post_status : '' );
		if ( $status !== 'publish' && $status !== 'future' ) {
			return $prepared_post;
		}

		$content = isset( $prepared_post->post_content )
			? $prepared_post->post_content
			: ( $existing ? $existing->post_content : '' );

		$errors = self::find_validation_errors( $content );
		if ( empty( $errors ) ) {
			return $prepared_post;
		}
		return new \WP_Error(
			'cvdb_validation_failed',
			self::format_publish_error_message( $errors ),
			array( 'status' => 400, 'cvdb_errors' => $errors ),
		);
	}

	/**
	 * Classic-editor publish gate. Fires for the classic post.php form, Divi 3/4 backend builder,
	 * and Divi 3/4 visual builder AJAX saves (et_fb_ajax_save). Cannot return WP_Error from this
	 * filter, so when validation fails we either abort the request (interactive contexts) or
	 * silently preserve the existing post's content & status (non-interactive contexts).
	 *
	 * Either way, the existing post is left in its current state — already-published pages stay
	 * published with their previous content. No demote-to-draft. The user's in-progress edits stay
	 * in the VB's React state (AJAX) or in the browser's form history (classic), so a Back-button
	 * → fix → save flow recovers cleanly.
	 */
	public static function gate_classic_publish( $data, $postarr ) {
		if ( !self::validation_enabled() ) {
			return $data;
		}
		if ( !isset( $data['post_status'] ) ) {
			return $data;
		}
		if ( $data['post_status'] !== 'publish' && $data['post_status'] !== 'future' ) {
			return $data;
		}

		// Resolve EFFECTIVE content. wp_update_post() with only status changed leaves
		// $data['post_content'] empty — fall back to the existing stored post content.
		// Note: wp_insert_post() runs wp_slash() on $data before this filter fires (and
		// wp_unslash() afterwards), so $data['post_content'] is escaped here. Unslash
		// before parsing so the shortcode regex / shortcode_parse_atts see real quotes.
		$content = isset( $data['post_content'] ) && $data['post_content'] !== ''
			? wp_unslash( $data['post_content'] )
			: '';
		$existing = isset( $postarr['ID'] ) && (int) $postarr['ID'] > 0 ? get_post( (int) $postarr['ID'] ) : null;
		if ( $content === '' && $existing ) {
			$content = $existing->post_content;
		}

		$errors = self::find_validation_errors( $content );
		if ( empty( $errors ) ) {
			return $data;
		}

		// Interactive context — abort with a clear error. The user's in-progress edits remain in
		// the VB's React state (AJAX) or the browser's form history (classic post.php) so they can
		// fix and re-save.
		if ( is_admin() || wp_doing_ajax() ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array(
					'message'     => self::format_publish_error_message( $errors ),
					'html'        => self::format_publish_error_html( $errors ),
					'cvdb_errors' => $errors,
				), 400 );
				// wp_send_json_error() calls wp_die() internally; this return is just a safety net.
				return $data;
			}

			wp_die(
				self::format_publish_error_html( $errors ),
				__( 'Save blocked — Content Visibility validation', ContentVisibilityForDiviBuilder::get_text_domain() ),
				array( 'back_link' => true, 'response' => 400 )
			);
		}

		// Non-interactive context (cron, WP-CLI, programmatic wp_update_post calls). wp_die-ing
		// would crash a background process. Instead, silently no-op the post_status + post_content
		// to the existing values so the bad expression never reaches the database. Other field
		// changes (title, excerpt, etc.) still go through.
		if ( $existing ) {
			$data['post_status']  = $existing->post_status;
			$data['post_content'] = wp_slash( $existing->post_content );
		} else {
			// No existing post (this is an insert). Demote to draft so the bad expression doesn't
			// go live; there's no live page to preserve.
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	public static function register_rest_routes() {
		register_rest_route( 'cvdb/v1', '/security/scan', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
			'callback'            => array( __CLASS__, 'rest_scan' ),
			'args'                => array(
				'offset' => array(
					'type'              => 'integer',
					'default'           => 0,
					'minimum'           => 0,
					'sanitize_callback' => 'absint',
				),
				'batch_size' => array(
					'type'              => 'integer',
					'default'           => 50,
					'minimum'           => 1,
					'maximum'           => 200,
					'sanitize_callback' => 'absint',
				),
				'include_revisions' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		) );

		register_rest_route( 'cvdb/v1', '/security/validate-expression', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
			'callback'            => array( __CLASS__, 'rest_validate' ),
			'args'                => array(
				'expression' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		) );
	}

	public static function rest_validate( \WP_REST_Request $request ) {
		$expression = trim( (string) $request->get_param( 'expression' ) );

		// Empty expression is always valid — means "always show".
		if ( $expression === '' ) {
			return new \WP_REST_Response( array(
				'valid'              => true,
				'error'              => null,
				'warnings'           => array(),
				'validation_enabled' => get_option( ContentVisibilityForDiviBuilder::get_underscore_text_domain() . '_expression_validation_enabled' ) === '1',
			), 200 );
		}

		$analysis = self::analyze_expression( $expression );

		return new \WP_REST_Response( array(
			'valid'              => $analysis['valid'],
			'error'              => $analysis['error'],
			'warnings'           => $analysis['warnings'],
			'validation_enabled' => get_option( ContentVisibilityForDiviBuilder::get_underscore_text_domain() . '_expression_validation_enabled' ) === '1',
		), 200 );
	}

	public static function maybe_enqueue_scanner_script() {
		global $pagenow;
		$text_domain = ContentVisibilityForDiviBuilder::get_text_domain();
		if (
			$pagenow !== 'tools.php' ||
			!isset( $_GET['page'] ) || $_GET['page'] !== $text_domain . '-api-reference' ||
			!isset( $_GET['tab'] ) || $_GET['tab'] !== 'security'
		) {
			return;
		}

		wp_enqueue_script( $text_domain . '_security-scanner', plugins_url( '/js/security-scanner.js', CVDB_PLUGIN ), array( 'jquery', 'wp-api-fetch' ), ContentVisibilityForDiviBuilder::get_version() );
	}

	public static function render_scanner_section() {
		$text_domain = ContentVisibilityForDiviBuilder::get_text_domain();
?>
<hr>
<h3><?php _e( 'Content Scanner', $text_domain ); ?></h3>
<div id="cvdb-security-scan">
	<p>
		<label>
			<input type="checkbox" id="cvdb-scan-include-revisions">
			<?php _e( 'Include post revisions (slower; flags expressions in saved revisions even if the current content is clean)', $text_domain ); ?>
		</label>
	</p>
	<p><button type="button" id="cvdb-scan-start" class="button button-secondary"><?php _e( 'Start Scan', $text_domain ); ?></button></p>
	<div id="cvdb-scan-progress" style="display:none;">
		<p id="cvdb-scan-progress-text"></p>
		<div style="background:#e0e0e0;height:20px;border-radius:3px;margin:10px 0;">
			<div id="cvdb-scan-progress-bar" style="background:#0073aa;height:20px;border-radius:3px;width:0;transition:width 0.3s;"></div>
		</div>
	</div>
	<div id="cvdb-scan-results" style="display:none;">
		<h4 id="cvdb-scan-summary"></h4>
		<table class="widefat striped" id="cvdb-scan-results-table" style="display:none;">
			<thead>
				<tr>
					<th><?php _e( 'Post', $text_domain ); ?></th>
					<th><?php _e( 'Expression', $text_domain ); ?></th>
					<th><?php _e( 'Editor', $text_domain ); ?></th>
					<th><?php _e( 'Error', $text_domain ); ?></th>
				</tr>
			</thead>
			<tbody id="cvdb-scan-results-body"></tbody>
		</table>
	</div>
</div>
<?php
	}

	public static function rest_scan( \WP_REST_Request $request ) {
		global $wpdb;

		$offset            = (int) $request->get_param( 'offset' );
		$batch_size        = (int) $request->get_param( 'batch_size' );
		$include_revisions = (bool) $request->get_param( 'include_revisions' );

		$revision_clause = $include_revisions ? '' : "AND post_type != 'revision'";

		$like_shortcode = '%' . $wpdb->esc_like( 'cvdb_content_visibility_check' ) . '%';
		$like_block     = '%' . $wpdb->esc_like( 'contentVisibilityCheck' ) . '%';

		$total = null;
		if ( $offset === 0 ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status != 'auto-draft' {$revision_clause} AND (post_content LIKE %s OR post_content LIKE %s)",
				$like_shortcode,
				$like_block
			) );
		}

		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_content, post_type, post_parent, post_date FROM {$wpdb->posts} WHERE post_status != 'auto-draft' {$revision_clause} AND (post_content LIKE %s OR post_content LIKE %s) ORDER BY ID ASC LIMIT %d OFFSET %d",
			$like_shortcode,
			$like_block,
			$batch_size,
			$offset
		) );

		$parent_data = array();
		if ( $include_revisions ) {
			$parent_ids = array();
			foreach ( $posts as $post ) {
				if ( $post->post_type === 'revision' && (int) $post->post_parent > 0 ) {
					$parent_ids[ (int) $post->post_parent ] = true;
				}
			}
			if ( !empty( $parent_ids ) ) {
				$parent_id_list = array_keys( $parent_ids );
				$placeholders = implode( ',', array_fill( 0, count( $parent_id_list ), '%d' ) );
				$parent_rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
					$parent_id_list
				) );
				foreach ( $parent_rows as $p ) {
					$flagged_count = 0;
					$parent_expressions = self::extract_expressions_from_content( $p->post_content );
					foreach ( $parent_expressions as $expr ) {
						if ( ContentVisibilityForDiviBuilder::validate_expression( $expr['expression'] ) !== true ) {
							$flagged_count++;
						}
					}
					$parent_data[ (int) $p->ID ] = array(
						'id'              => (int) $p->ID,
						'title'           => $p->post_title,
						'edit_url'        => get_edit_post_link( $p->ID, 'raw' ),
						'current_flagged' => $flagged_count,
					);
				}
			}
		}

		$text_domain = ContentVisibilityForDiviBuilder::get_text_domain();
		$results = array();
		foreach ( $posts as $post ) {
			$expressions = self::extract_expressions_from_content( $post->post_content );
			if ( empty( $expressions ) ) {
				continue;
			}

			$post_expressions = array();
			foreach ( $expressions as $expr ) {
				$analysis = self::analyze_expression( $expr['expression'] );
				$post_expressions[] = array(
					'expression'  => $expr['expression'],
					'editor'      => $expr['type'] === 'block' ? __( 'Divi 5 (block)', $text_domain ) : __( 'Divi 4 (shortcode)', $text_domain ),
					'module_name' => isset( $expr['module_name'] ) ? $expr['module_name'] : '',
					'admin_label' => isset( $expr['admin_label'] ) ? $expr['admin_label'] : '',
					'valid'       => $analysis['valid'],
					'error'       => $analysis['error'],
					'warnings'    => $analysis['warnings'],
				);
			}

			$is_revision = $post->post_type === 'revision';
			$results[] = array(
				'id'          => (int) $post->ID,
				'title'       => $post->post_title,
				'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
				'expressions' => $post_expressions,
				'is_revision' => $is_revision,
				'post_date'   => $post->post_date,
				'parent'      => ( $is_revision && isset( $parent_data[ (int) $post->post_parent ] ) ) ? $parent_data[ (int) $post->post_parent ] : null,
			);
		}

		$response = array(
			'posts'    => $results,
			'has_more' => count( $posts ) === $batch_size,
		);

		if ( $total !== null ) {
			$response['total'] = $total;
			$response['reflection_available'] = self::is_reflection_available();
			$response['validation_enabled']   = get_option( ContentVisibilityForDiviBuilder::get_underscore_text_domain() . '_expression_validation_enabled' ) === '1';
		}

		return new \WP_REST_Response( $response, 200 );
	}

	public static function analyze_expression( $expression ) {
		$analysis = array(
			'valid'    => true,
			'error'    => null,
			'warnings' => array(),
		);

		$validation = ContentVisibilityForDiviBuilder::validate_expression( $expression );
		if ( $validation !== true ) {
			$analysis['valid'] = false;
			$analysis['error'] = $validation;
		}

		// Only enumerate callables for the warnings/migration list when the expression is
		// either valid or fails specifically with "Unknown callable" (the case allowlisting
		// can solve). For any other structural error — disallowed token, disallowed character,
		// instance method chain, bare identifier — apparent callables are unreliable noise.
		if ( $validation !== true && strpos( $validation, 'Unknown callable' ) !== 0 ) {
			return $analysis;
		}

		$allowed_callables = array_map( array( ContentVisibilityForDiviBuilder::class, 'normalize_callable_name' ), ContentVisibilityForDiviBuilder::get_allowed_callables() );
		$blocked_callables = array_map( array( ContentVisibilityForDiviBuilder::class, 'normalize_callable_name' ), ContentVisibilityForDiviBuilder::get_blocked_functions() );

		$tokens = @token_get_all( '<?php ' . $expression );
		if ( !is_array( $tokens ) || count( $tokens ) < 2 ) {
			return $analysis;
		}
		array_shift( $tokens );

		$tokens = array_values( array_filter( $tokens, function( $t ) {
			return !( is_array( $t ) && $t[0] === T_WHITESPACE );
		} ) );

		// Match validate_expression's namespace handling on PHP 7.x
		$tokens = ContentVisibilityForDiviBuilder::merge_namespace_tokens( $tokens );

		$name_token_types = array( T_STRING );
		if ( defined( 'T_NAME_QUALIFIED' ) ) {
			$name_token_types[] = T_NAME_QUALIFIED;
		}
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
			$name_token_types[] = T_NAME_FULLY_QUALIFIED;
		}
		if ( defined( 'T_NAME_RELATIVE' ) ) {
			$name_token_types[] = T_NAME_RELATIVE;
		}

		$seen = array();
		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$tok = $tokens[ $i ];
			if ( !is_array( $tok ) ) {
				continue;
			}

			$is_name = in_array( $tok[0], $name_token_types, true );
			$next = $i + 1 < $count ? $tokens[ $i + 1 ] : null;

			// Static method call: Name :: Name (
			if ( $is_name && is_array( $next ) && $next[0] === T_DOUBLE_COLON ) {
				$method_tok = $i + 2 < $count ? $tokens[ $i + 2 ] : null;
				$after = $i + 3 < $count ? $tokens[ $i + 3 ] : null;
				if ( is_array( $method_tok ) && $method_tok[0] === T_STRING && $after === '(' ) {
					$callable = $tok[1] . '::' . $method_tok[1];
					$normalized = ContentVisibilityForDiviBuilder::normalize_callable_name( $callable );
					if ( !in_array( $normalized, $allowed_callables, true ) && !in_array( $normalized, $blocked_callables, true ) ) {
						$key = 'sm:' . $normalized;
						if ( !isset( $seen[ $key ] ) ) {
							$seen[ $key ] = true;
							$analysis['warnings'][] = self::describe_custom_callable( 'static_method', $callable );
						}
					}
					$i += 2;
					continue;
				}
			}

			// Plain or namespaced function call: Name (
			if ( $is_name && $next === '(' ) {
				$normalized = ContentVisibilityForDiviBuilder::normalize_callable_name( $tok[1] );
				if ( !in_array( $normalized, $allowed_callables, true ) && !in_array( $normalized, $blocked_callables, true ) ) {
					$key = 'fn:' . $normalized;
					if ( !isset( $seen[ $key ] ) ) {
						$seen[ $key ] = true;
						$analysis['warnings'][] = self::describe_custom_callable( 'function', $tok[1] );
					}
				}
				continue;
			}

			// Chained instance method: ... -> Name (
			$is_object_op = $tok[0] === T_OBJECT_OPERATOR ||
				( defined( 'T_NULLSAFE_OPERATOR' ) && $tok[0] === T_NULLSAFE_OPERATOR );
			if ( $is_object_op ) {
				$name_tok = $i + 1 < $count ? $tokens[ $i + 1 ] : null;
				$after = $i + 2 < $count ? $tokens[ $i + 2 ] : null;
				if ( is_array( $name_tok ) && $name_tok[0] === T_STRING && $after === '(' ) {
					$callable = '->' . $name_tok[1];
					$key = 'm:' . strtolower( $callable );
					if ( !isset( $seen[ $key ] ) ) {
						$seen[ $key ] = true;
						$analysis['warnings'][] = self::describe_custom_callable( 'instance_method', $callable );
					}
					// Skip past the `->method` so we don't also emit a "function" warning for `method`
					$i += 1;
					continue;
				}
			}
		}

		return $analysis;
	}

	public static function extract_expressions_from_content( $content ) {
		$expressions = array();

		if ( preg_match_all( '/\[([a-zA-Z][a-zA-Z0-9_]*)(?:[^\[\]]*?)cvdb_content_visibility_check(?:[^\[\]]*?)\]/', $content, $shortcode_matches ) ) {
			foreach ( $shortcode_matches[0] as $i => $shortcode_tag ) {
				// Strip surrounding brackets so shortcode_parse_atts sees end-of-string
				// after the last attribute's closing quote (its regex requires \s or $).
				$atts = shortcode_parse_atts( substr( $shortcode_tag, 1, -1 ) );
				if ( isset( $atts['cvdb_content_visibility_check'] ) && trim( $atts['cvdb_content_visibility_check'] ) !== '' ) {
					$expression = str_replace( array( '%22', '%5D' ), array( '"', ']' ), $atts['cvdb_content_visibility_check'] );
					$expressions[] = array(
						'expression'  => $expression,
						'type'        => 'shortcode',
						'module_name' => $shortcode_matches[1][ $i ],
						'admin_label' => isset( $atts['admin_label'] ) ? trim( $atts['admin_label'] ) : '',
					);
				}
			}
		}

		if ( function_exists( 'parse_blocks' ) ) {
			$blocks = parse_blocks( $content );
			self::extract_expressions_from_blocks( $blocks, $expressions );
		}

		return $expressions;
	}

	private static function extract_expressions_from_blocks( $blocks, &$expressions ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['attrs']['module']['cvdb']['contentVisibilityCheck']['desktop']['value'] ) ) {
				$value = $block['attrs']['module']['cvdb']['contentVisibilityCheck']['desktop']['value'];
				if ( is_string( $value ) && trim( $value ) !== '' ) {
					$admin_label = '';
					if ( isset( $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] ) && is_string( $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] ) ) {
						$admin_label = trim( $block['attrs']['module']['meta']['adminLabel']['desktop']['value'] );
					}
					$expressions[] = array(
						'expression'  => $value,
						'type'        => 'block',
						'module_name' => isset( $block['blockName'] ) ? $block['blockName'] : '',
						'admin_label' => $admin_label,
					);
				}
			}

			if ( !empty( $block['innerBlocks'] ) ) {
				self::extract_expressions_from_blocks( $block['innerBlocks'], $expressions );
			}
		}
	}

	private static function describe_custom_callable( $kind, $name ) {
		$state = 'unresolved';
		$location = null;

		$reflection_available = self::is_reflection_available();

		if ( $kind === 'function' ) {
			$clean = ltrim( $name, '\\' );
			if ( !function_exists( $clean ) ) {
				$state = 'unknown_function';
			} elseif ( !$reflection_available ) {
				$state = 'reflection_unavailable';
			} else {
				try {
					$reflection = new \ReflectionFunction( $clean );
					$file = $reflection->getFileName();
					$line = $reflection->getStartLine();
					if ( $file ) {
						$state    = 'located';
						$location = self::format_callable_path( $file ) . ':' . $line;
					} else {
						$state = 'internal';
					}
				} catch ( \Throwable $e ) {
					$state = 'unresolved';
				}
			}
		} elseif ( $kind === 'static_method' ) {
			$parts = explode( '::', $name, 2 );
			if ( count( $parts ) === 2 ) {
				$class_clean = ltrim( $parts[0], '\\' );
				if ( !class_exists( $class_clean ) ) {
					$state = 'unknown_class';
				} elseif ( !method_exists( $class_clean, $parts[1] ) ) {
					$state = 'unknown_method';
				} elseif ( !$reflection_available ) {
					$state = 'reflection_unavailable';
				} else {
					try {
						$reflection = new \ReflectionMethod( $class_clean, $parts[1] );
						$file = $reflection->getFileName();
						$line = $reflection->getStartLine();
						if ( $file ) {
							$state    = 'located';
							$location = self::format_callable_path( $file ) . ':' . $line;
						} else {
							$state = 'internal';
						}
					} catch ( \Throwable $e ) {
						$state = 'unresolved';
					}
				}
			}
		}
		// instance_method: cannot resolve without runtime type info

		return array(
			'kind'       => $kind,
			'callable'   => $name,
			'normalized' => ContentVisibilityForDiviBuilder::normalize_callable_name( $name ),
			'state'      => $state,
			'location'   => $location,
		);
	}

	private static function format_callable_path( $path ) {
		$abspath = defined( 'ABSPATH' ) ? ABSPATH : '';
		if ( $abspath !== '' && strpos( $path, $abspath ) === 0 ) {
			return substr( $path, strlen( $abspath ) );
		}
		return $path;
	}
}
