<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * @file
 * Defines basic OOP containers for NPR JSON.
 */
require_once( dirname( __FILE__ ) . '/npr_json.php' );

/**
 * Defines a class for NPR JSON creation/transmission and retrieval/parsing, for any PHP-based system.
 */
class NPR_CDS_WP {

	// HTTP status code = OK
	const NPR_CDS_STATUS_OK = 200;

	// HTTP status code for successful deletion
	const NPR_CDS_DELETE_OK = 204;

	// Default URL for pulling stories
	const NPR_CDS_VERSION = 'v1';

	public array|WP_Error $response;
	public stdClass $request;
	public string $message;
	public string $json;
	public array $notice;
	public array $stories;

	/**
	 * Initializes an NPR JSON object.
	 */
	function __construct() {
		$this->request = new stdClass;
		$this->request->method = NULL;
		$this->request->params = NULL;
		$this->request->data = NULL;
		$this->request->path = NULL;
		$this->request->base = NULL;
		$this->request->request_url = NULL;
	}

	function request( $params = [], $path = 'documents' ): void {
		$this->request->params = $params;
		$this->request->path = $path;
		$this->request->base = get_option( 'npr_cds_pull_url' );

		$queries = [];
		foreach ( $this->request->params as $k => $v ) {
			if ( $k !== 'id' ) {
				if ( $k === 'profileIds' && is_array( $v ) ) {
					foreach ( $v as $vv ) {
						$queries[] = "$k=$vv";
					}
				} else {
					$queries[] = "$k=$v";
				}
			}
		}
		$request_url = $this->request->base . '/' . self::NPR_CDS_VERSION . '/' . $this->request->path;
		if ( !empty( $params['id'] ) ) {
			$request_url .= '/' . $params['id'];
		}
		if ( !empty( $queries ) ) {
			$request_url .= '?' . implode( '&', $queries );
		}
		$this->request->request_url = $request_url;
		$this->query_by_url( $request_url );
	}

	function get_token_options(): array {
		$token = NPR_CDS_WP::get_cds_token();
		if ( empty( $token ) ) {
			npr_cds_show_message( 'No CDS bearer token present. Please enter one on the main settings page.', TRUE );
		}
		return [
			'headers' => [
				"Authorization" => "Bearer " . $token
			]
		];
	}

	static public function get_cds_token(): string {
		if ( defined( 'NPR_CDS_TOKEN' ) ) {
			$token = NPR_CDS_TOKEN;
		} else {
			$token = get_option( 'npr_cds_token' );
		}
		return $token;
	}

	function query_by_url( $url ): void {
		//fill out the $this->request->param array so we can know what params were sent
		$parsed_url = parse_url( $url );
		if ( !empty( $parsed_url['query'] ) ) {
			$params = explode( '&', $parsed_url['query'] );
			$profileIds = [];
			if ( !empty( $params ) ) {
				foreach ( $params as $p ){
					$attrs = explode( '=', $p );
					if ( $attrs[0] === 'profileIds' ) {
						$profileIds[] = $attrs[1];
					} else {
						$this->request->params[ $attrs[0] ] = $attrs[1];
					}
				}
				if ( !empty( $profileIds ) ) {
					$this->request->params['profileIds'] = $profileIds;
				}
			}
		}
		$options = $this->get_token_options();
		$response = wp_remote_get( $url, $options );
		if ( !is_wp_error( $response ) ) {
			$this->response = $response;
			if ( $response['response']['code'] == self::NPR_CDS_STATUS_OK ) {
				if ( $response['body'] ) {
					$this->json = $response['body'];
				} else {
					$this->notice[] = __( 'No data available.', 'npr-content-distribution-service' );
				}
			} else {
				npr_cds_show_message( 'An error occurred pulling your story from the NPR CDS.  The CDS responded with message = ' . $response['response']['message'], TRUE );
			}
		} else {
			$error_text = '';
			if ( !empty( $response->errors['http_request_failed'][0] ) ) {
				$error_text = '<br> HTTP Error response =  ' . $response->errors['http_request_failed'][0];
			}
			npr_cds_show_message( 'Error pulling story for url=' . $url . $error_text, TRUE );
			npr_cds_error_log( 'Error retrieving story for url=' . $url );
		}
	}

	function extract_asset_id( $href ): bool|string {
		$href_xp = explode( '/', $href );
		return end( $href_xp );
	}

	function extract_profiles( $story ): array {
		$output = [];
		foreach ( $story as $p ) {
			$p_xp = explode( '/', $p->href );
			$output[] = end( $p_xp );
		}
		return $output;
	}

	function get_document( $href ): stdClass|WP_Error {
		$url = NPR_CDS_PULL_URL . $href;
		$options = $this->get_token_options();
		$response = wp_remote_get( $url, $options );
		if ( is_wp_error( $response ) ) {
			/**
			 * @var WP_Error $response
			 */
			$code = $response->get_error_code();
			$message = $response->get_error_message();
			$message = sprintf( 'Error requesting document via CDS URL: %s (%s [%d])', $url, $message, $code );
			error_log( $message );
			return $response;
		}
		$json = json_decode( $response['body'], false );
		if ( !empty( $json->resources ) ) {
			return $json->resources[0];
		} else {
			return new WP_Error( 404, 'Sorry, no resources were returned' );
		}
	}

