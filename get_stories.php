<?php
/**
 * The class DS_NPR_API and related functions for getting stories from the API
 */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once( NPR_CDS_PLUGIN_DIR . 'classes/NPR_CDS_WP.php' );

class NPR_CDS {
	var string $created_message = '';

	/**
	 * Class constructor that hooks up the menu and the "Get NPR Stories" page action.
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

		add_submenu_page(
			'edit.php' . ( $post_type !== 'post' ? '?post_type=' . $post_type : '' ),
			'Get NPR Stories',
			'Get NPR Stories',
			$required_capability,
			'get-npr-stories',
			[ $this, 'get_stories' ]
		);
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
		$publish = false;
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

		$valid = false;
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
						preg_match( '/https?\:\/\/[^\s\/]*npr\.org\/([^&\s\<]*storyId\=([0-9]+)).*/', $story_id, $matches );
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
				Enter an NPR Story ID or URL: <input type="text" name="story_id" value="<?php echo esc_attr( $story_id ); ?>" />
				<?php wp_nonce_field( 'npr_cds_nonce_story_id', 'npr_cds_nonce_story_id_field' ); ?>
				<input type="submit" name='createDraft' value="Create Draft" />
				<input type="submit" name='publishNow' value="Publish Now" />
			</form>
		</div>
		</div><?php
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
