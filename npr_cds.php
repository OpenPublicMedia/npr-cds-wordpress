<?php
/**
 * Plugin Name: NPR Content Distribution Service
 * Plugin URI: https://github.com/OpenPublicMedia/npr-cds-wordpress
 * Description: A collection of tools for reusing content from NPR.org, now maintained and updated by NPR member station developers
 * Version: 1.3.5
 * Requires at least: 4.0
 * Requires PHP: 8.0
 * Author: Open Public Media
 * Author URI: https://github.com/OpenPublicMedia/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: npr-content-distribution-service
*/
/*
	Copyright 2024 Open Public Media

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) exit;
const NPR_STORY_ID_META_KEY = 'npr_story_id';
const NPR_HTML_LINK_META_KEY = 'npr_html_link';
const NPR_BYLINE_LINK_META_KEY = 'npr_byline_link';
const NPR_MULTI_BYLINE_META_KEY = 'npr_multi_byline';
const NPR_AUDIO_META_KEY = 'npr_audio';
const NPR_PUB_DATE_META_KEY = 'npr_pub_date';
const NPR_STORY_DATE_META_KEY = 'npr_story_date';
const NPR_LAST_MODIFIED_DATE_KEY = 'npr_last_modified_date';
const NPR_RETRIEVED_STORY_META_KEY = 'npr_retrieved_story';
const NPR_IMAGE_CAPTION_META_KEY = 'npr_image_caption';
const NPR_STORY_HAS_VIDEO_META_KEY = 'npr_has_video';
const NPR_HAS_VIDEO_STREAMING_META_KEY = 'npr_has_video_streaming';
const NPR_HAS_SLIDESHOW_META_KEY = 'npr_has_slideshow';
const NPR_STORY_HAS_LAYOUT_META_KEY = 'npr_has_layout';
const NPR_PUSH_STORY_ERROR = 'npr_push_story_error';
const NPR_MAX_QUERIES = 10;
const NPR_POST_TYPE = 'npr_story_post';

define( 'NPR_STORY_TITLE_META_KEY', get_option( 'npr_cds_mapping_title', 'npr_story_title' ) );
define( 'NPR_STORY_CONTENT_META_KEY', get_option( 'npr_cds_mapping_body', 'npr_story_content' ) );
define( 'NPR_BYLINE_META_KEY', get_option( 'npr_cds_mapping_byline', 'npr_byline' ) );
define( 'NPR_IMAGE_CREDIT_META_KEY', get_option( 'npr_cds_mapping_media_credit', 'npr_image_credit' ) );
define( 'NPR_IMAGE_AGENCY_META_KEY', get_option( 'npr_cds_mapping_media_agency', 'npr_image_agency' ) );
define( 'NPR_CDS_PULL_URL', get_option( 'npr_cds_pull_url', 'https://content.api.npr.org' ) );
define( 'NPR_CDS_PLUGIN_URL', plugin_dir_url(__FILE__) );

// Load files
define( 'NPR_CDS_PLUGIN_DIR', plugin_dir_path(__FILE__) );
require_once( NPR_CDS_PLUGIN_DIR . 'settings.php' );
require_once( NPR_CDS_PLUGIN_DIR . 'classes/NPR_CDS_WP.php' );
require_once( NPR_CDS_PLUGIN_DIR . 'get_stories.php' );
require_once( NPR_CDS_PLUGIN_DIR . 'meta_boxes.php' );
require_once( NPR_CDS_PLUGIN_DIR . 'push_story.php' );

//add the cron to get stories
register_activation_hook( NPR_CDS_PLUGIN_DIR . 'npr_cds.php', 'npr_cds_activation' );
add_action( 'npr_cds_hourly_cron', [ 'NPR_CDS', 'cron_pull' ] );
register_deactivation_hook( NPR_CDS_PLUGIN_DIR . 'npr_cds.php', 'npr_cds_deactivation' );

function npr_cds_activation(): void {
	global $wpdb;
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		// check if it is a network activation - if so, run the activation function for each blog id
		$old_blog = $wpdb->blogid;
		// Get all blog ids
		$blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM %s", $wpdb->blogs ) );
		foreach ( $blogids as $blog_id ) {
			switch_to_blog( $blog_id );
			npr_cds_activate();
		}
		switch_to_blog( $old_blog );
	} else {
		npr_cds_activate();
	}
}

/**
 * Add cron intervals
 */