	/**
	 *
	 * This function will go through the list of stories in the object and check to see if there are updates
	 * available from the NPR API if the pubDate on the API is after the pubDate originally stored locally.
	 *
	 * @param bool $publish
	 * @param int $qnum
	 *
	 * @return int $post_id or 0
	 * @throws Exception
	 */
	function update_posts_from_stories( bool $publish = TRUE, int $qnum = 0 ): int {
		$pull_post_type = get_option( 'npr_cds_pull_post_type', 'post' );
		$import_tags = get_option( 'npr_cds_import_tags', '1' );
		$cds_query = get_option( 'npr_cds_query_' . $qnum );
		if ( !empty( $cds_query['pull_type'] ) ) {
			$pull_post_type = $cds_query['pull_type'];
		}
		if ( !empty( $cds_query['import_tags'] ) ) {
			$import_tags = $cds_query['import_tags'];
		}

		if ( !empty( $this->stories ) ) {
			$single_story = TRUE;
			if ( sizeof( $this->stories ) > 1 ) {
				$single_story = FALSE;
			}
			foreach ( $this->stories as $story ) {
				$post_id = null;
				$exists = new WP_Query([
					'meta_key' => NPR_STORY_ID_META_KEY,
					'meta_value' => $story->id,
					'post_type' => $pull_post_type,
					'post_status' => 'any',
					'no_found_rows' => true
				]);

				$cats = [];
				$post_mod_date = $post_pub_date = 0;
				if ( empty( $story->body ) ) {
					$story->body = null;
				}
				if ( $exists->have_posts() ) {
					$existing = $exists->post;
					$post_id = $existing->ID;
					$existing_skip = true;
					$existing_status = $exists->posts[0]->post_status;
					$existing_meta = get_post_meta( $existing->ID );
					if ( array_key_exists( '#NONE#', $existing_meta ) ) {
						delete_post_meta( $existing->ID, '#NONE#' );
					}
					$post_mod_date_meta = $existing_meta[ NPR_LAST_MODIFIED_DATE_KEY ];
					// to store the category ids of the existing post
					if ( !empty( $post_mod_date_meta[0] ) ) {
						$post_mod_date = strtotime( $post_mod_date_meta[0] );
					}
					$post_pub_date_meta = $existing_meta[ NPR_PUB_DATE_META_KEY ];
					if ( !empty( $post_pub_date_meta[0] ) ) {
						$post_pub_date = strtotime( $post_pub_date_meta[0] );
					}

					if ( ( $post_mod_date === strtotime( $story->editorialLastModifiedDateTime ) || $post_pub_date === strtotime( $story->publishDateTime ) ) && !array_key_exists( '#NONE#', $existing_meta ) ) {
						continue;
					}
					// get ids of existing categories for post
					$cats = wp_get_post_categories( $post_id );
				} else {
					$existing = $existing_status = null;
				}

				$npr_has_video = FALSE;
				$npr_has_layout = FALSE;
				$npr_layout = $this->get_body_with_layout( $story );
				if ( !empty( $npr_layout['body'] ) ) {
					$npr_has_layout = TRUE;
					$story->body = $npr_layout['body'];
					$npr_has_video = $npr_layout['has_video'];
				}

				// add the transcript
				$story->body .= $this->get_transcript_body( $story );

				$story_date = new DateTime( $story->publishDateTime );
				$post_date = $story_date->format( 'Y-m-d H:i:s' );

				// set the story as draft, so we don't try ingesting it
				$args = [
					'post_title'	=> $story->title,
					'post_excerpt'  => ( !empty( $story->teaser ) ? $story->teaser : '' ),
					'post_content'	=> $story->body,
					'post_status'	=> 'draft',
					'post_type'		=> $pull_post_type,
					'post_date'		=> $post_date
				];

				$wp_category_ids = [];
				$wp_category_id = "";
				if ( $existing === null ) {
					if ( $pull_post_type === 'post' ) {
						// Get Default category from options table and store in array for post_array
						if ( !empty( $cds_query['category'] ) ) {
							$wp_category_id = intval( $cds_query['category'] );
						} else {
							$wp_category_id = intval( get_option( 'default_category' ) );
						}
					} else {
						$wp_category_id = intval( get_option( 'default_category' ) );
					}
					$wp_category_ids[] = $wp_category_id;
				} else {
					if ( 0 < sizeof( $cats ) ) {
						// merge arrays and remove duplicate ids
						$wp_category_ids = array_unique( array_merge( $wp_category_ids, $cats ) );
					}
				}
				$by_lines = [];
				$multi_by_line = '';

				// continue to save single byline into npr_byline as is, but also set multi to false
				if ( !empty( $story->bylines ) ) { // Always treats like an array, because it *should* always be an array
					foreach ( $story->bylines as $byline ) {
						$byl_id = $this->extract_asset_id( $byline->href );
						$byl_current = $story->assets->{$byl_id};
						$byl_profile = $this->extract_asset_profile( $byl_current );
						if ( $byl_profile === 'reference-byline' ) {
							foreach ( $byl_current->bylineDocuments as $byl_doc ) {
								$byl_data = $this->get_document( $byl_doc->href );
								$byl_link = '';
								if ( !empty( $byl_data->webPages ) ) {
									foreach ( $byl_data->webPages as $byl_web ) {
										if ( !empty( $byl_web->rels ) && in_array( 'canonical', $byl_web->rels ) ) {
											$byl_link = $byl_web->href;
										}
									}
								}
								$by_lines[] = [
									'name' => trim( $byl_data->title ),
									'link' => $byl_link
								];
							}
						} else {
							if ( !empty( $byl_current->name ) ) {
								$by_lines[] = [
									'name' => trim( $byl_current->name )
								];
							}
						}
					}
				}
				if ( !empty( $by_lines ) && count( $by_lines ) > 1 ) {
					$all_bylines = [];
					foreach ( $by_lines as $bl ) {
						if ( ! empty( $bl['link'] ) ) {
							$all_bylines[] = '<a href="' . $bl['link'] . '">' . $bl['name'] . '</a>';
						} else {
							$all_bylines[] = $bl['name'];
						}
					}
					$multi_by_line = implode( '|', $all_bylines );
				}
				$webPage = '';
				if ( !empty( $story->webPages ) ) {
					foreach ( $story->webPages as $web ) {
						if ( in_array( 'canonical', $web->rels ) ) {
							$webPage = $web->href;
						}
					}
				}
				$profiles = $this->extract_profiles( $story->profiles );
				$npr_story_content_meta_key = NPR_STORY_CONTENT_META_KEY;
				$npr_story_title_meta_key = NPR_STORY_TITLE_META_KEY;
				$npr_byline_meta_key = NPR_BYLINE_META_KEY;
				$npr_image_credit_meta_key = NPR_IMAGE_CREDIT_META_KEY;
				$npr_image_agency_meta_key = NPR_IMAGE_AGENCY_META_KEY;
				if ( $npr_story_content_meta_key === '#NONE#' ) {
					$npr_story_content_meta_key = 'npr_story_content';
				}
				if ( $npr_story_title_meta_key === '#NONE#' ) {
					$npr_story_title_meta_key = 'npr_story_title';
				}
				if ( $npr_byline_meta_key === '#NONE#' ) {
					$npr_byline_meta_key = 'npr_byline';
				}
				if ( $npr_image_credit_meta_key === '#NONE#' ) {
					$npr_image_credit_meta_key = 'npr_image_credit';
				}
				if ( $npr_image_agency_meta_key === '#NONE#' ) {
					$npr_image_agency_meta_key = 'npr_image_agency';
				}

				// set the meta RETRIEVED so when we publish the post, we don't try ingesting it
				$metas = [
					NPR_STORY_ID_META_KEY		  => $story->id,
					NPR_HTML_LINK_META_KEY		  => $webPage,
					$npr_story_content_meta_key	  => $story->body,
					$npr_story_title_meta_key	  => $story->title,
					$npr_byline_meta_key		  => ( !empty( $by_lines[0]['name'] ) ? $by_lines[0]['name'] : '' ),
					NPR_BYLINE_LINK_META_KEY	  => ( !empty( $by_lines[0]['link'] ) ? $by_lines[0]['link'] : '' ),
					NPR_MULTI_BYLINE_META_KEY	  => $multi_by_line,
					NPR_RETRIEVED_STORY_META_KEY  => 1,
					NPR_PUB_DATE_META_KEY		  => $story->publishDateTime,
					NPR_STORY_DATE_META_KEY		  => $story->publishDateTime,
					NPR_LAST_MODIFIED_DATE_KEY	  => $story->editorialLastModifiedDateTime,
					NPR_STORY_HAS_VIDEO_META_KEY  => $npr_has_video,
					NPR_STORY_HAS_LAYOUT_META_KEY => $npr_has_layout
				];
				if ( $npr_layout['has_video_streaming'] ) {
					$metas[NPR_HAS_VIDEO_STREAMING_META_KEY] = $npr_layout['has_video_streaming'];
				}
				if ( $npr_layout['has_slideshow'] ) {
					$metas[NPR_HAS_SLIDESHOW_META_KEY] = $npr_layout['has_slideshow'];
				}
				// get audio
				if ( in_array( 'has-audio', $profiles ) && !empty( $story->audio ) ) {
					$mp3_array = [];
					foreach ( $story->audio as $audio ) {
						if ( empty( $audio->rels ) ) {
							// malformed
							continue;
						}
						$audio_id = $this->extract_asset_id( $audio->href );
						if ( in_array( 'primary', $audio->rels ) && !in_array( 'premium', $audio->rels ) ) {
							$audio_current = $story->assets->{ $audio_id };
							if ( $audio_current->isAvailable && $audio_current->isDownloadable ) {
								foreach ( $audio_current->enclosures as $enclose ) {
									if ( $enclose->type == 'audio/mpeg' ) {
										$mp3_array[] = $enclose->href;
									}
								}
							}
						}
					}
					$metas[NPR_AUDIO_META_KEY] = implode( ',', $mp3_array );
				}
				if ( $existing ) {
					$created = false;
					$args['ID'] = $existing->ID;
				} else {
					$created = true;
				}
				// keep WP from stripping content from NPR posts
				kses_remove_filters();

				/**
				 * Filters the post meta before series of update_post_meta() calls
				 *
				 * Allow a site to modify the post meta values prior to
				 * passing each element via update_post_meta().
				 *
				 * @since 1.7
				 *
				 * @param array $metas Array of key/value pairs to be updated
				 * @param int $post_id Post ID or NULL if no post ID.
				 * @param stdClass $story Story object created during import
				 * @param bool $created true if not pre-existing, false otherwise
				 */
				$metas = apply_filters( 'npr_pre_update_post_metas', $metas, $post_id, $story, $created, $qnum );

				/**
				 * Filters the $args passed to wp_insert_post()
				 *
				 * Allow a site to modify the $args passed to wp_insert_post() prior to post being inserted.
				 *
				 * @since 1.7
				 *
				 * @param array $args Parameters passed to wp_insert_post()
				 * @param int $post_id Post ID or NULL if no post ID.
				 * @param stdClass $story Story object created during import
				 * @param bool $created true if not pre-existing, false otherwise
				 */

				$args = apply_filters( 'npr_pre_insert_post', $args, $post_id, $story, $created, $qnum );
				$args['meta_input'] = $metas;

				$post_id = wp_insert_post( $args );
				if ( is_wp_error ( $post_id ) && WP_DEBUG ) {
					npr_cds_error_log( 'Cron '. $qnum . ': CDS ID ' . $story->id . ' database insertion failed' );
				}
				wp_set_post_terms( $post_id, $wp_category_ids, 'category', true );

				// re-enable the built-in content stripping
				kses_init_filters();

				// now that we have an id, we can add images
				// this is the way WP seems to do it, but we couldn't call media_sideload_image or media_ because that returned only the URL
				// for the attachment, and we want to be able to set the primary image, so we had to use this method to get the attachment ID.
				if ( in_array( 'has-images', $profiles ) && !empty( $story->images ) ) {

					// are there any images saved for this post, probably on update, but no sense looking of the post didn't already exist
					if ( $existing ) {
						$image_args = [
							'order'=> 'ASC',
							'post_mime_type' => 'image',
							'post_parent' => $post_id,
							'post_status' => null,
							'post_type' => 'attachment',
							'post_date'	=> $post_date
						];
						$attached_images = get_children( $image_args );
					}
					foreach ( $story->images as $image ) {
						if ( empty( $image->rels ) || !in_array( 'primary', $image->rels ) ) {
							continue;
						}
						$image_url = [];
						$image_id = $this->extract_asset_id( $image->href );
						$image_current = $story->assets->{ $image_id };
						if ( !empty( $image_current->isRestrictedToAuthorizedOrgServiceIds ) ) {
							continue;
						}
						if ( !empty( $image_current->enclosures ) ) {
							foreach ( $image_current->enclosures as $enclosure ) {
								if ( in_array( 'primary', $enclosure->rels ) ) {
									$image_url = $this->get_image_url( $enclosure, true );
								}
							}
						}
						if ( empty( $image_url ) ) {
							foreach ( $image_current->enclosures as $enclosure ) {
								if ( in_array( 'enlargement', $enclosure->rels ) ) {
									$image_url = $this->get_image_url( $enclosure, true );
								}
							}
						}
						if ( WP_DEBUG ) {
							npr_cds_error_log( 'Got image from: ' . $image_url['url'] );
						}

						if ( !empty( $attached_images ) ) {
							foreach( $attached_images as $att_image ) {
								// see if the filename is very similar
								$attach_url = wp_get_original_image_url( $att_image->ID );
								$attach_filename = pathinfo( parse_url( $attach_url, PHP_URL_PATH ), PATHINFO_FILENAME );

								// so if the already attached image name is part of the name of the file
								// coming in, ignore the new/temp file, it's probably the same
								if ( strtolower( $attach_filename ) === strtolower( $image_url['filename'] ) ) {
									continue 2;
								}
							}
						}

						// Download file to temp location
						$tmp = download_url( $image_url['url'] );

						// Set variables for storage
						$file_array['name'] = $image_url['filename'];
						$file_array['tmp_name'] = $tmp;

						$file_OK = TRUE;
						// If error storing temporarily, unlink
						if ( is_wp_error( $tmp ) ) {
							//@unlink( $file_array['tmp_name'] );
							$file_array['tmp_name'] = '';
							$file_OK = FALSE;
						}

						// do the validation and storage stuff
						$image_title = !empty( $image_current->title ) ? $image_current->title : '';
						require_once( ABSPATH . 'wp-admin/includes/image.php' ); // needed for wp_read_image_metadata used by media_handle_sideload during cron
						$image_upload_id = media_handle_sideload( $file_array, $post_id, $image_title );
						// If error storing permanently, unlink
						if ( is_wp_error( $image_upload_id ) ) {
							@unlink( $file_array['tmp_name'] );
							$file_OK = FALSE;
						}

						//set the primary image
						if ( in_array( 'primary', $image->rels ) && $file_OK ) {
							$current_thumbnail_id = get_post_thumbnail_id( $post_id );
							if ( !empty( $current_thumbnail_id ) && $current_thumbnail_id !== $image_upload_id ) {
								delete_post_thumbnail( $post_id );
							}
							set_post_thumbnail( $post_id, $image_upload_id );
							//get any image metadata and attach it to the image post
							$image_producer = !empty( $image_current->producer ) ? $image_current->producer : '';
							$image_provider = !empty( $image_current->provider ) ? $image_current->provider : '';
							$image_caption = !empty( $image_current->caption ) ? $image_current->caption : '';
							if ( $npr_image_credit_meta_key === $npr_image_agency_meta_key ) {
								$image_credits = [ $image_producer, $image_provider ];
								$image_metas = [
									$npr_image_credit_meta_key => implode( ' | ', $image_credits ),
									NPR_IMAGE_CAPTION_META_KEY => $image_caption
								];
							} else {
								$image_metas = [
									$npr_image_credit_meta_key => $image_producer,
									$npr_image_agency_meta_key => $image_provider,
									NPR_IMAGE_CAPTION_META_KEY => $image_caption
								];
							}
							foreach ( $image_metas as $k => $v ) {
								update_post_meta( $image_upload_id, $k, $v );
							}
						}
					}
				}

				$args = [
					'post_title'	=> $story->title,
					'post_excerpt'  => ( !empty( $story->teaser ) ? $story->teaser : '' ),
					'post_content'	=> $story->body,
					'post_type'		=> $pull_post_type,
					'ID'			=> $post_id,
					'post_date'		=> $post_date
				];

				//set author
				if ( ! empty( $by_lines ) ) {
					$userQuery = new WP_User_Query([
						'search' => $by_lines[0]['name'],
						'search_columns' => [ 'nickname' ]
					]);

					$user_results = $userQuery->get_results();
					if ( count( $user_results ) == 1 && isset( $user_results[0]->data->ID ) ) {
						$args['post_author'] = $user_results[0]->data->ID;
					}
				}

				//now set the status
				if ( !$existing ) {
					if ( $publish ) {
						$args['post_status'] = 'publish';
					} else {
						$args['post_status'] = 'draft';
					}
				} else {
					//if the post existed, save its status
					$args['post_status'] = $existing_status;
				}

				/**
				 * Filters the $args passed to wp_insert_post() used to update
				 *
				 * Allow a site to modify the $args passed to wp_insert_post() prior to post being updated.
				 *
				 * @since 1.7
				 *
				 * @param array $args Parameters passed to wp_insert_post()
				 * @param int $post_id Post ID or NULL if no post ID.
				 * @param stdClass $story Story object created during import
				 */

				// keep WP from stripping content from NPR posts
				kses_remove_filters();

				$args = apply_filters( 'npr_pre_update_post', $args, $post_id, $story, $qnum );
				$post_id = wp_insert_post( $args );

				// re-enable content stripping
				kses_init_filters();

				// set categories for story
				$category_ids = $npr_tags = [];
				$category_ids = array_merge( $category_ids, $wp_category_ids );
				if ( !empty( $cds_query['tags'] ) ) {
					$tag_ex = explode( ',', $cds_query['tags'] );
					foreach ( $tag_ex as $tx ) {
						$npr_tags[] = trim( $tx );
					}
				}
				if ( !empty( $story->collections ) && $import_tags === '1' ) {
					foreach ( $story->collections as $collect ) {
						if ( in_array( 'topic', $collect->rels ) || in_array( 'category', $collect->rels ) ) {

							/**
							 * Filters term name prior to lookup of terms
							 *
							 * Allow a site to modify the terms looked-up before adding them to list of categories.
							 *
							 * @since 1.7
							 *
							 * @param string $term_name Name of term
							 * @param int $post_id Post ID or NULL if no post ID.
							 * @param stdClass $story Story object created during import
							 */
							$topic = $this->get_document( $collect->href );
							if ( !is_wp_error( $topic ) && in_array( 'topic', $collect->rels ) ) {
								$term_name = apply_filters( 'npr_resolve_category_term', $topic->title, $post_id, $story, $qnum );
								$category_id = get_cat_ID( $term_name );

								if ( !empty( $category_id ) ) {
									$category_ids[] = $category_id;
								}
							} elseif ( in_array( 'category', $collect->rels ) ) {
								$npr_tags[] = $topic->title;
							}
						}
					}
				}

				/**
				 * Filters category_ids prior to setting assigning to the post.
				 *
				 * Allow a site to modify category IDs before assigning to the post.
				 *
				 * @since 1.7
				 *
				 * @param int[] $category_ids Array of Category IDs to assign to post identified by $post_id
				 * @param int $post_id Post ID or NULL if no post ID.
				 * @param stdClass $story Story object created during import
				 */
				$category_ids = apply_filters( 'npr_pre_set_post_categories', $category_ids, $post_id, $story, $qnum );
				if ( 0 < count( $category_ids ) && is_integer( $post_id ) ) {
					wp_set_post_categories( $post_id, $category_ids );
				}

				if ( !empty( $npr_tags ) ) {
					wp_set_post_terms( $post_id, $npr_tags );
				}

				// If the Co-Authors Plus plugin is installed, use the bylines from the API output to set guest authors
				if ( function_exists( 'get_coauthors' ) ) {
					global $coauthors_plus;
					$coauthor_terms = [];
					if ( !empty( $by_lines ) ) {
						foreach ( $by_lines as $bl ) {
							$search_author = $coauthors_plus->search_authors( $bl['name'], [] );
							if ( !empty( $search_author ) ) {
								reset( $search_author );
								$coauthor_terms[] = key( $search_author );
							}
						}
						wp_set_post_terms( $post_id, $coauthor_terms, $coauthors_plus->coauthor_taxonomy );
					}
				}
				if ( WP_DEBUG ) {
					if ( $existing ) {
						npr_cds_error_log( 'Cron ' . $qnum . ': CDS ID ' . $story->id . ' story updated' );
					} else {
						npr_cds_error_log( 'Cron ' . $qnum . ': CDS ID ' . $story->id . ' story posted to database' );
					}
				}
			}
			if ( $single_story ) {
				return $post_id ?? 0;
			}
		}
		return 0;
	}

