<?php
/**
 * NPR API Settings Page and related control methods
 *
 * Also includes the cron jobs.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Push/Pull URLs:
 * Production: https://content.api.npr.org/
 * Staging: https://stage-content.api.npr.org/
 */

/**
 * add the options page
 *
 * @see npr_cds_publish_meta_box_prompt
 */
function npr_cds_add_options_page(): void {
	add_options_page( 'NPR CDS', 'NPR CDS', 'manage_options', 'npr_cds', 'npr_cds_options_general' );
	add_options_page( 'NPR CDS Get Multi Settings', 'NPR CDS Get Multi Settings', 'manage_options', 'npr_cds_options_multi', 'npr_cds_options_multi' );
	add_options_page( 'NPR CDS Push Mapping', 'NPR CDS Push Mapping', 'manage_options', 'npr_cds_options_push_mapping', 'npr_cds_options_push_mapping' );
}
add_action( 'admin_menu', 'npr_cds_add_options_page' );

function npr_cds_options_general(): void { ?>
	<style>
		h1 {
			line-height: 1.25;
		}
		.form-table td input[type="text"] {
			display: inline-block;
			max-width: 100%;
			width: 66%;
		}
		@media screen and (max-width: 500px) {
			.form-table td input[type="text"] {
				width: 100%;
			}
		}
	</style>
	<h1>NPR CDS: General Settings</h1>
	<form action="options.php" method="post">
	<?php settings_fields( 'npr_cds' ); ?>
	<?php echo npr_cds_restore_old(); ?>
	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('CDS Settings', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds', 'npr_cds_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('Organization Settings', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds', 'npr_cds_org_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('Theme Settings', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds', 'npr_cds_theme_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('Image Settings', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds', 'npr_cds_image_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<br class="clear" />
	<?php submit_button(); ?>
	</form><?php
}
function npr_cds_options_multi(): void { ?>
	<style>
		h1 {
			line-height: 1.25;
		}
		.form-table td input[type="text"] {
			display: inline-block;
			max-width: 100%;
			width: 66%;
		}
		.npr-cds-query h4 {
			margin: 0;
			text-align: right;
		}
		.npr-cds-query {
			display: grid;
			grid-template-columns: 10rem auto;
			gap: 1rem;
			align-items: center;
		}
		.cds-query-details .form-table tr + tr {
			border-top: 1px solid #808080;
		}
		@media screen and (max-width: 500px) {
			.npr-cds-query h4 {
				text-align: left;
			}
			.npr-cds-query {
				grid-template-columns: 1fr;
			}
			.form-table td input[type="text"] {
				width: 100%;
			}
		}
	</style>
	<h1>NPR CDS: Get Multi Settings</h1>
	<p><?php echo __( 'Create an NPR CDS query. Enter your queries into one of the rows below to have stories on that query automatically publish to your site. Please note, you do not need to include your CDS token in the query.', 'npr-content-distribution-service' ); ?></p>
	<?php echo npr_cds_restore_old(); ?>
	<form action="options.php" method="post">
	<?php settings_fields( 'npr_cds_get_multi_settings' ); ?>
	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('Query Settings', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds_get_multi_settings', 'npr_cds_get_multi_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
				<div>
					<div class="postbox cds-query-details">
						<div class="postbox-header"><h2><?php _e('Query Details', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds_get_multi_settings', 'npr_cds_query_details' ); ?>
							</table>
						</div>
					</div>
				</div>
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('Cron Settings', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds_get_multi_settings', 'npr_cds_cron_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<br class="clear" />
	<?php submit_button(); ?>
	</form><?php
}
function npr_cds_options_push_mapping(): void { ?>
	<style>
		h1 {
			line-height: 1.25;
		}
		.form-table td input[type="text"] {
			display: inline-block;
			max-width: 100%;
			width: 66%;
		}
		@media screen and (max-width: 500px) {
			.form-table td input[type="text"] {
				width: 100%;
			}
		}
	</style>
	<h1>NPR CDS: Push Mapping</h1>
	<?php echo npr_cds_restore_old(); ?>
	<form action="options.php" method="post">
	<?php settings_fields( 'npr_cds_push_mapping' ); ?>
	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php _e('CDS Push Mapping', 'npr-content-distribution-service' ); ?></h2></div>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'npr_cds_push_mapping', 'npr_cds_push_settings' ); ?>
							</table>
						</div>
					</div>
				</div>
				<div>
				</div>
			</div>
			<br class="clear" />
			<?php submit_button(); ?>
	</form><?php
}

function npr_cds_settings_init(): void {
	// CDS: General Settings
	add_settings_section( 'npr_cds_settings', 'General Settings', 'npr_cds_settings_callback', 'npr_cds' );

	add_settings_field( 'npr_cds_token', 'CDS Token', 'npr_cds_token_callback', 'npr_cds', 'npr_cds_settings' );
	register_setting( 'npr_cds', 'npr_cds_token' );

	add_settings_field( 'npr_cds_pull_url', 'Pull URL', 'npr_cds_pull_url_callback', 'npr_cds', 'npr_cds_settings' );
	register_setting( 'npr_cds', 'npr_cds_pull_url', [ 'sanitize_callback' => 'npr_cds_validation_callback_pull_url' ] );

	add_settings_field( 'npr_cds_push_url', 'Push URL', 'npr_cds_push_url_callback', 'npr_cds', 'npr_cds_settings' );
	register_setting( 'npr_cds', 'npr_cds_push_url', [ 'sanitize_callback' => 'npr_cds_validation_callback_pull_url' ] );

	// CDS: Org Settings
	add_settings_section( 'npr_cds_org_settings', 'Organization Settings', 'npr_cds_org_settings_callback', 'npr_cds' );

	add_settings_field( 'npr_cds_org_id', 'Service ID', 'npr_cds_org_id_callback', 'npr_cds', 'npr_cds_org_settings' );
	register_setting( 'npr_cds', 'npr_cds_org_id', [ 'sanitize_callback' => 'npr_cds_validation_callback_org_id' ] );

	add_settings_field( 'npr_cds_prefix', 'Document Prefix', 'npr_cds_prefix_callback', 'npr_cds', 'npr_cds_org_settings' );
	register_setting( 'npr_cds', 'npr_cds_prefix', [ 'sanitize_callback' => 'npr_cds_validation_callback_prefix' ] );

	// CDS: Theme Settings
	add_settings_section( 'npr_cds_theme_settings', 'Theme Settings', 'npr_cds_settings_callback', 'npr_cds' );

	add_settings_field( 'npr_cds_query_use_featured', 'Theme uses Featured Image', 'npr_cds_query_use_featured_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_query_use_featured', [ 'sanitize_callback' => 'npr_cds_validation_callback_checkbox' ] );

	add_settings_field( 'npr_cds_pull_post_type', 'NPR Default Pull Post Type', 'npr_cds_pull_post_type_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_pull_post_type' );

	add_settings_field( 'npr_cds_push_post_type', 'NPR Push Post Type', 'npr_cds_push_post_type_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_push_post_type' );

	add_settings_field( 'npr_cds_push_default', 'NPR Push to CDS Default', 'npr_cds_push_default_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_push_default' );

	add_settings_field( 'npr_cds_import_tags', 'Import Tags from CDS?', 'npr_cds_import_tags_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_import_tags' );

	add_settings_field( 'npr_cds_display_attribution', 'Append Article Attribution?', 'npr_cds_display_attribution_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_display_attribution' );

	add_settings_field( 'npr_cds_skip_promo_cards', 'Skip NPR promos', 'npr_cds_skip_promo_cards_callback', 'npr_cds', 'npr_cds_theme_settings' );
	register_setting( 'npr_cds', 'npr_cds_skip_promo_cards', [ 'sanitize_callback' => 'npr_cds_validation_callback_checkbox' ] );


	// CDS: Image Settings
	add_settings_section( 'npr_cds_image_settings', 'Image Settings', 'npr_cds_settings_callback', 'npr_cds' );

	add_settings_field( 'npr_cds_image_width', 'Max Image Width', 'npr_cds_image_width_callback', 'npr_cds', 'npr_cds_image_settings' );
	register_setting( 'npr_cds', 'npr_cds_image_width' );

	add_settings_field( 'npr_cds_image_quality', 'Image Quality', 'npr_cds_image_quality_callback', 'npr_cds', 'npr_cds_image_settings' );
	register_setting( 'npr_cds', 'npr_cds_image_quality' );

	add_settings_field( 'npr_cds_image_format', 'Image Format', 'npr_cds_image_format_callback', 'npr_cds', 'npr_cds_image_settings' );
	register_setting( 'npr_cds', 'npr_cds_image_format' );

	// CDS: Get Multi Settings
	add_settings_section( 'npr_cds_get_multi_settings', 'Multiple Get Settings', 'npr_cds_get_multi_settings_callback', 'npr_cds_get_multi_settings' );

	add_settings_field( 'npr_cds_num', 'Number of things to get', 'npr_cds_num_multi_callback', 'npr_cds_get_multi_settings', 'npr_cds_get_multi_settings' );
	register_setting( 'npr_cds_get_multi_settings', 'npr_cds_num', [ 'type' => 'integer', 'sanitize_callback' => 'npr_cds_num_validation' ] );

	// CDS: Query Details
	add_settings_section( 'npr_cds_query_details', 'Query Details', 'npr_cds_query_details_callback', 'npr_cds_get_multi_settings' );
	$num = get_option( 'npr_cds_num', 1 );
	for ( $i = 0; $i < $num; $i++ ) {
		add_settings_field( 'npr_cds_query_' . $i, 'Query ' . $i, 'npr_cds_query_callback', 'npr_cds_get_multi_settings', 'npr_cds_query_details', $i );
		register_setting( 'npr_cds_get_multi_settings', 'npr_cds_query_' . $i, [ 'type' => 'array', 'default' => [ 'filters' => '', 'sorting' => '', 'publish' => '', 'category' => '', 'tags' => '', 'import_tags' => '1' ] ] );
	}

	// CDS: Cron Settings
	add_settings_section( 'npr_cds_cron_settings', 'Cron Settings', 'npr_cds_settings_callback', 'npr_cds_get_multi_settings' );

	add_settings_field( 'npr_cds_query_run_multi', 'Run the queries on saving changes', 'npr_cds_query_run_multi_callback', 'npr_cds_get_multi_settings', 'npr_cds_cron_settings' );
	register_setting( 'npr_cds_get_multi_settings', 'npr_cds_query_run_multi', [ 'sanitize_callback' => 'npr_cds_validation_callback_checkbox' ] );

	add_settings_field( 'npr_cds_query_multi_cron_interval', 'Interval to run Get Multi cron', 'npr_cds_query_multi_cron_interval_callback', 'npr_cds_get_multi_settings', 'npr_cds_cron_settings' );
	register_setting( 'npr_cds_get_multi_settings', 'npr_cds_query_multi_cron_interval', [ 'type' => 'integer' ] );

	// CDS: Push Settings
	add_settings_section( 'npr_cds_push_settings', 'Metadata Settings', 'npr_cds_push_settings_callback', 'npr_cds_push_mapping' );

	add_settings_field( 'npr_cds_push_use_custom_map', 'Use Custom Settings', 'npr_cds_use_custom_mapping_callback', 'npr_cds_push_mapping', 'npr_cds_push_settings' );
	register_setting( 'npr_cds_push_mapping', 'npr_cds_push_use_custom_map', [ 'sanitize_callback' => 'npr_cds_validation_callback_checkbox' ] );

	add_settings_field( 'npr_cds_mapping_title', 'Story Title', 'npr_cds_mapping_title_callback', 'npr_cds_push_mapping', 'npr_cds_push_settings' );
	register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_title' );

	add_settings_field( 'npr_cds_mapping_body', 'Story Body', 'npr_cds_mapping_body_callback', 'npr_cds_push_mapping', 'npr_cds_push_settings' );
	register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_body' );

	add_settings_field( 'npr_cds_mapping_byline', 'Story Byline', 'npr_cds_mapping_byline_callback', 'npr_cds_push_mapping', 'npr_cds_push_settings' );
	register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_byline' );

	add_settings_field( 'npr_cds_mapping_media_credit', 'Media Credit Field', 'npr_cds_mapping_media_credit_callback', 'npr_cds_push_mapping', 'npr_cds_push_settings' );
	register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_media_credit' );

	add_settings_field( 'npr_cds_mapping_media_agency', 'Media Agency Field', 'npr_cds_mapping_media_agency_callback', 'npr_cds_push_mapping', 'npr_cds_push_settings' );
	register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_media_agency' );
}
add_action( 'admin_init', 'npr_cds_settings_init' );

/**
 * Settings group callback functions
 */
function npr_cds_settings_callback() { }

function npr_cds_push_settings_callback(): void { ?>
	<p>Use this page to map your custom WordPress Meta fields to fields sent to the NPR CDS, and vice versa. Clicking the <strong>Use Custom Settings</strong> checkbox will enable these mappings. If you wish to use the default mapping for a field, select &mdash; default &mdash; and we will use the obvious WordPress field.</p>
	<p>Select for the Meta fields for the <strong><?php echo esc_html( npr_cds_get_push_post_type() ); ?></strong> post
		type.</p> <?php
}

/**
 * NPR General Settings Group Callbacks
 */
function npr_cds_token_callback(): void {
	$attr = '';
	if ( defined( 'NPR_CDS_TOKEN' ) ) {
		$option = 'This field managed in wp-config.php';
		$attr = 'disabled ';
	} else {
		$option = get_option( 'npr_cds_token' );
	}
	echo npr_cds_esc_html( '<p><input type="text" value="' . $option . '" name="npr_cds_token" ' . $attr . '/></p><p><em>This is a bearer token provided by NPR. If you do not already have one, you can request one through <a href="https://studio.npr.org/">NPR Studio</a>.<br /><br />You can also manage your token by adding the following to wp-config.php: <code>define( \'NPR_CDS_TOKEN\', \'{ TOKEN STRING }\' );</code></em></p>' );
}

function npr_cds_pull_url_callback(): void {
	$option = get_option( 'npr_cds_pull_url' );
	$output = '<p><label><input type="radio" name="npr_cds_pull_url" value="https://stage-content.api.npr.org"' . ( $option == 'https://stage-content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Staging</label></p>';
	$output .= '<p><label><input type="radio" name="npr_cds_pull_url" value="https://content.api.npr.org"' . ( $option == 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Production</label></p>';
	$output .= '<p><label><input type="radio" name="npr_cds_pull_url" value="other"' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Other</label> <input type="text" name="npr_cds_pull_url_other" id="npr_cds_pull_url_other" value="' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? $option : '' ) . '" placeholder="Type other URL here" /></p>';
	echo npr_cds_esc_html( $output );
}

function npr_cds_push_url_callback(): void {
	$option = get_option( 'npr_cds_push_url' );
	$output = '<p><label><input type="radio" name="npr_cds_push_url" value="https://stage-content.api.npr.org"' . ( $option == 'https://stage-content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Staging</label></p>';
	$output .= '<p><label><input type="radio" name="npr_cds_push_url" value="https://content.api.npr.org"' . ( $option == 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Production</label></p>';
	$output .= '<p><label><input type="radio" name="npr_cds_push_url" value="other"' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Other</label> <input type="text" name="npr_cds_push_url_other" id="npr_cds_push_url_other" value="' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? $option : '' ) . '" placeholder="Type other URL here" /></p>';
	echo npr_cds_esc_html( $output );
}

function npr_cds_org_id_callback(): void {
	$option = get_option( 'npr_cds_org_id' );
	echo npr_cds_esc_html( '<p><input type="text" value="' . $option . '" name="npr_cds_org_id" /></p><p><em>Enter the service ID provided by NPR. If your stories will be co-owned with another organization, you can include all of the service IDs as a comma-separated list.</em></p>' );
}

function npr_cds_prefix_callback(): void {
	$option = get_option( 'npr_cds_prefix' );
	echo npr_cds_esc_html( '<p><input type="text" value="' . $option . '" name="npr_cds_prefix" placeholder="callletters" /></p><p><em>When given write permission to the CDS, NPR will assign a code that will be prefixed on all of your document IDs (e.g. "kuhf-12345").</em></p>' );
}

function npr_cds_query_use_featured_callback(): void {
	$use_featured = get_option( 'npr_cds_query_use_featured' );
	$check_box_string = '<input id="npr_cds_query_use_featured" name="npr_cds_query_use_featured" type="checkbox" value="true"' .
	                    ( $use_featured ? ' checked="checked"' : '' ) . ' />';

	echo npr_cds_esc_html( '<p>' . $check_box_string . " If your theme uses the featured image, checking this box will remove the lead image from imported posts.</p>" );
}

function npr_cds_pull_post_type_callback(): void {
	$post_types = get_post_types();
	npr_cds_show_post_types_select( 'npr_cds_pull_post_type', $post_types );
}

function npr_cds_push_post_type_callback(): void {
	$post_types = get_post_types();
	npr_cds_show_post_types_select( 'npr_cds_push_post_type', $post_types );
	echo npr_cds_esc_html( '<p><em>If you change the Push Post Type setting remember to update the mappings for CDS Fields at <a href="' . admin_url( 'options-general.php?page=npr_cds#npr-fields' ) . '">NPR CDS Field Mapping</a> tab.</em></p>' );
}

function npr_cds_push_default_callback(): void {
	$push_default = get_option( 'npr_cds_push_default', '1' );
	$check_box_string = '<select id="npr_cds_push_default" name="npr_cds_push_default"><option value="1"' . ( $push_default === '1' ? ' selected' : '' ) . '>Checked</option>' .
	                    '<option value="0"' . ( $push_default === '0' ? ' selected' : '' ) . '>Not Checked</option>' .
	                    '</select>';
	echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>When creating a new post in your NPR Push Post Type, do you want the "Push to NPR CDS" box to be checked by default or not?</em></p>' );
}

function npr_cds_import_tags_callback(): void {
	$import_tags_default = get_option( 'npr_cds_import_tags', '1' );
	$check_box_string = '<select id="npr_cds_import_tags" name="npr_cds_import_tags"><option value="1"' . ( $import_tags_default === '1' ? ' selected' : '' ) . '>Import</option>' .
	                    '<option value="0"' . ( $import_tags_default === '0' ? ' selected' : '' ) . '>Do Not Import</option>' .
	                    '</select>';
	echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>When importing an article from the NPR CDS, do you want to import all of the article\'s tags into WordPress?</em></p>' );
}

function npr_cds_display_attribution_callback(): void {
	$attribution_default = get_option( 'npr_cds_display_attribution', '0' );
	$check_box_string = '<select id="npr_cds_display_attribution" name="npr_cds_display_attribution"><option value="0"' . ( $attribution_default === '0' ? ' selected' : '' ) . '>Do Not Append</option>' .
	                    '<option value="1"' . ( $attribution_default === '1' ? ' selected' : '' ) . '>Append</option>' .
	                    '</select>';
	echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>Do you want to append an attribution message to the bottom of imported articles? (e.g. "Copyright &copy; 2024 NPR")</em></p>' );
}

function npr_cds_skip_promo_cards_callback(): void {
	$skip_promos = get_option( 'npr_cds_skip_promo_cards' );
	$check_box_string = '<input id="npr_cds_skip_promo_cards" name="npr_cds_skip_promo_cards" type="checkbox" value="true"' . ( $skip_promos ? ' checked="checked"' : '' ) . ' />';

	echo npr_cds_esc_html( '<p>' . $check_box_string . " Filter out any NPR promo cards embedded in posts.</p>" );
}

function npr_cds_image_format_callback(): void {
	npr_cds_show_post_types_select( 'npr_cds_image_format', [ 'jpeg', 'png', 'webp' ] );
}

function npr_cds_image_quality_callback(): void {
	$option = get_option( 'npr_cds_image_quality', 75 );
	echo npr_cds_esc_html( '<p><input type="number" value="' . $option . '" name="npr_cds_image_quality" min="1" max="100" /></p><p><em>Set the quality level of the images from the NPR CDS (default: 75).</em></p>' );
}

function npr_cds_image_width_callback(): void {
	$option = get_option( 'npr_cds_image_width', 1200 );
	echo npr_cds_esc_html( '<p><input type="number" value="' . $option . '" name="npr_cds_image_width" min="500" max="3000" /></p><p><em>Maximum width of images pulled in from the NPR CDS (default: 1200).</em></p>' );
}

/**
 * NPR Get Multi Settings Group Callbacks
 */
function npr_cds_num_multi_callback(): void {
	$run_multi = get_option( 'npr_cds_query_run_multi' );

	$num = get_option( 'npr_cds_num', 5 );
	$enable = false;
	for ( $i = 0; $i < $num; $i++ ) {
		$option = get_option( 'npr_cds_query_' . $i );
		if ( !empty( $option['filters'] ) || !empty( $options['sorting'] ) ) {
			$enable = true;
		}
	}
	if ( $run_multi && $enable ) {
		update_option( 'npr_cds_query_run_multi', false );
		NPR_CDS::cron_pull();
	}

	if ( $enable && !wp_next_scheduled( 'npr_cds_hourly_cron') ) {
		wp_schedule_event( time(), 'npr_cds_interval', 'npr_cds_hourly_cron' );
	}
	echo npr_cds_esc_html( '<p><input type="number" value="' . $num . '" min="0" max="' . NPR_MAX_QUERIES . '" name="npr_cds_num" /></p><p><em>Increase the number of queries by changing the number in the field above, to a maximum of 10.</em></p>' );
}

function npr_cds_query_callback( $i ): void {
	if ( is_integer( $i ) ) {
		$query = get_option( 'npr_cds_query_' . $i );
		$optionType = get_option( 'npr_cds_pull_post_type', 'post' );
		if ( !empty( $query['pull_type'] ) ) {
			$optionType = $query['pull_type'];
		}
		if ( empty( $query['import_tags'] ) ) {
			$query['import_tags'] = '1';
		}
		$post_types = get_post_types();

		$output = '<div class="npr-cds-query"><h4>Filters</h4><div><p><input type="text" value="' . $query['filters'] . '" name="npr_cds_query_' . $i . '[filters]" placeholder="profileIds=renderable&collectionIds=1002" /></p>' .
		          '<p><em>A list of available filtering options can be found <a href="https://npr.github.io/content-distribution-service/querying/filtering.html">in the CDS documentation</a></em></p></div>' .
		          '<h4>Sorting</h4><div><p><input type="text" value="' . $query['sorting'] . '" name="npr_cds_query_' . $i . '[sorting]" placeholder="sort=<type>[:<direction>]" /></p>' .
		          '<p><em>A list of available sorting query parameters can be found <a href="https://npr.github.io/content-distribution-service/querying/sorting.html">in the CDS documentation</a></em></p></div>' .
		          '<h4>Publish or Save as Draft?</h4> ' .
		          '<div><select id="npr_cds_query_' . $i . '[publish]" name="npr_cds_query_' . $i . '[publish]">' .
		          '<option value="Publish"' . ( $query['publish'] == 'Publish' ? ' selected' : '' ) . '>Publish</option>' .
		          '<option value="Draft"' . ( $query['publish'] == 'Draft' ? ' selected' : '' ) . '>Draft</option>' .
		          '</select></div>' .
		          '<h4>Save as post type?</h4>' .
		          npr_cds_show_post_types_select( 'npr_cds_query_' . $i . '[pull_type]', $post_types, true );
		if ( $optionType == 'post' ) {
			$args = [
				'show_option_none'	=> __( 'Select category', 'npr-content-distribution-service' ),
				'name'				=> 'npr_query_' . $i . '[category]',
				'hierarchical'		=> true,
				'show_count'		=> 0,
				'orderby'			=> 'name',
				'echo'				=> 0,
				'selected'			=> ( !empty( $query['category'] ) ? (int)$query['category'] : 0 ),
				'hide_empty'		=> 0,
				'multiple'			=> true
			];
			$select = wp_dropdown_categories( $args );
			$output .= '<h4>Add Category</h4><div>' . $select . '</div>';
		}
		$output .= '<h4>Add Tags</h4><div><p><input type="text" value="' . $query['tags'] . '" name="npr_cds_query_' . $i . '[tags]" placeholder="pepperoni,pineapple,mozzarella" /></p>' .
		           '<p><em>Add tag(s) to each story pulled from NPR (comma separated).</em></p></div>';
		$output .= '<h4>Import CDS Tags?</h4><div><p><select id="npr_cds_query_' . $i . '[import_tags]" name="npr_cds_query_' . $i . '[import_tags]">'.
		           '<option value="1"' . ( $query['import_tags'] === '1' ? ' selected' : '' ) . '>Import</option>' .
		           '<option value="0"' . ( $query['import_tags'] === '0' ? ' selected' : '' ) . '>Do Not Import</option>' .
		           '</select></p></div>';
		echo npr_cds_esc_html( $output );
	}
}

function npr_cds_query_run_multi_callback(): void {
	$run_multi = get_option( 'npr_cds_query_run_multi' );
	$num = get_option( 'npr_cds_num', 5 );
	$enable = false;
	for ( $i = 0; $i < $num; $i++ ) {
		$option = get_option( 'npr_cds_query_' . $i );
		if ( !empty( $option['filters'] ) || !empty( $options['sorting'] ) ) {
			$enable = true;
		}
	}
	if ( $enable ) {
		$check_box_string = '<p><input id="npr_cds_query_run_multi" name="npr_cds_query_run_multi" type="checkbox" value="true"' . ( $run_multi ? ' checked="checked"' : '' ) . ' /></p>';
	} else {
		$check_box_string = '<p><input id="npr_cds_query_run_multi" name="npr_cds_query_run_multi" type="checkbox" value="true" disabled /> <em>Add filters or sorting to the queries above to enable this option</em></p>';
	}
	echo npr_cds_esc_html( $check_box_string );
}

function npr_cds_query_multi_cron_interval_callback(): void {
	$option = get_option( 'npr_cds_query_multi_cron_interval' );
	if ( !wp_next_scheduled( 'npr_cds_hourly_cron' ) ) {
		npr_cds_error_log( 'turning on cron event for NPR CDS plugin' );
		wp_schedule_event( time(), 'npr_cds_interval', 'npr_cds_hourly_cron' );
	}
	echo npr_cds_esc_html( '<p><input type="number" value="' . $option . '" name="npr_cds_query_multi_cron_interval" id="npr_cds_query_multi_cron_interval" /></p><p><em>How often, in minutes, should the Get Multi function run?  (default = 60)</em></p>' );
}

/**
 * NPR Push Settings Group Callbacks
 */
function npr_cds_use_custom_mapping_callback(): void {
	$use_custom = get_option( 'npr_cds_push_use_custom_map' );
	$check_box_string = '<input id="npr_cds_push_use_custom_map" name="npr_cds_push_use_custom_map" type="checkbox" value="true"' .
	                    ( $use_custom ? ' checked="checked"' : '' ) . ' />';
	echo npr_cds_esc_html( $check_box_string );
}

function npr_cds_mapping_title_callback(): void {
	$push_post_type = npr_cds_get_push_post_type();
	$keys = npr_cds_get_post_meta_keys( $push_post_type );
	npr_cds_show_keys_select( 'npr_cds_mapping_title', $keys );
}

function npr_cds_mapping_body_callback(): void {
	$push_post_type = npr_cds_get_push_post_type();
	$keys = npr_cds_get_post_meta_keys( $push_post_type );
	npr_cds_show_keys_select( 'npr_cds_mapping_body', $keys );
}

function npr_cds_mapping_byline_callback(): void {
	$push_post_type = npr_cds_get_push_post_type();
	$keys = npr_cds_get_post_meta_keys( $push_post_type );
	npr_cds_show_keys_select( 'npr_cds_mapping_byline', $keys );
}

function npr_cds_mapping_media_credit_callback(): void {
	$keys = npr_cds_get_post_meta_keys( 'attachment' );
	npr_cds_show_keys_select( 'npr_cds_mapping_media_credit', $keys );
}

function npr_cds_mapping_media_agency_callback(): void {
	$keys = npr_cds_get_post_meta_keys( 'attachment' );
	npr_cds_show_keys_select( 'npr_cds_mapping_media_agency', $keys );
}

/**
 * create the select widget where the ID is the value in the array
 *
 * @param string $field_name
 * @param array $keys - an array like (1=>'Value1', 2=>'Value2', 3=>'Value3');
 */
function npr_cds_show_post_types_select( string $field_name, array $keys, bool $return = false ): string {
	$selected = $output = '';
	$first_label = 'Select';
	if ( str_contains( $field_name, 'npr_cds_query_' ) ) {
		$first_label = 'Default';
		preg_match( '/(npr_cds_query_[0-9]+)\[(.+)\]/', $field_name, $match );
		if ( !empty( $match ) ) {
			$option = get_option( $match[1] );
			if ( !empty( $option[ $match[2] ] ) ) {
				$selected = $option[ $match[2] ];
			}
		}
	} else {
		$selected = get_option( $field_name );
	}

	$output .= npr_cds_esc_html( '<div><select id="' . $field_name . '" name="' . $field_name . '">' );

	$output .= '<option value=""> &mdash; ' . $first_label . ' &mdash; </option>';
	foreach ( $keys as $key ) {
		$option_string = "\n<option  ";
		if ( $key == $selected ) {
			$option_string .= " selected ";
		}
		$option_string .=   "value='" . esc_attr( $key ) . "'>" . esc_html( $key ) . " </option>";
		$output .= npr_cds_esc_html( $option_string );
	}
	$output .= "</select> </div>";
	if ( !$return ) {
		echo $output;
	}
	return $output;
}

/**
 * checkbox validation callback
 */
function npr_cds_validation_callback_checkbox( $value ): bool {
	return (bool) $value;
}

/**
 * Prefix validation callback. We only want to save the prefix without the hyphen
 */
function npr_cds_validation_callback_prefix( $value ): string {
	if ( empty( $_GET['page'] ) || !str_contains( $_GET['page'], 'npr_cds' ) || empty( $_GET['cds_action'] ) || $_GET['cds_action'] !== 'restore' ) {
		if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), sanitize_text_field( $_POST[ 'option_page' ] ) . '-options' ) ) {
			return '';
		}
	}
	$value = strtolower( $value );
	preg_match( '/([a-z0-9]+)/', $value, $match );
	if ( !empty( $match ) ) {
		return $match[1];
	}
	add_settings_error(
		'npr_cds_prefix',
		'prefix-is-invalid',
		esc_html( $value ) . __( ' is not a valid value for the NPR CDS Prefix. It can only contain lowercase alphanumeric characters.', 'npr-content-distribution-service' )
	);
	return '';
}

/**
 * URL validation callbacks for the CDS URLs
 */
function npr_cds_validation_callback_pull_url( string $value ): string {
	if ( empty( $_GET['page'] ) || !str_contains( $_GET['page'], 'npr_cds' ) || empty( $_GET['cds_action'] ) || $_GET['cds_action'] !== 'restore' ) {
		if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), sanitize_text_field( $_POST[ 'option_page' ] ) . '-options' ) ) {
			return '';
		}
	}
	if ( $value == 'https://stage-content.api.npr.org' || $value == 'https://content.api.npr.org' ) {
		return esc_attr( $value );
	} elseif ( $value == 'other' ) {
		$value = rtrim( sanitize_url( $_POST['npr_cds_pull_url_other'] ), "/" );
		if ( !preg_match( '/https:\/\/[a-z0-9\.\-]+/', $value ) ) {
			add_settings_error(
				'npr_cds_pull_url',
				'not-https-url',
				esc_url( $value ) . __( ' is not a valid value for the NPR CDS Pull URL. It must be a URL starting with <code>https</code>.', 'npr-content-distribution-service' )
			);
			$value = '';
		}
	}
	return esc_attr( $value );
}
function npr_cds_validation_callback_push_url( string $value ): string {
	if ( empty( $_GET['page'] ) || !str_contains( $_GET['page'], 'npr_cds' ) || empty( $_GET['cds_action'] ) || $_GET['cds_action'] !== 'restore' ) {
		if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), sanitize_text_field( $_POST[ 'option_page' ] ) . '-options' ) ) {
			return '';
		}
	}
	if ( $value == 'https://stage-content.api.npr.org' || $value == 'https://content.api.npr.org' ) {
		return esc_attr( $value );
	} elseif ( $value == 'other' ) {
		$value = sanitize_url( $_POST['npr_cds_push_url_other'] );
		if ( !preg_match( '/https:\/\/[a-z0-9\.\-]+/', $value ) ) {
			add_settings_error(
				'npr_cds_push_url',
				'not-https-url',
				esc_url( $value ) . __( ' is not a valid value for the NPR CDS Push URL. It must be a URL starting with <code>https</code>.', 'npr-content-distribution-service' )
			);
			$value = '';
		}
	}
	return esc_attr( $value );
}

