;( function() {
	'use strict';

	var fieldLibrary = window.divi && window.divi.fieldLibrary;
	var React        = window.vendor && window.vendor.React;
	var apiFetch     = window.wp && window.wp.apiFetch;
	var hooks        = window.vendor && window.vendor.wp && window.vendor.wp.hooks;

	if ( ! fieldLibrary || ! React || ! apiFetch || ! hooks ) {
		return;
	}

	// IMPORTANT: source React from Divi's bundle (window.vendor.React), not wp.element.
	// Divi 5 renders with its own React instance; mixing instances breaks hooks.
	var createElement = React.createElement;
	var Fragment      = React.Fragment;
	var useState      = React.useState;
	var useEffect     = React.useEffect;
	var useRef        = React.useRef;

	var FIELD_NAME           = 'cvdb/visibility-expression';
	var VALIDATE_PATH        = '/cvdb/v1/security/validate-expression';
	var DEBOUNCE_MS          = 350;
	var ATTR_NAME            = 'module.cvdb.contentVisibilityCheck';

	function severity( valid, validationEnabled ) {
		if ( valid ) return 'ok';
		return validationEnabled ? 'error' : 'warning';
	}

	function indicatorStyle( level ) {
		switch ( level ) {
			case 'error':   return { color: '#d63638', borderColor: '#d63638' };
			case 'warning': return { color: '#996800', borderColor: '#dba617' };
			default:        return null;
		}
	}

	function CvdbVisibilityField( props ) {
		var TextField = fieldLibrary.getFieldComponent( 'divi/text' );

		var value = props.value != null ? String( props.value ) : '';

		var stateInit  = useState( null );  var error              = stateInit[0],  setError              = stateInit[1];
		var stateInit2 = useState( false ); var validationEnabled  = stateInit2[0], setValidationEnabled  = stateInit2[1];
		var stateInit3 = useState( false ); var validating         = stateInit3[0], setValidating         = stateInit3[1];

		var debounceRef = useRef( null );
		var requestId   = useRef( 0 );

		useEffect( function() {
			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}

			var trimmed = value.trim();
			if ( trimmed === '' ) {
				setError( null );
				setValidating( false );
				return;
			}

			setValidating( true );
			debounceRef.current = setTimeout( function() {
				var myId = ++requestId.current;
				apiFetch( {
					path:   VALIDATE_PATH,
					method: 'POST',
					data:   { expression: trimmed }
				} ).then( function( result ) {
					if ( myId !== requestId.current ) return;
					setError( result.error || null );
					setValidationEnabled( !! result.validation_enabled );
					setValidating( false );
				} ).catch( function() {
					if ( myId !== requestId.current ) return;
					setValidating( false );
				} );
			}, DEBOUNCE_MS );

			return function() {
				if ( debounceRef.current ) {
					clearTimeout( debounceRef.current );
				}
			};
		}, [ value ] );

		var level = severity( ! error, validationEnabled );
		var indicator = indicatorStyle( level );

		var children = [
			createElement( TextField, Object.assign( {}, props, { key: 'input' } ) )
		];

		if ( error && level !== 'ok' ) {
			children.push( createElement( 'div', {
				key: 'msg',
				style: {
					marginTop:   '6px',
					padding:     '6px 8px',
					fontSize:    '12px',
					lineHeight:  '1.4',
					color:       indicator.color,
					background:  level === 'error' ? '#fcf0f1' : '#fcf9e8',
					borderLeft:  '3px solid ' + indicator.borderColor,
					whiteSpace:  'pre-wrap'
				}
			},
				createElement( 'strong', null, level === 'error' ? 'Validation error: ' : 'Heads-up: ' ),
				error,
				validationEnabled
					? null
					: createElement( 'div', { style: { marginTop: '4px', color: '#666' } },
						'(Validation is currently disabled — this would block at runtime when enabled.)'
					)
			) );
		} else if ( validating && value.trim() !== '' ) {
			children.push( createElement( 'div', {
				key: 'spin',
				style: { marginTop: '4px', fontSize: '11px', color: '#888' }
			}, 'Validating...' ) );
		}

		return createElement( Fragment, null, children );
	}

	CvdbVisibilityField.fieldName = FIELD_NAME;

	fieldLibrary.registerFieldComponent( {
		name:      FIELD_NAME,
		component: CvdbVisibilityField
	} );

	var cvdbFieldDefinition = {
		attrName: ATTR_NAME,
		component: {
			name: FIELD_NAME,
			type: 'field'
		},
		defaultAttr: {
			desktop: {
				value: ''
			}
		},
		description: 'Enter a boolean expression which evaluates to true when you want to display this element, or leave blank to always display it.',
		features: {
			hover: false,
			responsive: false,
			sticky: false
		},
		groupName: 'cvdb/content-visibility-check',
		label: 'Content Visibility',
		priority: 1337,
		render: true
	};

	hooks.addFilter(
		'divi.module.options.composite.group.fields',
		'cvdb/add-visibility-to-composite-group-fields',
		function( fields, groupSettings ) {
			if ( ! groupSettings ) {
				return fields;
			}
			switch ( groupSettings.attrName ) {
				case 'advancedVisibility':
				case 'advancedVisibilityModule':
					fields.cvdbContentVisibilityCheck = cvdbFieldDefinition;
					break;
				default:
					break;
			}
			return fields;
		}
	);

	hooks.addFilter(
		'divi.conversion.moduleLibrary.conversionMap',
		'cvdb/convert-divi-4-content-visibility-check',
		function( moduleLibraryConversionMap ) {
			var entries = Object.values( moduleLibraryConversionMap );
			for ( var i = 0; i < entries.length; i++ ) {
				var entry = entries[ i ];
				if ( ! entry ) continue;
				if ( ! entry.attributeMap ) {
					entry.attributeMap = {};
				}
				entry.attributeMap.cvdb_content_visibility_check = ATTR_NAME + '.*';
			}
			return moduleLibraryConversionMap;
		},
		1337
	);
} )();