	/**
	 * This function will send the push request to the NPR CDS to add/update a story.
	 *
	 * @param string $json
	 * @param int $post_ID
	 *
	 * @see NPRCDS::send_request()
	 *
	 */
	function send_request( string $json, int $post_ID ): void {
		$error_text = '';
		$service_id = get_option( 'npr_cds_org_id' );
		$prefix = get_option( 'npr_cds_prefix' );
		if ( !empty( $service_id ) && !empty( $prefix ) ) {
			$cds_id = $prefix . '-' . $post_ID;
			$options = $this->get_token_options();
			$url = get_option( 'npr_cds_push_url' ) . '/' . self::NPR_CDS_VERSION . '/documents/' . $cds_id;
			npr_cds_error_log( 'Sending json = ' . $json );

			$options['body'] = $json;
			$options['method'] = 'PUT';
			$options['timeout'] = 30;
			$options = apply_filters( 'npr_pre_article_push', $options, $cds_id );
			$result = wp_remote_request( $url, $options );
			if ( !is_wp_error( $result ) ) {
				if ( $result['response']['code'] == self::NPR_CDS_STATUS_OK ) {
					$body = wp_remote_retrieve_body( $result );
					if ( $body ) {
						update_post_meta( $post_ID, NPR_STORY_ID_META_KEY, $cds_id );
					} else {
						error_log( 'Error returned from NPR CDS with status code 200 OK but failed wp_remote_retrieve_body: ' . print_r( $result, true ) ); // debug use
					}
				} else {
					if ( !empty( $result['response']['message'] ) ) {
						$error_text = 'Error pushing story with post_id = ' . $post_ID . ' for url=' . $url . ' HTTP Error response =  ' . $result['response']['message'];
					}
					$body = wp_remote_retrieve_body( $result );

					if ( $body ) {
						$response_json = json_decode( $body );
						$error_text .= '  API Error Message = ' . print_r( $response_json, true );
					}
					error_log( 'Error returned from NPR CDS with status code other than 200 OK: ' . $error_text ); // debug use
				}
			} else {
				$error_text = 'WP_Error returned when sending story with post_ID ' . $post_ID . ' for url ' . $url . ' to NPR CDS:' . $result->get_error_message();
				error_log( $error_text ); // debug use
			}
		} else {
			$error_text = 'OrgID was not set when tried to push post_ID ' . $post_ID . ' to the NPR CDS.';
			error_log( $error_text ); // debug use
		}

		// Add errors to the post that you just tried to push
		if ( !empty( $error_text ) ) {
			update_post_meta( $post_ID, NPR_PUSH_STORY_ERROR, $error_text );
		} else {
			delete_post_meta( $post_ID, NPR_PUSH_STORY_ERROR );
		}
	}

