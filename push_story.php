<?php
/**
 * Functions relating to pushing content to the NPR CDS
 */

if ( ! defined( 'ABSPATH' ) ) exit;
require_once ( NPR_CDS_PLUGIN_DIR . 'classes/NPR_CDS_WP.php' );

/**
 * push the contents and fields for a post to the NPR CDS
 *
 * Limited to users that can publish posts
 *
 * @param Int $post_ID
 * @param WP_Post $post
 */
function npr_cds_push( int $post_ID, WP_Post $post ): void {
	// Don't push stories to the NPR CDS if they were originally pulled from the NPR CDS
	$retrieved = get_post_meta( $post_ID, NPR_RETRIEVED_STORY_META_KEY, true );
	if ( !empty( $retrieved ) ) {
		npr_cds_error_log( 'Not pushing the story with post_ID ' . $post_ID . ' to the NPR CDS because it was retrieved from the CDS' );
		return;
	}
	$push_post_type = npr_cds_get_push_post_type( $post );

	//if the push url isn't set, don't even try to push.
	$push_url = get_option( 'npr_cds_push_url' );

	if ( !empty ( $push_url ) ) {
		// For now, only submit the sort of post that is the push post type, and then only if published
		if ( $post->post_type !== $push_post_type || $post->post_status !== 'publish' ) {
			return;
		}
		$send_to_cds = get_post_meta( $post->ID, '_send_to_nprone', true );
		if ( $send_to_cds !== '1' ) {
			return;
		}
	}
	if ( !current_user_can( 'publish_posts' ) ) {
		npr_cds_error_log( 'You do not have permission to publish posts, and therefore you do not have permission to push posts to the NPR CDS.' );
		return;
	}


	/*
	 * If there's a custom mapping for the post content,
	 * use that content instead of the post's post_content
	 */
	$content = $post->post_content;
	$use_custom = get_option( 'npr_cds_push_use_custom_map' );
	$body_field = 'Body';
	if ( $use_custom ) {
		// Get the list of post meta keys available for this post.
		$post_metas = get_post_custom_keys( $post->ID );

		$custom_content_meta = npr_cds_get_mapping_body( $post );
		$body_field = $custom_content_meta;
		if ( !empty( $custom_content_meta ) && $custom_content_meta !== '#NONE#' && in_array( $custom_content_meta, $post_metas, true ) ) {
			$content = get_post_meta( $post->ID, $custom_content_meta, true );
		}
	}

	// Abort pushing to NPR if the post has no content
	if ( empty( $content ) ) {
		if ( $body_field === '#NONE#' ) {
			$body_field = 'Body content';
		}
		update_post_meta( $post_ID, NPR_PUSH_STORY_ERROR, esc_html( $body_field ) . ' is required for a post to be pushed to the NPR CDS.' );
		return;
	} else {
		delete_post_meta( $post_ID, NPR_PUSH_STORY_ERROR, esc_html( $body_field ) . ' is required for a post to be pushed to the NPR CDS.' );
	}

	$api = new NPR_CDS_WP();
	$api->send_request( $api->create_json( $post ), $post_ID );
}

/**
 * Inform the NPR CDS that a post needs to be deleted.
 *
 * Limited to users that can delete other users' posts
 *
 * @param int $post_ID
 */
function npr_cds_delete( int $post_ID ): void {
	if ( !current_user_can( 'delete_others_posts' ) ) {
		wp_die(
			__('You do not have permission to delete posts in the NPR CDS. Users that can delete other users\' posts have that ability: administrators and editors.', ),
			__('NPR CDS Error', ),
			403
		);
	}
	$push_post_type = npr_cds_get_push_post_type( get_post( $post_ID ) );

	$api_id = get_post_meta( $post_ID, NPR_STORY_ID_META_KEY, true );

	$post = get_post( $post_ID );
	//if the push url isn't set, don't even try to delete.
	$push_url = get_option( 'npr_cds_push_url' );
	if ( $post->post_type == $push_post_type && !empty( $push_url ) && !empty( $api_id ) ) {
		$api = new NPR_CDS_WP();
		$retrieved = get_post_meta( $post_ID, NPR_RETRIEVED_STORY_META_KEY, true );

		if ( empty( $retrieved ) ) {
			npr_cds_error_log( 'Pushing delete action to the NPR CDS for the story with post_ID ' . $post_ID );
			$api->send_delete( $api_id );
		}
	}
}

