<?php
/**
 * The class NPR_CDS and related functions for getting stories from the API
 * Also includes the settings pages and cron jobs
 */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once( NPR_CDS_PLUGIN_DIR . 'classes/NPR_CDS_WP.php' );
/**
 * Push/Pull URLs:
 * Production: https://content.api.npr.org/
 * Staging: https://stage-content.api.npr.org/
 */
class NPR_CDS {
	/**
	 * Class constructor that hooks up the menu and settings pages.
	 */
	public function __construct() {
		if ( !is_admin() ) {
			return;
		}
		$post_type_option = get_option( 'npr_cds_pull_post_type', 'post' );
		// Allow customization of the post type used for the loading screen
		$post_type = ( $post_type_option === 'post' ? 'posts' : $post_type_option ) . '_page';

		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_filter( 'manage_edit-' . $this->get_pull_post_type() . '_columns', [ $this, 'add_new_story_columns' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'bulk_action_update_dropdown' ] );
		add_action( 'load-edit.php', [ $this, 'bulk_action_update_action' ] );
		add_action( 'manage_posts_custom_column', [ $this, 'update_column_content' ], 10, 2 );
		add_action( 'load-' . $post_type . '_get-npr-stories', [ $this, 'load_page_hook' ] );
		add_action( 'admin_init', [ $this, 'settings_init' ] );
		add_action( 'admin_init', [ $this, 'restore_page_hook' ] );
	}

	/**
	 * Register the admin menu for "Get NPR Stories"
	 */
	public function admin_menu(): void {
		$post_type = get_option( 'npr_cds_pull_post_type', 'post' );
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}
		$required_capability = apply_filters( 'npr_cds_get_stories_capability', 'edit_posts' );

