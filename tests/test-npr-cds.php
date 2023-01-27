<?php

class Test_NprCds extends WP_UnitTestCase {

	function test_npr_cds_activation() {
		npr_cds_activation();

		$result = wp_next_scheduled( 'npr_cds_hourly_cron' );
		$this->assertTrue( ! empty( $result ) );

		$option = get_option( 'npr_cds_num' );
		$this->assertEquals( $option, 5 );

		$option = get_option( 'npr_cds_pull_url' );
		$this->assertEquals( $option, 'https://content.api.npr.org' );
	}

	function test_npr_cds_activate() {
		$this->markTestSkipped(
			'Functional test of npr_cds_activate performed by Test_NprCds::test_npr_cds_activation');
	}

	function test_npr_cds_deactivation() {
		npr_cds_deactivation();

		$result = wp_next_scheduled( 'npr_cds_hourly_cron' );
		$this->assertTrue( empty( $result ) );

		$option = get_option( 'npr_cds_num', false );
		$this->assertFalse( $option );

		$option = get_option( 'npr_cds_pull_url', false );
		$this->assertFalse( $option );
	}

	function test_npr_cds_deactivate() {
		$this->markTestSkipped(
			'Functional test of npr_cds_deactivate performed by Test_NprCds::test_npr_cds_deactivation');
	}

	function test_npr_cds_show_message() {
		$test_message = 'Test message';
		ob_start();
		npr_cds_show_message( $test_message, false );
		$result = ob_get_clean();
		$this->assertTrue( (bool) strstr( $result, $test_message ) );
		ob_flush();

		ob_start();
		npr_cds_show_message( $test_message, true );
		$result = ob_get_clean();
		ob_flush();
		$this->assertTrue( (bool) strstr( $result, 'class="error"' ) );
	}

	function test_npr_cds_create_post_type() {
		npr_cds_create_post_type();
		$post_types = get_post_types();
		$this->assertTrue( in_array( NPR_POST_TYPE, $post_types ) );
	}

}
