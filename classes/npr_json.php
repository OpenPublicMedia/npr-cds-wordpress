<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 *
 * Do the mapping from WP post to the array that we're going to build the JSON from.
 * This is also where we will do custom mapping if need be.
 * If a mapped custom field does not exist in a certain post, just send the default field.
 *
 * @param  $post
 *
 * @return bool|string
 */
function npr_cds_to_json( $post ): bool|string {
	$cds_version = NPR_CDS_WP::NPR_CDS_VERSION;
	$story = new stdClass;
	$prefix = get_option( 'npr_cds_prefix' );
	$cds_id = $prefix . '-' . $post->ID;
	$story->id = $cds_id;

	$service_id = get_option( 'npr_cds_org_id' );
	if ( has_filter( 'npr_cds_push_service_ids_filter' ) ) {
		$service_id = apply_filters( 'npr_cds_push_service_ids_filter', $service_id, $post );
	}
	$service_ids = explode( ',', $service_id );
	$owners = $brandings = [];
	foreach( $service_ids as $oi ) {
		$npr_org = new stdClass;
		$npr_org->href = 'https://organization.api.npr.org/v4/services/' . $oi;
		$owners[] = $npr_org;
		if ( $oi !== 's1' ) {
			$brandings[] = $npr_org;
		}
	}
	$story->owners = $owners;
	$story->brandings = $brandings;
	$webPage = new stdClass;
	$webPage->href = get_permalink( $post );
	$webPage->rels = [ 'canonical' ];

	$cds_count = 0;

	$story->authorizedOrgServiceIds = $service_ids;
	$story->webPages = [ $webPage ];
	$story->layout = [];
	$story->assets = new stdClass;
	$story->collections = [];
	$story->profiles = npr_cds_base_profiles();
	$story->bylines = [];
	$story->publishDateTime = mysql2date( 'c', $post->post_modified_gmt );
	$story->editorialLastModifiedDateTime = mysql2date( 'c', $post->post_modified_gmt );

	$use_custom = get_option( 'npr_cds_push_use_custom_map' );

	//get the list of metas available for this post
	$post_metas = get_post_custom_keys( $post->ID );

	$teaser_text = '';
	if ( !empty( $post->post_excerpt ) ) {
		$teaser_text = $post->post_excerpt;
	}

	/*
	 * Custom content
	 */
	$custom_content_meta = get_option( 'npr_cds_mapping_body' );
	if ( !empty( $custom_content_meta ) && $custom_content_meta === '#NONE' ) {
		$custom_content_meta = 'npr_story_content';
		update_option( 'npr_cds_mapping_body', $custom_content_meta );
	}
	if (
		$use_custom &&
		!empty( $custom_content_meta ) &&
		in_array( $custom_content_meta, $post_metas )
	) {
		$content = get_post_meta( $post->ID, $custom_content_meta, true );
		$post_for_teaser = $post;
		$post_for_teaser->post_content = $content;
		if ( empty( $teaser_text ) ) {
			$teaser_text = get_the_excerpt( $post_for_teaser );
		}
	} else {
		$content = $post->post_content;
		if ( empty( $teaser_text ) ) {
			$teaser_text = get_the_excerpt( $post );
		}
	}

	/*
	 * Clean up the content by applying shortcodes and then stripping any remaining shortcodes.
	 */
	// Let's see if there are any plugins that need to fix their shortcodes before we run do_shortcode
	if ( has_filter( 'npr_cds_shortcode_filter' ) ) {
		$content = apply_filters( 'npr_cds_shortcode_filter', $content );
	}

	// Since we don't have a standard way to handle galleries across installs, let's just nuke them
	// Also, NPR is still trying to figure out how to handle galleries in CDS, so we can circle back when they do
	$content = preg_replace( '/\[gallery(.*)\]/U', '', $content );

	// The [embed] shortcode also gets kinda hinky, along with the Twitter/YouTube oEmbed stuff
	// In lieu of removing them, let's just convert them into links
	$content = preg_replace( '/\[embed\](.*)\[\/embed\]/', '<a href="$1">$1</a>', $content );
	$content = preg_replace( '/<p>(https?:\/\/.+)<\/p>/U', '<p><a href="$1">$1</a></p>', $content );

	// Apply the usual filters from 'the_content', which should resolve any remaining shortcodes
	$content = apply_filters( 'the_content', $content );

	// for any remaining short codes, nuke 'em
	$content = strip_shortcodes( $content );

	$story->teaser = $teaser_text;

	/*
	 * Custom title
	 */
	$custom_title_meta = get_option( 'npr_cds_mapping_title' );
	if (
		$use_custom
		&& !empty( $custom_title_meta )
		&& $custom_title_meta !== '#NONE#'
		&& in_array( $custom_content_meta, $post_metas )
	) {
		$custom_title = get_post_meta( $post->ID, $custom_title_meta, true );
		$story->title = $custom_title;
	} else {
		$story->title = $post->post_title;
	}

	/*
	 * If there is a custom byline configured, send that.
	 *
	 * If the site is using the coauthors plugin, and get_coauthors exists, send the display names
	 * If no cool things are going on, just send the display name for the post_author field.
	 */
	$bylines = [];
	$custom_byline_meta = get_option( 'npr_cds_mapping_byline' );
	// Custom field mapping byline
	if (
		$use_custom
		&& !empty( $custom_byline_meta )
		&& $custom_byline_meta !== '#NONE#'
		&& in_array( $custom_content_meta, $post_metas )
	) {
		$bylines[] = get_post_meta( $post->ID, $custom_byline_meta, true );
	}

	// Co-Authors Plus support overrides the NPR custom byline
	if ( function_exists( 'get_coauthors' ) ) {
		$coauthors = get_coauthors( $post->ID );
		if ( !empty( $coauthors ) ) {
			foreach( $coauthors as $i => $co ) {
				$bylines[] = $co->display_name;
			}
		} else {
			npr_cds_error_log( 'we do not have co authors' );
		}
	} else {
		npr_cds_error_log( 'can not find get_coauthors' );
	}
	if ( empty( $bylines ) ) {
		$bylines[] = get_the_author_meta( 'display_name', $post->post_author );
	}
	foreach ( $bylines as $byline ) {
		$byl = new stdClass;
		$byl_asset = new stdClass;
		$byline_id = $cds_id . '-' . $cds_count;
		$byl->id = $byline_id;
		$byl->name = $byline;
		$byl->profiles = npr_cds_asset_profile( 'byline' );
		$story->assets->{$byline_id} = $byl;
		$byl_asset->href = '#/assets/' . $byline_id;
		$story->bylines[] = $byl_asset;
		$cds_count++;
	}

	/*
	 * Send to NPR One
	 *
	 * If the box is checked, the value here is '1'
	 * @see nprstory_save_send_to_one
	 */
	$nprapi = get_post_meta( $post->ID, '_send_to_one', true ); // 0 or 1
	if ( ! empty( $nprapi ) && ( '1' === $nprapi || 1 === $nprapi ) ) {
		$collect = new stdClass;
		$collect->rels = [ 'collection' ];
		$collect->href = '/' . $cds_version . '/documents/319418027';
		$story->collections[] = $collect;
	}

	/*
	 * This story should be featured in NPR One
	 *
	 * @see nprstory_save_nprone_featured
	 */
	$nprapi = get_post_meta( $post->ID, '_nprone_featured', true ); // 0 or 1
	if ( ! empty( $nprapi ) && ( '1' === $nprapi || 1 === $nprapi ) ) {
		$collect = new stdClass;
		$collect->rels = [ 'collection' ];
		$collect->href = '/' . $cds_version . '/documents/500549367';
		$story->collections[] = $collect;
	}

	// NPR One audio run-by date
	$datetime = npr_cds_get_post_expiry_datetime( $post ); // if expiry date is not set, returns publication date plus 7 days
	$story->recommendUntilDateTime = date_format( $datetime, 'c' );


	// Parse through the paragraphs, add references to layout array, and paragraph text to assets
	$parts = array_filter(
		array_map( 'trim', preg_split( "/<\/?p>/", $content ) )
	);
	foreach ( $parts as $part ) {
		$para = new stdClass;
		$para_asset = new stdClass;
		$para_id = $cds_id . '-' . $cds_count;
		$para->id = $para_id;

		$para_type = 'text';
		if ( preg_match( '/^<(figure|div)/', $part ) ) {
			$para_type = 'html';
		}
		if ( $para_type == 'html' ) {
			$para->profiles = npr_cds_asset_profile( $para_type . '-block' );
		} else {
			$para->profiles = npr_cds_asset_profile( $para_type );
		}
		$para->{$para_type} = $part;
		$story->assets->{$para_id} = $para;
		$para_asset->href = '#/assets/' . $para_id;
		$story->layout[] = $para_asset;
		$cds_count++;
	}

	$custom_media_credit = get_option( 'npr_cds_mapping_media_credit' );
	$custom_media_agency = get_option( 'npr_cds_mapping_media_agency' );

	/*
	 * Attach images to the post
	 */
	$args = [
		'order' => 'DESC',
		'post_mime_type' => 'image',
		'post_parent' => $post->ID,
		'post_status' => null,
		'post_type' => 'attachment'
	];

	$images = get_children( $args );
	$primary_image = get_post_thumbnail_id( $post->ID );

	if ( !empty( $images ) ) {
		$story->images = [];
		$image_profile = new stdClass;
		$image_profile->href = '/' . $cds_version . '/profiles/has-images';
		$image_profile->rels = [ 'interface' ];
		$story->profiles[] = $image_profile;
	}

	foreach ( $images as $image ) {
		$custom_credit = '';
		$custom_agency = '';
		$image_metas = get_post_custom_keys( $image->ID );
		if (
			$use_custom &&
			!empty( $custom_media_credit ) &&
			$custom_media_credit !== '#NONE#' &&
			in_array( $custom_media_credit, $image_metas )
		) {
			$custom_credit = get_post_meta( $image->ID, $custom_media_credit, true );
		}

		if (
			$use_custom &&
			!empty( $custom_media_agency ) &&
			$custom_media_agency !== '#NONE#' &&
			in_array( $custom_media_agency, $image_metas )
		) {
			$custom_agency = get_post_meta( $image->ID, $custom_media_agency, true);
		}

		// If the image field for distribute is set and polarity then send it.
		// All kinds of other math when polarity is negative or the field isn't set.
		$image_type = [];
		if ( $image->ID == $primary_image ) {
			$image_type = [ 'primary', 'promo-image-standard' ];
		}

		// Is the image in the content?  If so, tell the API with a flag that CorePublisher knows.
		// WordPress may add something like "-150X150" to the end of the filename, before the extension.
		// Isn't that nice? Let's remove that.
		$image_attach_url = wp_get_attachment_url( $image->ID );
		$image_url = parse_url( $image_attach_url );
		$image_name_parts = pathinfo( $image_url['path'] );

		$image_regex = "/" . $image_name_parts['filename'] . "\-[a-zA-Z0-9]*" . $image_name_parts['extension'] . "/";
		$in_body = "";
		if ( preg_match( $image_regex, $content ) ) {
			if ( str_contains( $image_attach_url, '?' ) ) {
				$in_body = "&origin=body";
			} else {
				$in_body = "?origin=body";
			}
		}

		$image_meta = wp_get_attachment_metadata( $image->ID );

		$new_image = new stdClass;
		$image_asset = new stdClass;
		$image_asset_id = $prefix . '-' . $image->ID;
		$image_asset->id = $image_asset_id;
		$image_asset->profiles = npr_cds_asset_profile( 'image' );
		$image_asset->title = $image->post_title;
		$image_asset->caption = $image->post_excerpt;
		$image_asset->producer = $custom_credit;
		$image_asset->provider = $custom_agency;
		$image_asset->enclosures = [];

		$image_enc = new stdClass;
		$image_enc->href = $image_attach_url . $in_body;
		$image_enc->rels = [ 'image-custom' ];
		if ( !empty( $image_type ) ) {
			$image_enc->rels[] = 'primary';
			$new_image->rels = $image_type;
		}
		$image_enc->type = $image->post_mime_type;
		if ( !empty( $image_meta ) ) {
			$image_enc->width = $image_meta['width'];
			$image_enc->height = $image_meta['height'];
		}

		$image_asset->enclosures[] = $image_enc;
		$story->assets->{$image_asset_id} = $image_asset;

		$new_image->href = '#/assets/' . $image_asset_id;
		$story->images[] = $new_image;
	}

	/*
	 * Attach audio to the post
	 *
	 * Should be able to do the same as image for audio, with post_mime_type = 'audio' or something.
	 */
	$args = [
		'order'=> 'DESC',
		'post_mime_type' => 'audio',
		'post_parent' => $post->ID,
		'post_status' => null,
		'post_type' => 'attachment'
	];
	$audios = get_children( $args );
	$audio_files = $audio_assets = [];
	$has_audio = false;
	if ( !empty( $audios ) ) {
		foreach ( $audios as $audio ) {
			$audio_meta = wp_get_attachment_metadata( $audio->ID );
			$audio_guid = wp_get_attachment_url( $audio->ID );
			$audio_files[] = $audio->ID;

			$new_audio = new stdClass;
			$audio_asset = new stdClass;
			$audio_asset_id = $prefix . '-' . $audio->ID;
			$audio_asset->id = $audio_asset_id;
			$audio_asset->profiles = npr_cds_asset_profile( 'audio' );
			$audio_asset->title = $audio->post_title;
			$audio_asset->isAvailable = true;
			$audio_asset->isDownloadable = true;
			$audio_asset->isEmbeddable = false;
			$audio_asset->isStreamable = false;
			$audio_asset->duration = $audio_meta['length'];

			$audio_enc = new stdClass;
			$audio_enc->href = $audio_guid;
			$audio_enc->type = $audio->post_mime_type;

			$audio_asset->enclosures = [ $audio_enc ];
			$story->assets->{$audio_asset_id} = $audio_asset;

			$new_audio->href = '#/assets/' . $audio_asset_id;
			if ( count( $audio_files ) == 1 ) {
				$new_audio->rels = [ 'headline', 'primary' ];
			}

			$audio_assets[] = $new_audio;
			$has_audio = true;
		}
	}

	/*
	 * Support for Powerpress enclosures
	 *
	 * This logic is specifically driven by enclosure metadata items that are
	 * created by the PowerPress podcasting plug-in. It will likely have to be
	 * re-worked if we need to accomodate other plug-ins that use enclosures.
	 */
	if ( $enclosures = get_metadata( 'post', $post->ID, 'enclosure' ) ) {
		foreach( $enclosures as $enclosure ) {
			$pieces = explode( "\n", $enclosure );

			$audio_guid = trim( $pieces[0] );
			$attach_id = attachment_url_to_postid( $audio_guid );
			if ( $attach_id === 0 ) {
				$attach_id = npr_cds_guid_to_post_id( $audio_guid );
			}
			if ( !in_array( $attach_id, $audio_files ) && $attach_id > 0 ) {
				$audio_files[] = $attach_id;

				$audio_meta = wp_get_attachment_metadata( $attach_id );
				$duration = 0;
				if ( !empty( $audio_meta['length'] ) ) {
					$duration = $audio_meta['length'];
				} elseif ( !empty( $audio_meta['length_formatted'] ) ) {
					$duration = npr_cds_convert_duration_to_seconds( $audio_meta['length_formatted'] );
				} elseif ( !empty( $pieces[3] ) ) {
					$metadata = unserialize( trim( $pieces[3] ) );
					$duration = ( !empty($metadata['duration'] ) ) ? npr_cds_convert_duration_to_seconds( $metadata['duration'] ) : 0;
				}
				$audio_type = 'audio/mpeg';
				if ( !empty( $audio_meta['mime_type'] ) ) {
					$audio_type = $audio_meta['mime_type'];
				}

				$new_audio = new stdClass;
				$audio_asset = new stdClass;
				$audio_asset_id = $prefix . '-' . $attach_id;
				$audio_asset->id = $audio_asset_id;
				$audio_asset->profiles = npr_cds_asset_profile( 'audio' );
				$audio_asset->isAvailable = true;
				$audio_asset->isDownloadable = true;
				$audio_asset->isEmbeddable = false;
				$audio_asset->isStreamable = false;
				$audio_asset->duration = $duration;

				$audio_enc = new stdClass;
				$audio_enc->href = wp_get_attachment_url( $attach_id );
				$audio_enc->type = $audio_type;

				$audio_asset->enclosures = [ $audio_enc ];
				$story->assets->{$audio_asset_id} = $audio_asset;

				$new_audio->href = '#/assets/' . $audio_asset_id;
				if ( count( $audio_files ) == 1 ) {
					$new_audio->rels = [ 'headline', 'primary' ];
				}

				$audio_assets[] = $new_audio;
				$has_audio = true;
			}
		}
	}

	if ( $has_audio ) {
		$story->audio = $audio_assets;
		$audio_has = new stdClass;
		$audio_has->href = '/' . $cds_version . '/profiles/has-audio';
		$audio_has->rels = [ 'interface' ];
		$story->profiles[] = $audio_has;
		$audio_listen = new stdClass;
		$audio_listen->href = '/' . $cds_version . '/profiles/listenable';
		$audio_listen->rels = [ 'interface' ];
		$story->profiles[] = $audio_listen;
	}

	/*
	 * The story has been assembled; now we shall return it
	 */
	return json_encode( $story );
}