		add_menu_page(
			esc_html__( 'View Stories Uploaded to the CDS', 'npr-content-distribution-service' ),
			esc_html__( 'NPR CDS', 'npr-content-distribution-service' ),
			$required_capability,
			'npr-cds-overview',
			[ $this, 'view_uploads' ],
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'data:image/svg+xml;base64,' . base64_encode( '<svg id="Layer_1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 650.54 215.48"><polygon points="218.17 213.48 218.17 2 2 2 2 213.48 218.17 213.48 218.17 213.48" fill="none" stroke="#231f20" stroke-miterlimit="10" stroke-width="4"/><polygon points="435.43 213.48 435.43 2 218.17 2 218.17 213.48 435.43 213.48 435.43 213.48" fill="none" stroke="#231f20" stroke-miterlimit="10" stroke-width="4"/><polygon points="649.04 213.48 649.04 2 435.43 2 435.43 213.48 649.04 213.48 649.04 213.48" fill="none" stroke="#231f20" stroke-miterlimit="10" stroke-width="3"/><path d="M132.76,165.05v-64.1c.61-7.32-1.32-14.63-5.47-20.7-4.66-4.74-11.21-7.14-17.84-6.51-8.68.46-16.78,4.52-22.35,11.2v80.12h-26.04V53.69h18.71l4.77,10.42c7.07-8.16,17.36-12.28,31.29-12.28,11.65-.59,23.01,3.73,31.33,11.89,7.64,7.94,11.46,18.97,11.46,33.16v68.18h-25.87Z"/><path d="M324.76,167.26c16.95,0,30.34-4.92,40.14-14.76,9.85-9.85,14.71-23.87,14.71-42.1,0-39.06-17.74-58.59-53.21-58.59-8.94-.21-17.6,3.16-24.04,9.37v-7.51h-26.04v140.96h26.04v-32.29c6.99,3.32,14.65,5,22.39,4.9h0ZM318.94,74.04c11.98,0,20.53,2.69,25.74,8.03s7.73,14.58,7.73,27.73c0,12.33-2.6,21.32-7.81,26.99-5.21,5.73-13.8,8.68-25.74,8.68-6,.11-11.84-1.9-16.49-5.69v-58.37c4.3-4.58,10.29-7.2,16.58-7.25v-.13Z"/><path d="M572.74,78.12c-4.52-2.9-9.78-4.41-15.15-4.34-6.29.13-12.18,3.13-15.97,8.16-4.72,5.58-7.21,12.7-6.99,20.01v63.15h-26.04V53.69h26.04v10.68c7.19-8.36,17.8-12.98,28.82-12.54,6.99-.41,13.98,1,20.27,4.08l-10.98,22.22Z"/></svg>' ),
			50.887
		);
		add_submenu_page(
			'npr-cds-overview',
			'Get NPR Stories',
			'Get NPR Stories',
			$required_capability,
			'get-npr-stories',
			[ $this, 'get_stories' ]
		);
		add_submenu_page(
			'npr-cds-overview',
			'NPR CDS Main Settings',
			'Main Settings',
			$required_capability,
			'npr-cds-settings',
			[ $this, 'general_settings' ]
		);
		add_submenu_page(
			'npr-cds-overview',
			'NPR CDS Get Multi Settings',
			'Get Multi Settings',
			$required_capability,
			'npr-cds-get-multi',
			[ $this, 'get_multi' ]
		);
		add_submenu_page(
			'npr-cds-overview',
			'NPR CDS Push Mapping',
			'Push Mapping',
			$required_capability,
			'npr-cds-push-mapping',
			[ $this, 'push_mapping' ]
		);
		add_submenu_page(
			'edit.php' . ( $post_type !== 'post' ? '?post_type=' . $post_type : '' ),
			'Get NPR Stories',
			'Get NPR Stories',
			$required_capability,
			'get-npr-stories',
			[ $this, 'get_stories' ]
		);
	}
	public function general_settings(): void { ?>
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
		<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
		<?php settings_fields( 'npr_cds' ); ?>
		<?php echo $this->restore_old(); ?>
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
	public function get_multi(): void { ?>
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
		<?php echo $this->restore_old(); ?>
		<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
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
	public function push_mapping(): void { ?>
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
		<?php echo $this->restore_old(); ?>
		<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
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
	public function settings_init(): void {
		/**
		 * CDS: General Settings
		 */
		add_settings_section( 'npr_cds_settings', 'General Settings', [ $this, 'settings_callback' ], 'npr_cds' );

		add_settings_field( 'npr_cds_token', 'CDS Token', [ $this, 'token_callback' ], 'npr_cds', 'npr_cds_settings' );
		register_setting( 'npr_cds', 'npr_cds_token' );

		add_settings_field( 'npr_cds_pull_url', 'Pull URL', [ $this, 'pull_url_callback' ], 'npr_cds', 'npr_cds_settings' );
		register_setting( 'npr_cds', 'npr_cds_pull_url', [ 'sanitize_callback' => [ $this, 'validation_callback_pull_url' ] ] );

		add_settings_field( 'npr_cds_push_url', 'Push URL', [ $this, 'push_url_callback' ], 'npr_cds', 'npr_cds_settings' );
		register_setting( 'npr_cds', 'npr_cds_push_url', [ 'sanitize_callback' => [ $this, 'validation_callback_push_url' ] ] );

		/**
		 * CDS: Org Settings
		 */
		add_settings_section( 'npr_cds_org_settings', 'Organization Settings', [ $this, 'settings_callback' ], 'npr_cds' );

		add_settings_field( 'npr_cds_org_id', 'Service ID', [ $this, 'org_id_callback' ], 'npr_cds', 'npr_cds_org_settings' );
		register_setting( 'npr_cds', 'npr_cds_org_id', [ 'sanitize_callback' => [ $this, 'validation_callback_org_id' ] ] );

		add_settings_field( 'npr_cds_prefix', 'Document Prefix', [ $this, 'prefix_callback' ], 'npr_cds', 'npr_cds_org_settings' );
		register_setting( 'npr_cds', 'npr_cds_prefix', [ 'sanitize_callback' => [ $this, 'validation_callback_prefix' ] ] );

		/**
		 * CDS: Theme Settings
		 */
		add_settings_section( 'npr_cds_theme_settings', 'Theme Settings', [ $this, 'settings_callback' ], 'npr_cds' );

		add_settings_field( 'npr_cds_query_use_featured', 'Theme uses Featured Image', [ $this, 'query_use_featured_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_query_use_featured', [ 'sanitize_callback' => [ $this, 'validation_callback_checkbox' ] ] );

		add_settings_field( 'npr_cds_pull_post_type', 'NPR Default Pull Post Type', [ $this, 'pull_post_type_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_pull_post_type' );

		add_settings_field( 'npr_cds_push_post_type', 'NPR Push Post Type', [ $this, 'push_post_type_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_push_post_type' );

		add_settings_field( 'npr_cds_push_default', 'Push to CDS Default', [ $this, 'push_default_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_push_default' );

		add_settings_field( 'npr_cds_push_one_homepage_default', 'Include for NPR One/ Homepage Default', [ $this, 'push_one_homepage_default_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_push_one_homepage_default' );

		add_settings_field( 'npr_cds_import_tags', 'Import Tags from CDS?', [ $this, 'import_tags_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_import_tags' );

		add_settings_field( 'npr_cds_display_attribution', 'Append Article Attribution?', [ $this, 'display_attribution_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_display_attribution' );

		add_settings_field( 'npr_cds_skip_promo_cards', 'Skip NPR promos', [ $this, 'skip_promo_cards_callback' ], 'npr_cds', 'npr_cds_theme_settings' );
		register_setting( 'npr_cds', 'npr_cds_skip_promo_cards', [ 'sanitize_callback' => [ $this, 'validation_callback_checkbox'] ] );

		/**
		 * CDS: Image Settings
		 */
		add_settings_section( 'npr_cds_image_settings', 'Image Settings', [ $this, 'settings_callback' ], 'npr_cds' );

		add_settings_field( 'npr_cds_image_width', 'Max Image Width', [ $this, 'image_width_callback' ], 'npr_cds', 'npr_cds_image_settings' );
		register_setting( 'npr_cds', 'npr_cds_image_width' );

		add_settings_field( 'npr_cds_image_quality', 'Image Quality', [ $this, 'image_quality_callback' ], 'npr_cds', 'npr_cds_image_settings' );
		register_setting( 'npr_cds', 'npr_cds_image_quality' );

		add_settings_field( 'npr_cds_image_format', 'Image Format', [ $this, 'image_format_callback' ], 'npr_cds', 'npr_cds_image_settings' );
		register_setting( 'npr_cds', 'npr_cds_image_format' );

		/**
		 * CDS: Get Multi Settings
		 */
		add_settings_section( 'npr_cds_get_multi_settings', 'Multiple Get Settings', [ $this, 'get_multi_settings_callback' ], 'npr_cds_get_multi_settings' );

		add_settings_field( 'npr_cds_num', 'Number of things to get', [ $this, 'num_multi_callback' ], 'npr_cds_get_multi_settings', 'npr_cds_get_multi_settings' );
		register_setting( 'npr_cds_get_multi_settings', 'npr_cds_num', [ 'type' => 'integer', 'sanitize_callback' => [ $this, 'validation_num' ] ] );

		/**
		 * CDS: Query Details
		 */
		add_settings_section( 'npr_cds_query_details', 'Query Details', [ $this, 'query_details_callback' ], 'npr_cds_get_multi_settings' );
		$num = get_option( 'npr_cds_num', 1 );
		for ( $i = 0; $i < $num; $i++ ) {
			add_settings_field( 'npr_cds_query_' . $i, 'Query ' . $i, [ $this, 'query_callback' ], 'npr_cds_get_multi_settings', 'npr_cds_query_details', $i );
			register_setting( 'npr_cds_get_multi_settings', 'npr_cds_query_' . $i, [ 'type' => 'array', 'default' => [ 'filters' => '', 'sorting' => '', 'publish' => '', 'category' => '', 'tags' => '', 'import_tags' => '1' ] ] );
		}

		/**
		 * CDS: Cron Settings
		 */
		add_settings_section( 'npr_cds_cron_settings', 'Cron Settings', [ $this, 'settings_callback' ], 'npr_cds_get_multi_settings' );

		add_settings_field( 'npr_cds_query_run_multi', 'Run the queries on saving changes', [ $this, 'query_run_multi_callback' ], 'npr_cds_get_multi_settings', 'npr_cds_cron_settings' );
		register_setting( 'npr_cds_get_multi_settings', 'npr_cds_query_run_multi', [ 'sanitize_callback' => [ $this, 'validation_callback_checkbox' ] ] );

		add_settings_field( 'npr_cds_query_multi_cron_interval', 'Interval to run Get Multi cron', [ $this, 'query_multi_cron_interval_callback' ], 'npr_cds_get_multi_settings', 'npr_cds_cron_settings' );
		register_setting( 'npr_cds_get_multi_settings', 'npr_cds_query_multi_cron_interval', [ 'type' => 'integer' ] );

		/**
		 * CDS: Push Settings
		 */
		add_settings_section( 'npr_cds_push_settings', 'Metadata Settings', [ $this, 'push_settings_callback' ], 'npr_cds_push_mapping' );

		add_settings_field( 'npr_cds_push_use_custom_map', 'Use Custom Settings', [ $this, 'use_custom_mapping_callback' ], 'npr_cds_push_mapping', 'npr_cds_push_settings' );
		register_setting( 'npr_cds_push_mapping', 'npr_cds_push_use_custom_map', [ 'sanitize_callback' => [ $this, 'validation_callback_checkbox' ] ] );

		add_settings_field( 'npr_cds_mapping_title', 'Story Title', [ $this, 'mapping_title_callback' ], 'npr_cds_push_mapping', 'npr_cds_push_settings' );
		register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_title' );

		add_settings_field( 'npr_cds_mapping_body', 'Story Body', [ $this, 'mapping_body_callback' ], 'npr_cds_push_mapping', 'npr_cds_push_settings' );
		register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_body' );

		add_settings_field( 'npr_cds_mapping_byline', 'Story Byline', [ $this, 'mapping_byline_callback' ], 'npr_cds_push_mapping', 'npr_cds_push_settings' );
		register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_byline' );

		add_settings_field( 'npr_cds_mapping_media_credit', 'Media Credit Field', [ $this, 'mapping_media_credit_callback' ], 'npr_cds_push_mapping', 'npr_cds_push_settings' );
		register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_media_credit' );

		add_settings_field( 'npr_cds_mapping_media_agency', 'Media Agency Field', [ $this, 'mapping_media_agency_callback' ], 'npr_cds_push_mapping', 'npr_cds_push_settings' );
		register_setting( 'npr_cds_push_mapping', 'npr_cds_mapping_media_agency' );
	}

	/**
	 * Settings group callback functions
	 */
	public function settings_callback() { }
	public function get_multi_settings_callback() { }
	public function push_settings_callback(): void { ?>
		<p>Use this page to map your custom WordPress Meta fields to fields sent to the NPR CDS, and vice versa. Clicking the <strong>Use Custom Settings</strong> checkbox will enable these mappings. If you wish to use the default mapping for a field, select &mdash; default &mdash; and we will use the obvious WordPress field.</p>
		<p>Select for the Meta fields for the <strong><?php echo esc_html( npr_cds_get_push_post_type() ); ?></strong> post
			type.</p> <?php
	}

	/**
	 * NPR General Settings Group Callbacks
	 */
	public function token_callback(): void {
		$attr = '';
		if ( defined( 'NPR_CDS_TOKEN' ) ) {
			$option = 'This field managed in wp-config.php';
			$attr = 'disabled ';
		} else {
			$option = get_option( 'npr_cds_token' );
		}
		echo npr_cds_esc_html( '<p><input type="text" value="' . $option . '" name="npr_cds_token" ' . $attr . '/></p><p><em>This is a bearer token provided by NPR. If you do not already have one, you can request one through <a href="https://studio.npr.org/">NPR Studio</a>.<br /><br />You can also manage your token by adding the following to wp-config.php: <code>define( \'NPR_CDS_TOKEN\', \'{ TOKEN STRING }\' );</code></em></p>' );
	}
	public function pull_url_callback(): void {
		$option = get_option( 'npr_cds_pull_url' );
		$output = '<p><label><input type="radio" name="npr_cds_pull_url" value="https://stage-content.api.npr.org"' . ( $option == 'https://stage-content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Staging</label></p>';
		$output .= '<p><label><input type="radio" name="npr_cds_pull_url" value="https://content.api.npr.org"' . ( $option == 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Production</label></p>';
		$output .= '<p><label><input type="radio" name="npr_cds_pull_url" value="other"' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Other</label> <input type="text" name="npr_cds_pull_url_other" id="npr_cds_pull_url_other" value="' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? $option : '' ) . '" placeholder="Type other URL here" /></p>';
		echo npr_cds_esc_html( $output );
	}
	public function push_url_callback(): void {
		$option = get_option( 'npr_cds_push_url' );
		$output = '<p><label><input type="radio" name="npr_cds_push_url" value="https://stage-content.api.npr.org"' . ( $option == 'https://stage-content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Staging</label></p>';
		$output .= '<p><label><input type="radio" name="npr_cds_push_url" value="https://content.api.npr.org"' . ( $option == 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Production</label></p>';
		$output .= '<p><label><input type="radio" name="npr_cds_push_url" value="other"' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? ' checked="checked"' : '' ) . ' /> Other</label> <input type="text" name="npr_cds_push_url_other" id="npr_cds_push_url_other" value="' . ( $option !== 'https://stage-content.api.npr.org' && $option !== 'https://content.api.npr.org' ? $option : '' ) . '" placeholder="Type other URL here" /></p>';
		echo npr_cds_esc_html( $output );
	}
	public function org_id_callback(): void {
		$option = get_option( 'npr_cds_org_id' );
		echo npr_cds_esc_html( '<p><input type="text" value="' . $option . '" name="npr_cds_org_id" /></p><p><em>Enter the service ID provided by NPR. If your stories will be co-owned with another organization, you can include all of the service IDs as a comma-separated list.</em></p>' );
	}
	public function prefix_callback(): void {
		$option = get_option( 'npr_cds_prefix' );
		echo npr_cds_esc_html( '<p><input type="text" value="' . $option . '" name="npr_cds_prefix" placeholder="callletters" /></p><p><em>When given write permission to the CDS, NPR will assign a code that will be prefixed on all of your document IDs (e.g. "kuhf-12345").</em></p>' );
	}
	public function query_use_featured_callback(): void {
		$use_featured = get_option( 'npr_cds_query_use_featured' );
		$check_box_string = '<input id="npr_cds_query_use_featured" name="npr_cds_query_use_featured" type="checkbox" value="true"' .
		                    ( $use_featured ? ' checked="checked"' : '' ) . ' />';

		echo npr_cds_esc_html( '<p>' . $check_box_string . " If your theme uses the featured image, checking this box will remove the lead image from imported posts.</p>" );
	}
	public function pull_post_type_callback(): void {
		$post_types = get_post_types();
		$this->show_post_types_select( 'npr_cds_pull_post_type', $post_types );
	}
	public function push_post_type_callback(): void {
		$post_types = get_post_types();
		$this->show_post_types_select( 'npr_cds_push_post_type', $post_types );
		echo npr_cds_esc_html( '<p><em>If you change the Push Post Type setting remember to update the mappings for CDS Fields at <a href="' . admin_url( 'options-general.php?page=npr_cds#npr-fields' ) . '">NPR CDS Field Mapping</a> tab.</em></p>' );
	}
	public function push_default_callback(): void {
		$push_default = get_option( 'npr_cds_push_default', '1' );
		$check_box_string = '<select id="npr_cds_push_default" name="npr_cds_push_default"><option value="1"' . ( $push_default === '1' ? ' selected' : '' ) . '>Checked</option>' .
		                    '<option value="0"' . ( $push_default === '0' ? ' selected' : '' ) . '>Not Checked</option>' .
		                    '</select>';
		echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>When creating a new post in your NPR Push Post Type, do you want the "Push to NPR CDS" box to be checked by default or not?</em></p>' );
	}
	public function push_one_homepage_default_callback(): void {
		$push_default = get_option( 'npr_cds_push_one_homepage_default', '0' );
		$check_box_string = '<select id="npr_cds_push_one_homepage_default" name="npr_cds_push_one_homepage_default"><option value="0"' . ( $push_default === '0' ? ' selected' : '' ) . '>Not Checked</option>' .
		                    '<option value="1"' . ( $push_default === '1' ? ' selected' : '' ) . '>Checked</option>' .
		                    '</select>';
		echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>When creating a new post in your NPR Push Post Type, do you want the "Include for NPR One and NPR Homepage" box to be checked by default or not?</em></p>' );
	}
	public function import_tags_callback(): void {
		$import_tags_default = get_option( 'npr_cds_import_tags', '1' );
		$check_box_string = '<select id="npr_cds_import_tags" name="npr_cds_import_tags"><option value="1"' . ( $import_tags_default === '1' ? ' selected' : '' ) . '>Import</option>' .
		                    '<option value="0"' . ( $import_tags_default === '0' ? ' selected' : '' ) . '>Do Not Import</option>' .
		                    '</select>';
		echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>When importing an article from the NPR CDS, do you want to import all of the article\'s tags into WordPress?</em></p>' );
	}
	public function display_attribution_callback(): void {
		$attribution_default = get_option( 'npr_cds_display_attribution', '0' );
		$check_box_string = '<select id="npr_cds_display_attribution" name="npr_cds_display_attribution"><option value="0"' . ( $attribution_default === '0' ? ' selected' : '' ) . '>Do Not Append</option>' .
		                    '<option value="1"' . ( $attribution_default === '1' ? ' selected' : '' ) . '>Append</option>' .
		                    '</select>';
		echo npr_cds_esc_html( '<p>' . $check_box_string . '</p><p><em>Do you want to append an attribution message to the bottom of imported articles? (e.g. "Copyright &copy; ' . date( 'Y' ) . ' NPR")</em></p>' );
	}
	public function skip_promo_cards_callback(): void {
		$skip_promos = get_option( 'npr_cds_skip_promo_cards' );
		$check_box_string = '<input id="npr_cds_skip_promo_cards" name="npr_cds_skip_promo_cards" type="checkbox" value="true"' . ( $skip_promos ? ' checked="checked"' : '' ) . ' />';

		echo npr_cds_esc_html( '<p>' . $check_box_string . " Filter out any NPR promo cards embedded in posts.</p>" );
	}
	public function image_format_callback(): void {
		$this->show_post_types_select( 'npr_cds_image_format', [ 'jpeg', 'png', 'webp' ] );
	}
	public function image_quality_callback(): void {
		$option = get_option( 'npr_cds_image_quality', 75 );
		echo npr_cds_esc_html( '<p><input type="number" value="' . $option . '" name="npr_cds_image_quality" min="1" max="100" /></p><p><em>Set the quality level of the images from the NPR CDS (default: 75).</em></p>' );
	}
	public function image_width_callback(): void {
		$option = get_option( 'npr_cds_image_width', 1200 );
		echo npr_cds_esc_html( '<p><input type="number" value="' . $option . '" name="npr_cds_image_width" min="500" max="3000" /></p><p><em>Maximum width of images pulled in from the NPR CDS (default: 1200).</em></p>' );
	}

	/**
	 * NPR Get Multi Settings Group Callbacks
	 */
	public function num_multi_callback(): void {
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
			$this->cron_pull();
		}

		if ( $enable && !wp_next_scheduled( 'npr_cds_hourly_cron') ) {
			wp_schedule_event( time(), 'npr_cds_interval', 'npr_cds_hourly_cron' );
		}
		echo npr_cds_esc_html( '<p><input type="number" value="' . $num . '" min="0" max="' . NPR_MAX_QUERIES . '" name="npr_cds_num" /></p><p><em>Increase the number of queries by changing the number in the field above, to a maximum of 10.</em></p>' );
	}
	public function query_callback( int $i ): void {
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
		          '<p><em>A list of available filtering options can be found <a href="https://npr.github.io/content-distribution-service/api-reference/core-concepts/querying/#filtering">in the CDS documentation</a></em></p></div>' .
		          '<h4>Sorting</h4><div><p><input type="text" value="' . $query['sorting'] . '" name="npr_cds_query_' . $i . '[sorting]" placeholder="sort=<type>[:<direction>]" /></p>' .
		          '<p><em>A list of available sorting query parameters can be found <a href="https://npr.github.io/content-distribution-service/api-reference/core-concepts/querying/#sorting">in the CDS documentation</a></em></p></div>' .
		          '<h4>Publish or Save as Draft?</h4> ' .
		          '<div><select id="npr_cds_query_' . $i . '[publish]" name="npr_cds_query_' . $i . '[publish]">' .
		          '<option value="Publish"' . ( $query['publish'] == 'Publish' ? ' selected' : '' ) . '>Publish</option>' .
		          '<option value="Draft"' . ( $query['publish'] == 'Draft' ? ' selected' : '' ) . '>Draft</option>' .
		          '</select></div>' .
		          '<h4>Save as post type?</h4>' .
		          $this->show_post_types_select( 'npr_cds_query_' . $i . '[pull_type]', $post_types, true );
		if ( $optionType == 'post' ) {
			$args = [
				'show_option_none'	=> __( 'Select category', 'npr-content-distribution-service' ),
				'id'				=> 'npr_cds_query_' . $i . '[category]',
				'name'				=> 'npr_cds_query_' . $i . '[category]',
				'hierarchical'		=> true,
				'show_count'		=> 0,
				'orderby'			=> 'name',
				'echo'				=> 0,
				'selected'			=> ( !empty( $query['category'] ) ? (int)$query['category'] : 0 ),
				'hide_empty'		=> 0,
				'multiple'			=> true
			];
			$select = wp_dropdown_categories( $args );
			$output .= '<h4>Add Category</h4><div>' . $select . '<p><em>This option applies to posts only</em></p></div>';
		}
		$output .= '<h4>Add Tags</h4><div><p><input type="text" value="' . $query['tags'] . '" name="npr_cds_query_' . $i . '[tags]" placeholder="pepperoni,pineapple,mozzarella" /></p>' .
		           '<p><em>Add tag(s) to each story pulled from NPR (comma separated).</em></p></div>';
		$output .= '<h4>Import CDS Tags?</h4><div><p><select id="npr_cds_query_' . $i . '[import_tags]" name="npr_cds_query_' . $i . '[import_tags]">'.
		           '<option value="1"' . ( $query['import_tags'] === '1' ? ' selected' : '' ) . '>Import</option>' .
		           '<option value="0"' . ( $query['import_tags'] === '0' ? ' selected' : '' ) . '>Do Not Import</option>' .
		           '</select></p></div>';
		echo npr_cds_esc_html( $output );
	}
	public function query_run_multi_callback(): void {
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
	public function query_multi_cron_interval_callback(): void {
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
	public function use_custom_mapping_callback(): void {
		$use_custom = get_option( 'npr_cds_push_use_custom_map' );
		$check_box_string = '<input id="npr_cds_push_use_custom_map" name="npr_cds_push_use_custom_map" type="checkbox" value="true"' .
		                    ( $use_custom ? ' checked="checked"' : '' ) . ' />';
		echo npr_cds_esc_html( $check_box_string );
	}
	public function mapping_title_callback(): void {
		$push_post_type = npr_cds_get_push_post_type();
		$keys = npr_cds_get_post_meta_keys( $push_post_type );
		$this->show_keys_select( 'npr_cds_mapping_title', $keys );
	}
	public function mapping_body_callback(): void {
		$push_post_type = npr_cds_get_push_post_type();
		$keys = npr_cds_get_post_meta_keys( $push_post_type );
		$this->show_keys_select( 'npr_cds_mapping_body', $keys );
	}
	public function mapping_byline_callback(): void {
		$push_post_type = npr_cds_get_push_post_type();
		$keys = npr_cds_get_post_meta_keys( $push_post_type );
		$this->show_keys_select( 'npr_cds_mapping_byline', $keys );
	}
	public function mapping_media_credit_callback(): void {
		$keys = npr_cds_get_post_meta_keys( 'attachment' );
		$this->show_keys_select( 'npr_cds_mapping_media_credit', $keys );
	}
	public function mapping_media_agency_callback(): void {
		$keys = npr_cds_get_post_meta_keys( 'attachment' );
		$this->show_keys_select( 'npr_cds_mapping_media_agency', $keys );
	}

	/**
	 * create the select widget where the ID is the value in the array
	 *
	 * @param string $field_name
	 * @param array $keys - an array like (1=>'Value1', 2=>'Value2', 3=>'Value3');
	 * @param bool $return
	 *
	 * @return string
	 */
	public function show_post_types_select( string $field_name, array $keys, bool $return = false ): string {
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
	public function validation_callback_checkbox( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Prefix validation callback. We only want to save the prefix without the hyphen
	 */
	public function validation_callback_prefix( $value ): string {
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
	public function validation_callback_pull_url( string $value ): string {
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
	public function validation_callback_push_url( string $value ): string {
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
	public function validation_num( int $value ): int {
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
	public function validation_callback_org_id( $value ): string {
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
	 * @param bool $return
	 *
	 * @return string
	 */
	public function show_keys_select( string $field_name, array $keys ): void {
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
		echo $output;
	}

	/**
	 * Functions to restore previous settings if they're available in the database
	 */
	public function restore_old(): string {
		$output = '';
		$old_options = get_option( 'npr_cds_old_options' );
		$page = 'npr_cds';
		if ( !empty( $old_options ) ) {
			if ( ! empty( $_GET['page'] ) ) {
				$page = $_GET['page'];
			}
			$output = '<div class="notice notice-warning"><p style="display: inline-flex; align-items: center; gap: 1rem;">You have previous stored options for this plugin. What would you like to do? <a class="button-secondary" href="' . admin_url( 'admin.php?page=' . $page . '&cds_action=restore' ) . '">Restore Previous Options</a> <a class="button-secondary" href="' . admin_url( 'admin.php?page=' . $page . '&cds_action=delete' ) . '">Delete Previous Options</a></p></div>';
		} elseif ( !empty( $_GET['cds_result'] ) ) {
			if ( $_GET['cds_result'] === 'restored' ) {
				$output = '<div class="notice notice-warning"><p>The previous options have been restored.</p></div>';
			} elseif ( $_GET['cds_result'] === 'deleted' ) {
				$output = '<div class="notice notice-warning"><p>The previous options have been deleted.</p></div>';
			}
		}
		return $output;
	}
	public function restore_page_hook(): void {
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
					$post_link = admin_url( 'admin.php?page=' . $page . '&cds_result=restored' );
				} elseif ( $_GET['cds_action'] === 'delete' ) {
					delete_option( 'npr_cds_old_options' );
					$post_link = admin_url( 'admin.php?page=' . $page . '&cds_result=deleted' );
				}
				if ( !empty( $post_link ) ) {
					wp_redirect( $post_link );
				}
			}
		}
	}

	/**
	 * What is the post type that pulled stories should be created as?
	 *
	 * @return string The post type
	 */
	public static function get_pull_post_type(): string {
		return get_option( 'npr_cds_pull_post_type', 'post' );
	}

	/**
	 * The cron job to pull stories from the API
	 */
	public static function cron_pull(): void {
		// here we should get the list of IDs/full urls that need to be checked hourly
		//because this is run on cron, and may be fired off by an non-admin, we need to load a bunch of stuff
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		$pull_url = NPR_CDS_PULL_URL;
		// This is debug code. It may be save future devs some time; please keep it around.
		/*
			$now = gmDate("D, d M Y G:i:s O ");
			error_log("right now the time is -- ".$now); // debug use
		*/

		// here we go.
		$num = get_option( 'npr_cds_num' );
		for ( $i = 0; $i < $num; $i++ ) {
			$api = new NPR_CDS_WP();
			$query = get_option( 'npr_cds_query_' . $i );
			if ( !empty( $query ) ) {
				npr_cds_error_log( 'Cron '. $i . ' querying NPR CDS for ' . $query['filters'] );
				//if the query string contains the pull url and 'query', just make request from the API
				$url = $pull_url . '/' . NPR_CDS_WP::NPR_CDS_VERSION . '/documents?';
				$query_array = [];
				if ( empty( $query['filters'] ) && empty( $query['sorting'] ) ) {
					continue;
				}
				if ( !empty( $query['filters'] ) ) {
					$filters = explode( '&', $query['filters'] );
					if ( !empty( $filters ) ) {
						foreach ( $filters as $filter ) {
							$filt = explode( '=', $filter );
							if ( !empty( $filt[1] ) ) {
								$query_array[] = $filter;
							}
						}
					}
				}
				if ( !empty( $query['sorting'] ) ) {
					$sorting = explode( '&', $query['sorting'] );
					if ( !empty( $sorting ) ) {
						foreach ( $sorting as $sort ) {
							$sort_x = explode( '=', $sort );
							if ( !empty( $sort_x[1] ) ) {
								$query_array[] = $sort;
							}
						}
					}
				}
				$url .= implode( '&', $query_array );
				$api->query_by_url( $url );
				$api->parse();
				try {
					$pub_flag = FALSE;
					if ( $query['publish'] == 'Publish' ) {
						$pub_flag = TRUE;
					}
					$api->update_posts_from_stories( $pub_flag, $i );
					if ( !empty( $api->message ) ) {
						error_log( 'NPR CDS: not going to save story. Query ' . implode( '&', $query_array ) . ' returned an error ' . $api->message . ' error' ); // debug use
					}
				} catch( Exception $e ) {
					error_log( 'NPR CDS: error in ' .  __FUNCTION__ . ' like this :'. $e ); // debug use
				}
			}
		}
	}

	/**
	 * Function to convert an alleged NPR story URL or ID into a story ID, then request it
	 * @throws Exception
	 */
	public function load_page_hook(): void {
		$required_capability = apply_filters( 'npr_cds_get_stories_capability', 'edit_posts' );

		// if the current user shouldn't be doing this, fail
		if ( !current_user_can( $required_capability ) ) {
			wp_die(
				__( 'You do not have permission to edit posts, and therefore you do not have permission to pull posts from the NPR CDS', 'npr-content-distribution-service' ),
				__( 'NPR CDS Error', 'npr-content-distribution-service' ),
				403
			);
		}
		$publish = $valid = false;
		$story_id = 0;
		// find the input that is allegedly a story id
		// We validate these later
		if ( isset( $_POST['story_id'] ) ) {
			if ( !check_admin_referer( 'npr_cds_nonce_story_id', 'npr_cds_nonce_story_id_field' ) ) {
				wp_die(
					__( 'Nonce did not verify in NPR_CDS::load_page_hook. Are you sure you should be doing this?', 'npr-content-distribution-service' ),
					__( 'NPR CDS Error', 'npr-content-distribution-service' ),
					403
				);
			}
			$story_id = trim( sanitize_text_field( $_POST[ 'story_id' ] ) );
			if ( isset( $_POST['publishNow'] ) ) {
				$publish = true;
			}
			if ( isset( $_POST['createDraft'] ) ) {
				$publish = false;
			}
		} elseif ( isset( $_GET['story_id'] ) && isset( $_GET['create_draft'] ) ) {
			$story_id = trim( sanitize_text_field( $_GET['story_id'] ) );
		}

		// try to get the ID of the story from the URL
		if ( !empty( $story_id ) ) {
			//check to see if we got an ID or a URL
			if ( is_numeric( $story_id ) && strlen( $story_id ) >= 8 ) {
				$valid = true;
			} elseif ( preg_match( '/^[a-z0-9\-]+$/', $story_id ) ) {
				$valid = true;
			} elseif ( wp_http_validate_url( $story_id ) ) {
				$story_id = sanitize_url( $story_id );
				// url format: /yyyy/mm/dd/id
				// url format: /blogs/name/yyyy/mm/dd/id
				if ( str_contains( $story_id, 'npr.org' ) ) {
					preg_match( '/https?:\/\/[^\s\/]*npr\.org\/((([^\/]*\/){3,5})([a-z\-0-9]+))\/.*/', $story_id, $matches );
					if ( !empty( $matches[4] ) ) {
						$story_id = $matches[4];
						$valid = true;
					} else {
						preg_match( '/https?:\/\/[^\s\/]*npr\.org\/([^&\s<]*storyId=([0-9]+)).*/', $story_id, $matches );
						if ( !empty( $matches[2] ) ) {
							$story_id = $matches[2];
							$valid = true;
						}
					}
				} else {
					$meta = get_meta_tags( $story_id );
					if ( ! empty( $meta['brightspot-datalayer'] ) ) {
						$json = json_decode( html_entity_decode( $meta['brightspot-datalayer'] ), true );
						if ( ! empty( $json['nprStoryId'] ) ) {
							$story_id = $json['nprStoryId'];
							$valid = true;
						}
					} elseif ( ! empty( $meta['story_id'] ) ) {
						$story_id = $meta['story_id'];
						$valid = true;
					} else {
						npr_cds_show_message( "The referenced URL (" . $story_id . ") does not contain a valid NPR CDS ID. Please try again.", true );
						error_log( "The referenced URL (" . $story_id . ") does not contain a valid NPR CDS ID. Please try again." ); // debug use
					}
				}
			}
		}

		// Don't do anything if $story_id isn't an ID
		if ( $valid ) {
			// start the API class
			$api = new NPR_CDS_WP();

			$params = [ 'id' => $story_id ];
			$api->request( $params );
			$api->parse();

			if ( empty( $api->message ) ) {
				$post_id = $api->update_posts_from_stories( $publish );
				if ( !empty( $post_id ) ) {
					//redirect to the edit page if we just updated one story
					$post_link = admin_url( 'post.php?action=edit&post=' . $post_id );
					wp_redirect( $post_link );
				}
			} else {
				if ( empty( $story ) ) {
					npr_cds_show_message( 'Error retrieving story for id = ' . $story_id . '<br> CDS Message = ' . $api->message, TRUE );
					error_log( 'Not going to save the return from query for story_id = ' . $story_id .', we got an error = ' . $api->message . ' from the NPR CDS' ); // debug use
				}
			}
		}
	}

	/**
	 * @throws Exception
	 */
	public function get_stories(): void {
		$api_key = NPR_CDS_WP::get_cds_token();
		$pull_url = NPR_CDS_PULL_URL; ?>
		<div class="wrap">
		<h2>Get NPR Stories</h2>
		<?php
		if ( !$api_key ) {
			npr_cds_show_message( 'You do not currently have a CDS token set. <a href="' . admin_url( 'options-general.php?page=npr_cds#npr-general' ) . '">Set your CDS token here.</a>', TRUE );
		}
		if ( !$pull_url ) {
			npr_cds_show_message( 'You do not currently have a CDS Pull URL set. <a href="' . admin_url( 'options-general.php?page=npr_cds#npr-general' ) . '">Set your CDS Pull URL here.</a>', TRUE );
		}

		// Get the story ID from the URL, then paste it into the input's value field with esc_attr
		$story_id = '';
		if ( !isset( $_POST['_wpnonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'npr_cds_nonce_story_id_field' ) ) {
			if ( ( isset( $_POST['story_id'] ) ) || ( isset( $_GET ) && isset( $_GET['story_id'] ) ) ) {
				if ( !empty( $_POST['story_id'] ) ) {
					$story_id = sanitize_text_field( $_POST['story_id'] );
				}
				if ( !empty( $_GET['story_id'] ) ) {
					$story_id = sanitize_text_field( $_GET['story_id'] );
				}
			}
		}
		?>

		<div style="float: left;">
			<form action="" method="POST">
				<label for="story_id">Enter an NPR Story ID or URL:</label> <input type="text" name="story_id" id="story_id" value="<?php echo esc_attr( $story_id ); ?>" />
				<?php wp_nonce_field( 'npr_cds_nonce_story_id', 'npr_cds_nonce_story_id_field' ); ?>
				<input type="submit" name='createDraft' value="Create Draft" />
				<input type="submit" name='publishNow' value="Publish Now" />
			</form>
		</div>
		</div><?php
	}
	public function view_uploads(): void {
		$api_key = NPR_CDS_WP::get_cds_token();
		$pull_url = NPR_CDS_PULL_URL;
		if ( !$api_key ) {
			npr_cds_show_message( 'You do not currently have a CDS token set. <a href="' . admin_url( 'admin.php?page=npr-cds-settings' ) . '">Set your CDS token here.</a>', TRUE );
		}
		if ( !$pull_url ) {
			npr_cds_show_message( 'You do not currently have a CDS Pull URL set. <a href="' . admin_url( 'admin.php?page=npr-cds-settings' ) . '">Set your CDS Pull URL here.</a>', TRUE );
		} ?>
		<style>
			details {
				padding: 0.75rem;
				background-color: white;
				border: 1px solid #5b5b5b;
				&.npr-homepage-eligible {
					summary {
						font-weight: bolder;
					}
					ul {
						list-style: disc;
						margin-inline-start: 1rem;
						li.homepage-No {
							border: 1px solid #F15B1C;
							padding: 0.25rem;
							background-color: #F15B1C25;
						}
					}
				}
				&.npr-upload {
					&[open] > summary {
						border-bottom: 1px dotted #000000;
						padding-bottom: 1rem;
						&::after {
							transform: rotate(0deg);
						}
					}
					+ details {
						margin-top: 0.5rem;
					}
					> summary {
						position: relative;
						anchor-name: --summary;
						&::marker {
							content: "";
						}
						&::before,
						&::after {
							content: "";
							border-block-start: 3px solid #F15B1C;
							height: 0;
							width: 1rem;
							inset-block-start: 50%;
							inset-inline-end: 0;
							position: absolute;
							position-anchor: --summary;
							position-area: top end;
						}
						&::after {
							transform: rotate(90deg);
							transform-origin: 50%;
						}
						.cds-summary {
							display: grid;
							width: calc(100% - 2rem);
							gap: 1rem;
							align-items: center;
							grid-template-columns: 1fr 1fr;
							@media (width >= 800px) {
								grid-template-columns: 3fr 1fr 1fr;
							}
							div:first-child {
								font-weight: 700;
								font-size: 1rem;
								line-height: 1.25;
								grid-column-end: span 2;
								@media (width >= 800px) {
									grid-column-end: span 1;
								}
							}
							div:nth-child(n+2) {
								font-size: 0.85rem;
								font-style: italic;
							}
						}
					}
					.npr-grid {
						display: grid;
						width: calc(100% - 2rem);
						gap: 1rem;
						align-items: start;
						grid-template-columns: 1fr 1fr;
						div:first-child {
							grid-column-end: span 2;
						}
						@media (width >= 800px) {
							grid-template-columns: 3fr 1fr 1fr;
							div:first-child {
								grid-column-end: span 1;
							}
						}
					}
					.npr-images {
						display: grid;
						width: 100%;
						grid-template-columns: 4fr 1fr;
						gap: 1rem;
						align-items: center;
						img {
							max-width: 100%;
						}
						ul {
							list-style: disc;
							margin-inline-start: 1rem;
							margin-block-start: 0.25rem;
						}
						p {
							margin-block-end: 0;
						}
					}
				}
			}
			.npr-homepage {
				background-color: #4C85C5;
				padding: 0.5rem;
				p {
					margin-block-start: 0;
					&.homepage-eligible {
						color: white;
					}
				}
			}
		</style>
		<div class="wrap">
			<h1>NPR CDS: View Uploaded Stories</h1>
		<?php
		$offset = 0;
		if ( !empty( $_GET['cds_offset'] ) ) {
			$get_offset = sanitize_text_field( $_GET['cds_offset'] );
			if ( is_numeric( $get_offset ) ) {
				if ( $get_offset % 50 == 0 ) {
					$offset = $get_offset;
				} else {
					$offset = round($get_offset / 50) * 50;
				}
			}
		}
		if ( $offset > 1950 ) {
			$offset = 1950;
		}
		$next_offset = $offset + 50;
		$prev_offset = $offset - 50;
		echo '<p style="text-align: right;">';
		if ( $prev_offset >= 0 ) {
			echo '<a href="' . admin_url( 'admin.php?page=npr-cds-overview&cds_offset=' . $prev_offset  ) . '">&larr; Previous 50</a>';
		}
		if ( $prev_offset >= 0 && $next_offset < 2000 ) {
			echo " | ";
		}
		if ( $next_offset < 2000 ) {
			echo '<a href="' . admin_url( 'admin.php?page=npr-cds-overview&cds_offset=' . $next_offset  ) . '">Next 50 &rarr;</a>';
		}
		echo '</p>';
		$api = new NPR_CDS_WP();

		$service_id = get_option( 'npr_cds_org_id' );
		$service_ids = explode( ',', $service_id );
		$owners = [];
		foreach( $service_ids as $oi ) {
			$owners[] = 'https://organization.api.npr.org/v4/services/' . $oi;
		}
		$params = [
			'sort' => 'publishDateTime:desc',
			'limit' => 50,
			'offset' => $offset,
			'profileIds' => [ 'story', 'publishable', 'document', 'renderable', 'buildout' ],
			'ownerHrefs' => implode( ',', $owners ),
		];
		$api->request( $params );
		$api->parse();

		if ( empty( $api->message ) ) {
			foreach ( $api->stories as $story ) {
				$homepage_eligible = [
					'overall' => 'No',
					'collection' => 'No',
					'publish-time' => 'No',
					'image-wide-primary' => 'No',
					'image-producer-credit' => 'No',
					'image-provider-credit' => 'No',
					'teaser' => 'No'
				];
				$date_format = get_option( 'date_format' );
				$gmt_offset = get_option( 'gmt_offset' ) * 3600;
				$pubTimestamp = strtotime( $story->publishDateTime ) + $gmt_offset;
				$now = time() + $gmt_offset;
				if ( $now - $pubTimestamp < 259200 ) {
					$homepage_eligible['publish-time'] = 'Yes';
				}
				$publishTime = date( $date_format, $pubTimestamp );
				$lastModified = date( $date_format, strtotime( $story->editorialLastModifiedDateTime ) + $gmt_offset );
				$local_id = explode( '-', $story->id )[1];
				$edit_link = admin_url( 'post.php?post=' . $local_id . '&action=edit');
				$profiles_arr = $owners_arr = $collect_arr = $bylines_arr = $images_arr = [];
				$primary_image = '<div class="npr-images"><div><p><strong>Primary Image:</strong><br>None</p></div></div>';
				if ( !empty( $story->profiles ) ) {
					foreach ( $story->profiles as $profile ) {
						$pexp           = explode( '/', $profile->href );
						$profiles_arr[] = end( $pexp );
					}
				}
				if ( !empty( $story->owners ) ) {
					foreach ( $story->owners as $owner ) {
						$oexp         = explode( '/', $owner->href );
						$owners_arr[] = end( $oexp );
					}
				}
				if ( !empty( $story->collections ) ) {
					foreach ( $story->collections as $collect ) {
						$cexp       = explode( '/', $collect->href );
						$collect_id = end( $cexp );
						if ( $collect_id == '319418027' ) {
							$collect_arr[] = 'NPR One/Homepage';
							$homepage_eligible['collection'] = 'Yes';
						} elseif ( $collect_id == '500549368' ) {
							$collect_arr[] = 'NPR One Featured';
						}
					}
				}
				if ( !empty( $story->bylines ) ) {
					foreach ( $story->bylines as $byline ) {
						$bexp          = explode( '/', $byline->href );
						$byline_id     = end( $bexp );
						$bylines_arr[] = $story->assets->{$byline_id}->name;
					}
				}
				if ( !empty( $story->images ) ) {
					foreach ( $story->images as $image ) {
						if ( !empty( $image->rels ) && in_array( 'primary', $image->rels ) ) {
							$iexp        = explode( '/', $image->href );
							$image_id    = end( $iexp );
							$image_asset = $story->assets->{$image_id};
							$main_rel    = $image_src = '';
							foreach ( $image->rels as $rel ) {
								if ( $rel !== 'primary' ) {
									$main_rel = $rel;
									if ( $main_rel == 'promo-image-wide' ) {
										$homepage_eligible['image-wide-primary'] = 'Yes';
									}
								}
							}
							foreach ( $image_asset->enclosures as $enclosure ) {
								if ( in_array( 'primary', $enclosure->rels ) ) {
									$image_src = $enclosure->href;
								}
							}
							if ( !empty( $image_asset->producer ) ) {
								$homepage_eligible['image-producer-credit'] = 'Yes';
							}
							if ( !empty( $image_asset->provider ) ) {
								$homepage_eligible['image-provider-credit'] = 'Yes';
							}

							$primary_image = <<<EOT
								<div class="npr-images">
									<div>
										<p><strong>Primary Image:</strong></p>
										<ul>
											<li><strong>Profile:</strong> {$main_rel}</li>
											<li><strong>Caption:</strong> {$image_asset->caption}</li>
											<li><strong>Credit:</strong> {$image_asset->producer} / {$image_asset->provider}</li>
										</ul>
									</div>
									<div>
										<img src="{$image_src}" loading="lazy" alt="{$image_asset->caption}">
									</div>
								</div>
EOT;
						}
					}
				}
				if ( !empty( $story->teaser ) && ( !str_contains( $story->teaser, '>' ) || !str_contains( $story->teaser, '<' ) ) ) {
					$homepage_eligible['teaser'] = 'Yes';
				}
				if (
					$homepage_eligible['collection'] == 'Yes' &&
					$homepage_eligible['publish-time'] == 'Yes' &&
					$homepage_eligible['image-wide-primary'] == 'Yes' &&
					$homepage_eligible['image-producer-credit'] == 'Yes' &&
					$homepage_eligible['image-provider-credit'] == 'Yes' &&
					$homepage_eligible['teaser'] == 'Yes'
				) {
					$homepage_eligible['overall'] = 'Yes';
				}
				$profiles = implode( ', ', $profiles_arr );
				$owners = implode( ', ', $owners_arr );
				$collections = implode( ', ', $collect_arr );
				$bylines = implode( ', ', $bylines_arr );
				$homepage = <<<EOT
					<details class="npr-homepage-eligible">
						<summary>Why?</summary>
						<p>Your story...</p>
						<ul>
							<li class="homepage-{$homepage_eligible['collection']}">is in the NPR One collection? <strong>{$homepage_eligible['collection']}</strong></li>
							<li class="homepage-{$homepage_eligible['publish-time']}">was published < 72 hours ago? <strong>{$homepage_eligible['publish-time']}</strong></li>
							<li class="homepage-{$homepage_eligible['teaser']}">has a teaser/description with no formatting? <strong>{$homepage_eligible['teaser']}</strong></li>
							<li class="homepage-{$homepage_eligible['image-wide-primary']}">has a wide primary image? <strong>{$homepage_eligible['image-wide-primary']}</strong></li>
							<li class="homepage-{$homepage_eligible['image-producer-credit']}">has an image producer/source? <strong>{$homepage_eligible['image-producer-credit']}</strong></li>
							<li class="homepage-{$homepage_eligible['image-provider-credit']}">has an image provider/credit? <strong>{$homepage_eligible['image-provider-credit']}</strong></li>
						</ul>
					</details>
EOT;
				echo <<<EOT
				<details class="npr-upload">
					<summary>
						<div class="cds-summary">
							<div>{$story->title}</div>
							<div>Published:<br><strong>{$publishTime}</strong></div>
							<div>NPR Homepage Eligible:<br><strong>{$homepage_eligible['overall']}</strong></div>
						</div>
					</summary>
					<div class="npr-grid">
						<div>
							<p><strong>Teaser:</strong><br>{$story->teaser}</p>
							{$primary_image}
						</div>
						<div>
							<p><strong>Bylines:</strong><br>{$bylines}</p>
							<p><strong>Owners:</strong><br>{$owners}</p>
							<p><strong>Collections:</strong><br>{$collections}</p>
							<p><strong>Profiles:</strong><br>{$profiles}</p>
						</div>
						<div>
							<p><strong>CDS ID:</strong><br>{$story->id}</p>
							<p>Last Modified Date:<br><strong>{$lastModified}</strong></p>
							<p><strong><a href="{$edit_link}">Edit in WordPress</a></strong></p>
							<div class="npr-homepage">
								<p class="homepage-eligible">NPR Homepage Eligible: <strong>{$homepage_eligible['overall']}</strong></p>
								{$homepage}
							</div>
						</div>
					</div>
				</details>
EOT;
			}
		} else {
			if ( empty( $story ) ) {
				npr_cds_show_message( 'Error retrieving stories<br> CDS Message = ' . $api->message, TRUE );
			}
		}
		echo '<p style="text-align: right;">';
		if ( $prev_offset >= 0 ) {
			echo '<a href="' . admin_url( 'admin.php?page=npr-cds-overview&cds_offset=' . $prev_offset  ) . '">&larr; Previous 50</a>';
		}
		if ( $prev_offset >= 0 && $next_offset < 2000 ) {
			echo " | ";
		}
		if ( $next_offset < 2000 ) {
			echo '<a href="' . admin_url( 'admin.php?page=npr-cds-overview&cds_offset=' . $next_offset  ) . '">Next 50 &rarr;</a>';
		}
		echo '</p>';
		echo "</div>";
	}
	public function add_new_story_columns( $cols ) {
		$cols['update_story'] = 'Update Story';
		return $cols;
	}
	public function update_column_content( string $column_name, int $post_ID ): void {
		if ( $column_name == 'update_story' ) {
			$retrieved = get_post_meta( $post_ID, NPR_RETRIEVED_STORY_META_KEY, true );
			if ( $retrieved ) {
				$api_id = get_post_meta( $post_ID, NPR_STORY_ID_META_KEY, TRUE );
				$post_type = $this->get_pull_post_type();
				$path = 'edit.php?page=get-npr-stories&story_id=' .$api_id;
				if ( $post_type !== 'post' ) {
					$path .= '&post_type=' . $post_type;
				}
				echo npr_cds_esc_html( '<a href="' . admin_url( $path ) . '"> Update </a>' );
			}
		}
	}
	public function bulk_action_update_dropdown(): void {
		$pull_post_type = $this->get_pull_post_type();
		global $post_type;
		if ( $post_type == $pull_post_type ) {
			printf(
				'<script>jQuery(document).ready(function($) {$("<option>").val("updateNprStory").text("%s").appendTo("select[name=\'action\']");$("<option>").val("updateNprStory").text("%s").appendTo("select[name=\'action2\']");});</script>',
				__( 'Update NPR Story', 'npr-content-distribution-service' ),
				__( 'Update NPR Story', 'npr-content-distribution-service' )
			);
		}
	}

	/**
	 * @throws Exception
	 */
	public function bulk_action_update_action(): void {
		// 1. get the action
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action = $wp_list_table->current_action();

		switch ( $action ) {
			// 3. Perform the action
			case 'updateNprStory':
				$post_ids = [];
				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if ( isset( $_REQUEST['post'] ) ) {
					$post_ids = array_map( 'intval', $_REQUEST['post'] );
				}
				foreach( $post_ids as $post_id ) {
					$api_id = get_post_meta( $post_id, NPR_STORY_ID_META_KEY, TRUE );

					// don't run API queries for posts that have no ID
					if ( !empty( $api_id ) ) {
						$api = new NPR_CDS_WP();
						$params = [ 'id' => $api_id ];
						$api->request( $params );
						$api->parse();
						if ( empty( $api->message ) ) {
							npr_cds_error_log( 'updating story for CDS ID = ' . $api_id );
							$api->update_posts_from_stories();
						}
					}
				}
				break;
			default:
		}
	}
}

new NPR_CDS;
