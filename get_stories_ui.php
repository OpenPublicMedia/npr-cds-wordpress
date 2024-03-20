<?php
/**
 * Functions related to the user interface for fetching stories from the API
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// Add the update story column to the page listing the posts for the pull-type
add_filter( 'manage_edit-' . NPR_CDS::get_pull_post_type() . '_columns', 'npr_cds_add_new_story_columns' );

function npr_cds_add_new_story_columns( $cols ) {
	$cols['update_story'] = 'Update Story';
	return $cols;
}

add_action( 'manage_posts_custom_column', 'npr_cds_update_column_content', 10, 2 );

function npr_cds_update_column_content( string $column_name, int $post_ID ): void {
	if ( $column_name == 'update_story' ) {
		$retrieved = get_post_meta( $post_ID, NPR_RETRIEVED_STORY_META_KEY, true );
		if ( $retrieved ) {
			$api_id = get_post_meta( $post_ID, NPR_STORY_ID_META_KEY, TRUE );
			echo npr_cds_esc_html( '<a href="' . admin_url( 'edit.php?page=get-npr-stories&story_id=' .$api_id ) . '"> Update </a>' );
		}
	}
}

function npr_cds_bulk_action_update_dropdown(): void {
	$pull_post_type = NPR_CDS::get_pull_post_type();
	global $post_type;
	if ( $post_type == $pull_post_type ) {
		printf(
			'<script>jQuery(document).ready(function($) {$("<option>").val("updateNprStory").text("%s").appendTo("select[name=\'action\']");$("<option>").val("updateNprStory").text("%s").appendTo("select[name=\'action2\']");});</script>',
			__( 'Update NPR Story', 'npr-content-distribution-service' ),
			__( 'Update NPR Story', 'npr-content-distribution-service' )
		);
	}
}
add_action( 'admin_print_footer_scripts', 'npr_cds_bulk_action_update_dropdown' );
//do the new bulk action
add_action( 'load-edit.php', 'npr_cds_bulk_action_update_action' );

/**
 * @throws Exception
 */
function npr_cds_bulk_action_update_action(): void {
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
				// @todo: why do some posts have no ID
				// @todo: oh, it's only imported drafts that don't have an ID
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

function npr_cds_get_stories(): void {
	$api_key = NPR_CDS_WP::get_cds_token();
	$pull_url = NPR_CDS_PULL_URL;
?>
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