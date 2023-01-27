<?php

class Test_GetStoriesUi extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->post_id = $this->factory->post->create();
	}

	function test_npr_cds_add_new_story_columns() {
		$ret = npr_cds_add_new_story_columns( [] );
		$this->assertTrue( isset( $ret['update_story'] ) );
	}

	function test_npr_cds_update_column_content() {
		$ret = npr_cds_update_column_content( 'update_story', $this->post_id );
		$this->assertTrue( is_null( $ret ) );

		update_post_meta( $this->post_id, NPR_RETRIEVED_STORY_META_KEY, true );
		npr_cds_update_column_content( 'update_story', $this->post_id );
		$this->expectOutputRegex('/<a href\=".*">.*/');
	}

	function test_npr_cds_bulk_action_update_dropdown() {
		# global $post_type must be set
		global $post_type;
		$post_type = 'post';

		update_option( 'npr_cds_push_url', 'test' );
		$this->expectOutputRegex('/<script type\="text\/javascript">.*/');
		npr_cds_bulk_action_update_dropdown();
	}

	function test_npr_cds_get_stories() {
		$this->expectOutputRegex('/Enter an NPR Story ID or URL: <input type="text" name="story_id"/');
		npr_cds_get_stories();
	}

}
