<?php foreach ( $invoices as $invoice_id ) : ?>
<?php printf( "\n\n\t*%s*", get_the_title( $invoice_id ) ) ?>
<?php printf( "\n\t\t%s", esc_url( get_permalink( $invoice_id ) ) ) ?>
<?php endforeach ?>