function npr_cds_add_cron_interval( $schedules ): array {
	$ds_interval = get_option( 'npr_cds_query_multi_cron_interval' );
	//if for some reason we don't get a number in the option, use 60 minutes as the default.
	if ( !is_numeric( $ds_interval ) || $ds_interval < 1 ) {
		$ds_interval = 60;
		update_option( 'npr_cds_query_multi_cron_interval', $ds_interval );
	}
	$new_interval = $ds_interval * 60;
	$schedules['npr_cds_interval'] = [
		'interval' => $new_interval,
		'display' => sprintf(
			__( 'NPR CDS Cron, run Once every %s minutes', 'npr-content-distribution-service' ),
			esc_html( $ds_interval )
		)
	];
	return $schedules;
}
add_filter( 'cron_schedules', 'npr_cds_add_cron_interval', 10, 2 );

function npr_cds_activate(): void {
	$cron_interval = get_option( 'dp_npr_query_multi_cron_interval', 60 );
	update_option( 'npr_cds_query_multi_cron_interval', $cron_interval );
	if ( !wp_next_scheduled( 'npr_cds_hourly_cron' ) ) {
		npr_cds_error_log( 'turning on cron event for NPR CDS plugin' );
		wp_schedule_event( time(), 'npr_cds_interval', 'npr_cds_hourly_cron' );
	}

	// Check for number of cron queries from old plugin, migrate to new
	$num_old = get_option( 'ds_npr_num' );
	$num = get_option( 'npr_cds_num', 5 );

	if ( !empty( $num_old ) ) {
		$num = $num_old;
	}
	update_option( 'npr_cds_num', $num );

	// Migrate queries
	for ( $i = 0; $i < $num; $i++ ) {
		$query_old = get_option( 'ds_npr_query_' . $i );
		if ( !empty( $query_old ) ) {
			$profile = get_option( 'ds_npr_query_profileTypeID_' . $i );
			$filters = [];
			$sorting = [];
			$profileIds = [ 'story', 'renderable', 'publishable', 'buildout' ];
			$parse = parse_url( urldecode( $query_old ), PHP_URL_QUERY );
			if ( !empty( $parse ) ) {
				parse_str( $parse, $output );
				if ( !empty( $output ) ) {
					if ( !empty( $output['id'] ) ) {
						$filters['collectionIds'] = $output['id'];
					}
					if ( !empty( $output['orgId'] ) ) {
						if ( preg_match( '/^[0-9]{1,4}$/', $output['orgId'] ) ) {
							$filters['ownerHrefs'] = 'https://organization.api.npr.org/v4/services/s' . $output['orgId'];
						} elseif ( preg_match( '/^s[0-9]{1,4}$/', $output['orgId'] ) ) {
							$filters['ownerHrefs'] = 'https://organization.api.npr.org/v4/services/' . $output['orgId'];
						}
					}
					if ( !empty( $output['sort'] ) ) {
						if ( $output['sort'] == 'dateAsc' ) {
							$sorting['sort'] = 'publishDateTime:asc';
						} elseif ( $output['sort'] == 'dateDesc' ) {
							$sorting['sort'] = 'publishDateTime:desc';
						} elseif ( $output['sort'] == 'editorial' ) {
							$sorting['sort'] = 'editorial';
						}
					}
					if ( !empty( $output['startNum'] ) ) {
						$sorting['offset'] = $output['startNum'];
					}
					if ( !empty( $output['numResults'] ) ) {
						$sorting['limit'] = $output['numResults'];
					}
					if ( !empty( $output['requiredAssets'] ) ) {
						$ra_exp = explode( ',', $output['requiredAssets'] );
						if ( in_array( 'image', $ra_exp ) ) {
							$profileIds[] = 'has-images';
						}
						if ( in_array( 'audio', $ra_exp ) || str_contains( $profile, '15' ) ) {
							$profileIds[] = 'has-audio';
						}
					}
				}
			}
			$filters['profileIds'] = implode( '&profileIds=', $profileIds );

			$new_query = [
				'filters' => http_build_query( $filters ),
				'sorting' => http_build_query( $sorting ),
				'publish' => get_option( 'ds_npr_query_publish_' . $i ),
				'category' => get_option( 'ds_npr_query_category_' . $i ),
				'tags' => get_option( 'ds_npr_query_tags_' . $i )
			];
			update_option( 'npr_cds_query_' . $i, $new_query );
		}
		delete_option( 'ds_npr_query_' . $i );
		delete_option( 'ds_npr_query_profileTypeID_' . $i );
		delete_option( 'ds_npr_query_publish_' . $i );
		delete_option( 'ds_npr_query_category_' . $i );
		delete_option( 'ds_npr_query_tags_' . $i );
	}

	$pull_post = get_option( 'ds_npr_pull_post_type' );
	if ( !empty( $pull_post ) ) {
		update_option( 'npr_cds_pull_post_type', $pull_post );
	}
	$push_post = get_option( 'ds_npr_push_post_type' );
	if ( !empty( $push_post ) ) {
		update_option( 'npr_cds_push_post_type', $push_post );
	}
	$org_id = get_option( 'ds_npr_api_org_id' );
	if ( !empty( $org_id ) ) {
		if ( preg_match( '/^[0-9]{1,4}$/', $org_id ) ) {
			update_option( 'npr_cds_org_id', 's' . $org_id );
		} elseif ( preg_match( '/^s[0-9]{1,4}$/', $org_id ) ) {
			update_option( 'npr_cds_org_id', $org_id );
		}
	}
	$run_multi = get_option( 'dp_npr_query_run_multi' );
	if ( !empty( $run_multi ) ) {
		update_option( 'npr_cds_query_run_multi', $run_multi );
	}
	$featured = get_option( 'dp_npr_query_use_featured' );
	if ( !empty( $featured ) ) {
		update_option( 'npr_cds_query_use_featured', $featured );
	}
	$custom_map = get_option( 'ds_npr_push_use_custom_map' );
	if ( !empty( $custom_map ) ) {
		update_option( 'npr_cds_push_use_custom_map', $custom_map );
		get_option( 'ds_npr_push_use_custom_map' );
		$custom_map_title = get_option( 'ds_npr_api_mapping_title' );
		if ( !empty( $custom_map_title ) ) {
			update_option( 'npr_cds_mapping_title', $custom_map_title );
		}
		$custom_map_body = get_option( 'ds_npr_api_mapping_body' );
		if ( !empty( $custom_map_body ) ) {
			update_option( 'npr_cds_mapping_body', $custom_map_body );
		}
		$custom_map_byline = get_option( 'ds_npr_api_mapping_byline' );
		if ( !empty( $custom_map_byline ) ) {
			update_option( 'npr_cds_mapping_byline', $custom_map_byline );
		}
		$custom_map_media_credit = get_option( 'ds_npr_api_mapping_media_credit' );
		if ( !empty( $custom_map_media_credit ) ) {
			update_option( 'npr_cds_mapping_media_credit', $custom_map_media_credit );
		}
		$custom_map_media_agency = get_option( 'ds_npr_api_mapping_media_agency' );
		if ( !empty( $custom_map_media_agency ) ) {
			update_option( 'npr_cds_mapping_media_agency', $custom_map_media_agency );
		}
	}

	$def_url = 'https://content.api.npr.org';
	$pull_url = get_option( 'npr_cds_pull_url' );
	if ( empty( $pull_url ) ) {
		update_option( 'npr_cds_pull_url', $def_url );
	}
	delete_option( 'dp_npr_query_use_layout' );
	delete_option( 'ds_npr_story_default_permission' );
	delete_option( 'ds_npr_api_key' );
	delete_option( 'ds_npr_pull_post_type' );
	delete_option( 'dp_npr_query_multi_cron_interval' );
	delete_option( 'ds_npr_num' );
	delete_option( 'ds_npr_push_post_type' );
	delete_option( 'ds_npr_api_org_id' );
	delete_option( 'dp_npr_query_run_multi' );
	delete_option( 'dp_npr_query_use_featured' );
	delete_option( 'ds_npr_api_mapping_title' );
	delete_option( 'ds_npr_api_mapping_body' );
	delete_option( 'ds_npr_api_mapping_byline' );
	delete_option( 'ds_npr_api_mapping_media_credit' );
	delete_option( 'ds_npr_api_mapping_media_agency' );
	delete_option( 'ds_npr_api_pull_url' );
	delete_option( 'ds_npr_api_push_url' );
	delete_option( 'ds_npr_api_get_multi_settings' );
	delete_option( 'dp_npr_push_use_custom_map' );
}

