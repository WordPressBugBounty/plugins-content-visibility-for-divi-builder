(function($) {
	'use strict';
	
	$( document ).ready( function() {
		var OriginalPerformSaving = ET_PageBuilder.ModalView.prototype.performSaving;
		ET_PageBuilder.ModalView = ET_PageBuilder.ModalView.extend({
			performSaving : function( option_tabs_selector ) {
				var attributes = {},
					options_selector = typeof option_tabs_selector !== 'undefined' && '' !== option_tabs_selector ? option_tabs_selector : 'input, select, textarea, #et_pb_content_main';
				var $et_form_validation;
				$et_form_validation = $(this)[0].$el.find('form.validate');
				if ( $et_form_validation.length ) {
					validator = $et_form_validation.validate();
					if ( !validator.form() ) {
						et_builder_debug_message('failed form validation');
						et_builder_debug_message('failed elements: ');
						et_builder_debug_message( validator.errorList );
						validator.focusInvalid();
						return;
					}
					et_builder_debug_message('passed form validation');
				}
				this.$( options_selector ).each( function() {
					var $this_el = $(this),
						name = $this_el.is('#et_pb_content_main') ? 'et_pb_content_new' : $this_el.attr('id');
					
					// name attribute is used in normal html checkboxes, use it instead of ID
					if ( $this_el.is( ':checkbox' ) ) {
						name = $this_el.attr('name');
					}
					
					if ( typeof name === 'undefined' || ( -1 !== name.indexOf( 'qt_' ) && 'button' === $this_el.attr( 'type' ) ) ) {
						// settings should have an ID and shouldn't be a Quick Tag button from the tinyMCE in order to be saved
						return true;
					}
					
					if ( $this_el.hasClass( 'et-pb-helper-field' ) ) {
						// don't process helper fields
						return true;
					}
					
					if ( ( !$this_el.is( ':checkbox' ) || typeof attributes[name] !== 'undefined' ) && $this_el.is( '#et_pb_cvdb_content_visibility_check' ) ) {
						// Process content visibility check
						
						// Replace temporary ^~ signs with right brackets
						$this_el.val( $this_el.val().replace( /\]/g, '%5D' ) );
					}
				});
				return OriginalPerformSaving.call(this, option_tabs_selector);
			}
		});
		
		var OriginalRender = ET_PageBuilder.ModuleSettingsView.prototype.render;
		ET_PageBuilder.ModuleSettingsView = ET_PageBuilder.ModuleSettingsView.extend({
			render : function() {
				var $icon_font_options = [ "et_pb_font_icon", "et_pb_button_one_icon", "et_pb_button_two_icon", "et_pb_button_icon" ];
				// Replace encoded double quotes with normal quotes,
				// escaping is applied in modules templates
				_.each( this.model.attributes, function( value, key, list ) {
					if ( typeof value === 'string' && key !== 'et_pb_content_new' && -1 === $.inArray( key, $icon_font_options ) && ! /^\%\%\d+D\%\%$/.test( $.trim( value ) ) ) {
						return list[ key ] = value.replace( /%5D/g, ']' );
					}
				} );
				return OriginalRender.call(this);
			}
		});
		
		var OriginalSectionViewShowSettings = ET_PageBuilder.SectionView.prototype.showSettings;
		ET_PageBuilder.SectionView = ET_PageBuilder.SectionView.extend({
			showSettings: function(event) {
				if ( typeof event === 'undefined' ) {
					ET_PageBuilder.Events.trigger( 'et-pb-loading:ended' );
				}
				OriginalSectionViewShowSettings.call(this, event);
			}
		});
		
		var OriginalRowViewShowSettings = ET_PageBuilder.RowView.prototype.showSettings;
		ET_PageBuilder.RowView = ET_PageBuilder.RowView.extend({
			showSettings: function(event) {
				if ( typeof event === 'undefined' ) {
					ET_PageBuilder.Events.trigger( 'et-pb-loading:ended' );
				}
				OriginalRowViewShowSettings.call(this, event);
			}
		});
		
		var OriginalBlockModuleViewShowSettings = ET_PageBuilder.BlockModuleView.prototype.showSettings;
		ET_PageBuilder.BlockModuleView = ET_PageBuilder.BlockModuleView.extend({
			showSettings: function(event) {
				if ( typeof event === 'undefined' ) {
					ET_PageBuilder.Events.trigger( 'et-pb-loading:ended' );
				}
				OriginalBlockModuleViewShowSettings.call(this, event);
			}
		});
	});
})(jQuery);