function npr_cds_num_validation( int $value ): int {
	if ( empty( $_GET['page'] ) || !str_contains( $_GET['page'], 'npr_cds' ) || empty( $_GET['cds_action'] ) || $_GET['cds_action'] !== 'restore' ) {
		if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), sanitize_text_field( $_POST[ 'option_page' ] ) . '-options' ) ) {
			return 0;
		}
	}
	if ( $value < 0 ) {
		return 0;
	}
	if ( $value > NPR_MAX_QUERIES ) {
		return NPR_MAX_QUERIES;
	}
	return $value;
}
function npr_cds_validation_callback_org_id( $value ): string {
	if ( empty( $_GET['page'] ) || !str_contains( $_GET['page'], 'npr_cds' ) || empty( $_GET['cds_action'] ) || $_GET['cds_action'] !== 'restore' ) {
		if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), sanitize_text_field( $_POST[ 'option_page' ] ) . '-options' ) ) {
			return '';
		}
	}
	$value = str_replace( 's', '', $value );
	if ( preg_match( '/[0-9,]+/', $value ) ) {
		$value_x = explode( ',', $value );
		foreach( $value_x as $k => $v ) {
			$value_x[ $k ] = 's' . $v;
		}
		$value = implode( ',', $value_x );
	}
	return esc_attr( $value );
}