function npr_cds_deactivation(): void {
	global $wpdb;
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		// check if it is a network activation - if so, run the activation function for each blog id
		$old_blog = $wpdb->blogid;
		// Get all blog ids
		$blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM %s", $wpdb->blogs ) );
		foreach ( $blogids as $blog_id ) {
			switch_to_blog( $blog_id );
			npr_cds_deactivate();
		}
		switch_to_blog( $old_blog );
	} else {
		npr_cds_deactivate();
	}
}

function npr_cds_deactivate(): void {
	wp_clear_scheduled_hook( 'npr_cds_hourly_cron' );
	$options = [
		'npr_cds_query_multi_cron_interval',
		'npr_cds_pull_post_type',
		'npr_cds_push_post_type',
		'npr_cds_org_id',
		'npr_cds_pull_url',
		'npr_cds_token',
		'npr_cds_prefix',
		'npr_cds_image_width',
		'npr_cds_image_quality',
		'npr_cds_image_format',
		'npr_cds_push_use_custom_map',
		'npr_cds_mapping_title',
		'npr_cds_mapping_body',
		'npr_cds_mapping_byline',
		'npr_cds_mapping_media_credit',
		'npr_cds_mapping_media_agency',
		'npr_cds_num',
		'npr_cds_push_url',
		'npr_cds_push_default',
		'npr_cds_query_0',
		'npr_cds_import_tags',
		'npr_cds_display_attribution',
		'npr_cds_query_use_featured',
		'npr_cds_import_tags',
		'npr_cds_skip_promo_cards',
		'npr_cds_num',
		'npr_cds_query_run_multi'
	];
	$query_num = get_option( 'npr_cds_num' );
	for ( $i = $query_num; $i <= $query_num; $i++ ) {
		$options[] = 'npr_cds_query_' . $i;
	}
	$old_options = get_options( $options );
	update_option( 'npr_cds_old_options', $old_options, false );
	foreach ( $options as $option ) {
		delete_option( $option );
	}
}


