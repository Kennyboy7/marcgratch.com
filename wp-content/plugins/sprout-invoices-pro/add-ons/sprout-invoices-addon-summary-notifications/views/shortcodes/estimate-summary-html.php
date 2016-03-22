<table width="100%" cellpadding="0" cellspacing="0" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"><tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;">
	<thead>
		<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;">
			<th style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0; padding-bottom: 10px"><?php _e( 'Status', 'sprout-invoices' ) ?></th>
			<th style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0; padding-bottom: 10px"><?php _e( 'Estimate', 'sprout-invoices' ) ?></th>
			<th style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0; padding-bottom: 10px"><?php _e( 'Action', 'sprout-invoices' ) ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! empty( $estimates ) ) :  ?>
			<?php foreach ( $estimates as $estimate_id ) : ?>
				<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;">
					<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
							<?php if ( si_get_estimate_issue_date( $estimate_id ) ) :  ?>
								<small><time datetime="<?php si_get_estimate_issue_date( $estimate_id ) ?>"><?php printf( __( '<b>Issue Date:</b> %s', 'sprout-invoices' ), date_i18n( apply_filters( 'si_client_summary_date_format', 'M. jS' ), si_get_estimate_issue_date( $estimate_id ) ) ) ?></time></small>
							<?php endif ?>
							<?php if ( si_get_estimate_expiration_date( $estimate_id ) ) :  ?>
								<small><time datetime="<?php si_estimate_expiration_date( $estimate_id ) ?>"><?php printf( __( '<b>Expiration:</b> %s', 'sprout-invoices' ), date_i18n( apply_filters( 'si_client_summary_date_format', 'M. jS' ), si_get_estimate_expiration_date( $estimate_id ) ) ) ?></time></small>
							<?php endif ?>
					</td>
					<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
						<?php if ( si_get_estimate_status( $estimate_id ) == 'pending' ) :  ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'dashboard' => 1 ), get_permalink( $estimate_id ) ) ) ?>"><?php echo get_the_title( $estimate_id ) ?></a>
						<?php else : ?>
							<?php echo get_the_title( $estimate_id ) ?>
						<?php endif ?>
					</td>

					<?php if ( si_get_estimate_status( $estimate_id ) == 'pending' ) :  ?>
						<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
							<a href="<?php echo esc_url( add_query_arg( array( 'dashboard' => 'pay' ), get_permalink( $estimate_id ) ) ) ?>" class="est_action_link"><span class="button label"><?php _e( 'Accept/Decline', 'sprout-invoices' ) ?></span></a>
						</td>
					<?php else : ?>  <!-- Estimate is in review -->
						<td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
							<small><?php _e( '<b>Pending</b> Review', 'sprout-invoices' ) ?></small>
						</td>
					<?php endif ?>
				</tr>
			<?php endforeach ?>
		<?php else : ?>
			<tr style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; padding: 0;"><td class="content-block" style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top"colspan="6" rowspan="3"><?php _e( 'No estimates pending action.', 'sprout-invoices' ) ?></td></tr>
		<?php endif ?>
	</tbody>
</table>
