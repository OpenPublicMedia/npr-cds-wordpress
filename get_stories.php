<?php
/**
 * The class DS_NPR_API and related functions for getting stories from the API
 */

require_once( NPR_CDS_PLUGIN_DIR . 'get_stories_ui.php' );
require_once( NPR_CDS_PLUGIN_DIR . 'classes/NPR_CDS_WP.php' );

class NPR_CDS {
	var $created_message = '';

	/**
	 * What is the post type that pulled stories should be created as?
	 *
	 * @return string The post type
	 */
	public static function get_pull_post_type() {
		return get_option( 'npr_cds_pull_post_type', 'post' );
	}

	/**
	 * The cron job to pull stories from the API
	 */
	public static function cron_pull() {
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
					if ( empty( $api->message ) ) {
						//check the publish flag and send that along.
						$pub_flag = FALSE;
						if ( $query['publish'] == 'Publish' ) {
							$pub_flag = TRUE;
						}
						$story = $api->update_posts_from_stories( $pub_flag, $i );
					} else {
						if ( empty( $story ) ) {
							error_log( 'NPR CDS: not going to save story. Query ' . $query_string . ' returned an error ' . $api->message . ' error' ); // debug use
						}
					}
				} catch( Exception $e ) {
					error_log( 'NPR CDS: error in ' .  __FUNCTION__ . ' like this :'. $e ); // debug use
				}
			}
		}
	}

	/**
	 * Function to convert an alleged NPR story URL or ID into a story ID, then request it
	 */
	public function load_page_hook() {
		// if the current user shouldn't be doing this, fail
		if ( !current_user_can( 'edit_posts' ) ) {
			wp_die(
				__( 'You do not have permission to edit posts, and therefore you do not have permission to pull posts from the NPR CDS' ),
				__( 'NPR CDS Error' ),
				403
			);
		}

		// find the input that is allegedly a story id
		// We validate these later
		if ( isset( $_POST ) && isset( $_POST[ 'story_id' ] ) ) {
			if ( !check_admin_referer( 'npr_cds_nonce_story_id', 'npr_cds_nonce_story_id_field' ) ) {
				wp_die(
					__( 'Nonce did not verify in NPR_CDS::load_page_hook. Are you sure you should be doing this?' ),
					__( 'NPR CDS Error' ),
					403
				);
			}
			$story_id = sanitize_text_field( $_POST[ 'story_id' ] );
			if ( isset( $_POST['publishNow'] ) ) {
				$publish = true;
			}
			if ( isset( $_POST['createDraft'] ) ) {
				$publish = false;
			}
		} elseif ( isset( $_GET['story_id'] ) && isset( $_GET['create_draft'] ) ) {
			$story_id = sanitize_text_field( $_GET['story_id'] );
		}

		// try to get the ID of the story from the URL
		if ( isset( $story_id ) ) {
			//check to see if we got an ID or a URL
			if ( is_numeric( $story_id ) ) {
				if ( strlen( $story_id ) >= 8 ) {
					$story_id = $story_id;
				}
			} elseif ( strpos( $story_id, 'npr.org' ) !== false ) {
				$story_id = sanitize_url( $story_id );
				// url format: /yyyy/mm/dd/id
				// url format: /blogs/name/yyyy/mm/dd/id
				$story_id = preg_replace( '/https?\:\/\/[^\s\/]*npr\.org\/((([^\/]*\/){3,5})([0-9]{8,12}))\/.*/', '$4', $story_id );
				if ( !is_numeric( $story_id ) ) {
					// url format: /templates/story/story.php?storyId=id
					$story_id = preg_replace( '/https?\:\/\/[^\s\/]*npr\.org\/([^&\s\<]*storyId\=([0-9]+)).*/', '$2', $story_id );
				}
			} else {
				$story_id = sanitize_url( $story_id );
				$meta = get_meta_tags( $story_id );
				if ( !empty( $meta['brightspot-datalayer'] ) ) {
					$json = json_decode( html_entity_decode( $meta['brightspot-datalayer'] ), TRUE );
					if ( !empty( $json['nprStoryId'] ) ) {
						$story_id = $json['nprStoryId'];
					}
				} elseif ( !empty( $meta['story_id'] ) ) {
					$story_id = $meta['story_id'];
				} else {
					npr_cds_show_message( "The referenced URL (" . $story_id . ") does not contain a valid NPR CDS ID. Please try again.", TRUE );
					error_log( "The referenced URL (" . $story_id . ") does not contain a valid NPR CDS ID. Please try again." ); // debug use
				}
			}
		}

		// Don't do anything if $story_id isn't an ID
		if ( isset( $story_id ) && is_numeric( $story_id ) ) {
			// start the API class
			// todo: check that the CDS token is actually set
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
					npr_cds_show_message( 'Error retrieving story for id = ' . $story_id . '<br> CDS Message =' . $api->message, TRUE );
					error_log( 'Not going to save the return from query for story_id=' . $story_id .', we got an error=' . $api->message . ' from the NPR CDS' ); // debug use
					return;
				}
			}
		}
	}

	/**
	 * Class constructor that hooks up the menu and the "Get NPR Stories" page action.
	 */
	public function __construct() {
		if ( !is_admin() ) {
			return;
		}
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'load-posts_page_get-npr-stories', [ $this, 'load_page_hook' ] );
	}

	/**
	 * Register the admin menu for "Get NPR Stories"
	 */
	public function admin_menu() {
		add_posts_page( 'Get NPR Stories', 'Get NPR Stories', 'edit_posts', 'get-npr-stories',   'npr_cds_get_stories' );
	}
}

new NPR_CDS;