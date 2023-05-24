<?php
/**
 * Plugin Name: NPR Content Distribution Service
 * Description: A collection of tools for reusing content from NPR.org, now maintained and updated by NPR member station developers
 * Version: 1.0
 * Author: Open Public Media
 * License: GPLv2
*/
/*
	Copyright 2022 Open Public Media

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

const NPR_STORY_ID_META_KEY = 'npr_story_id';
const NPR_HTML_LINK_META_KEY = 'npr_html_link';
const NPR_BYLINE_LINK_META_KEY = 'npr_byline_link';
const NPR_MULTI_BYLINE_META_KEY = 'npr_multi_byline';
const NPR_AUDIO_META_KEY = 'npr_audio';
const NPR_PUB_DATE_META_KEY = 'npr_pub_date';
const NPR_STORY_DATE_MEATA_KEY = 'npr_story_date';
const NPR_LAST_MODIFIED_DATE_KEY = 'npr_last_modified_date';
const NPR_RETRIEVED_STORY_META_KEY = 'npr_retrieved_story';
const NPR_IMAGE_CAPTION_META_KEY = 'npr_image_caption';
const NPR_STORY_HAS_VIDEO_META_KEY = 'npr_has_video';
const NPR_PUSH_STORY_ERROR = 'npr_push_story_error';
const NPR_MAX_QUERIES = 10;
const NPR_POST_TYPE = 'npr_story_post';

define( 'NPR_STORY_CONTENT_META_KEY', get_option( 'npr_cds_mapping_body', 'npr_story_content' ) );
define( 'NPR_BYLINE_META_KEY', get_option( 'npr_cds_mapping_media_credit', 'npr_byline' ) );
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

function npr_cds_activate(): void {
	$cron_interval = get_option( 'dp_npr_query_multi_cron_interval', 60 );
	update_option( 'npr_cds_query_multi_cron_interval', $cron_interval );
	if ( !wp_next_scheduled( 'npr_cds_hourly_cron' ) ) {
		npr_cds_error_log( 'turning on cron event for NPR CDS plugin' );
		wp_schedule_event( time(), 'hourly', 'npr_cds_hourly_cron' );
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
			$profileIds = [ 'story', 'renderable', 'publishable' ];
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
							$sorting['sort'] = 'publistDateTime:asc';
						} elseif ( $output['sort'] == 'dateDesc' ) {
							$sorting['sort'] = 'publistDateTime:desc';
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
						if ( in_array( 'audio', $ra_exp ) || strpos( $profile, '15' ) !== false ) {
							$profileIds[] = 'has-audio';
						}
					}
				}
			}
			$filters['profileIds'] = implode( ',', $profileIds );

			$new_query = [
				'filters' => http_build_query( $filters ),
				'sorting' => http_build_query( $sorting ),
				'publish' => get_option( 'ds_npr_query_publish_' . $i ),
				'category' => get_option( 'ds_npr_query_category_' . $i ),
				'tags' => get_option( 'ds_npr_query_tags_' . $i )
			];
			update_option( 'npr_cds_query_' . $i, $new_query );
		}
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
		update_option( 'dp_npr_query_run_multi', $run_multi );
	}
	$featured = get_option( 'dp_npr_query_use_featured' );
	if ( !empty( $featured ) ) {
		update_option( 'npr_cds_query_use_featured', $featured );
	}
	$custom_map = get_option( 'ds_npr_push_use_custom_map' );
	if ( !empty( $custom_map ) ) {
		update_option( 'npr_cds_push_use_custom_map', $custom_map );

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
	$num = get_option( 'npr_cds_num' );
	for ( $i = 0; $i < $num; $i++ ) {
		delete_option( 'npr_cds_query_' . $i );
	}
	delete_option( 'npr_cds_num' );
	delete_option( 'npr_cds_push_url' );
}


function npr_cds_show_message( $message, $errormsg = false ): void {
	if ( $errormsg ) {
		echo '<div id="message" class="error">';
	} else {
		echo '<div id="message" class="updated fade">';
	}
	echo npr_cds_esc_html( "<p><strong>$message</strong></p></div>" );
}

add_action( 'init', 'npr_cds_create_post_type' );

function npr_cds_create_post_type(): void {
	register_post_type( NPR_POST_TYPE, [
		'labels' => [
			'name' => __( 'NPR Stories' ),
			'singular_name' => __( 'NPR Story' ),
			'menu_name' => __( 'NPR Stories' ),
			'edit_item' => __( 'Edit NPR Story' ),
			'view_item' => __( 'View NPR Story' ),
			'search_items' => __( 'Search NPR Stories' ),
			'not_found' => __( 'NPR Story Not Found' ),
			'not_found_in_trash' => __( 'NPR Story not found in trash' )
		],
		'description' => 'Stories pulled from NPR or member stations via the NPR CDS',
		'public' => true,
		'menu_position' => 5,
		'menu_icon' => 'dashicons-admin-post',
		'has_archive' => true,
		'rewrite' => [
			'slug' => __( 'npr-story' ),
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
				$push_post_type, 'side'
			);
			add_action( 'admin_enqueue_scripts', 'npr_cds_publish_meta_box_assets' );
		} else {
			global $post;
			add_meta_box(
				'npr_cds_document_meta',
				'NPR CDS',
				'npr_cds_publish_meta_box_prompt',
				$push_post_type, 'side'
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
	return html_entity_decode( esc_html( $string ), ENT_QUOTES );
}

function npr_cds_add_header_meta(): void {
	global $wp_query;
	if ( !is_home() && !is_404() &&
		( get_post_type() === get_option( 'npr_cds_pull_post_type' ) || get_post_type() === get_option( 'npr_cds_push_post_type' ) )
	) {
		$id = $wp_query->queried_object_id;
		$npr_story_id = get_post_meta( $id, 'npr_story_id', 1 );
		if ( !empty( $npr_story_id ) ) {
			$has_audio = ( preg_match( '/\[audio/', $wp_query->post->post_content ) ? 1 : 0 );
			$word_count = str_word_count( strip_tags( $wp_query->post->post_content ) );
			$npr_retrieved_story = get_post_meta( $id, 'npr_retrieved_story', 1 );
			if ( $npr_retrieved_story == 1 ) {
				$byline = get_post_meta( $id, 'npr_byline', 1 );
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
			} ?>
		<meta name="datePublished" content="<?php echo get_the_date( 'c', $id ); ?>" />
		<meta name="story_id" content="<?php echo $npr_story_id; ?>" />
		<meta name="has_audio" content="<?php echo $has_audio; ?>" />
		<meta name="org_id" content="<?php echo get_option( 'ds_npr_api_org_id' ); ?>" />
		<meta name="category" content="<?php echo $primary_cat; ?>" />
		<meta name="author" content="<?php echo $byline; ?>" />
		<meta name="programs" content="none" />
		<meta name="wordCount" content="<?php echo $word_count; ?>" />
		<meta name="keywords" content="<?php echo implode( ',', $keywords ); ?>" />
<?php
		}
	}
}
add_action( 'wp_head', 'npr_cds_add_header_meta', 100 );