<?php
/**
 * NPR API Settings Page and related control methods
 *
 * Also includes the cron jobs.
 */

//require_once( NPRSTORY_PLUGIN_DIR . 'settings_ui.php' );

/**
 * add the options page
 *
 * @see nprstory_publish_meta_box_prompt
 */
function nprstory_add_options_page() {
	add_options_page( 'NPR CDS', 'NPR CDS', 'manage_options', 'ds_npr_api', 'nprstory_api_options_page' );
}
add_action( 'admin_menu', 'nprstory_add_options_page' );

function nprstory_api_options_page() {
	?>
		<style>
			.npr-settings-group {
				display: none;
				padding: 1rem;
				border-bottom: 0.125em solid #808080;
				border-left: 0.125em solid #808080;
				border-right: 0.125em solid #808080;
				margin-right: 1rem;
			}
			.npr-settings-group.active {
				display: block;
			}
			.npr-selector {
				display: grid;
				grid-template-columns: 1fr 1fr 1fr;
				align-content: center;
				justify-content: center;
				margin-right: 1rem;
			}
			.npr-selector div {
				background-color: #ffffff;
				border-top: 0.125em solid #808080;
				border-bottom: 0.125em solid #808080;
				border-left: 0.125em solid #808080;
				border-right: 0.125em solid #808080;
				transition: opacity 0.2s;
				text-align: center;
				font-size: 1.25em;
				padding: 0.5rem;
			}
			.npr-selector div:hover {
				opacity: 0.75;
				cursor: pointer;
			}
			.npr-selector div.active {
				color: #135e96;
				border-bottom: 0.125em solid transparent;
				border-top: 0.125em solid #135e96;
				background-color: #f0f0f1;
			}
		</style>
		<h1>NPR Content Distribution Service Settings</h1>
		<div class="npr-selector">
			<div class="active" data-tab="npr-general">General Settings</div>
			<div data-tab="npr-multi">Get Multi Settings</div>
			<div data-tab="npr-fields">Push Field Mapping</div>
		</div>
		<div class="active npr-settings-group" id="npr-general">
			<form action="options.php" method="post">
				<?php
					settings_fields( 'ds_npr_api' );
					do_settings_sections( 'ds_npr_api' );
					submit_button(); ?>
			</form>
		</div>
		<div class="npr-settings-group" id="npr-multi">
			<h2>Get Multi Settings</h2>
			<p>Create an NPR API query (see the <a target="_" href="https://legacy.npr.org/api/queryGenerator.php">NPR API query generator</a>). Enter your queries into one of the rows below to have stories on that query automatically publish to your site. Please note, you do not need to include your API key to the query.</p>
			<form action="options.php" method="post">
				<?php
					settings_fields( 'ds_npr_api_get_multi_settings' );
					do_settings_sections( 'ds_npr_api_get_multi_settings' );
					submit_button();
				?>
			</form>
		</div>
		<div class="npr-settings-group" id="npr-fields">
			<h2>Push Field Mapping</h2>
			<p>Use this page to map your custom WordPress Meta fields to fields sent to the NPR API, and vice versa. Clicking the <strong>Use Custom Settings</strong> checkbox will enable these mappings. If you wish to use the default mapping for a field, select &mdash; default &mdash; and we will use the obvious WordPress field.</p>
			<p>Select for the Meta fields for the <strong><?php echo nprstory_get_push_post_type(); ?></strong> post type.</p>
			<form action="options.php" method="post">
				<?php
					settings_fields( 'ds_npr_api_push_mapping' );
					do_settings_sections( 'ds_npr_api_push_mapping' );
					submit_button();
				?>
			</form>
		</div>
		<script>
			const nprSections = document.querySelectorAll('.npr-selector > div');
			const nprGroups = document.querySelectorAll('.npr-settings-group');
			Array.from(nprSections).forEach((ns) => {
				ns.addEventListener('click', (evt) => {
					console.log(evt.target);
					var tab = evt.target.getAttribute('data-tab');
					Array.from(nprSections).forEach((nse) => {
						nse.classList.remove('active');
					});
					evt.target.classList.add('active');
					Array.from(nprGroups).forEach((ng) => {
						ng.classList.remove('active');
					});
					document.querySelector('#'+tab).classList.add('active');
				});
			});
		</script>
	<?php
}