/**
 * Register npr_cds_npr_push and npr_cds_npr_delete on appropriate hooks
 * this is where the magic happens
 */
add_action( 'save_post', 'npr_cds_push', 100, 2 );
add_action( 'trash_post', 'npr_cds_delete', 100, 2 );
//this may need to check version and use 'wp_trash_post'
add_action( 'wp_trash_post', 'npr_cds_delete', 100, 2 );

/**
 * Query the database for any meta fields for a post type, then store that in a WP transient/cache for a day.
 * I don't see the need for this cache to be any shorter, there's not a lot of adding of meta keys happening.
 * To clear this cache, after adding meta keys, you need to run delete_transient( 'npr_cds_' . $post_type . '_meta_keys' )
 *
 * @param string $post_type
 *
 * @return array
 */
function npr_cds_push_meta_keys( string $post_type = 'post' ): array {
	global $wpdb;
	//AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)'
	$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT( $wpdb->postmeta.meta_key ) FROM $wpdb->posts LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id WHERE $wpdb->posts.post_type = %s AND $wpdb->postmeta.meta_key != '' AND $wpdb->postmeta.meta_key NOT LIKE %s AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'", $post_type, '_oembed_%' ) );
	if ( $keys ) natcasesort( $keys );

	set_transient( 'npr_cds_' . $post_type . '_meta_keys', $keys, 60*60*24 ); # 1 Day Expiration
	return $keys;
}

/**
 * get the meta keys for a post type, they could be stored in a cache.
 *
 * @param string $post_type default is 'post'
 *
 * @return array
 */
function npr_cds_get_post_meta_keys( string $post_type = 'post' ): array {
	$cache = get_transient( 'npr_cds_' .  $post_type . '_meta_keys' );
	if ( !empty( $cache ) ) {
		$meta_keys = $cache;
	} else {
		$meta_keys = npr_cds_push_meta_keys( $post_type );
	}
	return $meta_keys;
}

function npr_cds_bulk_action_push_dropdown(): void {
	$push_post_type = npr_cds_get_push_post_type();

	$push_url = get_option( 'npr_cds_push_url' );
	global $post_type;

	//make sure we have the right post_type and that the push URL is filled in, so we know we want to push this post-type
	if ( $post_type == $push_post_type && !empty( $push_url ) ) {
		printf(
			'<script>jQuery(document).ready(function($) {$("<option>").val("pushNprStory").text("%s").appendTo("select[name=\'action\']");$("<option>").val("pushNprStory").text("%s").appendTo("select[name=\'action2\']");});</script>',
			__( 'Push Story to NPR', 'npr-content-distribution-service' ),
			__( 'Push Story to NPR', 'npr-content-distribution-service' )
		);
	}
}
add_action( 'admin_print_footer_scripts', 'npr_cds_bulk_action_push_dropdown' );

//do the new bulk action
add_action( 'load-edit.php', 'npr_cds_bulk_action_push_action' );

function npr_cds_bulk_action_push_action(): void {
	// 1. get the action
	$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
	$action = $wp_list_table->current_action();
	switch( $action ) {
		// 3. Perform the action
		case 'pushNprStory':

			// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
			if ( isset( $_REQUEST['post'] ) ) {
				$post_ids = array_map( 'intval', $_REQUEST['post'] );
			}

			//only export 20 at a time
			$exported = 0;
			foreach( $post_ids as $post_id ) {
				$api_id = get_post_meta( $post_id, NPR_STORY_ID_META_KEY, TRUE );
				//if this story doesn't have an API ID, push it to the API.
				if ( empty( $api_id ) && $exported < 20 ) {
					$post = get_post( $post_id );
					npr_cds_push( $post_id, $post );
					$exported++;
				}
			}
			break;
		default:
	}
}

/**
 * Save the "send to the API" metadata
 *
 * The meta name here is '_send_to_nprone' for backwards compatibility with plugin versions 1.6 and prior
 *
 * @param Int $post_ID The post ID of the post we're saving
 *
 * @since 1.6 at least
 * @see npr_cds_publish_meta_box
 */
