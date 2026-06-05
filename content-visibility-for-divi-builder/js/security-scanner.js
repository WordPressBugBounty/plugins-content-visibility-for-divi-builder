jQuery( function( $ ) {
	'use strict';

	var $scanContainer = $( '#cvdb-security-scan' );
	if ( ! $scanContainer.length ) {
		return;
	}

	var $startBtn = $( '#cvdb-scan-start' );
	var $includeRevisions = $( '#cvdb-scan-include-revisions' );
	var $progress = $( '#cvdb-scan-progress' );
	var $progressText = $( '#cvdb-scan-progress-text' );
	var $progressBar = $( '#cvdb-scan-progress-bar' );
	var $results = $( '#cvdb-scan-results' );
	var $summary = $( '#cvdb-scan-summary' );
	var $table = $( '#cvdb-scan-results-table' );
	var $tbody = $( '#cvdb-scan-results-body' );

	var totalPosts = 0;
	var scannedPosts = 0;
	var totalExpressions = 0;
	var flaggedExpressions = 0;
	var warningExpressions = 0;
	var batchSize = 50;
	var groupsByKey = {};
	var includeRevisions = false;
	var validationEnabled = false;
	var allowlistableSeen = {};   // key: normalized callable name -> entry
	var nonAllowlistableSeen = {}; // key: normalized callable name -> entry

	$startBtn.on( 'click', function() {
		$startBtn.prop( 'disabled', true );
		$includeRevisions.prop( 'disabled', true );
		$progress.show();
		$results.show();
		$summary.text( '' );
		$table.hide();
		$tbody.empty();
		totalPosts = 0;
		scannedPosts = 0;
		totalExpressions = 0;
		flaggedExpressions = 0;
		warningExpressions = 0;
		groupsByKey = {};
		allowlistableSeen = {};
		nonAllowlistableSeen = {};
		$( '#cvdb-scan-migration' ).remove();
		includeRevisions = $includeRevisions.is( ':checked' );

		scanBatch( 0 );
	} );

	function scanBatch( offset ) {
		wp.apiFetch( {
			path: '/cvdb/v1/security/scan',
			method: 'POST',
			data: {
				offset: offset,
				batch_size: batchSize,
				include_revisions: includeRevisions
			}
		} ).then( function( data ) {
			if ( typeof data.total !== 'undefined' ) {
				totalPosts = data.total;
			}

			if ( typeof data.reflection_available !== 'undefined' && ! data.reflection_available ) {
				showReflectionBanner();
			}

			if ( typeof data.validation_enabled !== 'undefined' ) {
				validationEnabled = !! data.validation_enabled;
			}

			scannedPosts += data.posts.length;

			$.each( data.posts, function( _, post ) {
				$.each( post.expressions, function( _, expr ) {
					totalExpressions++;
					var hasWarnings = expr.warnings && expr.warnings.length > 0;
					if ( ! expr.valid ) {
						flaggedExpressions++;
					} else if ( hasWarnings ) {
						warningExpressions++;
					}
					if ( ! expr.valid || hasWarnings ) {
						$table.show();
						appendFlaggedRow( post, expr );
						accumulateMigrationEntries( post, expr );
					}
				} );
			} );

			var pct = totalPosts > 0 ? Math.round( ( scannedPosts / totalPosts ) * 100 ) : 100;
			$progressBar.css( 'width', pct + '%' );
			$progressText.text( 'Scanning... ' + scannedPosts + ' of ' + totalPosts + ' posts checked' );

			if ( data.has_more ) {
				scanBatch( offset + batchSize );
			} else {
				$progressText.text( 'Scan complete.' );
				$summary.text(
					scannedPosts + ' post(s) scanned, ' +
					totalExpressions + ' expression(s) found, ' +
					flaggedExpressions + ' flagged, ' +
					warningExpressions + ' with warnings.'
				);
				renderMigrationSection();
				$startBtn.prop( 'disabled', false );
				$includeRevisions.prop( 'disabled', false );
			}
		} ).catch( function( err ) {
			var msg = ( err && err.message ) ? err.message : 'Request failed';
			$progressText.text( 'Error: ' + msg );
			$startBtn.prop( 'disabled', false );
			$includeRevisions.prop( 'disabled', false );
		} );
	}

	function ensureGroup( post ) {
		var isRev = !!post.is_revision;
		var headerInfo;
		var key;
		var note = '';

		if ( isRev && post.parent ) {
			key = 'post-' + post.parent.id;
			headerInfo = post.parent;
			note = post.parent.current_flagged > 0
				? 'Current content: ' + post.parent.current_flagged + ' flagged expression(s) - listed below'
				: 'Current content: clean - issues only in older revisions';
		} else if ( isRev ) {
			// Revision with no resolvable parent (orphaned)
			key = 'orphan-' + post.id;
			headerInfo = { id: post.id, title: post.title, edit_url: post.edit_url };
			note = 'Orphaned revision (parent post not found)';
		} else {
			key = 'post-' + post.id;
			headerInfo = post;
		}

		if ( groupsByKey[ key ] ) {
			return groupsByKey[ key ];
		}

		var titleHtml = headerInfo.edit_url
			? '<a href="' + escHtml( headerInfo.edit_url ) + '">' + escHtml( headerInfo.title || '(no title)' ) + '</a>'
			: escHtml( headerInfo.title || '(no title)' );

		var $headerRow = $(
			'<tr class="cvdb-scan-group-header" style="background:#f0f0f1;">' +
				'<td colspan="4" style="padding:8px 10px;">' +
					'<strong>' + titleHtml + '</strong> ' +
					'<span style="color:#666;">(#' + headerInfo.id + ')</span>' +
					( note ? ' &mdash; <em style="color:#666;">' + escHtml( note ) + '</em>' : '' ) +
				'</td>' +
			'</tr>'
		);
		$tbody.append( $headerRow );

		groupsByKey[ key ] = { $headerRow: $headerRow };
		return groupsByKey[ key ];
	}

	function appendFlaggedRow( post, expr ) {
		ensureGroup( post );

		var isRev = !!post.is_revision;
		var firstCellHtml;
		if ( isRev ) {
			firstCellHtml =
				'<span style="color:#666;">&#8627;</span> ' +
				'<strong>Revision</strong>' +
				'<br><small style="color:#666;">saved ' + escHtml( post.post_date || '' ) + ' &middot; #' + post.id + '</small>';
		} else {
			firstCellHtml = '<strong>Current content</strong>';
		}

		var locationParts = [];
		if ( expr.module_name ) {
			locationParts.push( '<code>' + escHtml( expr.module_name ) + '</code>' );
		}
		if ( expr.admin_label ) {
			locationParts.push( '&ldquo;' + escHtml( expr.admin_label ) + '&rdquo;' );
		}
		var locationHtml = locationParts.length
			? '<br><small style="color:#666;">Module: ' + locationParts.join( ' &mdash; ' ) + '</small>'
			: '';

		var warningsHtml = '';
		if ( expr.warnings && expr.warnings.length ) {
			var items = $.map( expr.warnings, function( w ) {
				return '<li>' + warningKindLabel( w.kind ) + ' <code>' + escHtml( w.callable ) + '</code> &mdash; ' + describeWarningState( w ) + '</li>';
			} );
			warningsHtml =
				'<div style="margin-top:6px;padding:6px 8px;background:#fcf9e8;border-left:3px solid #dba617;font-size:12px;">' +
					'<strong>&#9888; Custom callables - validator cannot verify:</strong>' +
					'<ul style="margin:4px 0 0 18px;padding:0;">' + items.join( '' ) + '</ul>' +
				'</div>';
		}

		var rowClass = isRev ? ' class="cvdb-scan-revision-row"' : '';
		var rowStyle = !expr.valid
			? ' style="background:#fdf2f2;"'
			: ( expr.warnings && expr.warnings.length ? ' style="background:#fffbe5;"' : '' );

		var errorCellHtml = expr.error
			? escHtml( expr.error )
			: ( expr.warnings && expr.warnings.length ? '<em style="color:#996800;">warnings - see below</em>' : '' );

		var $row = $(
			'<tr' + rowClass + rowStyle + '>' +
				'<td style="padding-left:32px;">' + firstCellHtml + '</td>' +
				'<td><code>' + escHtml( expr.expression ) + '</code>' + locationHtml + warningsHtml + '</td>' +
				'<td>' + escHtml( expr.editor ) + '</td>' +
				'<td>' + errorCellHtml + '</td>' +
			'</tr>'
		);
		$tbody.append( $row );
	}

	function warningKindLabel( kind ) {
		switch ( kind ) {
			case 'static_method':   return 'Static method';
			case 'instance_method': return 'Instance method';
			case 'function':        return 'Function';
			default:                return 'Callable';
		}
	}

	function snippetLocationComment( entry ) {
		switch ( entry.state ) {
			case 'located':
				return entry.location ? '; defined at ' + entry.location : '';
			case 'unknown_function':
				return '; WARNING: function not defined - verify before allowlisting';
			case 'unknown_class':
				return '; WARNING: class not defined - verify before allowlisting';
			case 'unknown_method':
				return '; WARNING: method not defined on class - verify before allowlisting';
			case 'internal':
				return '; built-in (PHP/extension) - review behavior';
			default:
				return '';
		}
	}

	function describeWarningState( warning ) {
		switch ( warning.state ) {
			case 'located':
				return 'defined at <code>' + escHtml( warning.location ) + '</code>';
			case 'internal':
				return '<em>built-in (PHP or extension) - review what this does</em>';
			case 'unknown_function':
				return '<em>function not defined - likely a typo or missing plugin (will throw at runtime)</em>';
			case 'unknown_class':
				return '<em>class not defined - likely a typo or missing plugin (will throw at runtime)</em>';
			case 'unknown_method':
				return '<em>class exists but method does not - likely a typo (will throw at runtime)</em>';
			case 'reflection_unavailable':
				return '<em>location unresolved (Reflection extension is unavailable on this host)</em>';
			default:
				return '<em>location unresolved</em>';
		}
	}

	var reflectionBannerShown = false;
	function showReflectionBanner() {
		if ( reflectionBannerShown ) {
			return;
		}
		reflectionBannerShown = true;
		$results.prepend(
			'<div class="notice notice-warning inline" style="margin:6px 0;padding:8px 12px;">' +
				'<strong>Note:</strong> PHP Reflection is not available on this host, so file:line locations for custom callables cannot be resolved. Definitions will need to be looked up manually.' +
			'</div>'
		);
	}

	function accumulateMigrationEntries( post, expr ) {
		if ( ! expr.warnings || ! expr.warnings.length ) {
			return;
		}
		var sighting = {
			post_id:     post.id,
			post_title:  post.title || '(no title)',
			module_name: expr.module_name || '',
			admin_label: expr.admin_label || ''
		};
		$.each( expr.warnings, function( _, w ) {
			var bucket = ( w.kind === 'instance_method' ) ? nonAllowlistableSeen : allowlistableSeen;
			if ( ! bucket[ w.normalized ] ) {
				bucket[ w.normalized ] = {
					kind:        w.kind,
					callable:    w.callable,
					normalized:  w.normalized,
					state:       w.state,
					location:    w.location,
					first:       sighting
				};
			}
		} );
	}

	function renderMigrationSection() {
		var allowlistable    = Object.keys( allowlistableSeen ).map( function( k ) { return allowlistableSeen[ k ]; } );
		var nonAllowlistable = Object.keys( nonAllowlistableSeen ).map( function( k ) { return nonAllowlistableSeen[ k ]; } );

		if ( allowlistable.length === 0 && nonAllowlistable.length === 0 ) {
			return;
		}

		var modeBadge = validationEnabled
			? '<span style="display:inline-block;padding:2px 8px;background:#d63638;color:#fff;border-radius:3px;font-size:11px;font-weight:600;letter-spacing:.5px;">VALIDATION ON - these block at runtime</span>'
			: '<span style="display:inline-block;padding:2px 8px;background:#dba617;color:#1d2327;border-radius:3px;font-size:11px;font-weight:600;letter-spacing:.5px;">VALIDATION OFF - these would block when enabled</span>';

		var html = '<div id="cvdb-scan-migration" style="margin-top:24px;">' +
			'<h3>Migration helper ' + modeBadge + '</h3>';

		if ( allowlistable.length ) {
			var snippet = buildAllowlistSnippet( allowlistable );
			html +=
				'<h4 style="margin-top:18px;">Allowlistable callables (' + allowlistable.length + ')</h4>' +
				'<p>Paste the snippet below into a site-specific plugin or your theme\'s <code>functions.php</code> ' +
				'after auditing each callable. Then re-run the scan to confirm zero remaining errors before enabling validation.</p>' +
				'<div style="position:relative;">' +
					'<button type="button" class="button button-small cvdb-copy-snippet" data-target="cvdb-allowlist-snippet" style="position:absolute;top:8px;right:8px;">Copy</button>' +
					'<pre id="cvdb-allowlist-snippet" style="background:#f6f7f7;border:1px solid #c3c4c7;padding:12px 12px 12px 12px;margin:0;overflow-x:auto;font-size:12px;line-height:1.5;">' +
						escHtml( snippet ) +
					'</pre>' +
				'</div>';
		}

		if ( nonAllowlistable.length ) {
			html +=
				'<h4 style="margin-top:18px;">Non-allowlistable patterns - must be rewritten (' + nonAllowlistable.length + ')</h4>' +
				'<p>The following patterns cannot be allowlisted because the validator can\'t determine what code they invoke. ' +
				'Rewrite each occurrence as a static helper and call that instead:</p>' +
				'<ul style="margin:8px 0 0 24px;">' +
				$.map( nonAllowlistable, function( e ) {
					var ctx = e.first.module_name
						? '<code>' + escHtml( e.first.module_name ) + '</code>'
						+ ( e.first.admin_label ? ' &ldquo;' + escHtml( e.first.admin_label ) + '&rdquo;' : '' )
						+ ' in <a href="#post-' + e.first.post_id + '">post #' + e.first.post_id + '</a>'
						: 'post #' + e.first.post_id;
					return '<li><code>' + escHtml( e.callable ) + '</code> &mdash; first seen in ' + ctx + '</li>';
				} ).join( '' ) +
				'</ul>';
		}

		html += '</div>';

		$results.append( html );

		$( '.cvdb-copy-snippet' ).on( 'click', function() {
			var $btn = $( this );
			var targetId = $btn.data( 'target' );
			var text = document.getElementById( targetId ).textContent;
			var done = function() {
				var orig = $btn.text();
				$btn.text( 'Copied!' );
				setTimeout( function() { $btn.text( orig ); }, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done, function() { window.prompt( 'Copy:', text ); } );
			} else {
				window.prompt( 'Copy:', text );
			}
		} );
	}

	function buildAllowlistSnippet( entries ) {
		var lines = [];
		lines.push( "add_filter( 'content_visibility_for_divi_builder_allowed_callables', function( $callables ) {" );
		entries.forEach( function( e ) {
			var ctx = '';
			if ( e.first.module_name ) {
				ctx = e.first.module_name;
				if ( e.first.admin_label ) {
					ctx += ' "' + e.first.admin_label + '"';
				}
				ctx += ' in post #' + e.first.post_id;
			} else {
				ctx = 'post #' + e.first.post_id;
			}
			lines.push( "    // First seen: " + ctx + snippetLocationComment( e ) );
			lines.push( "    $callables[] = '" + e.callable.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) + "';" );
		} );
		lines.push( "    return $callables;" );
		lines.push( "} );" );
		return lines.join( "\n" );
	}

	function escHtml( str ) {
		if ( str === null || typeof str === 'undefined' ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( String( str ) ) );
		return div.innerHTML;
	}
} );
