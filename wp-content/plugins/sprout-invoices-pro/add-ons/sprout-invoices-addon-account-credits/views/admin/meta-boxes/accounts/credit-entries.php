<div id="credit_entries" data-client-id="<?php echo (int) $client_id ?>">
	<table class="table sa_table sa_table-hover">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?php esc_html_e( 'Info', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Type', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Note', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Credit', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Payment', 'sprout-invoices' ) ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
				$total_credit = 0;
				$total_credits = 0;
				$total_credits_invoiced = 0;
				$unbilled_total_credit = 0;

			if ( ! empty( $credits ) ) :

				$credit_records = array();
				foreach ( $credits as $credit_id ) {
					$credit = SI_Credit::get_credit_entry( $credit_id );
					if ( ! is_a( $credit, 'SI_Record' ) ) {
						continue;
					}
					$credit_records[ $credit_id ] = $credit->get_data();
				}

				uasort( $credit_records, array( 'SI_Controller', 'sort_by_date' ) );

				foreach ( $credit_records as $credit_id => $data ) :

					$credit = SI_Credit::get_credit_entry( $credit_id );
					$credit_type = SI_Credit::get_instance( $credit->get_associate_id() );
					$user = get_userdata( $data['user_id'] );

					$credit_val = (float) $data['credit_val'];
					$total_credits += $credit_val;
					if ( isset( $data['payment_id'] ) ) {
						$total_credits_invoiced += $credit_val;
					} else {
						$unbilled_total_credit += $credit_val;
					} ?>
						<tr id="<?php echo (int) $credit_id ?>">
							<td><span class="credit_entry_deletion item_action item_delete" data-id="<?php echo esc_attr( $credit_id ); ?>" data-client-id="<?php echo esc_attr( $client_id ); ?>" data-nonce="<?php echo wp_create_nonce( SI_Account_Credits::SUBMISSION_NONCE ) ?>"></span></td>
							<td><span class="credit_submitted_by"><a href="<?php echo get_edit_user_link( $user->ID ) ?>"><?php echo $user->data->display_name ?></a></span><br/>@&nbsp;<?php echo date_i18n( get_option( 'date_format' ), $data['date'] ) ?></td>
							<td><?php
							$credit_type_title = ( is_a( $credit_type, 'SI_Credit' ) ) ? $credit_type->get_title() : '&nbsp;' ;
							echo esc_html( $credit_type_title );  ?></td>
							<td><?php echo wpautop( $credit->get_title() ) ?></td>
							<?php if ( ! isset( $data['payment_id'] ) ) : ?>
								<td>
									<?php si_number_format( $credit_val ) ?>
								</td>
								<td>&nbsp;</td>
							<?php else : ?>
								<td>&nbsp;</td>
								<td><?php sa_formatted_money( abs( $credit_val ) ) ?></td>
							<?php endif ?>
						</tr>
				<?php endforeach ?>
			<?php else : ?>
				<tr><td colspan="6"><?php _e( 'No credit entries for this client.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>

		<?php if ( ! empty( $credit_records ) ) : ?>
			<tfoot>
				<tr>
					<td colspan="4" class="tfoot_total_desc"><?php _e( 'Invoiced', 'sprout-invoices' ) ?></td>
					<td>&nbsp;</td>
					<td><?php sa_formatted_money( (float) abs( $total_credits_invoiced ) ); ?></td>
				</tr>
				<tr>
					<td colspan="4" class="tfoot_total_desc"><?php _e( 'Credit Balance', 'sprout-invoices' ) ?></td>
					<td><?php si_number_format( (float) $total_credits ); ?></td>
					<td>&nbsp;</td>
				</tr>
			</tfoot>
		<?php endif ?>
		
	</table>
</div><!-- #credit_entries -->