function npr_cds_save_send_to_cds( Int $post_ID ): bool {
	// safety checks
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
	if ( !current_user_can( 'edit_page', $post_ID ) ) return false;
	if ( empty( $post_ID ) ) return false;
	if ( !isset( $_POST['npr_cds_send_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['npr_cds_send_nonce'] ) ), 'npr_cds-' . $post_ID ) ) return false;
	global $post;

	if ( get_post_type( $post ) !== npr_cds_get_push_post_type( $post ) ) return false;
	$value = ( isset( $_POST['send_to_cds'] ) && $_POST['send_to_cds'] == 1 ) ? 1 : 0;

	// see historical note
	update_post_meta( $post_ID, '_send_to_nprone', $value );
	return true;
}
add_action( 'save_post', 'npr_cds_save_send_to_cds', 15 );

/**
 * Save the "Send to NPR One" metadata
 *
 * If the send_to_cds value is falsy, then this should not be saved as truthy
 *
 * @param Int $post_ID The post ID of the post we're saving
 * @since 1.7
 */
function npr_cds_save_send_to_one( int $post_ID ): bool {
	// safety checks
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
	if ( !current_user_can( 'edit_page', $post_ID ) ) return false;
	if ( empty( $post_ID ) ) return false;
	if ( !isset( $_POST['npr_cds_send_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['npr_cds_send_nonce'] ) ), 'npr_cds-' . $post_ID ) ) return false;

	global $post;

	if ( get_post_type( $post ) !== npr_cds_get_push_post_type( $post ) ) return false;
	$value = (
		isset( $_POST['_send_to_one'] )
		&& $_POST['_send_to_one'] == 1
		&& isset( $_POST['send_to_cds'] )
		&& $_POST['send_to_cds'] == 1
	) ? 1 : 0;
	update_post_meta( $post_ID, '_send_to_one', $value );
	return true;
}
add_action( 'save_post', 'npr_cds_save_send_to_one', 15 );

/**
 * Save the "NPR One Featured" metadata
 *
 * If the send_to_one value is falsy, then this should not be saved as truthy
 * And thus, if the send_to_cds value is falsy, then this should not be saved as truthy
 *
 * @param Int $post_ID The post ID of the post we're saving
 * @since 1.7
 */
function npr_cds_save_nprone_featured( int $post_ID ): bool {
	// safety checks
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
	if ( !current_user_can( 'edit_page', $post_ID ) ) return false;
	if ( empty( $post_ID ) ) return false;
	if ( !isset( $_POST['npr_cds_send_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['npr_cds_send_nonce'] ) ), 'npr_cds-' . $post_ID ) ) return false;

	global $post;

	if ( get_post_type( $post ) != npr_cds_get_push_post_type( $post ) ) return false;
	$value = (
		isset( $_POST['_nprone_featured'] )
		&& $_POST['_nprone_featured'] == 1
		&& isset( $_POST['send_to_cds'] )
		&& $_POST['send_to_cds'] == 1
		&& isset( $_POST['_send_to_one'] )
		&& $_POST['_send_to_one'] == 1
	) ? 1 : 0;
	update_post_meta( $post_ID, '_nprone_featured', $value );
	return true;
}
add_action( 'save_post', 'npr_cds_save_nprone_featured', 15 );

/**
 * Save the NPR One expiry datetime
 *
 * The meta name here is '_nprone_expiry_8601', and is saved in the ISO 8601 format for ease of conversion, not including the datetime.
 *
 * @param Int $post_ID The post ID of the post we're saving
 * @since 1.7
 * @see npr_cds_publish_meta_box
 * @uses npr_cds_get_datetimezone
 * @link https://en.wikipedia.org/wiki/ISO_8601
 */
function npr_cds_save_datetime( int $post_ID ): bool {
	// safety checks
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
	if ( !current_user_can( 'edit_page', $post_ID ) ) return false;
	if ( empty( $post_ID ) ) return false;
	if ( !isset( $_POST['npr_cds_send_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['npr_cds_send_nonce'] ) ), 'npr_cds-' . $post_ID ) ) return false;

	global $post;

	if ( get_post_type( $post ) != npr_cds_get_push_post_type( $post ) ) return false;

	$date = ( isset( $_POST['nprone-expiry-datetime'] ) ) ? sanitize_text_field( $_POST['nprone-expiry-datetime'] ) : '';

	// If the post is not published and values are not set, save an empty post meta
	if ( !empty( $date ) && !empty( $post->post_status ) && 'publish' === $post->post_status ) {
		$datetime = date_create( $date, npr_cds_get_datetimezone() );
		$value = date_format( $datetime, DATE_ATOM );
		update_post_meta( $post_ID, '_nprone_expiry_8601', $value );
	} else {
		delete_post_meta( $post_ID, '_nprone_expiry_8601' );
	}
	return true;
}
add_action( 'save_post', 'npr_cds_save_datetime', 15 );

/**
 * Helper function to get the post expiry datetime
 *
 * The datetime is stored in post meta _nprone_expiry_8601
 * This assumes that the post has been published
 *
 * @param int|WP_Post $post the post ID or WP_Post object
 *
 * @return DateTime the DateTime object created from the post expiry date
 * @see note on DATE_ATOM and DATE_ISO8601 https://secure.php.net/manual/en/class.datetime.php#datetime.constants.types
 * @uses npr_cds_get_datetimezone
 * @since 1.7
 */
function npr_cds_get_post_expiry_datetime( int|WP_Post $post ): DateTime {
	$post = ( $post instanceof WP_Post ) ? $post->ID : $post ;
	$iso_8601 = get_post_meta( $post, '_nprone_expiry_8601', true );
	$timezone = npr_cds_get_datetimezone();

	if ( empty( $iso_8601 ) ) {
		// return DateTime for the publish date plus seven days
		$future = get_the_date( DATE_ATOM, $post ); // publish date
		return date_add( date_create( $future, $timezone ), new DateInterval( 'P7D' ) );
	} else {
		// return DateTime for the expiry date
		return date_create( $iso_8601, $timezone );
	}
}

/**
 * Helper for getting WordPress GMT offset
 *
 * Changing this function to send get_option( 'timezone_string' ) to DateTimeZone
 * because the previous version was converting this to a number of seconds, but
 * DateTimeZone requires a string in the constructor. If get_option( 'timezone_string' )
 * is empty, it defaults to '+0000' which is Greenwich Mean Time.
 *
 * @since 1.9.4
 * @return DateTimeZone
 */
function npr_cds_get_datetimezone(): DateTimeZone {
	$offset = get_option( 'timezone_string', '+0000' );
	try {
		$return = new DateTimeZone( $offset );
	} catch( Exception $e ) {
		npr_cds_error_log( $e->getMessage() );
		$return = new DateTimeZone( '+0000' );
	}
	return $return;
}

/**
 * Add an admin notice to the post editor with the post's error message if it exists
 */
function npr_cds_post_admin_message_error(): void {
	// only run on a post edit page
	$screen = get_current_screen();
	if ( $screen->id !== 'post' ) {
		return;
	}

	// Push errors are saved in this piece of post meta, and there may not ba just one
	$errors = get_post_meta( get_the_ID(), NPR_PUSH_STORY_ERROR );

	if ( !empty( $errors ) ) {
		$errortext = '';
		foreach ( $errors as $error ) {
			$errortext .= sprintf(
				'<p>%1$s</p>',
				$error
			);
		}

		printf(
			'<div class="%1$s"><p>%2$s</p>%3$s</div>',
			'notice notice-error',
			esc_html__( 'An error occurred when pushing this post to NPR:',  ),
			$errortext
		);
	}
}
add_action( 'admin_notices', 'npr_cds_post_admin_message_error' );

/**
 * Edit the post admin notices to include the post's id when it has been pushed successfully
 */
function npr_cds_post_updated_messages_success( $messages ): array {
	$id = get_post_meta( get_the_ID(), NPR_STORY_ID_META_KEY, true ); // single

	if ( !empty( $id ) ) {

		// what do we call this thing?
		$post_type = get_post_type( get_the_ID() );
		$obj = get_post_type_object( $post_type );
		$singular = $obj->labels->singular_name;

		// Create the message about the thing being updated
		$messages['post'][4] = sprintf(
			__( '%s updated. This post\'s NPR ID is %s. ',  ),
			esc_attr( $singular ),
			(string) $id
		);
	}
	return $messages;
}
add_filter( 'post_updated_messages', 'npr_cds_post_updated_messages_success' );