<?php if ( empty( $children ) || $duration > count( $children ) ) : ?>
	<div id="recurring_invoice_options_wrap" class="admin_fields clearfix">
		<?php sa_admin_fields( $fields, 'recurring_invoice' ); ?>
	</div>	
<?php else : ?>
	<p><?php _e( 'No options available, since all recurring invoices have been generated.', 'sprout-invoices' ) ?></p>
<?php endif ?>


<?php if ( ! empty( $children ) ) :  ?>
	<b><?php _e( 'Generation History', 'sprout-invoices' ) ?></b>
	<ul>
		<?php foreach ( $children as $c_invoice_id ) :  ?>
			<li><?php printf( '%s &mdash; <a href="%s">%s</a>', get_post_time( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), false, $c_invoice_id ), get_edit_post_link( $c_invoice_id ), get_the_title( $c_invoice_id ) ); ?></li>
		<?php endforeach ?>
	</ul>
<?php elseif ( strtotime( $next_time ) > current_time( 'timestamp' ) ) : ?>
	<b><?php _e( 'Generation History', 'sprout-invoices' ) ?></b>
	<p><?php printf( 'The first invoice will be generated on <em>%s</em>.', date_i18n( get_option( 'date_format' ), strtotime( $next_time ) ) ) ?></p>
<?php endif ?>