function nprstory_add_query_page() {
	$num = get_option( 'ds_npr_num' );
	if ( empty( $num ) ) {
		$num = 1;
	}
	$k = $num;
	$opt = get_option( 'ds_npr_query_' . $k );
	while ( $k < NPR_MAX_QUERIES ) {
		delete_option( 'ds_npr_query_' . $k );
		delete_option( 'ds_npr_query_category_' . $k );
		delete_option( 'ds_npr_query_tags_' . $k );
		delete_option( 'ds_npr_query_publish_' . $k );
		delete_option( 'ds_npr_query_profileTypeID_' . $k );
		$k++;
		$opt = get_option( 'ds_npr_query_' . $k );
	}

	//make sure we remove any queries we didn't want to use
	if ( !empty( $num ) && $num < NPR_MAX_QUERIES ) {
		$k = $num;
		$opt = get_option( 'ds_npr_query_' . $k );
		while ( $k < NPR_MAX_QUERIES ) {
			delete_option( 'ds_npr_query_' . $k );
			delete_option( 'ds_npr_query_category_' . $k );
			delete_option( 'ds_npr_query_tags_' . $k );
			delete_option( 'ds_npr_query_publish_' . $k );
			delete_option( 'ds_npr_query_profileTypeID_' . $k );
			$k++;
		}
	}
	add_options_page( 'Auto Fetch from the NPR API settings',
		'NPR API Get Multi', 'manage_options',
		'ds_npr_api_get_multi_settings',
		'nprstory_api_get_multi_options_page' );
}
// add_action( 'admin_menu', 'nprstory_add_query_page' );

function nprstory_settings_init() {
	add_settings_section( 'ds_npr_api_settings', 'General Settings', 'nprstory_api_settings_callback', 'ds_npr_api' );

	add_settings_field( 'ds_npr_api_key', 'CDS Token', 'nprstory_api_key_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_api_key', 'nprstory_validation_callback_api_key' );

	add_settings_field( 'ds_npr_api_pull_url', 'Pull URL', 'nprstory_api_pull_url_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_api_pull_url', 'nprstory_validation_callback_pull_url' );

	add_settings_field( 'ds_npr_api_push_url', 'Push URL', 'nprstory_api_push_url_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_api_push_url', 'nprstory_validation_callback_push_url' );

	add_settings_field( 'ds_npr_api_org_id', 'Org ID', 'nprstory_api_org_id_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_api_org_id', 'nprstory_validation_callback_api_key' );

	add_settings_field( 'dp_npr_query_use_featured', 'Theme uses Featured Image', 'nprstory_query_use_featured_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api_settings', 'dp_npr_query_use_featured' , 'nprstory_validation_callback_checkbox');

	add_settings_section( 'ds_npr_api_get_multi_settings', 'NPR API multiple get settings', 'nprstory_api_get_multi_settings_callback', 'ds_npr_api_get_multi_settings' );

	add_settings_field( 'ds_npr_num', 'Number of things to get', 'nprstory_api_num_multi_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings' );
	register_setting( 'ds_npr_api_get_multi_settings', 'ds_npr_num', 'intval' );

	add_settings_field( 'ds_npr_pull_post_type', 'NPR Pull Post Type', 'nprstory_pull_post_type_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_pull_post_type', 'nprstory_validation_callback_select' );

	add_settings_field( 'ds_npr_push_post_type', 'NPR Push Post Type', 'nprstory_push_post_type_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_push_post_type' , 'nprstory_validation_callback_select');

	add_settings_field( 'ds_npr_story_default_permission', 'NPR Permissions', 'nprstory_push_story_permissions_callback', 'ds_npr_api', 'ds_npr_api_settings' );
	register_setting( 'ds_npr_api', 'ds_npr_story_default_permission' );

	/**
	 * Create a number of forms based off of the number of queries that are registered
	 */
	$num =  get_option( 'ds_npr_num' );
	if ( empty( $num ) ) {
		$num = 5;
	}

	$optionType = get_option( 'ds_npr_pull_post_type' );

	for ( $i = 0; $i < $num; $i++ ) {
		add_settings_field( 'ds_npr_query_' . $i, 'Query String ' . $i, 'nprstory_api_query_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings', $i );
		register_setting( 'ds_npr_api_get_multi_settings', 'ds_npr_query_' . $i , 'nprstory_validation_callback_url');

		// Add ProfileTypeIDs
		add_settings_field( 'ds_npr_query_profileTypeID_' . $i, 'Add Profile Type IDs for querystring ' . $i, 'ds_npr_api_query_profileTypeID_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings', $i );
		register_setting( 'ds_npr_api_get_multi_settings', 'ds_npr_query_profileTypeID_' . $i, 'nprstory_validation_callback_url' );

		//ds_npr_query_publish_
		add_settings_field( 'ds_npr_query_publish_' . $i, 'Publish Stories ' . $i, 'nprstory_api_query_publish_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings', $i );
		register_setting( 'ds_npr_api_get_multi_settings', 'ds_npr_query_publish_' . $i , 'nprstory_validation_callback_select');

		if ( $optionType == "post" ) {
			// Select Category
			add_settings_field( 'ds_npr_query_category_' . $i, 'Select a default Category if using Post', 'nprstory_api_select_category_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings', $i );
			register_setting( 'ds_npr_api_get_multi_settings', 'ds_npr_query_category_' . $i );
		}

		// Add tags
		add_settings_field( 'ds_npr_query_tags_' . $i, 'Add Tags ' . $i, 'ds_npr_api_query_tags_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings', $i );
		register_setting( 'ds_npr_api_get_multi_settings', 'ds_npr_query_tags_' . $i );
	}

	add_settings_field( 'dp_npr_query_run_multi', 'Run the queries on saving changes', 'nprstory_query_run_multi_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings' );
	register_setting( 'ds_npr_api_get_multi_settings', 'dp_npr_query_run_multi' , 'nprstory_validation_callback_checkbox');

	add_settings_field( 'dp_npr_query_multi_cron_interval', 'Interval to run Get Multi cron', 'nprstory_query_multi_cron_interval_callback', 'ds_npr_api_get_multi_settings', 'ds_npr_api_get_multi_settings' );
	register_setting( 'ds_npr_api_get_multi_settings', 'dp_npr_query_multi_cron_interval', 'intval' );
}
add_action( 'admin_init', 'nprstory_settings_init' );

