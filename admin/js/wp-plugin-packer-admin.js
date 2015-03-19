(function( $ ) {
	'use strict';
	$( document ).ready(function() {
		var form = $( '#wp_plugin_packer_form' );
		init_table_body();

		form.find( '.add-pack' ).click( function() {
			var last_pack = form.find( '.single-pack' ).last();
			var new_pack = last_pack.clone();
			new_pack.find( 'table.wp-plugin-packer tbody' ).removeClass( 'ui-sortable' );
			new_pack.find( 'tr.placeholder' ).show().siblings().remove();
			var headline = new_pack.find( 'h3' );
			if ( ! new_pack.find( '.remove-pack' ).length ) {
				new_pack.find( '.single-pack-title' ).after( '<div class="button remove-pack">Remove Pack</div>' );
			}
			last_pack.after( new_pack );
			init_table_body();
		});

		form.on( 'click', 'h3.editable', function() {
			$( this ).hide().siblings().show().focus();
		});

		form.on( 'change', '.single-pack .select-pack', function() {
			if ( $( this ).is( ':checked' ) ) {
				$( this ).closest( '.single-pack' ).find( 'table input:checkbox:not([disabled])' ).prop( 'checked', true ).trigger( 'change' );
			} else {
				$( this ).closest( '.single-pack' ).find( 'table input:checkbox:not([disabled])' ).prop( 'checked', false ).trigger( 'change' );
			}
			
		});

		form.on( 'keyup paste', 'input.pack-title', function( event ) {
			$( this ).siblings( 'h3' ).text( $( this ).val() );
		});
		form.on( 'keydown', 'input.pack-title', function( event ) {
			if ( event.which == 13 || event.keyCode == 13 ) {
				event.preventDefault();
				$( this )[0].blur();
				return false;
			}
		});
		form.on( 'blur', 'input.pack-title', function() {
			form.find( 'input[type="submit"]' ).prop( 'disabled', true );
			$( this ).hide().siblings(':not(input)').show();
			var data = {
				action: 'sanitize_title',
				sanitize_title: $( this ).val(),
			}
			var that = $( this );
			$.post( ajaxurl, data, function( response ) {
				that.siblings( '.pack-slug' ).val( response.data.message );
				form.find( 'input[type="submit"]' ).prop( 'disabled', false );
			});
		});

		form.submit( function( e ) {
			var packs = {};
			var pack = {};
			form.find( '.single-pack' ).each( function( index ) {
				var slug = $( this ).find( 'input.pack-slug' ).val();
				packs[ slug ] = {
					'name': $( this ).find( 'input.pack-title' ).val(),
					'plugins': {}
				}
				$( this ).find( '.plugin_file_name' ).each( function( index ) {
					packs[ slug ].plugins[ $( this ).val() ] = {
						'name': $( this ).siblings( '.plugin-title-value' ).text(),
						'version': $( this ).siblings( '.version' ).children( '.version-value' ).text(),
						'file': $( this ).val(),						
					}
				});
			});

			form.find( '#plugin_packs' ).val( JSON.stringify( packs ) );

		});

		form.on( 'click', '.remove-pack', function( event ) {
			var plugins = $( this ).siblings( 'table' ).find( 'tr:not(.placeholder)' );
			if ( plugins.length ) {
				plugins.appendTo( 'table.wp-plugin-packer:first' );
			}
			$( this ).closest( '.single-pack' ).remove();
		});
		
		//Import dialog
		var frame = wp.media({
			title: translationStrings.import_modal_title,
			library: {
				type: 'application/json',
			},
			multiple: false
		});
		frame.on( 'select', function( event ) {
			var selection = frame.state().get('selection').first().toJSON();
			if ( selection ) {
				var data = {
					'action': 'wp_plugin_packer_import_file',
					'attachment_id': selection.id,
					'nonce': translationStrings.nonce,
				}
				$.post( ajaxurl, data, _.bind( function( response ) {
					window.location.reload();
				}))
			}
		});

		var checkboxes = form.find( 'input:checkbox:not(.select-pack)' );
		var reactive_btns = form.find( '.export-button, .disable-button, .enable-button' );
		if ( checkboxes.filter( ':checked' ).length ) {
			reactive_btns.removeClass( 'disabled' );
		} else {
			reactive_btns.addClass( 'disabled' );
		}

		form.on( 'change', 'table input[type="checkbox"]', function( event ) {
			var checkboxes = $( event.target ).closest( 'table' ).find( 'input:checkbox' );
			if ( checkboxes.filter( ':checked' ).length ) {
				reactive_btns.removeClass( 'disabled' );
			} else {
				reactive_btns.addClass( 'disabled' );
			}

			if ( $( this ).is( ':not(:checked)' ) ) {
				$( this ).closest( '.single-pack' ).find( 'input.select-pack' ).prop( 'checked', false );
			}
		});

		form.find( '.import-button' ).click( function() {
			if ( confirm( translationStrings.import_confirm ) ) {
				frame.open();
			}
			return false;
		});

		$( 'body' ).append( '<iframe id="export_file_iframe"></iframe>' );

		form.find( '.export-button' ).click( function() {
			if ( $( this ).hasClass( 'disabled' ) )
				return;
			
			var plugin_files = '';
			checkboxes.filter( ':checked' ).each( function( i ) {
				plugin_files += 'plugin_files[]=' + encodeURIComponent( $( this ).closest( 'tr' ).find( 'input.plugin_file_name' ).val() ) + '&';
			});
			$( '#export_file_iframe' ).attr( 'src', document.URL + '&' + plugin_files + 'action=export_file&nonce=' + translationStrings.nonce );
		});


		form.find( '.disable-button, .enable-button' ).click( function() {
			if ( $( this ).hasClass( 'disabled' ) )
				return;

			var plugin_files = '';
			checkboxes.filter( ':checked' ).each( function( i ) {
				plugin_files += 'checked[]=' + encodeURIComponent( $( this ).closest( 'tr' ).find( 'input.plugin_file_name' ).val() ) + '&';
			});
			plugin_files += 'action=';
			if ( $( this ).hasClass( 'enable-button' ) ) {
				plugin_files += 'activate-selected';
			} else {
				plugin_files += 'deactivate-selected';
			}
			plugin_files += '&_wpnonce=' + translationStrings.nonce_plugins;
			$.post( translationStrings.deactivate_url, plugin_files, _.bind( function( response ) {
				window.location.reload();
			}));

		});

		form.find( '.export-all-button' ).click( function() {
			$( '#export_file_iframe' ).attr( 'src', document.URL + '&action=export_file&nonce=' + translationStrings.nonce );
		});
	});

	function init_table_body() {
		var table_body = $( 'table.wp-plugin-packer tbody' );
		table_body.each( function( i ) {
			if ( $( this ).hasClass( 'ui-sortable' ) ) {
				$( this ).sortable( 'destroy' );
			}
			if ( $( this ).find( 'tr' ).length == 1 ) {
				//Table is empty, show placeholder
				$( this ).find( 'tr.placeholder' ).show();
			}
		});

		table_body.sortable({
			connectWith: 'table.wp-plugin-packer tbody',
			cancel: '.placeholder',
			distance: 15,
			out: function( event, ui ) {
				//if group is empty, show placeholder
				if ( ui.sender.find( 'tr' ).length == 1 )
					ui.sender.find( '.placeholder:hidden' ).show();
			},
			receive: function( event, ui ) {
				ui.item.siblings( '.placeholder:visible' ).hide();
			}
		});
	}

})( jQuery );
