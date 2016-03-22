<div id="si_dashboard" data-client-id="<?php echo (int) $client_id ?>">
	<table class="table table-hover">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'sprout-invoices' ) ?></th>
				<!--<th><?php esc_html_e( 'Type', 'sprout-invoices' ) ?></th>-->
				<th><?php esc_html_e( 'Note', 'sprout-invoices' ) ?></th>
				<th><?php esc_html_e( 'Credit Amount', 'sprout-invoices' ) ?></th>
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
					//$user = get_userdata( $data['user_id'] );

					$credit_val = (float) $data['credit_val'];
					$total_credits += $credit_val;

					if ( isset( $data['invoice_id'] ) ) {
						$total_credits_invoiced += $credit_val;
					}
					else {
						$unbilled_total_credit += $credit_val;
					} ?>
						<tr id="<?php echo (int) $credit_id ?>">
							<td><span class="credit_submitted_at"><?php echo date_i18n( get_option( 'date_format' ), $data['date'] ) ?></td>
							<!--<td><?php
							$credit_type_title = ( is_a( $credit_type, 'SI_Credit' ) ) ? $credit_type->get_title() : '&nbsp;' ;
							echo esc_html( $credit_type_title );  ?></td>-->
							<td><?php echo wpautop( $credit->get_title() ) ?></td>
							<td><?php si_number_format( $credit_val ) ?></td>
							<?php if ( isset( $data['invoice_id'] ) ) : ?>
								<tr class="info_row">
									<td colspan="3" class="tfoot_total_desc">
										<div class="invoice_info_row_wrap">
											<small><?php printf( '<b>Invoice Applied:</b> <a href="%s">%s</a>', get_permalink( $data['invoice_id'] ), get_the_title( $data['invoice_id'] ) ) ?></small>
										</div>
									</td>
								</tr>								
							<?php endif ?>
						</tr>
				<?php endforeach ?>
			<?php else : ?>
				<tr><td colspan="5"><?php _e( 'No credit entries for this client.', 'sprout-invoices' ) ?></td></tr>
			<?php endif ?>
		</tbody>

		<?php if ( $total_credits > 0.00 ) : ?>
			<tfoot>
				<tr>
					<td colspan="2" class="tfoot_total_desc"><?php _e( 'Total Applied', 'sprout-invoices' ) ?></td>
					<td><b><?php si_number_format( (float) $total_credits_invoiced ); ?></b></td>
				</tr>
				<?php if ( 0.00 < $unbilled_total_credit ) : ?>
					<tr>
						<td colspan="2" class="tfoot_total_desc"><?php _e( 'Credits Remaining', 'sprout-invoices' ) ?></td>
						<td><b><?php si_number_format( $unbilled_total_credit ); ?></b></td>
					</tr>
				<?php endif ?>
				<!--<tr>
					<td colspan="2" class="tfoot_total_desc"><?php _e( 'Totals', 'sprout-invoices' ) ?></td>
					<td><b><?php si_number_format( (float) $total_credits ); ?></b></td>
				</tr>-->
			</tfoot>
		<?php endif ?>
		
	</table>
</div><!-- #credit_entries -->