function nprstory_api_settings_callback() { }

function nprstory_add_cron_interval( $schedules ) {
	$ds_interval = get_option( 'dp_npr_query_multi_cron_interval' );
	//if for some reason we don't get a number in the option, use 60 minutes as the default.
	if ( !is_numeric( $ds_interval ) || $ds_interval < 1 ) {
		$ds_interval = 60;
		update_option( 'dp_npr_query_multi_cron_interval', 60 );
	}
	$new_interval = $ds_interval * 60;
	$schedules['ds_interval'] = [
	  'interval' => $new_interval,
	  'display' => __( 'DS Cron, run Once every ' . $ds_interval . ' minutes' )
	];
	return $schedules;
}
add_filter( 'cron_schedules', 'nprstory_add_cron_interval' );

function nprstory_api_get_multi_settings_callback() {
	$run_multi = get_option( 'dp_npr_query_run_multi' );
	if ( $run_multi ) {
		DS_NPR_API::nprstory_cron_pull();
	}

	//change the cron timer
	if ( wp_next_scheduled( 'npr_ds_hourly_cron' ) ) {
		wp_clear_scheduled_hook( 'npr_ds_hourly_cron' );
	}
	nprstory_error_log( 'NPR Story API plugin: updating the npr_ds_hourly_cron event timer' );
	wp_schedule_event( time(), 'ds_interval', 'npr_ds_hourly_cron');
}

function nprstory_query_run_multi_callback() {
	$run_multi = get_option( 'dp_npr_query_run_multi' );
	$check_box_string = "<input id='dp_npr_query_run_multi' name='dp_npr_query_run_multi' type='checkbox' value='true' ";

	if ( $run_multi ) {
		$check_box_string .= ' checked="checked" ';
	}
	$check_box_string .= "/>";

	echo nprstory_esc_html( $check_box_string );
	wp_nonce_field( 'nprstory_nonce_ds_npr_query_run_multi', 'nprstory_nonce_ds_npr_query_run_multi_name', true, true );
}

