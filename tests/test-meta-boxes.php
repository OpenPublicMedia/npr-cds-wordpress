<?php

class Test_MetaBoxes extends WP_UnitTestCase {
	/**
	 * Test the meta box
	 */
	function test_npr_cds_publish_meta_box() {
		$post_id = $this->factory->post->create();
		global $post;
		$tmp = $post;
		$post = get_post( $post_id );
		setup_postdata( $post );
		update_option( 'npr_cds_push_post_type', 'post' );

		# Simple test of output to verify some part of the expected markup is present
		$this->expectOutputRegex('/<div id\="npr\-cds\-publish\-actions"/');
		npr_cds_publish_meta_box( $post );

		// reset
		$post = $tmp;
		wp_reset_postdata();
	}

	/**
	 * Test that the assets for the meta box are registered
	 */
	function test_npr_cds_publish_meta_box_assets() {
		// bare minimum test: the function runs
		npr_cds_publish_meta_box_assets();
		$check = true;
		global $wp_scripts, $wp_styles;
		if ( empty( $wp_scripts->registered['npr_cds_publish_meta_box_script'] ) ) {
			$check = false;
		}
		if ( empty( $wp_styles->registered['npr_cds_publish_meta_box_stylesheet'] ) ) {
			$check = false;
		}
		$this->assertTrue( $check );
	}
}

