<?php
class Test_NPR_CDS_Class extends WP_UnitTestCase {

	function test_npr_cds_get_pull_post_type() {
		update_option( 'npr_cds_pull_post_type', 'test_post_type' );
		$post_type = NPR_CDS::get_pull_post_type();
		$this->assertEquals( 'test_post_type', $post_type );
	}

	function test_npr_cds_cron_pull() {
		$this->markTestSkipped('Functional test performed by Test_NPR_CDS_Class::test_npr_cds_cron_pull');
	}

	function test_load_page_hook() {
		$this->markTestSkipped('Functional test performed by Test_NPR_CDS_Class::test_load_page_hook');
	}

	function test_NPR_CDS() {
		$test_obj = new NPR_CDS;

		# Should be false when not in admin context
		$this->assertFalse( (bool) has_action( 'load-posts_page_get-npr-stories', [ 'NPR_CDS', 'load_page_hook' ] ) );
		$this->assertFalse( (bool) has_action( 'admin_menu', [ &$test_obj, 'admin_menu' ] ) );

		# Should be true when in admin context
		if ( isset( $GLOBALS['current_screen'] ) ) {
			$tmp = $GLOBALS['current_screen'];
		}
		$GLOBALS['current_screen'] = new FakeAdmin();

		# Re-create the $test_obj after setting admin context
		$test_obj = new NPR_CDS;

		$this->assertTrue( (bool) has_action( 'load-posts_page_get-npr-stories', [ $test_obj, 'load_page_hook' ] ) );
		$this->assertTrue( (bool) has_action( 'admin_menu', [ &$test_obj, 'admin_menu' ] ) );

		# Restore globals
		unset( $GLOBALS['current_screen'] );
		if ( isset ( $tmp ) ) {
			$GLOBALS['current_screen'] = $tmp;
		}
	}

	function test_admin_menu() {
		$this->markTestSkipped('Functional test performed by Test_NPR_CDS_Class::test_NPR_CDS');
	}

}