function nprstory_query_use_featured_callback() {
	$use_featured = get_option( 'dp_npr_query_use_featured' );
	$check_box_string = '<input id="dp_npr_query_use_feature" name="dp_npr_query_use_featured" type="checkbox" value="true"';

	if ( $use_featured ) {
		$check_box_string .= ' checked="checked"';
	}
	$check_box_string .= " />";

	echo nprstory_esc_html( '<p>' . $check_box_string . " If your theme uses the featured image, checking this box will remove the lead image from imported posts.</p>" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_query_use_feature', 'nprstory_nonce_ds_npr_query_use_feature_name', true, true );
}

function nprstory_query_multi_cron_interval_callback() {
	$option = get_option( 'dp_npr_query_multi_cron_interval' );
	echo nprstory_esc_html( "<input type='number' value='$option' name='dp_npr_query_multi_cron_interval' id='dp_npr_query_multi_cron_interval' /> <p> How often, in minutes, should the Get Multi function run?  (default = 60)" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_query_multi_cron_interval', 'nprstory_nonce_ds_npr_query_multi_cron_interval_name', true, true );
}

function nprstory_api_query_publish_callback( $i ) {
	$selected = get_option( 'ds_npr_query_publish_' . $i );

	echo nprstory_esc_html( "<div>Publish or Draft the returns from Query " . $i . "? <select id=" . 'ds_npr_query_publish_' . $i . " name=" . 'ds_npr_query_publish_' . $i . ">" );

	// echo '<option value=""> &mdash; Select &mdash; </option>';
	$keys = [ "Publish", "Draft" ];
	foreach ( $keys as $key ) {
		$option_string = "\n<option  ";
		if ($key == $selected) {
			$option_string .= " selected ";
		}
		$option_string .=   "value='" . esc_attr( $key ) . "'>" . esc_html( $key ) . " </option>";
		echo nprstory_esc_html( $option_string );
	}
	$option_string .= wp_nonce_field( 'nprstory_nonce_ds_npr_query_publish_' . $i, 'nprstory_nonce_ds_npr_query_publish_' . $i . '_name', true, false );
	echo "</select> </div>";
}

function nprstory_api_select_category_callback( $i ) {
	$selected = get_option( 'ds_npr_query_category_' . $i );
	settype( $selected, "integer" );
	$args = [
		'show_option_none'	=> __( 'Select category', '' ),
		'name'				=> 'ds_npr_query_category_' . $i,
		'hierarchical'		=> true,
		'show_count'		=> 0,
		'orderby'			=> 'name',
		'echo'				=> 0,
		'selected'			=> $selected,
		'hide_empty'		=> 0,
		'multiple'			=> true
	];
	$select = wp_dropdown_categories( $args );

	echo nprstory_esc_html( $select );
}

function nprstory_api_query_callback( $i ) {
	$option = get_option( 'ds_npr_query_' . $i );
	$name = 'ds_npr_query_' . $i;
	echo nprstory_esc_html( "<input type='text' value='$option' name='$name' style='width: 300px;' />" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_query_' . $i, 'nprstory_nonce_ds_npr_query_' . $i . '_name', true, true );

}
function ds_npr_api_query_tags_callback( $i ) {
	$name = 'ds_npr_query_tags_' . $i;
	$option = get_option( $name );

	echo nprstory_esc_html( "<input type='text' value='$option' name='$name' style='width: 300px;' /> <p> Add tag(s) to each story pulled from NPR (comma separated).</p>" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_tags_' . $i, 'nprstory_nonce_ds_npr_tags_' . $i . '_name', true, true );
	echo "<p><hr></p>";
}

// profile type id
function ds_npr_api_query_profileTypeID_callback( $i ) {
	$name = 'ds_npr_query_profileTypeID_' . $i;
	$option = get_option( $name, '1' );

	echo nprstory_esc_html( "<input type='text' value='$option' name='$name' style='width: 300px;' /> <p>***Optional Profile ID Type(s) to each story pulled from NPR (comma separated). Default is 1 (story), 15 (podcast episodes) also available.</p>" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_profileidtype_' . $i, 'nprstory_nonce_ds_npr_profileidtype_' . $i . '_name', true, true );
	echo "<p><hr></p>";
}

function nprstory_api_num_multi_callback() {
	$option = get_option( 'ds_npr_num' );
	echo nprstory_esc_html( "<input type='number' value='$option' name='ds_npr_num' /> <p> Increase the number of queries by changing the number in the field above." );
	wp_nonce_field( 'nprstory_nonce_ds_npr_num', 'nprstory_nonce_ds_npr_num_name', true, true );
}

function nprstory_api_key_callback() {
	$option = get_option( 'ds_npr_api_key' );
	echo nprstory_esc_html( "<input type='text' value='$option' name='ds_npr_api_key' style='width: 300px;' />" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_api_key', 'nprstory_nonce_ds_npr_api_key_name', true, true );
}

function nprstory_api_pull_url_callback() {
	$option = get_option( 'ds_npr_api_pull_url' );
	echo nprstory_esc_html( "<input type='text' value='$option' name='ds_npr_api_pull_url' style='width: 300px;' />" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_api_pull_url', 'nprstory_nonce_ds_npr_api_pull_url_name', true, true );
}

function nprstory_api_push_url_callback() {
	$option = get_option( 'ds_npr_api_push_url' );
	echo nprstory_esc_html( "<input type='text' value='$option' name='ds_npr_api_push_url' style='width: 300px;' />" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_api_push_url', 'nprstory_nonce_ds_npr_api_push_url_name', true, true );
}

function nprstory_api_org_id_callback() {
	$option = get_option( 'ds_npr_api_org_id' );
	echo nprstory_esc_html( "<input type='text' value='$option' name='ds_npr_api_org_id' style='width: 300px;' />" );
	wp_nonce_field( 'nprstory_nonce_ds_npr_api_org_id', 'nprstory_nonce_ds_npr_api_org_id_name', true, true );
}

function nprstory_pull_post_type_callback() {
	$post_types = get_post_types();
	nprstory_show_post_types_select( 'ds_npr_pull_post_type', $post_types );
}

function nprstory_push_post_type_callback() {
	$post_types = get_post_types();
	nprstory_show_post_types_select( 'ds_npr_push_post_type', $post_types );
	echo nprstory_esc_html( '<div> If you change the Push Post Type setting remember to update the mappings for API Fields at <a href="' . admin_url( 'options-general.php?page=ds_npr_api_push_mapping' ) . '">NPR API Field Mapping</a> page.</div>' );
}

function nprstory_push_story_permissions_callback() {
	$permissions_groups = nprstory_get_permission_groups();

	if ( !empty( $permissions_groups ) ) {
		nprstory_show_perms_select( 'ds_npr_story_default_permission', $permissions_groups );
		echo '<div>This is where you select the default permissions group to use when pushing stories to the NPR API.</div>';
	} else {
		echo '<div>You have no Permission Groups defined with the NPR API.</div>';
	}
}

/**
* create the select widget where the Id is the value in the array
* @param  $field_name
* @param  $keys - an array like (1=>'Value1', 2=>'Value2', 3=>'Value3');
*/
function nprstory_show_post_types_select( $field_name, $keys ) {
	$selected = get_option( $field_name );

	echo nprstory_esc_html( "<div><select id=" . $field_name . " name=" . $field_name . ">" );

	echo '<option value=""> &mdash; Select &mdash; </option>';
	foreach ( $keys as $key ) {
		$option_string = "\n<option  ";
		if ( $key == $selected ) {
			$option_string .= " selected ";
		}
		$option_string .=   "value='" . esc_attr( $key ) . "'>" . esc_html( $key ) . " </option>";
		echo nprstory_esc_html( $option_string );
	}
	echo "</select> </div>";
	wp_nonce_field( 'nprstory_nonce_' . $field_name, 'nprstory_nonce_' . $field_name . '_name', true, true );
}

/**
 * create the select widget where the ID for an element is the index to the array
 * @param  $field_name
 * @param  $keys an array like (id1=>'Value1', id2=>'Value2', id3=>'Value3');
 */
function nprstory_show_perms_select( $field_name, $keys ) {
	$selected = get_option( $field_name );
	echo nprstory_esc_html( "<div><select id=" . $field_name . " name=" . $field_name . ">" );

	echo '<option value=""> &mdash; Select &mdash; </option>';
	foreach ( $keys as $id => $key ) {
		$option_string = "\n<option  ";
		if ( $id == $selected ) {
			$option_string .= " selected ";
		}
		$option_string .=   "value='" . esc_attr( $id ) . "'>" . esc_html( $key['name'] ) . " </option>";
		echo nprstory_esc_html( $option_string );
	}
	echo "</select> </div>";
	wp_nonce_field( 'nprstory_nonce_' . $field_name, 'nprstory_nonce_' . $field_name . '_name', true, true );
}