/**
 * Create the select widget of all meta fields
 *
 * @param string $field_name
 * @param array $keys
 */
function npr_cds_show_keys_select( string $field_name, array $keys, bool $return = false ): string {
	$output = '';
	$selected = get_option( $field_name );

	$output .= '<div><select id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '">';

	$output .= '<option value="#NONE#"> &mdash; default &mdash; </option>';
	foreach ( $keys as $key ) {
		$option_string = "\n<option  ";
		if ($key == $selected) {
			$option_string .= " selected ";
		}
		$option_string .=   "value='" . esc_attr( $key ) . "'>" . esc_html( $key ) . " </option>";
		$output .= npr_cds_esc_html( $option_string );
	}
	$output .= "</select> </div>";
	if ( !$return ) {
		echo $output;
	}
	return $output;
}

function npr_cds_get_push_post_type(): string {
	return get_option( 'npr_cds_push_post_type', 'post' );
}

function npr_cds_restore_old(): string {
	$output = '';
	$old_options = get_option( 'npr_cds_old_options' );
	$page = 'npr_cds';
	if ( !empty( $old_options ) ) {
		if ( ! empty( $_GET['page'] ) ) {
			$page = $_GET['page'];
		}
		$output = '<div class="notice notice-warning"><p style="display: inline-flex; align-items: center; gap: 1rem;">You have previous stored options for this plugin. What would you like to do? <a class="button-secondary" href="' . admin_url( 'options-general.php?page=' . $page . '&cds_action=restore' ) . '">Restore Previous Options</a> <a class="button-secondary" href="' . admin_url( 'options-general.php?page=' . $page . '&cds_action=delete' ) . '">Delete Previous Options</a></p></div>';
	} elseif ( !empty( $_GET['cds_result'] ) ) {
		if ( $_GET['cds_result'] === 'restored' ) {
			$output = '<div class="notice notice-warning"><p>The previous options have been restored.</p></div>';
		} elseif ( $_GET['cds_result'] === 'deleted' ) {
			$output = '<div class="notice notice-warning"><p>The previous options have been deleted.</p></div>';
		}
	}
	return $output;
}

function npr_cds_restore_page_hook(): void {
	$page = $post_link = '';
	if ( !empty( $_GET['page'] ) ) {
		$page = $_GET['page'];
	}
	if ( empty( $page ) || !str_contains( $page, 'npr_cds' ) ) {
		return;
	}
	$old_options = get_option( 'npr_cds_old_options' );
	if ( !empty( $old_options ) ) {
		if ( !empty( $_GET['cds_action'] ) ) {
			if ( $_GET['cds_action'] === 'restore' ) {
				foreach ( $old_options as $key => $value ) {
					update_option( $key, $value );
				}
				delete_option( 'npr_cds_old_options' );
				$post_link = admin_url( 'options-general.php?page=' . $page . '&cds_result=restored' );
			} elseif ( $_GET['cds_action'] === 'delete' ) {
				delete_option( 'npr_cds_old_options' );
				$post_link = admin_url( 'options-general.php?page=' . $page . '&cds_result=deleted' );
			}
			if ( !empty( $post_link ) ) {
				wp_redirect( $post_link );
			}
		}
	}
}
add_action( 'admin_init', 'npr_cds_restore_page_hook' );