<table width="100%" cellpadding="0" cellspacing="0" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"><tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;">
	<thead>
		<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;">
			<th style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0; padding-bottom: 10px;"><?php _e( 'Status', 'sprout-invoices' ) ?></th>
			<th style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0; padding-bottom: 10px"><?php _e( 'Invoice', 'sprout-invoices' ) ?></th>
			<th style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0; padding-bottom: 10px"><?php _e( 'Balance', 'sprout-invoices' ) ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! empty( $invoices ) ) :

			foreach ( $invoices as $invoice_id ) :
				$invoice = SI_Invoice::get_instance( $invoice_id ); ?>
				<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;">
					<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
							<?php if ( si_get_invoice_issue_date( $invoice_id ) ) : ?>
								<small><time datetime="<?php si_invoice_issue_date( $invoice_id ) ?>"><?php printf( __( '<b>Issued:</b> %s', 'sprout-invoices' ), date_i18n( apply_filters( 'si_client_summary_date_format', 'M. jS' ), si_get_invoice_issue_date( $invoice_id ) ) ) ?></time></small>
							<?php endif ?>
							<?php if ( si_get_invoice_due_date( $invoice_id ) ) : ?>
								<small><time datetime="<?php si_invoice_due_date( $invoice_id ) ?>"><?php printf( __( '<b>Due:</b> %s', 'sprout-invoices' ), date_i18n( apply_filters( 'si_client_summary_date_format', 'M. jS' ), si_get_invoice_due_date( $invoice_id ) ) ) ?></time></small>
							<?php endif ?>
					</td>
					<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
						<?php if ( 'temp' !== si_get_invoice_status( $invoice_id ) ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'dashboard' => 1 ), get_permalink( $invoice_id ) ) )?>"><?php echo get_the_title( $invoice_id ) ?></a>
						<?php else : ?>
							<?php echo get_the_title( $invoice_id ) ?>
						<?php endif ?>
					</td>

					<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
						<?php si_invoice_balance( $invoice_id ) ?>
					</td>
				</tr>

			<?php endforeach ?>

		<?php else : ?>
			<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"><td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top" colspan="5" rowspan="3"><?php _e( 'No invoices available.', 'sprout-invoices' ) ?></td></tr>
		<?php endif ?>
	</tbody>
</table>
