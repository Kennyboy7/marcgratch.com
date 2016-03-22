<div id="admin_payments_import_wrap" class="admin_fields clearfix">
	<p>
		<span id="credit_importing">
			<span class="credit_import_question">
				<?php printf( '<button id="credit_payment_question_answer" class="button">%s</button>', __( 'Apply Credit Payment', 'sprout-invoices' ) ) ?>
			</span>
			<span id="credit_payment_client_selection" class="cloak">
				<select name="doc_invoice_credit" class="select2">
					<?php printf( '<option value="%s">%s</option>', 0, __( 'Select Client', 'sprout-invoices' ) ) ?>
					<?php foreach ( $client_options as $id => $client_name ) : ?>
						<?php printf( '<option value="%s">%s</option>', $id, $client_name ) ?>
					<?php endforeach ?>
				</select>
				<input id="account_credits_invoice_id" type="hidden" value="<?php echo get_the_ID() ?>" />
				<input id="account_credits_nonce" type="hidden" value="<?php echo wp_create_nonce( SI_Account_Credits::SUBMISSION_NONCE ) ?>" />
			</span><!-- #credit_importing_client_selection -->
		</span><!-- #credit_importing -->
		<?php if ( isset( $_GET[ SI_Account_Credits::IMPORT_QUERY_VAR ] ) && get_post_type( $_GET[ SI_Account_Credits::IMPORT_QUERY_VAR ] ) === SI_Client::POST_TYPE ) : ?>
			<script type="text/javascript">
				jQuery(function() {
					jQuery('#credit_importing_client_selection select').val( '<?php echo $_GET[ SI_Account_Credits::IMPORT_QUERY_VAR ] ?>' ).trigger('change');
				});
			</script>
		<?php endif ?>
	</p>
	<p class="description">
		<?php _e( 'Import a client\'s credit balance as an Admin Payment.', 'sprout-invoices' ) ?>
	</p>
</div>
<hr/>