	/**
	 * wp_remote_request supports sending a custom method, so the cURL code has been removed
	 *
	 * @param  $api_id
	 */
	function send_delete( $api_id ): void {
		$options = $this->get_token_options();
		$url = get_option( 'npr_cds_push_url' ) . '/' . self::NPR_CDS_VERSION . '/documents/' . $api_id;

		$options['method'] = 'DELETE';
		$options = apply_filters( 'npr_pre_article_delete', $options );
		$result = wp_remote_request( $url, $options );
		$body = wp_remote_retrieve_body( $result );
		if ( $result['response']['code'] == self::NPR_CDS_DELETE_OK && empty( $body ) ) {
			npr_cds_error_log( 'Uploaded article ' . $api_id . ' successfully deleted from the NPR CDS' );
		}
	}

	/**
	 * Create CDS JSON from WordPress post.
	 *
	 * @param object $post
	 *   A WordPress post.
	 *
	 * @return string
	 *   A JSON string.
	 */
	function create_json( object $post ): string {
		return npr_cds_to_json( $post );
	}

	/**
	 * Parses object. Turns raw JSON into various object properties.
	 */
	function parse(): void {
		if ( !empty( $this->json ) ) {
			$json = $this->json;
		} else {
			$this->notice[] = 'No JSON to parse.';
			return;
		}

		$object = json_decode( $json, false );
		if ( empty( $this->stories ) ) {
			$this->stories = [];
		}
		if ( !empty( $object->resources ) ) {
			foreach ( $object->resources as $story ) {
				if ( empty( $story->isRestrictedToAuthorizedOrgServiceIds ) ) {
					$this->stories[] = $story;
				} else {
					$this->notice[] = $story->id;
				}
			}
		}
		if ( !empty( $object->message ) ) {
			$this->message = $object->message;
		} elseif ( !empty( $this->notice ) ) {
			$this->message = "The following CDS IDs are not licensed for syndication, and cannot be downloaded: " . implode( ', ', $this->notice );
		}
	}

