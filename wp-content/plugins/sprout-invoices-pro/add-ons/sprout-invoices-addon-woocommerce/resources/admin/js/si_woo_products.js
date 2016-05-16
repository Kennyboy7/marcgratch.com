;(function( $, si, undefined ) {
	
	si.siWoocommerceProducts = {
		config: {
			inline_spinner: '<span class="spinner si_inline_spinner" style="visibility:visible;display:inline-block;"></span>'	
		},
	};

	si.siWoocommerceProducts.addLineItem = function( product_id ) {
		var $type_list = $('ol#line_item_list'),
			$type_header = $('#line_items_header');

		$type_list.after(si_js_object.inline_spinner);
		$.post( ajaxurl, { action: 'sa_get_woo_product', product_id: product_id },
			function( response ) {
				if ( response.success ) {
					var $row = $(response.data.option);

					$('.spinner').hide();
					
					// append the row to the list.
					$type_list.append($row);

					// update key
					si.lineItems.modifyInputKey();
					si.lineItems.calculateEachLineItemTotal();
					si.lineItems.calculateLineItemsTotals();
					
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

	si.siWoocommerceProducts.getItem = function( product_id ) {
		$.post( ajaxurl, { action: 'sa_get_woo_product', product_id: product_id },
			function( response ) {
				return response;
			}
		);
	};

	si.siWoocommerceProducts.refreshAddItemButton = function() {
		$.post( ajaxurl, { action: 'sa_pd_items_options_view' },
			function( response ) {
				$('#predefined_line_items').html( response );
			}
		);
	};

	/**
	 * methods
	 */
	si.siWoocommerceProducts.init = function() {
		
		$( '#woo_product_line_item' ).select2({
			placeholder: "Woo Products",
			// Support for optgroup searching
			matcher: function modelMatcher (params, data) {
				data.parentText = data.parentText || "";

				// Always return the object if there is nothing to compare
				if ($.trim(params.term) === '') {
					return data;
				}

				// Do a recursive check for options with children
				if (data.children && data.children.length > 0) {
					// Clone the data object if there are children
					// This is required as we modify the object to remove any non-matches
					var match = $.extend(true, {}, data);

					// Check each child of the option
					for (var c = data.children.length - 1; c >= 0; c--) {
						var child = data.children[c];
						child.parentText += data.parentText + " " + data.text;

						var matches = modelMatcher(params, child);

						// If there wasn't a match, remove the object in the array
						if (matches == null) {
							match.children.splice(c, 1);
						}
					}

					// If any children matched, return the new object
					if (match.children.length > 0) {
						return match;
					}

					// If there were no matching children, check just the plain object
					return modelMatcher(params, match);
				}

				// If the typed-in term matches the text of this term, or the text from any
				// parent term, then it's a match.
				var original = (data.parentText + ' ' + data.text).toUpperCase();
				var term = params.term.toUpperCase();


				// Check if the text contains the term
				if (original.indexOf(term) > -1) {
					return data;
				}

				// If it doesn't contain the term, don't return anything
				return null;
			}
		});

		// Add line item
		$( '#woo_product_line_item' ).live( 'change', function( e ) {
			var id = jQuery( this ).val();
			if ( $.isNumeric( id ) ) {
				si.siWoocommerceProducts.addLineItem( id );
			}
			return;
		} );

	};


	/**
	 * TODO refactor est_and_invoices.js and use those methods instead.
	 */
	

	/**
	 * Store the line item index 
	 * @return {} 
	 */
	function modify_input_key() {
		$('ol.items_list').each(function(i, ol) {
			ol = $(ol);
			level1 = ol.closest('li').index() + 1;

			ol.children('li').each(function(i, li) {
				li = $(li);
				$index = ( level1 === 0 ) ? li.index() + 1 : level1 + '.' + (li.index() + 1);
				li.find('.line_item_index').val($index);
			});
		});
	}

	/**
	 * Hide the inputs for parent line items
	 * @return {} 
	 */
	function handle_parents() {
		$('ol.items_list .item').each(function(i, li) {
			// If has children
			if ( $(li).children('ol').length > 0 ) {
				// hide the parent input fields
				$(li).find('.column.parent_hide input').attr( "type", "hidden" );
			}
			else {
				$(li).find('.column.parent_hide input').attr( "type", "text" );
			}
		});
		$('ol.items_list .has_children').each(function(i, parent) {
			$(parent).find('.column.parent_hide input').val('');
		});
	}
	
})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.siWoocommerceProducts.init();
});