function npr_cds_show_message( $message, $errormsg = false ): void {
	if ( is_admin() ) {
		if ( $errormsg ) {
			echo '<div id="message" class="error">';
		} else {
			echo '<div id="message" class="updated fade">';
		}
		echo npr_cds_esc_html( "<p><strong>$message</strong></p></div>" );
	}
}

add_action( 'init', 'npr_cds_create_post_type' );

function npr_cds_create_post_type(): void {
	register_post_type( NPR_POST_TYPE, [
		'labels' => [
			'name' => __( 'NPR Stories', 'npr-content-distribution-service' ),
			'singular_name' => __( 'NPR Story', 'npr-content-distribution-service' ),
			'menu_name' => __( 'NPR Stories', 'npr-content-distribution-service' ),
			'add_new' => __( 'Add New NPR Story' ),
			'add_new_item' => __( 'Add New NPR Story' ),
			'edit_item' => __( 'Edit NPR Story', 'npr-content-distribution-service' ),
			'view_item' => __( 'View NPR Story', 'npr-content-distribution-service' ),
			'search_items' => __( 'Search NPR Stories', 'npr-content-distribution-service' ),
			'not_found' => __( 'NPR Story Not Found', 'npr-content-distribution-service' ),
			'not_found_in_trash' => __( 'NPR Story not found in trash', 'npr-content-distribution-service' ),
			'all_items' => __( 'All NPR Stories' ),
			'archives' => __( 'NPR Story Archives' ),
		],
		'description' => __('Stories pulled from NPR or member stations via the NPR CDS', 'npr-content-distribution-service' ),
		'public' => true,
		'show_in_rest' => true,
		'menu_position' => 5,
		'menu_icon' => 'dashicons-admin-post',
		'has_archive' => true,
		'rewrite' => [
			'slug' => __( 'npr-story', 'npr-content-distribution-service' ),
			'with_front' => false,
			'feeds' => true,
			'pages' => true
		],
		'supports' => [ 'title', 'editor', 'thumbnail', 'author', 'excerpt', 'custom-fields' ],
		'taxonomies' => [ 'post_tag', 'category' ]
	]);
}

