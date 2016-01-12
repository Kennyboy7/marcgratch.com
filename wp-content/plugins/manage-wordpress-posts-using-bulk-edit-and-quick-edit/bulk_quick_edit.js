(function($) {

	// we create a copy of the WP inline edit post function
	var self = {};
	var $wp_inline_edit = inlineEditPost.edit;
	var $wp_inline_save = inlineEditPost.save;
	var $the_list = $('#the-list');
	var spinner = $( '.spinner').clone().css({float: "left"});

	// and then we overwrite the function with our own code
	inlineEditPost.edit = function( id ) {
		// "call" the original WP edit function
		// we don't want to leave WordPress hanging
		$wp_inline_edit.apply( this, arguments );
		
		// now we take care of our business

		// get the post ID
		var $post_id = 0;
		if ( typeof( id ) == 'object' )
			$post_id = parseInt( this.getId( id ) );


		if ( $post_id > 0 ) {

			// define the edit row
			var $edit_row = $( '#edit-' + $post_id );
			var $parent_task = $( '#inline_' + $post_id ).find(".post_parent").text();

			console.log($parent_task);

			if (self.triggerProjectAjax === true){
				console.log('no project in query string');
				$edit_row.find( 'select[name="post_parent"]' ).prop('disabled',true);
				var data = {
					action: 'mg_bulk_quick_edit_get_tasks_per_project',
					post_id: $post_id
				};

				self.response = $.post( ajaxurl, data );

				$.when( self.response ).done(function( response ){
					console.log(response);
					self.response = JSON.parse(response);
					console.log(self.response);
					self.updateTasks( self.response.tasks, $edit_row );
					$edit_row.find( 'select[name="post_parent"]' ).find( 'option[value="'+$parent_task+'"]').prop('selected',true);
					$edit_row.find( 'select[name="post_parent"]' ).prop('disabled',false);
				});
				$.when( self.response ).fail( function( response ){
					console.log('failed: ' + response);
				});
			} else {
				console.log('there IS project in query string');
				self.updateTasks( self.updatedTasks, $edit_row );
				$edit_row.find( 'select[name="post_parent"]' ).find( 'option[value="'+$parent_task+'"]').prop('selected',true);
			}

			// get the issue type
			var $issue_type = $( '#issue_type-' + $post_id ).find(":selected").text();

			// set the issue type
			$edit_row.find( 'select[name="issue_type"]' ).find( 'option[value="'+$issue_type+'"]').prop('selected',true);

			// get the priority
			var $priority = $( '#priority-' + $post_id).find(":selected").text();

			// set the priority
			$edit_row.find( 'select[name="priority"]' ).find( 'option[value="'+$priority+'"]').prop('selected',true);

			// get the project
			var $project = $( '#project-' + $post_id).data("project-id");

			// set the project
			$edit_row.find( 'select[name="project"]' ).find( 'option[value="'+$project+'"]').prop('selected',true);

			// get the Estimates
			var $estimates = $( '#estimates-' + $post_id).data('estimates-id');

			// set the Estimates
			$.each( $edit_row.find( 'input[data-name-clean="add-line-item-to-estimate"]' ), function(i,v){
				if($.inArray(parseInt($(this).val()), $estimates) !== -1){
					$(this).prop('checked','checked');
				} else {
					$(this).prop('checked','');
				}

			});

			// get the Invoices
			var $invoices = $( '#invoices-' + $post_id).data('invoices-id');

			// set the Invoices
			$.each( $edit_row.find( 'input[data-name-clean="add-line-item-to-invoice"]' ), function(i,v){
				if($.inArray(parseInt($(this).val()), $invoices) !== -1){
					$(this).prop('checked',true);
				}

			});

			var $estimated_time = $( '#estimated_time-' + $post_id).find(".editable").text();

			// set the estimated time
			$edit_row.find( 'input[name="estimated_time"]' ).val( $estimated_time);
			$edit_row.find( 'input[name="estimated_time"]' ).text( $estimated_time );


		}
	};

	inlineEditPost.save = function( id, referrer ) {

		if( typeof referrer === 'undefined' ){
			if ($(self.referrer).hasClass('save')){
				referrer = 'quick_save';

				var $post_id = 0;
				if ( typeof( id ) == 'object' )
					$post_id = parseInt( this.getId( id ) );

				var $edit_row = $( '#edit-' + $post_id );

				var estimates_removal_input =
						'<fieldset class="inline-edit-col-left">' +
						'<div class="inline-edit-col">' +
						'<label>' +
						'<span class="input-text-wrap">' +
						'<input name="estimates_to_remove" data-name-clean="estimates-to-remove" data-label="Estimates to Remove" id="estimates-to-remove" class="pods-form-ui-field-type-text" type="hidden" value="" tabindex="2" maxlength="255" />' +
						'</span>' +
						'</label>' +
						'</div>' +
						'</fieldset>';

				$edit_row.find("fieldset").last().after(estimates_removal_input);

				var $estimates = $( '#estimates-' + $post_id).data('estimates-id');
				var $estimates_not_checked = [];
				var $estimates_to_remove = [];

				// set the Estimates
				$.each( $edit_row.find( 'input[data-name-clean="add-line-item-to-estimate"]' ), function(i,v){
					if ($(this).prop("checked") == false){
						$estimates_not_checked.push(parseInt($(this).val()));
					}
				});


				$.grep($estimates_not_checked, function(el) {
					if ($.inArray(el, $estimates) !== -1){
						$estimates_to_remove.push(el);
					}
				});

				$("input[name='estimates_to_remove']").val($estimates_to_remove);


				// get the Invoices
				var invoices_removal_input =
						'<fieldset class="inline-edit-col-left">' +
						'<div class="inline-edit-col">' +
						'<label>' +
						'<span class="input-text-wrap">' +
						'<input name="invoices_to_remove" data-name-clean="invoices-to-remove" data-label="Invoices to Remove" id="invoices-to-remove" class="pods-form-ui-field-type-text" type="hidden" value="" tabindex="2" maxlength="255" />' +
						'</span>' +
						'</label>' +
						'</div>' +
						'</fieldset>';

				$edit_row.find("fieldset").last().after(invoices_removal_input);

				var $invoices = $( '#invoices-' + $post_id).data('invoices-id');
				var $invoices_not_checked = [];
				var $invoices_to_remove = [];

				// set the Estimates
				$.each( $edit_row.find( 'input[data-name-clean="add-line-item-to-invoice"]' ), function(i,v){
					if ($(this).prop("checked") == false){
						$invoices_not_checked.push(parseInt($(this).val()));
					}
				});

				$.grep($invoices_not_checked, function(el) {
					if ($.inArray(el, $invoices) !== -1){
						$invoices_to_remove.push(el);
					}
				});

				$("input[name='invoices_to_remove']").val($invoices_to_remove);

				$wp_inline_save.apply( this, arguments );
				return false;
			}
		}

		var params, fields, page = $('.post_status_page').val() || '';

		if ( typeof(id) === 'object' ) {
			id = this.getId(id);
		}

		var $issue_type = $( '#issue_type-' + id ).find(":selected").text();
		var $project = $( '#project-' + $post_id).attr("data-project-id");
		var $priority = $( '#priority-' + id).find(":selected").text();
		var $estimated_time = $( '#estimated_time-' + id).find('input[name="estimated_time"]').val();
		var $status = $( '#status-' + id ).find(":selected").val();

		$( 'table.widefat .spinner' ).addClass( 'is-active' );

		params = {
			action: 'inline_edit_mg_task_meta',
			post_type: typenow,
			post_ID: id,
			edit_date: 'true',
			post_status: page,
			issue_type: $issue_type,
			priority: $priority,
			project: $project,
			estimated_time: $estimated_time,
			task_status: $status,
			referrer: referrer
		};

		fields = $('#edit-'+id).find(':input').serialize();
		params = fields + '&' + $.param(params);

		// make ajax request
		$.post( ajaxurl, params )
			.done(function( r ) {
				var response = JSON.parse( r );
				$('table.widefat .spinner').removeClass('is-active');
				$('.ac_results').hide();
				if (typeof response === 'undefined') {
					$('#edit-' + id + ' .inline-edit-save .error').html(inlineEditL10n.error).show();
				}
				else if ( response !== 1 && 'undefined' !== typeof response.estimated_time ){
					var currentField =  $('#estimated_time-'+ response.post_id).find('.editable'),
						hours = +(response.estimated_time /60).toFixed(2) + 'h',
						row = currentField.parents('td');
					spinner.fadeOut('slow',function(){
						spinner.remove();
						currentField.text(hours).fadeIn('slow');
						currentField.next('input').val(hours);
					});
				}
				else if ( response !== 1 && 'undefined' !== typeof response.task_status ){
					var currentRow =  $('tr#post-'+ response.post_id);
					var currentRowClasses =  currentRow.attr('class');
					var currentRowClassesArray = currentRowClasses.split(' ');
					var currentInput = $(currentRow).find("select[name='status']");
					$.each(currentRowClassesArray, function(){
						if (this.indexOf('status-') > -1){
							$(currentRow).removeClass(this.valueOf());
							$(currentRow).addClass('status-'+response.task_status);
						}
					});
					currentInput.attr('disabled',false);
					$(currentRow).find("span.post-state").text($(currentInput).find(":selected").text());

				}
			})
			.fail(function(response){
				console.log("failed with the following response: ");
				console.log(response);
			});
		return false;
	};

	$(".inline-edit-save .save").on("click", function(){
		self.referrer = this;
	});


	$( '#bulk_edit' ).on( 'click', function( id ) {

		// define the bulk edit row
		var $bulk_row = $( '#bulk-edit' );

		// get the selected post ids that are being edited
		var $post_ids = new Array();
		$bulk_row.find( '#bulk-titles' ).children().each( function() {
			$post_ids.push( $( this ).attr( 'id' ).replace( /^(ttle)/i, '' ) );
		});

		// get the custom fields
		var $estimated_time = $bulk_row.find('input[name="estimated_time"]').val();
		var $issue_type = $bulk_row.find('select[name="issue_type"]').val();
		var $priority = $bulk_row.find('select[name="priority"]').val();
		var $project = $bulk_row.find('select[name="project"]').val();
		var $estimates = [];
		var $invoices = [];
		var $estimates_to_remove = [];
		var $invoices_to_remove = [];
		var $invoices_no_change = $bulk_row.find('#add_line_item_to_invoice').find('input[data-name-clean="dont-change-anything"]:checked').length;
		var $estimates_no_change = $bulk_row.find('#add_line_item_to_estimate').find('input[data-name-clean="dont-change-anything"]:checked').length;

		// get associated estimates
		$.each( $bulk_row.find( 'input[data-name-clean="add-line-item-to-estimate"]:checked' ), function(){
			var val = $(this).val();
			$estimates.push(val);
		});

		// get associated invoices
		$.each( $bulk_row.find( 'input[data-name-clean="add-line-item-to-invoice"]:checked' ), function(){
			var val = $(this).val();
			$invoices.push(val);
		});


		var data = {
			action: 'manage_wp_posts_using_bulk_quick_save_bulk_edit', // this is the name of our WP AJAX function that we'll set up next
			post_ids: $post_ids // and these are the 2 parameters we're passing to our function
		};

		if ($estimated_time !== ''){
			data['estimated_time'] = $estimated_time
		}

		if ($issue_type !== ''){
			data['issue_type'] = $issue_type
		}

		if ($priority !== ''){
			data['priority'] = $priority
		}

		if ($project !== ''){
			data['project'] = $project
		}

		if ($invoices !== []){
			data['add_line_item_to_invoice'] = $invoices
		}

		if ($estimates !== []){
			data['add_line_item_to_estimate'] = $estimates
		}

		if ($invoices_no_change < 1){
			$.each( $bulk_row.find( 'input[data-name-clean="add-line-item-to-invoice"]' ), function(){
				var cb = this;
				var val = $(cb).val();
				if ($(cb).prop('checked') !== true && $(cb).prop('checked') !== 'checked'){
					$invoices_to_remove.push(val);
				}
			});
			data['invoices_to_remove'] = $invoices_to_remove;
		}

		if ($estimates_no_change < 1){
			$.each( $bulk_row.find( 'input[data-name-clean="add-line-item-to-estimate"]' ), function(){
				var cb = this;
				var val = $(cb).val();
				if ($(cb).prop('checked') !== true && $(cb).prop('checked') !== 'checked'){
					$estimates_to_remove.push(val);
				}
			});
			data['estimates_to_remove'] = $estimates_to_remove;
		}

		// save the data
		$.ajax({
			url: ajaxurl, // this is a variable that WordPress has already defined for us
			type: 'POST',
			async: false,
			cache: false,
			data: data
		});

	});

	$($the_list).on( 'change', '.iedit select.pods-form-ui-field-type-pick', function( e, id ) {
		return inlineEditPost.save(this, $(e.target).attr('data-name-clean'));
	});

	$($the_list).on( 'change', '.iedit select[name="status"]', function( e, id ) {
		var clickedOpt = $(e.target);

		if (clickedOpt.val() !== ""){
			$(this).attr('disabled','disabled');
			return inlineEditPost.save(this, $(e.target).attr('data-name-clean'));
		}
	});

	$($the_list).on('click', 'td.estimated_time span.editable', function(evt){
		var elem = $(this);
		var oldElem = $("td.active").not(elem);
		oldElem.find('.editable').show();
		oldElem.find('input').prop('type','hidden');
		oldElem.find('span.save-options').hide();
		oldElem.removeClass('active');
		elem.parents('td').addClass('active');
		elem.hide();
		elem.siblings('input[name="estimated_time"]').prop('type','text');
		elem.siblings('span.save-options').show();
		evt.stopPropagation();
		return false;
	});

	$($the_list).on('click', 'td.estimated_time .dashicons-no', function(evt){
		var elem = $(this);
		elem.parents('td').removeClass('active');
		elem.parents('td').find('.editable').show();
		elem.parents('td').find('input').prop('type','hidden');
		elem.parents('span.save-options').hide();
		evt.stopPropagation();
		return false;
	});

	$('input[name="estimated_time"]').on('click',function(evt){
		evt.preventDefault();
		evt.stopPropagation();
	});

	$(document).on('click', function(evt){
		var elem = $("td.active");
		elem.find('.editable').show();
		elem.find('input').prop('type','hidden');
		elem.find('span.save-options').hide();
		elem.removeClass('active');
	});

	$($the_list).on( 'click', 'td.estimated_time .dashicons-yes', function( id ) {
		var row = $(this).parents('td'),
			input = row.find('input[name="estimated_time"]'),
			value = input.val();

		row.find('span.save-options').hide();
		input.prop('type','hidden');
		row.prepend(spinner.fadeIn('slow'));

		return inlineEditPost.save(this, 'estimated_time');
	});

	$("textarea[name='tax_input[fixversion]']").parents('label').remove();
	$("#bulk-edit .inline-edit-col-right").removeClass('inline-edit-col-right').addClass('inline-edit-col-left');
	$.each($("#bulk-edit select"), function(){
		$(this).val("");
	});
	$("#bulk-edit").find("input[name='estimated_time']").val('');
	$.each($("#bulk-edit").find(".pods-pick-values.pods-pick-checkbox"),function(){
		$(this).find("ul").prepend(
				'<li>' +
				'<div class="pods-field pods-boolean">' +
				'<input name="dont_change_anything" data-name-clean="dont-change-anything" data-label="Don\'t Change Anything" id="pods-form-ui-do-nothing" class="pods-form-ui-field-type-pick" type="checkbox" tabindex="2" value="pi" checked="checked">' +
				'<label class="pods-form-ui-label" for="dont_change_anything">' +
				'Don\'t Change Anything' +
				'</label>' +
				'</div>' +
				'</li>'
		)
	});
	$.each($("#bulk-edit").find("input[data-name-clean='add-line-item-to-estimate']"), function(){
		var cb = this;
		$(cb).on('click',function( cb ){
			$(cb.target).parents('ul').find('input[data-name-clean="dont-change-anything"]').prop('checked',false);
		});
		$(this).prop("checked",false);
	});
	$.each($("#bulk-edit").find("input[data-name-clean='add-line-item-to-invoice']"), function(){
		var cb = this;
		$(cb).on('click',function( cb ){
			$(cb.target).parents('ul').find('input[data-name-clean="dont-change-anything"]').prop('checked',false);
		});
		$(this).prop("checked",false);
	});
	$('input[data-name-clean="dont-change-anything"]').on('click',function(){
		$.each($("#bulk-edit").find("input[data-name-clean='add-line-item-to-estimate']"), function(){
			$(this).prop("checked",false);
		});
		$.each($("#bulk-edit").find("input[data-name-clean='add-line-item-to-invoice']"), function(){
			$(this).prop("checked",false);
		});
	});

	var url = window.location.toString();
	var query_string = url.split("?");
	var params = query_string[1].split("&");
	var param_list = {};

	$.each(params, function(){
		var param_item = this.split("=");
		if (param_item.indexOf("project") > -1 && 'undefined' !== typeof param_item[1] && 'project' === param_item[0]){
			param_list[param_item[0]] = param_item[1];
		}
	});

	if ( $.isEmptyObject(param_list) === true ){
		self.triggerProjectAjax = true;
	}
	else {
		var data = {
			action: 'mg_bulk_quick_edit_get_tasks_per_project',
			project_id: param_list.project
		};

		self.response = $.post( ajaxurl, data );

		$.when( self.response ).done(function( response ){
			self.response = JSON.parse(response);
			self.updatedTasks= self.response.tasks;
		});
		$.when( self.response ).fail( function( response ){
			console.log('failed: ' + response);
		});
	}

	self.updateTasks = function( tasks, context ){

		tasks.push(0);

		if ( context !== false ){
			var tasks_select = context.find('select[name="post_parent"]');
		}
		else {
			var tasks_select = $('select[name="post_parent"]');
		}

		var supplied_options = [];
		$.each( tasks_select.find("option") , function(){
			supplied_options.push(parseInt($(this).val()));
		});
		$.grep(supplied_options, function(el) {
			if ($.inArray(el, tasks) == -1){
				tasks_select.find("option[value='"+el+"']").remove();
			}
		});
	};


	var statusi = [
		{
			label: 'Pending',
			slug:	'not-started'
		},
		{
			label: 'In Progress',
			slug:	'in-progress'
		},
		{
			label: 'Testing',
			slug:	'testing'
		},
		{
			label: 'Complete',
			slug:	'complete'
		}
	];

	$.each(statusi, function(){
		$( 'select[name=\"_status\"]' ).append( '<option value="'+this.slug+'">'+this.label+'</option>' );
	});


})(jQuery);

jQuery( document.body ).on( 'post-load', function () {
	console.log('loaded');
} );