	/**
	 * This function will check a story to see if there are transcripts that should go with it, if there are
	 * we'll return the transcript as one big string with Transcript at the top and each paragraph separated by <p>
	 *
	 * @param object $story
	 *
	 * @return string
	 */
	function get_transcript_body( object $story ): string {
		$transcript_body = "";
		if ( !empty( $story->audio ) ) {
			foreach ( $story->audio as $audio ) {
				$audio_id = $this->extract_asset_id( $audio->href );
				$audio_current = $story->assets->{ $audio_id };
				if ( !empty( $audio_current->transcriptLink ) ) {
					$transcript = $this->get_document( $audio_current->transcriptLink->href );
					if ( !is_wp_error( $transcript ) && !empty( $transcript->text ) ) {
						$transcript_body .= '<div class="npr-transcript"><p><strong>Transcript:</strong></p>' . $transcript->text . '</div>';
					}
				}

			}
		}
		return $transcript_body;
	}

	/**
	 * Format and return a paragraph of text from an associated NPR API article
	 * This function checks if the text is already wrapped in an HTML element (e.g. <h3>, <div>, etc.)
	 * If not, the return text will be wrapped in a <p> tag
	 *
	 * @param string $p
	 *   A string of text
	 *
	 * @return string
	 *   A formatted string of text
	 */
	function add_paragraph_tag( string $p ): string {
		if ( preg_match( '/^<[a-zA-Z0-9 \="\-_\']+>.+<[a-zA-Z0-9\/]+>$/', $p ) ) {
			if ( preg_match( '/^<(a href|em|strong)/', $p ) ) {
				$output = '<p>' . $p . '</p>';
			} else {
				$output = $p;
			}
		} else {
			if ( str_contains( $p, '<div class="fullattribution">' ) ) {
				$output = '<p>' . str_replace( '<div class="fullattribution">', '</p><div class="fullattribution">', $p );
			} else {
				$output = '<p>' . $p . '</p>';
			}
		}
		return $output;
	}

