<?php foreach ( $estimates as $estimate_id ) : ?>
<?php printf( "\n\n\t*%s*", get_the_title( $estimate_id ) ) ?>
<?php printf( __( "\n\t\t<b>Expiration:</b> %s", 'sprout-invoices' ), date_i18n( apply_filters( 'si_client_summary_date_format', 'M. jS' ), si_get_estimate_expiration_date( $estimate_id ) ) ) ?>
		<?php echo esc_url( get_permalink( $estimate_id ) ); ?>
<?php endforeach ?>
