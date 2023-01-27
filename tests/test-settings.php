<?php
class Test_Settings extends WP_UnitTestCase {

	function test_npr_cds_add_options_page() {
		npr_cds_add_options_page();
		set_current_screen( 'options-general.php?page=npr_cds' );
		$this->assertTrue( is_admin() );
	}

	function test_npr_cds_settings_init() {
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	function test_npr_cds_add_cron_interval() {
		$test_schedules = [];
		$ret = npr_cds_add_cron_interval( $test_schedules );
		$this->assertTrue( isset( $ret['ds_interval'] ) );
	}

	function test_npr_cds_get_multi_settings_callback() {
		npr_cds_get_multi_settings_callback();
		$schedules = wp_get_schedule( 'npr_cds_hourly_cron' );
		$this->assertTrue( !empty( $schedules ) );
	}

	function test_npr_cds_query_run_multi_callback() {
		# Simple test of output -- expect an input with id dp_npr_query_run_multi
		$this->expectOutputRegex('/<input id\="npr_cds_query_run_multi".*/');
		npr_cds_query_run_multi_callback();
	}

	function test_npr_cds_query_multi_cron_interval_callback() {
		# Simple test of outut -- should output an input element with a label matching below
		$this->expectOutputRegex('/<input.*How often, in minutes, should the Get Multi function run\?  \(default \= 60\)/');
		npr_cds_query_multi_cron_interval_callback();
	}

	function test_npr_cds_query_callback() {
		# Output test -- make sure passed parameter is used in output of input element
		$i = 0;
		$default = [ 'filters' => 'test', 'sorting' => 'test', 'publish' => 'test', 'category' => 'test', 'tags' => 'test' ];
		update_option( 'npr_cds_query_' . $i, $default );
		$this->expectOutputRegex(
			'/<input type="text" value="test" name="npr_cds_query_0\[filters\]".*/');
		npr_cds_query_callback( $i );
	}

	function test_npr_cds_num_multi_callback() {
		update_option( 'npr_cds_num', 'test' );
		// this should really be a number
		$this->expectOutputRegex('/<p><input type="number" value="test" name="npr_cds_num".*/');
		npr_cds_num_multi_callback();
	}

	function test_npr_cds_token_callback() {
		update_option( 'npr_cds_token', 'test' );
		$this->expectOutputRegex('/<input type\="text" value\="test" name\="npr_cds_token".*/');
		npr_cds_token_callback();
	}

	function test_npr_cds_pull_url_callback() {
		update_option( 'npr_cds_pull_url', 'test' );
		$this->expectOutputRegex('/.*<input type\="text" name\="npr_cds_pull_url_other" id\="npr_cds_pull_url_other" value\="test".*/');
		npr_cds_pull_url_callback();
	}

	function test_npr_cds_push_url_callback() {
		update_option( 'npr_cds_push_url', 'test' );
		$this->expectOutputRegex('/.*<input type\="text" name\="npr_cds_push_url_other" id\="npr_cds_push_url_other" value\="test".*/');
		npr_cds_push_url_callback();
	}

	function test_npr_cds_org_id_callback() {
		update_option( 'npr_cds_org_id', 'test' );
		$this->expectOutputRegex('/<input type="text" value="test" name="npr_cds_org_id".*/');
		npr_cds_org_id_callback();
	}

	function test_npr_cds_prefix_callback() {
		update_option( 'npr_cds_prefix', 'test' );
		$this->expectOutputRegex('/<input type="text" value="test" name="npr_cds_prefix".*/');
		npr_cds_prefix_callback();
	}

	function test_npr_cds_pull_post_type_callback() {
		$field_name = 'npr_cds_pull_post_type';
		$this->expectOutputRegex('/<div><select id\="' . $field_name . '" name\="' . $field_name . '".*/');
		npr_cds_pull_post_type_callback();
	}

	function test_npr_cds_push_post_type_callback() {
		$field_name = 'npr_cds_push_post_type';
		$this->expectOutputRegex('/<div><select id\="' . $field_name . '" name\="' . $field_name . '".*/');
		npr_cds_push_post_type_callback();
	}

	function test_npr_cds_show_post_types_select() {
		$field_name = 'test_field';
		update_option( $field_name, 'test_value' );
		$this->expectOutputRegex('/<div><select id\="' . $field_name . '" name="' . $field_name . '".*/');
		npr_cds_show_post_types_select( $field_name, [] );
	}
}
