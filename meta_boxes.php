<?php
/**
 * File containing meta box callback functions
 */
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Output the NPR CDS publishing options metabox for the edit page admin interface
 *
 * @param WP_Post $post the WordPress post object.
 *
 * @see npr_cds_save_send_to_api
 * @see npr_cds_save_send_to_one
 * @see npr_cds_save_nprone_featured
 * @see npr_cds_publish_meta_box_assets
 * @since 1.7
 *
 */
function npr_cds_publish_meta_box( WP_Post $post ): void {
	wp_enqueue_style( 'npr_cds_publish_meta_box_stylesheet' );
	wp_enqueue_script( 'npr_cds_publish_meta_box_script' );
	wp_nonce_field( 'npr_cds-' . $post->ID, 'npr_cds_send_nonce' );
	$npr_id = get_post_meta( $post->ID, 'npr_story_id', true );
	$push_default = get_option( 'npr_cds_push_default', '1' );
	$push_npr_homepage_default = get_option( 'npr_cds_push_one_homepage_default', '0' ); ?>
	<div id="npr-cds-publish-actions">
<?php
	if ( !empty( $npr_id ) ) {
		echo npr_cds_esc_html( '<h2>Current CDS ID: <strong>' . $npr_id . '</strong></h2>' );
	} ?>
		<ul>
		<?php
			// send to the npr api
			// The meta name here is '_send_to_nprone' for backwards compatibility with old plugin
			$nprapi = get_post_meta( $post->ID, '_send_to_nprone', true ); // 0 or 1
			if ( empty( $npr_id ) && $push_default === '0' ) {
				$nprapi = '0';
			} else {
				if ( '0' !== $nprapi && '1' !== $nprapi ) {
					$nprapi = $push_default;
				}
			} // defaults to checked; unset on new posts

			$push_homepage = get_post_meta( $post->ID, '_send_to_one', true ); // 0 or 1
			if ( empty( $npr_id ) && $push_npr_homepage_default === '0' ) {
				$push_homepage = '0';
			} else {
				if ( '0' !== $push_homepage && '1' !== $push_homepage ) {
					$push_homepage = $push_npr_homepage_default;
				}
			}
			// this list item contains all other list items, because their enabled/disabled depends on this checkbox
			printf(
				'<li><label><input value="1" type="checkbox" name="send_to_cds" id="send_to_cds" %2$s/> %1$s</label></li>',
				esc_html__( 'Send to NPR CDS', 'npr-content-distribution-service' ),
				checked( $nprapi, '1', false )
				// @see npr_cds_save_send_to_api for a historical note on this metadata name
			);
			// send to nprone
			printf(
				'<li><label><input value="1" type="checkbox" name="_send_to_one" id="_send_to_one" %2$s/> %1$s</label></li>',
				esc_html__( 'Include for NPR One and NPR homepage', 'npr-content-distribution-service' ),
				checked( $push_homepage, '1', false )
			);
			printf(
				'<li><label><input value="1" type="checkbox" name="_nprone_featured" id="_nprone_featured" %2$s/> %1$s</label></li>',
				esc_html__( 'Set as featured story in NPR One', 'npr-content-distribution-service' ),
				checked( get_post_meta( $post->ID, '_nprone_featured', true ), '1', false )
			); // end the "Send to NPR API" list item
		?>
		</ul>
	</div>
	<?php
	/*
	 * this section is only enabled if "include for listening in NPR One" is checked!
	 * This section does not use https://developer.wordpress.org/reference/functions/touch_time/ because there does not seem to be a way to pass it a custom element
	 */

	$datetime = npr_cds_get_post_expiry_datetime( $post ); ?>
	<div id="nprone-expiry">
		<div id="nprone-expiry-display">
			<span>Expires on:</span>
			<?php echo npr_cds_esc_html( '<time style="font-weight: bold;">' . date_format( $datetime, 'M j, Y @ H:i' ) . '</time>' ); ?>
			<button id="nprone-expiry-edit" class="link-effect"><?php esc_html_e( 'Edit', 'npr-content-distribution-service' ); ?></button>
		</div>
		<div id="nprone-expiry-form" class="hidden">
			<?php echo npr_cds_esc_html( '<input type="datetime-local" id="nprone-expiry-datetime" name="nprone-expiry-datetime" value="' . date_format( $datetime, 'Y-m-d\TH:i' ) . '" />' ); ?>
			<div class="row">
				<button id="nprone-expiry-ok" class="button"><?php esc_html_e( 'OK', 'npr-content-distribution-service' ); ?></button>
				<button id="nprone-expiry-cancel" class="link-effect"><?php esc_html_e( 'Cancel', 'npr-content-distribution-service' );
				?></button>
			</div>
		</div>
	</div>
<?php
}

/**
 * Register stylesheet for the NPR CDS publishing options metabox
 *
 * @since 1.7
 * @see npr_cds_publish_meta_box
 */
function npr_cds_publish_meta_box_assets(): void {
	wp_register_style(
		'npr_cds_publish_meta_box_stylesheet',
		NPR_CDS_PLUGIN_URL . 'assets/css/meta-box.css'
	);
	wp_register_script(
		'npr_cds_publish_meta_box_script',
		NPR_CDS_PLUGIN_URL . 'assets/js/meta-box.js',
		[ 'jquery' ],
		null,
		true
	);
}

/**
 * Alternate meta box output if the CDS Push URL option is not set
 *
 * Prompts the user to set that option.
 * @link https://github.com/npr/nprapi-wordpress/issues/51
 *
 * @param WP_Post $post the WordPress post object.
 *
 * @since 1.8
 * @see npr_cds_add_options_page
 */
function npr_cds_publish_meta_box_prompt( WP_Post $post ): void {
	if ( current_user_can( 'manage_options' ) ) { // this should match the value in npr_cds_add_options_page
		printf(
			'<p>%1$s</p>',
			wp_kses_post( __( 'The NPR CDS plugin\'s settings must be configured to push stories to the NPR CDS. Instructions are <a href="https://github.com/openpublicmedia/npr-cds-wordpress/blob/master/docs/settings.md">here</a>.', 'npr-content-distribution-service' ) )
		);

		$url = menu_page_url( 'npr_cds', false ); // this should match the value in npr_cds_add_options_page
		printf(
			'<a href="%2$s" class="button button-primary button-large">%1$s</a>',
			wp_kses_post( __( 'Configure the Plugin', 'npr-content-distribution-service' ) ),
			esc_attr( $url )
		);
	} else {
		printf(
			'<p>%1$s</p>',
			wp_kses_post( __( 'Your site administrator must set the NPR CDS Push URL in the NPR CDS plugin\'s settings in order to push to the NPR CDS.', 'npr-content-distribution-service' ) )
		);
	}
}