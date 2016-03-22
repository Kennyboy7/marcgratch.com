<div id="credit_table">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?php _e( 'Type', 'sprout-invoices' ) ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $credit ) ) : ?>
				<?php foreach ( $credit as $credit_id ) : ?>
					<?php
						$credit = SI_Credit::get_instance( $credit_id );
					if ( ! is_a( $credit, 'SI_Credit' ) ) {
						continue;
					} ?>
					<tr id="<?php echo (int) $credit_id ?>">
						<td width="10"><span class="credit_credit_type_deletion item_action item_delete" data-id="<?php echo (int) $credit_id ?>" data-nonce="<?php echo wp_create_nonce( SI_Account_Credits::SUBMISSION_NONCE ) ?>"></span></td>
						<td><?php echo esc_html( $credit->get_title() )  ?></td>
					</tr>
				<?php endforeach ?>
			<?php else : ?>
				<tr colspan="4"><td><?php _e( 'No credit type found.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>
	</table>
	<?php printf( __( '<a href="%s">Create New</a>', 'sprout-invoices' ), 'javascript:void(0)" id="show_credit_type_creation_modal" class="button button-primary' ) ?>
	
</div><!-- #credit_entries -->