	function parse_credits( $asset ): string {
		$credits = [];
		foreach ( [ 'producer', 'provider', 'copyright' ] as $item ) {
			if ( !empty( $asset->{ $item } ) ) {
				$credits[] = trim( $asset->{ $item } );
			}
		}
		if ( !empty( $credits ) ) {
			return ' (' . implode( ' | ', $credits ) . ')';
		}
		return '';
	}

	function get_image_url ( $image, $download = false ): array {
		$parse = parse_url( $image->href );
		if ( !empty( $parse['query'] ) ) {
			parse_str( $parse['query'], $output );
			if ( !empty( $output['url'] ) ) {
				$parse = parse_url( urldecode( $output['url'] ) );
			}
		}
		$path = pathinfo( $parse['path'] );
		$out = [
			'url' => $image->href,
			'filename' => $path['filename'] . '.' . $path['extension']
		];
		if ( empty( $image->hrefTemplate ) ) {
			return $out;
		}
		$format = get_option( 'npr_cds_image_format', 'webp' );
		$quality = get_option( 'npr_cds_image_quality', 75 );
		$width = get_option( 'npr_cds_image_width', 1200 );
		if ( $download ) {
			$width = $image->width;
		}
		$out['url'] = str_replace( [ '{width}', '{format}', '{quality}' ], [ $width, $format, $quality ], $image->hrefTemplate );
		if ( $format !== $path['extension'] ) {
			$out['filename'] = $path['filename'] . '.' . $format;
		}
		return $out;
	}

	function extract_asset_profile ( $asset ): bool|string {
		$output = '';
		foreach ( $asset->profiles as $profile ) {
			if ( !empty( $profile->rels ) && in_array( 'type', $profile->rels ) ) {
				$output = $this->extract_asset_id( $profile->href );
			}
		}
		return $output;
	}

