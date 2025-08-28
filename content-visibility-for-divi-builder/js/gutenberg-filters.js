;( function() {
	var cvdbFieldDefinition = {
		attrName: 'module.cvdb.contentVisibilityCheck',
		component: {
			name: 'divi/text',
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

	// Add the Content Visibility field
	vendor.wp.hooks.addFilter(
		'divi.module.options.composite.group.fields',
		'cvdb/add-visibility-to-composite-group-fields',
		function( fields, groupSettings ) {
			if ( !groupSettings ) {
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

	// Ensure Divi 4 modules can be converted to Divi 5 within the editor
	vendor.wp.hooks.addFilter(
		'divi.conversion.moduleLibrary.conversionMap',
		'cvdb/convert-divi-4-content-visibility-check',
		function( moduleLibraryConversionMap ) {
			var conversionMapEntries = Object.values( moduleLibraryConversionMap );

			for ( var i = 0; i < conversionMapEntries.length; i++ ) {
				var conversionMapEntry = conversionMapEntries[i];
				if ( !conversionMapEntry ) {
					continue;
				}

				if ( !conversionMapEntry.attributeMap ) {
					conversionMapEntry.attributeMap = {};
				}

				conversionMapEntry.attributeMap.cvdb_content_visibility_check = 'module.cvdb.contentVisibilityCheck.*';
			}

			return moduleLibraryConversionMap;
		},
		1337
	);
} )();