/**
 * Register the meta box and enqueue its scripts
 *
 * If the API Push URL option is not set, instead register a prompt to set it.
 *
 * @link https://github.com/npr/nprapi-wordpress/issues/51
 */
function npr_cds_add_meta_boxes(): void {
	$screen = get_current_screen();
	$push_post_type = get_option( 'npr_cds_push_post_type' ) ?: 'post';
	$push_url = get_option( 'npr_cds_push_url' );
	if ( $screen->id == $push_post_type ) {
		if ( !empty( $push_url ) ) {
			global $post;
			add_meta_box(
				'npr_cds_document_meta',
				'NPR CDS',
				'npr_cds_publish_meta_box',
				$push_post_type,
				'side',
				'core'
			);
			add_action( 'admin_enqueue_scripts', 'npr_cds_publish_meta_box_assets' );
		} else {
			global $post;
			add_meta_box(
				'npr_cds_document_meta',
				'NPR CDS',
				'npr_cds_publish_meta_box_prompt',
				$push_post_type,
				'side',
				'core'
			);
		}
	}
}
add_action( 'add_meta_boxes', 'npr_cds_add_meta_boxes' );

/**
 * Function to only enable error_logging if WP_DEBUG is true
 *
 * This should only be used for error_log in development environments
 * If the thing being logged is a fatal error, use error_log so it will always be logged
 */
function npr_cds_error_log( $thing ): void {
	if ( WP_DEBUG ) {
		error_log( $thing ); //debug use
	}
}

/**
 * Function to help with escaping HTML, especially for admin screens
 */
function npr_cds_esc_html( $string ): string {
	return html_entity_decode( sprintf( esc_html__( '%s', 'npr-content-distribution-service' ), esc_html( $string ) ), ENT_QUOTES );
}