	/**
	 * This function will format the body of the story with any provided assets inserted in the order they are in the layout
	 * and return an array of the transformed body and flags for what sort of elements are returned
	 *
	 * @param stdClass $story Story object created during import
	 * @return array with reconstructed body and flags describing returned elements
	 */
	function get_body_with_layout( stdClass $story ): array {
		$returnary = [ 'has_image' => FALSE, 'has_video' => FALSE, 'has_external' => FALSE, 'has_slideshow' => FALSE, 'has_video_streaming' => FALSE ];
		$body_with_layout = "";
		$use_npr_featured = !empty( get_option( 'npr_cds_query_use_featured' ) );
		$profiles = $this->extract_profiles( $story->profiles );
		$primary_image_id = '';
		$primary_image_in_layout = $include_featured_image = false;
		if ( in_array( 'has-images', $profiles ) ) {
			foreach ( $story->images as $images ) {
				if ( !empty( $images->rels ) && in_array( 'primary', $images->rels ) ) {
					$primary_image_id = $this->extract_asset_id( $images->href );
				}
			}
		}

		if ( in_array( 'buildout', $profiles ) && !empty( $story->layout ) ) {
			foreach ( $story->layout as $layout ) {
				$asset_id = $this->extract_asset_id( $layout->href );
				if ( $asset_id === $primary_image_id ) {
					$primary_image_in_layout = true;
				}
				$asset_current = $story->assets->{ $asset_id };
				$asset_profile = $this->extract_asset_profile( $asset_current );
				if ( !empty( $asset_current->isRestrictedToAuthorizedOrgServiceIds ) ) {
					continue;
				}
				switch ( $asset_profile ) {
					case 'text' :
						if ( !empty( $asset_current->text ) ) {
							$body_with_layout .= $this->add_paragraph_tag( $asset_current->text ) . "\n";
						}
						break;
					case 'promo-card' :
						if( get_option( 'npr_cds_skip_promo_cards', false ) ) {
							break;
						}

						if ( !empty( $asset_current->documentLink ) ) {
							$promo_card     = $this->get_document( $asset_current->documentLink->href );
							$promo_card_url = '';
							if ( ! is_wp_error( $promo_card ) && !empty( $promo_card->webPages ) ) {
								foreach ( $promo_card->webPages as $web ) {
									if ( !empty( $web->rels ) && in_array( 'canonical', $web->rels ) ) {
										$promo_card_url = $web->href;
									}
								}
							}
							$card_title = '';
							$card_teaser = 'Link';
							if ( !empty( $asset_current->eyebrowText ) ) {
								$card_title = '<h3>' . $asset_current->eyebrowText . '</h3>';
							} elseif ( !empty( $asset_current->title ) ) {
								$card_title = '<h3>' . $asset_current->title . '</h3>';
							}
							if ( !empty( $asset_current->teaser ) ) {
								$card_teaser = $asset_current->teaser;
							} elseif ( !empty( $asset_current->title ) ) {
								$card_teaser = $asset_current->title;
							}
							$body_with_layout .= '<figure class="wp-block-embed npr-promo-card ' . strtolower( $asset_current->cardStyle ) . '"><div class="wp-block-embed__wrapper">' . $card_title .
							                     '<p><a href="' . $promo_card_url . '">' . $card_teaser . '</a></p></div></figure>';
						}
						break;
					case 'html-block' :
						if ( !empty( $asset_current->html ) ) {
							$body_with_layout .= $asset_current->html;
						}
						$returnary['has_external'] = TRUE;
						if ( strpos( $asset_current->html, 'jwplayer.com' ) ) {
							$returnary['has_video'] = TRUE;
						}
						break;
					case 'audio' :
						if ( $asset_current->isAvailable ) {
							if ( $asset_current->isEmbeddable ) {
								$body_with_layout .= '<p><iframe class="npr-embed-audio" style="width: 100%; height: 239px;" src="' . $asset_current->embeddedPlayerLink->href . '"></iframe></p>';
							} elseif ( $asset_current->isDownloadable ) {
								foreach ( $asset_current->enclosures as $enclose ) {
									if ( $enclose->type == 'audio/mpeg' && empty( $enclose->rels ) || !in_array( 'premium', $enclose->rels ) ) {
										$body_with_layout .= '[audio mp3="' . $enclose->href . '"][/audio]';
									}
								}
							}
						}
						break;
					case 'pull-quote' :
						$body_with_layout .= '<blockquote class="npr-pull-quote"><h2>' . $asset_current->quote . '</h2>';
						if ( !empty( $asset_current->attributionParty ) ) {
							$body_with_layout .= '<p>' . $asset_current->attributionParty;
							if ( !empty( $asset_current->attributionContext ) ) {
								$body_with_layout .= ', ' . $asset_current->attributionContext;
							}
							$body_with_layout .= '</p>';
						}
						$body_with_layout .= '</blockquote>';
						break;
					case 'youtube-video' :
						$asset_title = 'YouTube video player';
						if ( !empty( $asset_current->headline ) ) {
							$asset_title = $asset_current->headline;
						}
						$returnary['has_video'] = TRUE;
						$body_with_layout .= '<figure class="wp-block-embed is-type-video"><div class="wp-block-embed__wrapper"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . $asset_current->videoId . '" title="' . $asset_title . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></figure>';
						break;
					case 'internal-link' :
						$link_url = '';
						$link_asset = $this->get_document( $asset_current->documentLink->href );
						if ( !empty( $link_asset->webPages ) ) {
							foreach ( $link_asset->webPages as $web ) {
								if ( in_array( 'canonical', $web->rels ) ) {
									$link_url = $web->href;
								}
							}
						}
						if ( !empty( $link_url ) ) {
							$body_with_layout .= '<p><a href="' . $link_url . '">' . $asset_current->linkText . '</a></p>';
						}
						break;
					case 'external-link' :
						if ( !empty( $asset_current->externalLink->href ) ) {
							$body_with_layout .= '<p><a href="' . $asset_current->externalLink->href . '">' . $asset_current->linkText . '</a></p>';
						}
						break;
					case 'image' :
						$thisimg_rels = [];
						foreach ( $story->images as $images ) {
							if ( $images->href == '#/assets/' . $asset_id && ! empty( $images->rels ) ) {
								$thisimg_rels = $images->rels;
							}
						}
						if ( in_array( 'primary', $thisimg_rels ) && $use_npr_featured ) {
							break;
						}
						$thisimg = $asset_current->enclosures[0];
						foreach ( $asset_current->enclosures as $img_enclose ) {
							if ( !empty( $img_enclose->rels ) && in_array( 'primary', $img_enclose->rels ) ) {
								$thisimg = $img_enclose;
							}
						}
						$figclass = "wp-block-image size-large";
						$image_href = $this->get_image_url( $thisimg );
						$fightml = '<img src="' . $image_href['url'] . '"';
						if ( in_array( 'image-vertical', $thisimg->rels ) ) {
							$figclass .= ' alignright';
							$fightml .= " width=200";
						}
						$cites = $this->parse_credits( $asset_current );
						$thiscaption = ( !empty( $asset_current->caption ) ? trim( $asset_current->caption ) : '' );
						$fightml .= ( !empty( $fightml ) && ! empty( $thiscaption ) ? ' alt="' . str_replace( '"', '\'', strip_tags( $thiscaption ) ) . '"' : '' );
						$fightml .= ( !empty( $fightml ) ? '>' : '' );
						$thiscaption .= ( !empty( $cites ) ? " <cite>" . $cites . "</cite>" : '' );
						$figcaption = ( !empty( $fightml ) && !empty( $thiscaption ) ? "<figcaption>$thiscaption</figcaption>" : '' );
						$fightml .= ( !empty( $fightml ) && !empty( $figcaption ) ? $figcaption : '' );
						$body_with_layout .= ( !empty( $fightml ) ? "<figure class=\"$figclass\">$fightml</figure>\n\n" : '' );
						break;
					case 'image-gallery' :
						$fightml = '';
						foreach ( $asset_current->layout as $ig_layout ) {
							$ig_asset_id = $this->extract_asset_id( $ig_layout->href );
							$ig_asset_current = $story->assets->{ $ig_asset_id };
							if ( empty( $ig_asset_current ) ) {
								$thisimg = $ig_asset_current->enclosures[0];
								foreach ( $ig_asset_current->enclosures as $ig_img_enclose ) {
									if ( ! empty( $ig_img_enclose->rels ) && in_array( 'primary', $ig_img_enclose->rels ) ) {
										$thisimg = $ig_img_enclose;
									}
								}
								$image_href = $this->get_image_url( $thisimg );
								$full_credits = $this->parse_credits( $ig_asset_current );

								$link_text = str_replace( '"', "'", $ig_asset_current->title . $full_credits );
								$fightml .= '<li class="splide__slide"><a href="' . esc_url( $thisimg->href ) . '" target="_blank"><img data-splide-lazy="' . esc_url( $image_href['url'] ) . '" alt="' . esc_attr( $link_text ) . '"></a><div>' . npr_cds_esc_html( $link_text ) . '</div></li>';
							}
						}
						if ( !empty( $fightml ) ) {
							$returnary['has_slideshow'] = TRUE;
							$fightml = '<figure class="wp-block-image"><div class="splide"><div class="splide__track"><ul class="splide__list">' . $fightml . '</div></div></ul></figure>';
						}
						$body_with_layout .= $fightml;
						break;
					case str_contains( $asset_profile, 'player-video' ) :
						$asset_caption = [];
						$full_caption = '';
						if ( !empty( $asset_current->title ) ) {
							$asset_caption[] = $asset_current->title;
						}
						if ( !empty( $asset_current->caption ) ) {
							$asset_caption[] = $asset_current->caption;
						}
						$credits = $this->parse_credits( $asset_current );
						if ( !empty( $credits ) ) {
							$asset_caption[] = '(' . $credits . ')';
						}
						if ( !empty( $asset_caption ) ) {
							$full_caption = '<figcaption>' . implode( ' ', $asset_caption ) . '</figcaption>';
						}
						$returnary['has_video'] = true;
						$video_asset = '';
						if ( $asset_profile == 'player-video' ) {
							$poster = '';
							$video_url = $asset_current->enclosures[0]->href;
							if ( !empty( $asset_current->images ) ) {
								foreach ( $asset_current->images as $v_image ) {
									if ( in_array( 'thumbnail', $v_image->rels ) ) {
										$v_image_id = $this->extract_asset_id( $v_image->href );
										$v_image_asset = $story->assets->{$v_image_id};
										foreach ( $v_image_asset->enclosures as $vma ) {
											$poster_image = $this->get_image_url( $vma );
											$poster = ' poster="' . $poster_image['url'] . '"';
										}
									}
								}
							}
							foreach ( $asset_current->enclosures as $v_enclose ) {
								if ( in_array( 'mp4-hd', $v_enclose->rels ) ) {
									$video_url = $v_enclose->href;
								} elseif ( in_array( 'mp4-high', $v_enclose->rels ) ) {
									$video_url = $v_enclose->href;
								}
							}
							$video_asset = '[video mp4="' . $video_url . '"' . $poster . '][/video]';
						} elseif ( $asset_profile == 'stream-player-video' ) {
							if ( in_array( 'hls', $asset_current->enclosures[0]->rels ) ) {
								$returnary['has_video_streaming'] = true;
								$video_asset = '<video id="'. $asset_current->id .'" controls></video>' .
								               '<script>' .
								               'let video = document.getElementById("' . $asset_current->id . '");' .
								               'if (Hls.isSupported()) {' .
								               'let hls = new Hls();' .
								               'hls.attachMedia(video);' .
								               'hls.on(Hls.Events.MEDIA_ATTACHED, () => {' .
								               'hls.loadSource("' . $asset_current->enclosures[0]->href .'");' .
								               'hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {'.
								               'console.log("manifest loaded, found " + data.levels.length + " quality level");' .
								               '});' .
								               '});' .
								               '}' .
								               '</script>';
							}
						}
						$body_with_layout .= '<figure class="wp-block-embed is-type-video"><div class="wp-block-embed__wrapper">' . $video_asset . '</div>' . $full_caption . '</figure>';
						break;
					default :
						// Do nothing???
						break;
				}

			}
			$display_attribution = get_option( 'npr_cds_display_attribution', '0' );
			if ( $display_attribution === '1' && !empty( $story->brandings ) ) {
				$attribution = [];
				foreach ( $story->brandings as $brand ) {
					$attr = wp_remote_get( $brand->href );
					if ( !is_wp_error( $attr ) ) {
						$attr_json = json_decode( $attr['body'], false );
						$attribution[] = $attr_json->brand->displayName;
					}
				}
				$publish_year = date( 'Y', strtotime( $story->publishDateTime ) );
				if ( !empty( $attribution ) ) {
					$body_with_layout .= '<p><em>Copyright &copy; ' . $publish_year . ' ' . implode( ', ', $attribution ) . '</em></p>';
				}
			}
		}
		if ( !empty( $story->corrections ) ) {
			$correction_text = '';
			foreach ( $story->corrections as $correction ) {
				$correct_id = $this->extract_asset_id( $correction->href );
				$correct_current = $story->assets->{ $correct_id };
				$correction_text .= '<li><strong><em>' .
				                    wp_date( get_option( 'date_format' ), strtotime( $correct_current->dateTime ) ) .
				                    '</em></strong><br />' . strip_tags( $correct_current->text ) . '</li>';
			}
			$body_with_layout .= '<figure class="wp-block-embed npr-correction"><div class="wp-block-embed__wrapper"><h3>Corrections:</h3><ul>' . $correction_text . '</ul></div></figure>';
		}
		if ( !empty( $story->audio ) ) {
			$audio_file = '';
			foreach ( $story->audio as $audio ) {
				if ( empty( $audio->rels ) ) {
					// malformed
					continue;
				}
				if ( in_array( 'primary', $audio->rels ) && ! in_array( 'premium', $audio->rels ) ) {
					$audio_id      = $this->extract_asset_id( $audio->href );
					$audio_current = $story->assets->{$audio_id};
					if ( $audio_current->isAvailable ) {
						if ( $audio_current->isEmbeddable && !empty( $audio_current->embeddedPlayerLink->href ) ) {
							$audio_file = '<p><iframe class="npr-embed-audio" style="width: 100%; height: 235px;" src="' . $audio_current->embeddedPlayerLink->href . '"></iframe></p>';
						} elseif ( $audio_current->isDownloadable ) {
							foreach ( $audio_current->enclosures as $enclose ) {
								if ( ! empty( $enclose->rels ) && $enclose->type == 'audio/mpeg' && ! in_array( 'premium', $enclose->rels ) ) {
									$audio_file = '[audio mp3="' . $enclose->href . '"][/audio]';
								}
							}
						}
					}
				}
			}
			if ( !empty( $audio_file ) ) {
				$body_with_layout = $audio_file . "\n" . $body_with_layout;
			}
		}
		if ( $use_npr_featured !== true && !empty( $primary_image_id ) && !$primary_image_in_layout ) {
			$featured_image = $story->assets->{ $primary_image_id };
			if ( empty( $featured_image->isRestrictedToAuthorizedOrgServiceIds ) ) {
				$thisimg = $featured_image->enclosures[0];
				foreach ( $featured_image->enclosures as $img_enclose ) {
					if ( !empty( $img_enclose->rels ) && in_array( 'primary', $img_enclose->rels ) ) {
						$thisimg = $img_enclose;
					}
				}
				$figclass = "wp-block-image size-large";
				$image_href = $this->get_image_url( $thisimg );
				$fightml = '<img src="' . $image_href['url'] . '"';
				if ( in_array( 'image-vertical', $thisimg->rels ) ) {
					$figclass .= ' alignright';
					$fightml .= " width=200";
				}
				$cites = $this->parse_credits( $featured_image );
				$thiscaption = ( !empty( $featured_image->caption ) ? trim( $featured_image->caption ) : '' );
				$fightml .= ( !empty( $fightml ) && ! empty( $thiscaption ) ? ' alt="' . str_replace( '"', '\'', strip_tags( $thiscaption ) ) . '"' : '' );
				$fightml .= ( !empty( $fightml ) ? '>' : '' );
				$thiscaption .= ( !empty( $cites ) ? " <cite>" . $cites . "</cite>" : '' );
				$figcaption = ( !empty( $fightml ) && !empty( $thiscaption ) ? "<figcaption>$thiscaption</figcaption>" : '' );
				$fightml .= ( !empty( $fightml ) && !empty( $figcaption ) ? $figcaption : '' );
				$body_with_layout = ( !empty( $fightml ) ? "<figure class=\"$figclass\">$fightml</figure>\n\n" : '' ) . $body_with_layout;
			}
		}
		$returnary['body'] = npr_cds_esc_html( $body_with_layout );
		return $returnary;
	}
}
