;(function( $, si, undefined ) {
	
	si.creditEntries = {
		config: {
			entries_table: '#credit_entries',
		},
	};

	/**
	 * Save credit entry
	 * @param  {array} data
	 */
	si.creditEntries.save = function( data, $save_button ) {
		data.action = 'sa_save_credit';
		$save_button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				console.log(response);
				if ( ! response.success ) {
					$('.spinner').hide();
					$save_button.after('<span class="inline_error_message">' + response.data.message + '</span>');	
				}
				else {
					$('.spinner').hide();
					$save_button.after('<span class="inline_success_message">' + si_js_object.credit_tracker_success_message + '</span>');
					si.creditEntries.RefreshTable();
				}
			}
		);
	};

	/**
	 * Delete the credit entry within the admin view of credit entries
	 */
	si.creditEntries.deleteCreditEntry = function( $button, data, to_remove ) {
		data.action = 'sa_remove_credit_entry';
		si.creditEntries.deleteActionTableRemoval( $button, data, to_remove );
	};

	/**
	 * Delete the credit credit_type within the admin view of credits
	 */
	si.creditEntries.deleteCreditType = function( $button, data, to_remove ) {
		data.action = 'sa_remove_credit';
		si.creditEntries.deleteActionTableRemoval( $button, data, to_remove );
	};

	si.creditEntries.deleteActionTableRemoval = function( $button, data, to_remove ) {
		$button.hide();
		$button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				if ( response.error ) {
					$('.spinner').hide();
					$button.after('<span class="inline_error_message">' + response.response + '</span>');	
				}
				else {
					$('.spinner').hide();
					$('#'+to_remove).fadeOut();
				}
			}
		);
	};

	/**
	 * use AJAX function to get table view
	 */
	si.creditEntries.RefreshTable = function() {
		var client_id = $('#credit_entries').data('client-id');
		$.post( si_js_object.ajax_url, { action: 'sa_credit_entries_table', client_id: client_id },
			function( response ) {
				$('#credit_entries').html( response );
				$('#sa_credit_credit').val('');
				$('#sa_credit_note').val('');
			}
		);
	};

	/**
	 * Create credit
	 * @param  {array} data
	 */
	si.creditEntries.saveType = function( data, $save_button ) {
		data.action = 'sa_create_credit_type';
		$save_button.after(si_js_object.inline_spinner);
		$.post( si_js_object.ajax_url, data,
			function( response ) {
				si.creditEntries.closeModalAfterCreditSave( response, $save_button );
			}
		);
	};

	si.creditEntries.closeModalAfterCreditSave = function( response, $save_button ) {
		if ( response.error ) {
			$('.spinner').hide();
			$save_button.after('<span class="inline_error_message">' + response.response + '</span>');	
		}
		else {
			// close modal
			self.parent.tb_remove();

			// Clear inputs
			$('#sa_credit_name').val('');
			$('#sa_credit_rate').val('');
			$('#sa_credit_percentage').val('');

			// change option text
			$('[name="sa_credit_credit_type_id"]').append($('<option/>', { 
					value: response.id,
					text : response.option_name 
				})).val(response.id);

		}
	};

	si.creditEntries.creditCreationModal = function( $drop ) {
		// thickbox
		tb_show( si_js_object.credit_creation_modal_title, si_js_object.credit_creation_modal_url );
	};

	si.creditEntries.creditTrackerModal = function( $drop ) {
		// Remove the dashboard widget if on the dashboard page
		if ( $('#credit_tracker.postbox' ).length ) {
			$('#credit_tracker.postbox' ).remove();
		};
		// thickbox
		tb_show( si_js_object.credit_tracker_modal_title, si_js_object.credit_tracker_modal_url );
	};

	si.creditEntries.creditPaymentButton = function( button ) {
		var $select_wrap = $('#credit_payment_client_selection'),
			$credit_help = $('.add_credit_help');
		$(button).hide();
		$credit_help.hide();
		$select_wrap.fadeIn();
	};

	si.creditEntries.creditImportingButton = function( button ) {
		var $select_wrap = $('#credit_importing_client_selection'),
			$credit_help = $('.add_credit_help');
		$(button).hide();
		$credit_help.hide();
		$select_wrap.fadeIn();
	};

	si.creditEntries.creditPaymentClientSelected = function( select ) {
		var $select = $(select),
			client_id = $select.val(),
			nonce = $('#account_credits_nonce').val(),
			invoice_id = $('#account_credits_invoice_id').val(),
			$info_client_span = $('#client b'),
			$info_client_select = $('[name="doc_client"]');

		$('span.inline_error_message').hide();
		$select.after(si_js_object.inline_spinner);
		$.post( ajaxurl, { action: 'sa_invoices_credit_payment', client_id: client_id, invoice_id: invoice_id, nonce: nonce },
			function( response ) {
				console.log(response);
				$('.spinner').hide();
				if ( ! response.success ) {
					$select.after('<span class="inline_error_message">' + response.data.message + '</span>');	
				}
				else {
					location.reload();
				}
			}
		);
	};

	si.creditEntries.creditImportingProjectSelected = function( select ) {
		var $select = $(select),
			client_id = $select.val(),
			nonce = $('#account_credits_nonce').val(),
			$info_client_span = $('#client b'),
			$info_client_select = $('[name="doc_client"]');

		$('span.inline_error_message').hide();
		$select.after(si_js_object.inline_spinner);
		$.post( ajaxurl, { action: 'sa_invoices_credit', client_id: client_id, nonce: nonce },
			function( response ) {
				$('.spinner').hide();
				if ( ! response.success ) {
					$select.after('<span class="inline_error_message">' + response.data.message + '</span>');	
				}
				else {
					$.each( response.data, function(i, credit) {
						si.creditEntries.creditAddItem( credit );
					});
					$('#credit_importing_client_selection').hide();
					$('#credit_import_question_answer').fadeIn();
				}
			}
		);

		// Update client dropdown if not other client is selected.
		// This will cause the dropdown to default to the first client.
		if ( $info_client_select.val() < 1 ) {
			$info_client_select.val( client_id );
			$info_client_span.text( $select.find('option:selected').text() );
		};
	};

	si.creditEntries.creditAddItem = function( credit ) {
		var $type_list = $('ol#line_item_list'),
			$type_header = $('#line_items_header');

		$type_list.after(si_js_object.inline_spinner);
		$.post( ajaxurl, { action: 'sa_get_credit_item', credit: credit },
			function( response ) {
				if ( response.success ) {
					var $row = $(response.data.option);

					$('.spinner').hide();
					
					// append the row to the list.
					$type_list.append($row);

					// update key
					si.lineItems.modifyInputKey();
					si.lineItems.calculateEachLineItemTotal();
					
					// Add the redactor
					if ( si_js_object.redactor ) {
						$row.find('.column_desc [name="line_item_desc[]"]').redactor();
					};
				}
				else {
					$type_list.append('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
				}
			}
		);
	};

	/**
	 * methods
	 */
	si.creditEntries.init = function() {
		// Save credit entry
		$( '#create_credit_entry' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();
			var data = {
				client_id: $('#sa_credit_client_id').val(),
				credit_type_id: $('#sa_credit_credit_type_id').val(),
				credit: $('#sa_credit_credit').val(),
				note: $('#sa_credit_note').val(),
				date: $('#sa_credit_date').val(),
				nonce: $('#sa_credit_nonce').val()
			};
			$('span.inline_error_message').hide();
			$('span.inline_success_message').hide();
			si.creditEntries.save( data, $( this ) );
		} );


		// Save credit credit_type
		$( '#create_credit_credit_type' ).live( 'click', function( e ) {
			e.stopPropagation();
			e.preventDefault();

			var data = {
				name: $('#sa_credit_name').val(),
				rate: $('#sa_credit_rate').val(),
				percentage: $('#sa_credit_percentage').val(),
				nonce: $('#sa_credit_nonce').val()
			};

			$('span.inline_error_message').hide();
			si.creditEntries.saveType( data, $( this ) );

		} );


		// Remove credit entry
		$( '.credit_entry_deletion' ).live( 'click', function( e ) {
			var data = {
					client_id: $(this).data('client-id'),
					id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			$('span.inline_error_message').hide();
			si.creditEntries.deleteCreditEntry( $(this), data, $(this).data('id') );
		} );

		// Dynamically insert the credit creation form.
		$( '#show_credit_type_creation_modal' ).live( 'click', function( e ) {
			si.creditEntries.creditCreationModal();
		} );

		// Dynamically add the credit tracking form.
		$( '.credit_tracker_popup' ).live( 'click', function( e ) {
			si.creditEntries.creditTrackerModal();
		} );

		// Credit importing
		$('#credit_import_question_answer').live( 'click', function(e) {
			e.preventDefault();
			si.creditEntries.creditImportingButton( this );
		});

		// Credit importing
		$('#credit_payment_question_answer').live( 'click', function(e) {
			e.preventDefault();
			si.creditEntries.creditPaymentButton( this );
		});

		// Credit importing
		$('#credit_importing_client_selection select').live( 'change', function(e) {
			e.preventDefault();
			si.creditEntries.creditImportingProjectSelected( this );
		});

		// Credit importing
		$('#credit_payment_client_selection select').live( 'change', function(e) {
			e.preventDefault();
			si.creditEntries.creditPaymentClientSelected( this );
		});


		// Remove credit credit_type
		$( '.credit_credit_type_deletion' ).live( 'click', function( e ) {
			var data = {
					id: $(this).data('id'),
					nonce: $(this).data('nonce')
				};
			$('span.inline_error_message').hide();
			si.creditEntries.deleteCreditType( $(this), data, $(this).data('id') );
		} );
	};
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.creditEntries.init();
});