function npr_cds_add_header_meta(): void {
	global $wp_query;
	if ( !is_home() && !is_404() &&
		( get_post_type() === get_option( 'npr_cds_pull_post_type' ) || get_post_type() === get_option( 'npr_cds_push_post_type' ) )
	) {
		$id = $wp_query->queried_object_id;
		$npr_story_id = get_post_meta( $id, NPR_STORY_ID_META_KEY, 1 );
		if ( !empty( $npr_story_id ) ) {
			$has_audio = ( preg_match( '/\[audio/', $wp_query->post->post_content ) ? 1 : 0 );
			$word_count = str_word_count( strip_tags( $wp_query->post->post_content ) );
			$npr_retrieved_story = get_post_meta( $id, NPR_RETRIEVED_STORY_META_KEY, 1 );
			if ( $npr_retrieved_story == 1 ) {
				$byline = get_post_meta( $id, NPR_BYLINE_META_KEY, 1 );
				if ( function_exists( 'rel_canonical' ) ) {
					remove_action( 'wp_head', 'rel_canonical' );
				}
				$original_url = get_post_meta( $id, NPR_HTML_LINK_META_KEY, 1 );
				if ( !has_filter( 'wpseo_canonical' ) ) {
					echo '<link rel="canonical" href="' . esc_url( $original_url ) . '" />' . "\n";
				}
			} elseif ( function_exists( 'get_coauthors' ) ) {
				$byline = coauthors( ', ', ', ', '', '', false );
			} else {
				$byline = get_the_author_meta( 'display_name', $wp_query->post->post_author );
			}
			$head_categories = get_the_category( $id );
			$head_tags = wp_get_post_tags( $id );
			$keywords = [];
			foreach( $head_categories as $hcat ) :
				$keywords[] = $hcat->name;
			endforeach;
			foreach( $head_tags as $htag ) :
				$keywords[] = $htag->name;
			endforeach;
			$primary_cat = get_post_meta( $id, 'epc_primary_category', true );
			if ( empty( $primary_cat ) && !empty( $keywords ) ) {
				$primary_cat = $keywords[0];
			}
			$npr_has_video_streaming = get_post_meta( $id, NPR_HAS_VIDEO_STREAMING_META_KEY, 1 );
			$npr_has_slideshow = get_post_meta( $id, NPR_HAS_SLIDESHOW_META_KEY, 1 );
			if ( $npr_has_video_streaming ) {
				wp_enqueue_script( 'npr-hls-js', NPR_CDS_PLUGIN_URL . 'assets/js/hls.js', [], '1.4.13', [ 'in_footer' => true ] );
				wp_enqueue_style( 'npr_hls-css', NPR_CDS_PLUGIN_URL . 'assets/css/hls.css' );
			}
			if ( $npr_has_slideshow ) {
				wp_enqueue_script( 'npr-splide-js', NPR_CDS_PLUGIN_URL . 'assets/js/splide.min.js', [], '3.6.12', [ 'in_footer' => true ] );
				wp_enqueue_script( 'npr-splide-settings-js', NPR_CDS_PLUGIN_URL . 'assets/js/splide-settings.js', [], '3.6.12', [ 'in_footer' => true ] );
				wp_enqueue_style( 'npr-splide-css', NPR_CDS_PLUGIN_URL . 'assets/css/splide.min.css' );
			}
			?>
		<meta name="datePublished" content="<?php echo esc_attr( get_the_date( 'c', $id ) ); ?>" />
		<meta name="story_id" content="<?php echo esc_attr( $npr_story_id ); ?>" />
		<meta name="has_audio" content="<?php echo esc_attr( $has_audio ); ?>" />
		<meta name="org_id" content="<?php echo esc_attr( get_option( 'npr_cds_org_id' ) ); ?>" />
		<meta name="category" content="<?php echo esc_attr( $primary_cat ); ?>" />
		<meta name="author" content="<?php echo esc_attr( $byline ); ?>" />
		<meta name="programs" content="none" />
		<meta name="wordCount" content="<?php echo esc_attr( $word_count ); ?>" />
		<meta name="keywords" content="<?php echo esc_html( implode( ',', $keywords ) ); ?>" />
<?php
		}
	}
}
add_action( 'wp_head', 'npr_cds_add_header_meta', 9 );

/**
 * Helper function for Yoast users to insert the correct canonical link 
 * credit @santalone 
 */
function npr_cds_filter_yoast_canonical( $canonical ) {
    if (is_singular() && !empty( get_post_meta( get_the_ID(), NPR_HTML_LINK_META_KEY, 1 ) ) ) {
        $canonical = get_post_meta( get_the_ID(), NPR_HTML_LINK_META_KEY, 1 );
    }
    return $canonical;
}
add_filter( 'wpseo_canonical', 'npr_cds_filter_yoast_canonical' );