// Convert "HH:MM:SS" duration (not time) into seconds
function npr_cds_convert_duration_to_seconds( $duration ): int {
	$pieces = explode( ':', $duration );
	return (int)$pieces[0] * 60 * 60 + (int)$pieces[1] * 60 + (int)$pieces[2];
}

function npr_cds_guid_to_post_id( $guid ): int {
	global $wpdb;
	$id = 0;
	$attach_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid ) );
	if ( $attach_id !== null ) {
		$id = (int)$attach_id;
	}
	return $id;
}

function npr_cds_base_profiles(): array {
	$output = [];
	$cds_version = NPR_CDS_WP::NPR_CDS_VERSION;
	$profiles = [ 'story', 'publishable', 'document', 'renderable', 'buildout' ];
	foreach ( $profiles as $p ) {
		$new = new stdClass;
		$new->href = '/' . $cds_version . '/profiles/' . $p;
		if ( $p !== 'document' ) {
			if ( $p == 'story' ) {
				$new->rels = [ 'type' ];
			} else {
				$new->rels = [ 'interface' ];
			}
		}
		$output[] = $new;
	}
	return $output;
}

function npr_cds_asset_profile( $type ): array {
	$profiles = [ $type, 'document' ];
	$cds_version = NPR_CDS_WP::NPR_CDS_VERSION;
	$output = [];
	foreach ( $profiles as $p ) {
		$new = new stdClass;
		$new->href = '/' . $cds_version . '/profiles/' . $p;
		if ( $p == $type ) {
			$new->rels = [ 'type' ];
		}
		$output[] = $new;
	}
	return $output;
}