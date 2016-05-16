<span id="woo_product_items">

	<select id="woo_product_line_item" class="button">

		<option selected="selected"></option>

		<?php foreach ( $categories as $cat ) : ?>
			<?php
			if ( ! isset( $products[ $cat->slug ] ) || empty( $products[ $cat->slug ] ) ) {
				continue;
			} ?>
			
			<optgroup label="<?php echo esc_attr( $cat->name ) ?>">
				<?php foreach ( $products[ $cat->slug ] as $product_id => $product ) : ?>
					<option value="<?php echo (int) esc_attr( $product_id ) ?>"><?php echo esc_html( $product['title'] ) ?></option>
				<?php endforeach ?>
			</optgroup>
			
		<?php endforeach ?>

		<?php if ( isset( $products['none'] ) && ! empty( $products['none'] ) ) :  ?>
				<optgroup label="<?php _e( 'No Category', 'sprout-invoices' ) ?>">
					<?php foreach ( $products['none'] as $product_id => $product ) :  ?>
						<option value="<?php echo (int) esc_attr( $product_id ) ?>"><?php echo esc_html( $product['title'] ) ?></option>
					<?php endforeach ?>
				</optgroup>
		<?php endif ?>

	</select>

</span>