/* add_action( 'rest_api_init', function() {
	register_rest_route( 'npr-cds/v1', '/notifications', [
		'methods'  => 'POST',
		'callback' => 'npr_cds_notify_webhook',
		'permission_callback' => function( $request ) {
			$authorization = $request->get_header( 'Authorization' );
			$cds_token = "Bearer " . NPR_CDS_WP::get_cds_token();
			if ( empty( $authorization ) || $authorization !== $cds_token ) {
				return false;
			} else {
				return true;
			}
		}
	] );
} );

function npr_cds_notify_webhook( WP_REST_Request $request ): WP_HTTP_Response|WP_REST_Response|WP_Error {
	if ( empty( $request ) ) {
		return new WP_Error( 'rest_api_sad', esc_html__( 'Empty POST request received. You are going to have to be more specific.', 'npr-content-distribution-service' ), [ 'status' => 403 ] );
	}
	if ( empty( $request['type'] ) ) {
		return new WP_Error( 'rest_api_sad', esc_html__( 'NPR CDS: Empty type field. Please try again.', 'npr-content-distribution-service' ), [ 'status' => 403 ] );
	}
	$cds = new NPR_CDS_WP();
	if ( $request['type'] === 'SubscriptionConfirmation' ) {
		$payload = [
			'body' => $request,
			$cds->get_token_options(),
			'method' => 'POST'
		];
		$url = 'https://prod-content-v1.api.nprinfra.org/v1/subscriptions/confirmations';
		$cds_result = wp_remote_post( $url, $payload );
		if ( !is_wp_error( $cds_result ) ) {
			if ( $cds_result['response']['code'] !== 200 ) {
				npr_cds_error_log( "NPR CDS Notification Subscription Failed: " . json_decode( wp_remote_retrieve_body( $cds_result ) ) );
				return new WP_Error( 'rest_api_sad', esc_html__( 'CDS Notification Subscription Failed', 'npr-content-distribution-service' ), [ 'status' => 500 ] );
			} else {
				return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( 'CDS Notification Subscription Approved', 'npr-content-distribution-service' ), 'data' => [ 'status' => 200 ] ] );
			}
		} else {
			npr_cds_error_log( "NPR CDS Notification Subscription Failed: " . json_decode( wp_remote_retrieve_body( $cds_result ) ) );
			return new WP_Error( 'rest_api_sad', esc_html__( 'CDS Notification Subscription Failed', 'npr-content-distribution-service' ), [ 'status' => 500 ] );
		}
	} else {
		if ( empty( $request['documentId'] ) || !preg_match( '/[a-z0-9\-]+/', $request['documentId'] ) ) {
			return new WP_Error( 'rest_api_sad', esc_html__( 'Invalid document ID format. Please try again.', 'npr-content-distribution-service' ), [ 'status' => 403 ] );
		}
		if ( $request['type'] === 'document.created' ) {
			return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( 'NPR CDS Document Created', 'npr-content-distribution-service' ), 'data' => [ "documentId" => $request['documentId'], "timestamp" => date('c') ] ] );
		} elseif ( $request['type'] === 'document.updated' ) {
			$exists = new WP_Query([
				'meta_key' => NPR_STORY_ID_META_KEY,
				'meta_value' => $request['documentId'],
				'post_type' => 'any',
				'post_status' => 'any',
				'no_found_rows' => true
			]);
			if ( $exists->have_posts() ) {
				$params = [ 'id' => $request['documentId'] ];
				$cds->request( $params );
				$cds->parse();
				if ( empty( $cds->message ) ) {
					try {
						$cds->update_posts_from_stories();
					} catch ( Exception $e ) {
						npr_cds_error_log( 'There was a problem updating ingested CDS stories: ' . print_r( $e, true ) );
						return new WP_Error( 'rest_api_sad', esc_html__( 'There was a problem updating ingested CDS stories: ' . print_r( $e, true ), 'npr-content-distribution-service' ), [ 'status' => 500 ] );
					}
				}
				return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( 'NPR CDS Document Updated', 'npr-content-distribution-service' ), 'data' => [ "documentId" => $request['documentId'], "localDocumentId" => $exists->post->ID, "localDocumentPermalink" => get_permalink( $exists->post->ID ), "timestamp" => date('c') ] ] );
			} else {
				return new WP_Error( 'rest_api_sad', esc_html__( 'The referenced CDS document does not exist on this server.', 'npr-content-distribution-service' ), [ 'status' => 404 ] );
			}
		} elseif ( $request[ 'type' ] === 'document.deleted' ) {
			$exists = new WP_Query([
				'meta_key' => NPR_STORY_ID_META_KEY,
				'meta_value' => $request[ 'documentId' ],
				'post_type' => 'any',
				'post_status' => 'any',
				'no_found_rows' => true
			]);
			if ( $exists->have_posts() ) {
				$existing = $exists->post;
				wp_delete_post( $existing->ID );
				return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( 'NPR CDS Document Deleted', 'npr-content-distribution-service' ), 'data' => [ "documentId" => $request['documentId'], "localWpId" => $exists->post->ID, "timestamp" => date('c') ] ] );
			} else {
				return new WP_Error( 'rest_api_sad', esc_html__( 'The requested CDS document does not exist on this server: ' . $request['documentId'], 'npr-content-distribution-service' ), [ 'status' => 404 ] );
			}
		}
	}
	return new WP_Error( 'rest_api_sad', esc_html__( 'NPR CDS: Unsupported type. Please try again.', 'npr-content-distribution-service' ), [ 'status' => 500 ] );
} */
