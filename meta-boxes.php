<?php
/**
 * File containing meta box callback functions
 */

/**
 * Output the NPR Story API publishing options metabox for the edit page admin interface
 *
 * @param WP_Post $post the WordPress post object.
 * @see npr_cds_save_send_to_api
 * @see npr_cds_save_send_to_one
 * @see npr_cds_save_nprone_featured
 * @see npr_cds_publish_meta_box_assets
 * @since 1.7
 *
 * @todo When there is better browser support for input type="datetime-local", replace the jQuery UI and weird forms with the html5 solution. https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
 */
function npr_cds_publish_meta_box( $post ) {
	$is_disabled = ( 'publish' !== $post->post_status );
	$attrs = [ 'id' => 'npr-cds-update-push' ];

	if ( $is_disabled ) {
		$attrs['disabled'] = 'disabled';
	}

	wp_enqueue_style( 'jquery-ui' );
	wp_enqueue_style( 'npr_cds_publish_meta_box_stylesheet' );
	wp_enqueue_script( 'npr_cds_publish_meta_box_script' );

	?>
	<div id="npr-cds-publish-actions">
		<ul>
		<?php
			// send to the npr api
			// The meta name here is '_send_to_nprone' for backwards compatibility with plugin versions 1.6 and prior
			$nprapi = get_post_meta( $post->ID, '_send_to_nprone', true ); // 0 or 1
			if ( '0' !== $nprapi && '1' !== $nprapi ) { $nprapi = '1'; } // defaults to checked; unset on new posts

			// this list item contains all other list items, because their enabled/disabled depends on this checkbox
			echo '<li>';
			printf(
				'<label><input value="1" type="checkbox" name="send_to_api" id="send_to_api" %2$s/> %1$s</label>',
				__( 'Send to NPR CDS', 'nprcds' ),
				checked( $nprapi, '1', false )
				// @see npr_cds_save_send_to_api for a historical note on this metadata name
			);

			echo '<ul>';

			// send to nprone
			printf(
				'<li><label><input value="1" type="checkbox" name="send_to_one" id="send_to_one" %2$s/> %1$s</label> %3$s </li>',
				__( 'Include for listening in NPR One', 'nprcds' ),
				checked( get_post_meta( $post->ID, '_send_to_one', true ), '1', false ),
				// the following is an ul li within the "Send to npr one" li
				// set the story as featured in NPR One
				sprintf(
					'<ul><li><label><input value="1" type="checkbox" name="nprone_featured" id="nprone_featured" %2$s/> %1$s</label></li></ul>',
					__( 'Set as featured story in NPR One', 'nprcds' ),
					checked( get_post_meta( $post->ID, '_nprone_featured', true ), '1', false )
				)
			);
			echo '</li>'; // end the "Send to NPR API" list item
		?>
		</ul>
	</div>
	<?php
	/*
	 * this section is only enabled if "include for listening in NPR One" is checked!
	 * This section does not use https://developer.wordpress.org/reference/functions/touch_time/ because there does not seem to be a way to pass it a custom element
	 */

	$datetime = npr_cds_get_post_expiry_datetime( $post );
	?>
	<div id="nprone-expiry">
		<div id="nprone-expiry-display">
			<span>Expires on:</span>
			<?php
				printf(
					'<time style="font-weight: bold;">%1$s</time>',
					date_format( $datetime, 'M j, Y @ H:i' ) // Nov 30, 2017 @ 20:45
				);
			?>
			<button id="nprone-expiry-edit" class="link-effect"><?php esc_html_e( 'Edit', 'nprcds' ); ?></button>
		</div>
		<div id="nprone-expiry-form" class="hidden">
			<?php
				printf(
					'<input type="date" id="nprone-expiry-datepicker" size="10" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" value="%1$s"/>',
					date_format( $datetime, 'Y-m-d' ) // 2017-01-01
				);
				printf(
					'<input type="time" id="nprone-expiry-hour" size="5" placeholder="HH:MM" pattern="[0-9]{2}:[0-9]{2}" value="%1$s"/>',
					date_format( $datetime, 'H:i' ) // 2017-01-01
				);
			?>

			<div class="row">
				<button id="nprone-expiry-ok" class="button"><?php esc_html_e( 'OK', 'nprcds' ); ?></button>
				<button id="nprone-expiry-cancel" class="link-effect"><?php esc_html_e( 'Cancel', 'nprcds' ); ?></button>
			</div>
		</div>
	</div>
<?php
}

/**
 * Register stylesheet for the NPR Story API publishing options metabox
 *
 * @since 1.7
 * @see npr_cds_publish_meta_box
 */
function npr_cds_publish_meta_box_assets() {
	wp_register_style(
		'npr_cds_publish_meta_box_stylesheet',
		NPR_CDS_PLUGIN_URL . 'assets/css/meta-box.css'
	);
	wp_register_style(
		'jquery-ui',
		NPR_CDS_PLUGIN_URL . 'assets/css/jquery-ui.css'
	);
	wp_register_script(
		'npr_cds_publish_meta_box_script',
		NPR_CDS_PLUGIN_URL . 'assets/js/meta-box.js',
		[ 'jquery', 'jquery-ui-datepicker' ],
		null,
		true
	);
}

/**
 * Alternate meta box output if the CDS Push URL option is not set
 *
 * Propmts the user to set that option.
 * @link https://github.com/npr/nprapi-wordpress/issues/51
 *
 * @param WP_Post $post the WordPress post object.
 * @since 1.8
 * @see npr_cds_add_options_page
 */
function npr_cds_publish_meta_box_prompt( $post ) {
	if ( current_user_can( 'manage_options' ) ) { // this should match the value in npr_cds_add_options_page
		printf(
			'<p>%1$s</p>',
			wp_kses_post( __( 'The NPR CDS plugin\'s settings must be configured to push stories to the NPR CDS. Instructions are <a href="https://github.com/openpublicmedia/npr-cds-wordpress/blob/master/docs/settings.md">here</a>.', 'nprcds' ) )
		);

		$url = menu_page_url( 'npr_cds', false ); // this should match the value in npr_cds_add_options_page
		printf(
			'<a href="%2$s" class="button button-primary button-large">%1$s</a>',
			wp_kses_post( __( 'Configure the Plugin', 'nprcds' ) ),
			esc_attr( $url )
		);
	} else {
		printf(
			'<p>%1$s</p>',
			wp_kses_post( __( 'Your site administrator must set the NPR CDS Push URL in the NPR CDS plugin\'s settings in order to push to the NPR CDS.', 'nprcds' ) )
		);
	}
}