<?php
/*
Plugin Name: Locatorade
Plugin URI: http://locatorade.com/
Description: Quickly create a top-shelf store locator in minutes.
Version: 1.9.6
Author: David Hudson
Author URI: http://hudsoncs.com/
License: GPL
*/

/*  
	Copyright 2011  David Hudson  (email : david@hudsoncs.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

ini_set('auto_detect_line_endings', true); // For Mac compatibility
register_activation_hook(__FILE__,'locatorade_install');
remove_filter( 'the_content', 'wpautop' );

$locatorade_db_version = "1.0";
$locatorade_version =  vk(get_option( 'locatorade_product_key' )) ? "1.9.6 PRO Edition" : "1.9.6 Free Edition";

// Let's internationalize this biz
add_action( 'init', 'locatorade_language' );

function locatorade_language() {
	load_plugin_textdomain( 'locatorade', false, 'locatorade/languages' );
}

function locatorade_install () {
	global $wpdb;
	global $locatorade_db_version;

	$table_name = $wpdb->prefix . "locatorade_locations";
	if($wpdb->get_var("show tables like '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE `{$table_name}` (
						`id` INT(10) NOT NULL AUTO_INCREMENT,
						`name` TINYTEXT NULL,
						`address1` TEXT NULL,
						`address2` TEXT NULL,
						`city` TINYTEXT NULL,
						`state` TINYTEXT NULL,
						`zip_code` TINYTEXT NULL,
						`phone` TINYTEXT NULL,
						`fax` TINYTEXT NULL,
						`business_hours` TEXT NULL,
						`pin_image` TEXT NULL,
						`tags` TEXT NULL,
						`extras` TEXT NULL,
						`latitude` TEXT NULL,
						`longitude` TEXT NULL,
						PRIMARY KEY id (id)
					)";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	// Setup a page for locatorade search to live
	if (!$wpdb->get_var("SELECT ID FROM {$wpdb->prefix}posts WHERE post_content LIKE '%[locatorade_search]%' AND post_status = 'publish' LIMIT 1")) {
		$locatorade_page = array(
			'post_name' => 'locationsearch',
			'post_title' => 'Location Search',
			'post_content' => '[locatorade_search]',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_category' => array(0),
			'comment_status' => 'closed'
		);

		wp_insert_post( $locatorade_page );
	}
}

// Frontend
if (get_option( 'locatorade_google_maps_api_key' ) != '') {
	add_action('init', 'locatorade_frontend_init');
	add_action('wp_print_scripts', 'locatorade_frontend_init_scripts');
	add_action('wp_print_styles', 'locatorade_frontend_init_styles');
	add_shortcode('locatorade_search', 'locatorade_search_shortcode');
} else {
	add_shortcode('locatorade_search', 'locatorade_google_api_required');
}

function locatorade_google_api_required($attr, $content) {
	return __("Locatorade will not work without a Google Maps API key. <a href='http://code.google.com/apis/maps/signup.html' target='_blank'>Click here to get one.</a>", 'locatorade');
}

function locatorade_frontend_init() {
	$template = get_option( 'locatorade_template' ) == '' ? 'default.css' : get_option( 'locatorade_template' );
	wp_register_style( 'locatoradeStyle', plugins_url() . "/locatorade/styles/templates/{$template}" );
	wp_register_script( 'locatoradejQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js' );
	wp_register_script( 'locatoradeGoogleMaps', 'http://www.google.com/jsapi?key=' . get_option( 'locatorade_google_maps_api_key' ) );
	wp_register_script( 'locatoradeScript', '/wp-content/plugins/locatorade/scripts/frontend.js' );
}

function locatorade_frontend_init_styles() {
   wp_enqueue_style( 'locatoradeStyle' );
}

function locatorade_frontend_init_scripts() {
	wp_enqueue_script( 'locatoradejQuery' );
	wp_enqueue_script( 'locatoradeGoogleMaps' );
	wp_enqueue_script( 'locatoradeScript' );
}

function locatorade_search_shortcode($attr, $content) {
	global $wpdb;
	global $custom_js;

	if (!isset($_REQUEST['locatorade_address'])) {
		$display = "No locations found. Please try another search.";
	} else {		
		// Setup the map
		require_once('classes/geocoder.php');
		
		// Set radius
		if (get_option( 'locatorade_distance_type' ) == 'Miles') {
			$_POST['locatorade_radius'] = isset($_POST['locatorade_radius']) ? $_POST['locatorade_radius'] : 50;
		} else {
			$_POST['locatorade_radius'] = isset($_POST['locatorade_radius']) ? ($_POST['locatorade_radius']/1.6093) : 50;
		}
		
		// Geocode the address
		$result = geocodeThis($_REQUEST['locatorade_address']);
		$coordinates = $result['coordinates'];
		
		if ($_POST['locatorade_tags'] != '') {
			$sql = "SELECT ((ACOS(SIN({$coordinates['latitude']} * PI() / 180) * SIN(latitude * PI() / 180) + COS({$coordinates['latitude']} * PI() / 180) * COS(latitude * PI() / 180) * COS(({$coordinates['longitude']} - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515) AS `distance`, id, name, address1, address2, city, state, zip_code, phone, fax, business_hours, pin_image, latitude, longitude FROM `{$wpdb->prefix}locatorade_locations` WHERE tags LIKE '%{$wpdb->escape($_POST['locatorade_tags'])}%' HAVING `distance` <= {$wpdb->escape($_POST['locatorade_radius'])} ORDER BY `distance` ASC LIMIT 20";
		} else {
			$sql = "SELECT ((ACOS(SIN({$coordinates['latitude']} * PI() / 180) * SIN(latitude * PI() / 180) + COS({$coordinates['latitude']} * PI() / 180) * COS(latitude * PI() / 180) * COS(({$coordinates['longitude']} - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515) AS `distance`, id, name, address1, address2, city, state, zip_code, phone, fax, business_hours, pin_image, latitude, longitude FROM `{$wpdb->prefix}locatorade_locations` HAVING `distance` <= {$wpdb->escape($_POST['locatorade_radius'])} ORDER BY `distance` ASC LIMIT 20";
		}
		
		if (!$locations = $wpdb->get_results($sql, ARRAY_A)) {
			$sql = "SELECT ((ACOS(SIN({$coordinates['latitude']} * PI() / 180) * SIN(latitude * PI() / 180) + COS({$coordinates['latitude']} * PI() / 180) * COS(latitude * PI() / 180) * COS(({$coordinates['longitude']} - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515) AS `distance`, id, name, address1, address2, city, state, zip_code, phone, fax, business_hours, pin_image, latitude, longitude FROM `{$wpdb->prefix}locatorade_locations` WHERE tags LIKE '%{$wpdb->escape($_POST['locatorade_tags'])}%' LIMIT 1";
			$locations = $wpdb->get_results($sql, ARRAY_A);
			
			$display .= __("No locations were found matching your criteria. Here's the closest location available to you.", 'locatorade');
		}

		// Generate map javascript
		$custom_js = <<<EOF
		<script type='text/javascript'>
			jQuery(window).load(function() {
				var bounds = new google.maps.LatLngBounds();
				var userIcon = new google.maps.MarkerImage('https://maps.google.com/mapfiles/ms/micons/blue-pushpin.png');
				var userLatLng = new google.maps.LatLng({$coordinates['latitude']},{$coordinates['longitude']});
				var userMarker = new google.maps.Marker({
					position: userLatLng,
					map: map,
					icon: userIcon,
					title:"Your location"
				});
				bounds.extend(userLatLng);
				map.fitBounds(bounds);
EOF;
		foreach ($locations as $location) {
			$location['name'] = trim($location['name']);
			$location['distance'] = get_option( 'locatorade_distance_type' ) == 'Miles' ? round($location['distance'], 2) . " miles" : round(($location['distance']*1.6093), 2) . " kilometres";
			$location['pin_image'] = $location['pin_image'] == '' ? (get_option( 'locatorade_default_pin_image' ) == '' ? plugins_url() . '/locatorade/images/default_pin.png' : get_option( 'locatorade_default_pin_image' )) : $location['pin_image'];
			$location['url_encoded_location_address'] = urlencode("{$location['address1']} {$location['address2']}, {$location['city']}, {$location['state']} {$location['zip_code']}");
			
			$bubble_information = get_option( 'locatorade_bubble_layout' );
			$bubble_information = str_replace('[[location_name]]', addslashes($location['name']), $bubble_information);
			$bubble_information = str_replace('[[location_address1]]', addslashes($location['address1']), $bubble_information);
			$bubble_information = str_replace('[[location_address2]]', addslashes($location['address2']), $bubble_information);
			$bubble_information = str_replace('[[location_city]]', addslashes($location['city']), $bubble_information);
			$bubble_information = str_replace('[[location_state]]', addslashes($location['state']), $bubble_information);
			$bubble_information = str_replace('[[location_zip_code]]', addslashes($location['zip_code']), $bubble_information);
			$bubble_information = str_replace('[[location_phone]]', addslashes($location['phone']), $bubble_information);
			$bubble_information = str_replace('[[location_fax]]', addslashes($location['fax']), $bubble_information);
			$bubble_information = str_replace('[[location_directions_address]]', addslashes($location['url_encoded_location_address']), $bubble_information);
			$bubble_information = str_replace('[[location_business_hours]]', addslashes($location['business_hours']), $bubble_information);			
			
			$bubble_information = str_replace(array("\r", "\r\n", "\n"), '', $bubble_information);
			
			if (trim($location['latitude']) != '' && trim($location['longitude']) != '') {
				$custom_js .=<<<EOF
				contentString{$location['id']} = '{$bubble_information}';

				infoWindow = new google.maps.InfoWindow({
					content: contentString{$location['id']}
				});
				
				var myLatLng{$location['id']} = new google.maps.LatLng({$location['latitude']},{$location['longitude']});
				var locationIcon{$location['id']} = new google.maps.MarkerImage('{$location['pin_image']}');
				marker{$location['id']} = new google.maps.Marker({
					position: myLatLng{$location['id']},
					map: map,
					icon: locationIcon{$location['id']},
					title:"{$location['name']}, {$location['distance']}"
				});
				
				google.maps.event.addListener(marker{$location['id']}, 'click', function() {
					infoWindow.setContent(contentString{$location['id']});
					infoWindow.open(map,marker{$location['id']});
				});
				
				bounds.extend(myLatLng{$location['id']});
				map.fitBounds(bounds);
EOF;
			}
		}
		$custom_js .=<<<EOF
			});
		</script>
		
EOF;

		add_action('wp_footer', 'add_custom_js');
		
		// Begin map template
		$display .= <<<EOF
		<div id="locatorade_wrapper">
			<div id="locatorade_map_wrapper">
				<div id="locatorade_map_canvas_fixed">
					<div id="locatorade_map_canvas"></div>
				</div>
			</div>
			<div id="locatorade_frontend_locations_wrapper">
				<div class="locatorade_frontend_locations_sidebar">
					<span class="locatorade_frontend_locations_title">Locations</span>
					<div class="locatorade_frontend_locations_container">
EOF;
		
		// The loop
		$search_number = 0;  // This is the count of the current search item
		
		// Pull the loop template
		$locatorade_loop_layout = get_option( 'locatorade_loop_layout' ) == '' ? "<div class='locatorade_search_number'>[[search_number]]</div>\r\n<div class='locatorade_location_information'>\r\n<div class='locatorade_location_name'>[[location_name]]</div>\r\n<div class='locatorade_distance'>Distance: [[location_distance]]</div>\r\n<div class='locatorade_location_address'>\r\n<span class='locatorade_address1'>[[location_address1]]</span> \r\n<span class='locatorade_address2'>[[location_address2]]</span>\r\n<span class='locatorade_address3'><span class='locatorade_city'>[[location_city]]</span>, <span class='locatorade_state'>[[location_state]]</span> <span class='locatorade_zip_code'>[[location_zip_code]]</span></span>\r\n</div>\r\n<div class='locatorade_contact'>\r\n<span class='locatorade_phone'>[[location_phone]]</span>\r\n<span class='locatorade_fax'>[[location_fax]]</span>\r\n</div>\r\n<div class='locatorade_directions'>\r\n<a href='http://maps.google.com/maps?saddr=[[user_address]]&daddr=[[location_directions_address]]'>Directions</a>\r\n</div>\r\n</div>\r\n<div class='locatorade_business_hours'>[[location_business_hours]]</div>\r\n<div class='locatorade_clear'></div>" : get_option( 'locatorade_loop_layout' );
		
		// Start the loop
		foreach ($locations as $location) {
			$location['url_encoded_location_address'] = urlencode("{$location['address1']} {$location['address2']}, {$location['city']}, {$location['state']} {$location['zip_code']}");
			
			if (trim($location['phone']) != "..") $location['phone'] = "<strong>PH:</strong> {$location['phone']}";
			else $location['phone'] = "";
			if (trim($location['fax']) != "..") $location['fax'] = "<strong>FX:</strong> {$location['fax']}";
			else $location['fax'] = "";
			
			$location['distance'] = get_option( 'locatorade_distance_type' ) == 'Miles' ? round($location['distance'], 2) . " miles" : round(($location['distance']*1.6093), 2) . " kilometres";
			
			$search_number++;

			$loop_item = $locatorade_loop_layout;
			$loop_item = str_replace( '[[search_number]]', $search_number, $loop_item );
			$loop_item = str_replace( '[[location_name]]', $location['name'], $loop_item );
			$loop_item = str_replace( '[[location_address1]]', $location['address1'], $loop_item );
			$loop_item = str_replace( '[[location_address2]]', $location['address2'], $loop_item );
			$loop_item = str_replace( '[[location_city]]', $location['city'], $loop_item );
			$loop_item = str_replace( '[[location_state]]', $location['state'], $loop_item );
			$loop_item = str_replace( '[[location_zip_code]]', $location['zip_code'], $loop_item );
			$loop_item = str_replace( '[[location_phone]]', $location['phone'], $loop_item );
			$loop_item = str_replace( '[[location_fax]]', $location['fax'], $loop_item );
			$loop_item = str_replace( '[[user_address]]', $_REQUEST['locatorade_address'], $loop_item );
			$loop_item = str_replace( '[[location_directions_address]]', $location['url_encoded_location_address'], $loop_item );
			$loop_item = str_replace( '[[location_business_hours]]', $location['business_hours'], $loop_item );
			
			if (trim($location['latitude']) == '' || trim($location['longitude']) == '') {
				$loop_item .= '<br />Unable to locate address on map. Not shown above.';
				$loop_item = str_replace( '[[location_distance]]', 'N/A', $loop_item );
			} else {
				$loop_item = str_replace( '[[location_distance]]', $location['distance'], $loop_item );
			}
			
			$display .= "<div class='locatorade_frontend_location' marker_id='{$location['id']}'>{$loop_item}</div>";
		}
	}
	
	$display .= "
					</div><!-- // sidebar -->
				</div><!-- // container -->
			</div><!-- // wrapper -->
		</div><!-- // locatorade wrapper -->		
		";
	
		
	$content = $display;
	return $content;
}

function add_custom_js() {
	global $custom_js;
	echo $custom_js;
}

// Admin
// Create navigation buttons
add_action('admin_menu', 'locatorade_menu');

// Initialize admin page styles
add_action( 'admin_init', 'locatorade_admin_init' );

// Initialize widgets
add_action('widgets_init', create_function('', 'return register_widget("LocatoradeSearchWidget");'));

function locatorade_admin_init() {
	wp_register_style( 'locatoradeAdminStyle', '/wp-content/plugins/locatorade/styles/admin.css' );
	wp_register_script( 'locatoradeAdminjQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js' );
	wp_register_script( 'locatoradeAdminScript', '/wp-content/plugins/locatorade/scripts/admin.js' );
}

function register_locatorade_settings() {
	register_setting( 'locatorade_options_group', 'locatorade_template' );
	register_setting( 'locatorade_options_group', 'locatorade_default_pin_image' );
	register_setting( 'locatorade_options_group', 'locatorade_bubble_layout' );
	register_setting( 'locatorade_options_group', 'locatorade_loop_layout' );
	register_setting( 'locatorade_options_group', 'locatorade_default_location' );
	register_setting( 'locatorade_options_group', 'locatorade_product_key' );
	register_setting( 'locatorade_options_group', 'locatorade_google_maps_api_key' );
	register_setting( 'locatorade_options_group', 'locatorade_distance_type' );
	register_setting( 'locatorade_options_group', '2flj89w8923hadsjfhiuhe7' );
}

function locatorade_admin_styles() {
   wp_enqueue_style( 'locatoradeAdminStyle' );
}

function locatorade_admin_scripts() {
   wp_enqueue_script( 'locatoradeAdminjQuery' );
   wp_enqueue_script( 'locatoradeAdminScript' );
}

function locatorade_menu() {
	$page = add_menu_page('Locatorade', 'Locatorade', 'manage_options', 'Locatorade', 'locatorade_dashboard', WP_PLUGIN_URL . '/locatorade/images/locatorade_menu_icon.png');
	$locations_page = add_submenu_page('Locatorade', 'Locations', 'Locations', 'manage_options', 'locatorade-locations', 'locatorade_locations_page');
	$import_page = add_submenu_page('Locatorade', 'Import', 'Import', 'manage_options', 'locatorade-import', 'locatorade_import_page');
	$settings_page = add_submenu_page('options-general.php', 'Locatorade Settings', 'Locatorade', 'manage_options', __FILE__, 'locatorade_settings_page');
	
	add_action( 'admin_print_styles-' . $page, 'locatorade_admin_styles' );
	add_action( 'admin_print_scripts-' . $page, 'locatorade_admin_scripts' );

	add_action( 'admin_print_styles-' . $locations_page, 'locatorade_admin_styles' );
	add_action( 'admin_print_scripts-' . $locations_page, 'locatorade_admin_scripts' );
	
	add_action( 'admin_print_styles-' . $import_page, 'locatorade_admin_styles' );
	add_action( 'admin_print_scripts-' . $import_page, 'locatorade_admin_scripts' );

	add_action( 'admin_print_styles-' . $settings_page, 'locatorade_admin_styles' );
	add_action( 'admin_print_scripts-' . $settings_page, 'locatorade_admin_scripts' );
	
	add_action( 'admin_init', 'register_locatorade_settings' );
}

add_filter('plugin_action_links', 'locatorade_plugin_action_links', 10, 2);

function locatorade_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=locatorade/locatorade.php">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

function locatorade_dashboard() {
	global $wpdb;
	global $locatorade_version;

	echo "<a href='http://locatorade.com/' target='_top'><img src='https://locatorade.com/wp-content/uploads/2011/12/locatorade_logo_bright_bg.png' /></a><div class='wrap'>";
	echo "<div id='icon-users' class='icon32'></div>";

	$dashboard = __('Dashboard', 'locatorade');
	$locations = __('Locations', 'locatorade');
	$import = __('Import', 'locatorade');
	$settings = __('Settings', 'locatorade');

	echo <<<EOF
	<h2 class="nav-tab-wrapper">
		<a href="admin.php?page=Locatorade" class="nav-tab nav-tab-active">{$dashboard}</a>
		<a href="admin.php?page=locatorade-locations" class="nav-tab">{$locations}</a>
		<a href="admin.php?page=locatorade-import" class="nav-tab">{$import}</a>
		<a href="options-general.php?page=locatorade/locatorade.php" class="nav-tab">{$settings}</a>
	</h2>
	
	<div style='width:100%;border:1px solid #ccc;background-color:#fff;min-height:100px;'>
		<div style='padding:10px;'>
			Welcome to Locatorade v{$locatorade_version}! 
			<p>
				<strong>Quick Start:</strong>
				<ol>
					<li>A WordPress page has already been generated for your location search. Feel free to go to the page, change the title, disable comments, etc.</li>
					<li>Next, choose <em>"Locations"</em> above to begin adding/editing/removing locations.</li>
					<li>Click <em>"Settings"</em> to make some tweaks like changing miles to kilometres or to change your Locatorade style.</li>
				</ol>
			</p>
		</div>
	</div>
	<div>
		<h3>Locatorade Twitter Feed</h3>
		<div id="tweet-container">
		</div>
	</div>
EOF;
	echo "</div>";
}

function locatorade_locations_page() {
	global $wpdb;
	
	// Internationalize some common terms
	$locatorade_name_lang = __("Name", 'locatorade');
	$locatorade_address1_lang = __("Address 1", 'locatorade');
	$locatorade_address2_lang = __("Address 2", 'locatorade');
	$locatorade_city_lang = __("City", 'locatorade');
	$locatorade_state_lang = __("State/Region", 'locatorade');
	$locatorade_zip_code_lang = __("Postal Code", 'locatorade');
	$locatorade_phone_lang = __("Phone", 'locatorade');
	$locatorade_fax_lang = __("Fax", 'locatorade');
	$locatorade_business_hours_lang = __("Business Hours", 'locatorade');
	$locatorade_tags_lang = __("Tags", 'locatorade');
	
	// Are we submitting a form? If so let's process it.
	// Add a location
	if (isset($_POST['locatorade_add_location'])) {
		if (add_location(array('name' => $_POST['locatorade_name'], 'address1' => $_POST['locatorade_address1'], 'address2' => $_POST['locatorade_address2'], 'city' => $_POST['locatorade_city'], 'state' => $_POST['locatorade_state'], 'zip_code' => $_POST['locatorade_zip_code'], 'phone' => $_POST['locatorade_phone'], 'fax' => $_POST['locatorade_fax'], 'business_hours' => $_POST['locatorade_business_hours'], 'tags' => $_POST['locatorade_tags']))) {
			wp_redirect(  admin_url() . "admin.php?page=locatorade-locations&add=success");
			exit();
		} else {
			wp_redirect(  admin_url() . "admin.php?page=locatorade-locations&add=fail&msg=" . urlencode('Couldn\'t add location to database.'));
			exit();
		}
	}
	
	// Update a location
	if (isset($_POST['locatorade_update_location'])) {
		// Check for updated pin error
		if ($_FILES['locatorade_default_pin_image_file']['error'] > 0 && $_FILES['locatorade_default_pin_image_file']['name'] != "") {
			wp_redirect( admin_url() . "admin.php?page=locatorade-locations&cmd=edit_location&id={$_POST['locatorade_id']}&update=fail&msg=" . urlencode('There was an error updating your pin image: ' . $_FILES['locatorade_default_pin_image_file']['error']));
			exit();
		} else {
			$pin_info = wp_handle_upload( $_FILES['locatorade_default_pin_image_file'], array('test_form' => FALSE) );
			$locatorade_default_pin_image = $pin_info['url'];
		}

		// Get updated coordinates
		require_once('classes/geocoder.php');
		$geocodeable_address = trim($_POST['locatorade_address1']) != "" ? $_POST['locatorade_address1'].", " : null;
		$geocodeable_address .= trim($_POST['locatorade_address2']) != "" ? $_POST['locatorade_address2'].", " : null;
		$geocodeable_address .= trim($_POST['locatorade_city']) != "" ? $_POST['locatorade_city'].", " : null;
		$geocodeable_address .= trim($_POST['locatorade_state']) != "" ? $_POST['locatorade_state'].", " : null;
		$geocodeable_address .= trim($_POST['locatorade_zip_code']) != "" ? $_POST['locatorade_zip_code'].", " : null;

		$coordinates = geocodeThis($geocodeable_address);
		
		$sql = "UPDATE {$wpdb->prefix}locatorade_locations SET
					name='{$wpdb->escape($_POST['locatorade_name'])}',
					address1='{$wpdb->escape($_POST['locatorade_address1'])}',
					address2='{$wpdb->escape($_POST['locatorade_address2'])}',
					city='{$wpdb->escape($_POST['locatorade_city'])}',
					state='{$wpdb->escape($_POST['locatorade_state'])}',
					zip_code='{$wpdb->escape($_POST['locatorade_zip_code'])}',
					phone='{$wpdb->escape($_POST['locatorade_phone'])}',
					fax='{$wpdb->escape($_POST['locatorade_fax'])}',
					business_hours='{$wpdb->escape($_POST['locatorade_business_hours'])}',
					pin_image='{$locatorade_default_pin_image}',
					tags='{$wpdb->escape($_POST['locatorade_tags'])}',
					extras='{$locatorade_extras}',
					latitude='{$coordinates['latitude']}',
					longitude='{$coordinates['longitude']}'
					WHERE id={$_POST['locatorade_id']}";
		
		if ($wpdb->query($sql)) {
			wp_redirect(  admin_url() . "admin.php?page=locatorade-locations&cmd=edit_location&id={$_POST['locatorade_id']}&update=success");
			exit();
		} else {
			wp_redirect(  admin_url() . "admin.php?page=locatorade-locations&cmd=edit_location&id={$_POST['locatorade_id']}&update=fail");
			exit();
		}
	}
	
	// Remove a location
	if ($_GET['cmd'] == 'remove_location' && is_numeric($_GET['id'])) {
		$sql = "DELETE FROM {$wpdb->prefix}locatorade_locations WHERE id={$wpdb->escape($_GET['id'])}";
		$wpdb->query($sql);
		
		$sql = "DELETE FROM {$wpdb->prefix}locatorade_extras WHERE location_id={$wpdb->escape($_GET['id'])}";
		$wpdb->query($sql);
		
		$sql = "DELETE FROM {$wpdb->prefix}locatorade_tags WHERE location_id={$wpdb->escape($_GET['id'])}";
		$wpdb->query($sql);
		
		wp_redirect(  admin_url() . "admin.php?page=locatorade-locations&remove=success");
		exit();
	}
	
	echo "<a href='http://locatorade.com/' target='_top'><img src='https://locatorade.com/wp-content/uploads/2011/12/locatorade_logo_bright_bg.png' /></a><div class='wrap'>";
	echo "<div id='icon-edit' class='icon32'></div>";
	
	$dashboard = __('Dashboard', 'locatorade');
	$locations = __('Locations', 'locatorade');
	$import = __('Import', 'locatorade');
	$settings = __('Settings', 'locatorade');
	
	echo <<<EOF
	<h2 class="nav-tab-wrapper">
		<a href="admin.php?page=Locatorade" class="nav-tab">{$dashboard}</a>
		<a href="admin.php?page=locatorade-locations" class="nav-tab nav-tab-active">{$locations}</a>
		<a href="admin.php?page=locatorade-import" class="nav-tab">{$import}</a>
		<a href="options-general.php?page=locatorade/locatorade.php" class="nav-tab">{$settings}</a>
	</h2>
EOF;

	if ($_GET['update'] == 'success') echo "<p class='locatorade_success'>You just updated a location. SWEET!</p>";
	elseif ($_GET['update'] == 'fail') echo "<p class='locatorade_error'>Uh oh, couldn't update location. Something's wrong! {$_GET['msg']}</p>";

	// Edit a location
	if ($_GET['cmd'] == 'edit_location' && is_numeric($_GET['id'])) {
		$sql = "SELECT name, address1, address2, city, state, zip_code, phone, fax, business_hours, pin_image, tags, extras FROM {$wpdb->prefix}locatorade_locations WHERE id={$wpdb->escape($_GET['id'])}";
		$location = $wpdb->get_row($sql, ARRAY_A);
		
		$locatorade_default_pin_image = get_option( 'locatorade_default_pin_image' ) == '' ? plugins_url() . '/locatorade/images/default_pin.png' : get_option( 'locatorade_default_pin_image' );
		$location['pin_image'] = $location['pin_image'] == '' ? (get_option( 'locatorade_default_pin_image' ) == '' ? plugins_url() . '/locatorade/images/default_pin.png' : get_option( 'locatorade_default_pin_image' )) : $location['pin_image'];
		
		$locatorade_update_location_lang = __("Update Location", 'locatorade');
		
		if (vk(get_option( 'locatorade_product_key' ))) $pin_upload = "<tr valign='top'><th scope='row'><label for='locatorade_default_pin_image'>Map Pin</label><img src='{$location['pin_image']}' style='float:right' /></th><td><input type='file' name='locatorade_default_pin_image_file' id='locatorade_default_pin_image_file' class='regular-text' /></td></tr>";

		echo <<<EOF
	<h3>Edit Location</h3>
	<form method='POST' action='admin.php?page=locatorade-locations&noheader=true' id='locatorade' enctype='multipart/form-data'>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><label for="locatorade_name" class="locatorade_req">$locatorade_name_lang</label></th>
				<td>
					<input name="locatorade_name" type="text" id="locatorade_name" value="{$location['name']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_address1" class="locatorade_req">{$locatorade_address1_lang}</label></th>
				<td>
					<input name="locatorade_address1" type="text" id="locatorade_address1" value="{$location['address1']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_address2">{$locatorade_address2_lang}</label></th>
				<td>
					<input name="locatorade_address2" type="text" id="locatorade_address2" value="{$location['address2']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_city" class="locatorade_req">{$locatorade_city_lang}</label></th>
				<td>
					<input name="locatorade_city" type="text" id="locatorade_city" value="{$location['city']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_state" class="locatorade_req">{$locatorade_state_lang}</label></th>
				<td>
					<input name="locatorade_state" type="text" id="locatorade_state" value="{$location['state']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_zip_code">{$locatorade_zip_code_lang}</label></th>
				<td>
					<input name="locatorade_zip_code" type="text" id="locatorade_zip_code" value="{$location['zip_code']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_phone">{$locatorade_phone_lang}</label></th>
				<td>
					<input name="locatorade_phone" type="text" id="locatorade_phone" value="{$location['phone']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_fax">{$locatorade_fax_lang}</label></th>
				<td>
					<input name="locatorade_fax" type="text" id="locatorade_fax" value="{$location['fax']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_business_hours">{$locatorade_business_hours_lang}</label></th>
				<td>
					<input name="locatorade_business_hours" type="text" id="locatorade_business_hours" value="{$location['business_hours']}" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_tags">{$locatorade_tags_lang}</label></th>
				<td>
					<input name="locatorade_tags" type="text" id="locatorade_tags" value="{$location['tags']}" class="regular-text" />
				</td>
			</tr>
			{$pin_upload}
			</tbody>
		</table>
		<input type="hidden" name="locatorade_id" id="locatorade_id" value="{$_GET['id']}" />
		<p class="submit"><input type="submit" name="locatorade_update_location" id="submit" class="button-primary" value="{$locatorade_update_location_lang}"></p>
	</form>
EOF;
		die();
	}
	
	// If we're going to post notices, now's the time!
	if ($_GET['add'] == 'success') echo "<p class='locatorade_success'>" . __("You just added a location. SWEET!", 'locatorade') . "</p>";
	elseif ($_GET['add'] == 'fail') echo "<p class='locatorade_error'>" . __("Uh oh, couldn't add a new location. Something's wrong!", 'locatorade') . "</p>";
	
	if ($_GET['remove'] == 'success') echo "<p class='locatorade_success'>" . __("You just removed a location. Way to go champ!", 'locatorade') . "</p>";

	if ($locations = $wpdb->get_results("SELECT IF(latitude = '' OR longitude = '', true, false) AS latlng_error, id, name, address1, address2, city, state, zip_code, phone, fax, business_hours, latitude, longitude FROM {$wpdb->prefix}locatorade_locations ORDER BY latlng_error DESC, state, city, name", ARRAY_A)) {
		echo <<<EOF
		<table class='widefat'>
			<thead>
				<tr>
					<th>{$locatorade_name_lang}</th>
					<th>{$locatorade_address1_lang}</th>
					<th>{$locatorade_address2_lang}</th>
					<th>{$locatorade_city_lang}</th>
					<th>{$locatorade_state_lang}</th>
					<th>{$locatorade_zip_code_lang}</th>
					<th>{$locatorade_phone_lang}</th>
					<th>{$locatorade_fax_lang}</th>
					<th>{$locatorade_business_hours_lang}</th>
					<th></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th>{$locatorade_name_lang}</th>
					<th>{$locatorade_address1_lang}</th>
					<th>{$locatorade_address2_lang}</th>
					<th>{$locatorade_city_lang}</th>
					<th>{$locatorade_state_lang}</th>
					<th>{$locatorade_zip_code_lang}</th>
					<th>{$locatorade_phone_lang}</th>
					<th>{$locatorade_fax_lang}</th>
					<th>{$locatorade_business_hours_lang}</th>
					<th></th>
				</tr>
			</tfoot>
			<tbody>
EOF;
		foreach ($locations as $location) {
			if ($location['latlng_error']) {
				echo "
				<tr style='background-color:#ffd7d7;'>
					<td><strong>{$location['name']}</strong><br /><strong>ERROR!</strong> <em>Google Maps API unable to find GPS coordinates for given address. Please check address and try again.</em></td>
					<td>{$location['address1']}</td>
					<td>{$location['address2']}</td>
					<td>{$location['city']}</td>
					<td>{$location['state']}</td>
					<td>{$location['zip_code']}</td>
					<td>{$location['phone']}</td>
					<td>{$location['fax']}</td>
					<td>{$location['business_hours']}</td>
					<td><a href='?page=locatorade-locations&cmd=edit_location&id={$location['id']}' class='button-secondary' title='Edit'>Edit</a><a href='?page=locatorade-locations&cmd=remove_location&id={$location['id']}&noheader=true' class='button-secondary' title='Remove'>Remove</a>
				</tr>";
			} else {
				echo "
				<tr>
					<td>{$location['name']}</td>
					<td>{$location['address1']}</td>
					<td>{$location['address2']}</td>
					<td>{$location['city']}</td>
					<td>{$location['state']}</td>
					<td>{$location['zip_code']}</td>
					<td>{$location['phone']}</td>
					<td>{$location['fax']}</td>
					<td>{$location['business_hours']}</td>
					<td><a href='?page=locatorade-locations&cmd=edit_location&id={$location['id']}' class='button-secondary' title='Edit'>Edit</a><a href='?page=locatorade-locations&cmd=remove_location&id={$location['id']}&noheader=true' class='button-secondary' title='Remove'>Remove</a>
				</tr>";
			}
		}
		echo "
			</tbody>
		</table>";
	} else {
		echo "<p class='locatorade_error'>" . __("No locations found. You should probably add some locations?", 'locatorade') . "</p>";
	}
	
	echo <<<EOF
	<h3>Add Location</h3>
	<form method='POST' action='admin.php?page=locatorade-locations&noheader=true' id='locatorade'>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><label for="locatorade_name" class="locatorade_req">{$locatorade_name_lang}</label></th>
				<td>
					<input name="locatorade_name" type="text" id="locatorade_name" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_address1" class="locatorade_req">{$locatorade_address1_lang}</label></th>
				<td>
					<input name="locatorade_address1" type="text" id="locatorade_address1" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_address2">{$locatorade_address2_lang}</label></th>
				<td>
					<input name="locatorade_address2" type="text" id="locatorade_address2" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_city" class="locatorade_req">{$locatorade_city_lang}</label></th>
				<td>
					<input name="locatorade_city" type="text" id="locatorade_city" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_state" class="locatorade_req">{$locatorade_state_lang}</label></th>
				<td>
					<input name="locatorade_state" type="text" id="locatorade_state" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_zip_code">{$locatorade_zip_code_lang}</label></th>
				<td>
					<input name="locatorade_zip_code" type="text" id="locatorade_zip_code" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_phone">{$locatorade_phone_lang}</label></th>
				<td>
					<input name="locatorade_phone" type="text" id="locatorade_phone" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_fax">{$locatorade_fax_lang}</label></th>
				<td>
					<input name="locatorade_fax" type="text" id="locatorade_fax" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_business_hours">{$locatorade_business_hours_lang}</label></th>
				<td>
					<input name="locatorade_business_hours" type="text" id="locatorade_business_hours" value="" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_tags">{$locatorade_tags_lang}</label></th>
				<td>
					<input name="locatorade_tags" type="text" id="locatorade_tags" value="" class="regular-text" />
				</td>
			</tr>
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="locatorade_add_location" id="submit" class="button-primary" value="Add Location"></p>
	</form>
EOF;
	echo "</div>";
}

function locatorade_import_page() {
	global $wpdb;

	$dashboard = __('Dashboard', 'locatorade');
	$locations = __('Locations', 'locatorade');
	$import = __('Import', 'locatorade');
	$settings = __('Settings', 'locatorade');
	$locatorade_import_locations_notice = __("First, some ground rules. To import, you need to use a CSV file. You will need to know the seperator (usually a comma) and the enclosure (usually an apostraphe). The file should probably not be bigger than 1,000 rows or so but it will definitely depend on your server.<br /><br /> The CSV file you use should be in the following order: name, address1, address2, city, state, zip_code, phone, fax, business_hours, tags. No other columns should be in the file. Once you click 'Import Locations', <strong>the import could take several minutes. During this time, do not leave this page and do not click the 'Import Locations' button twice.</strong> <a href='" . plugins_url() . "/locatorade/sample.csv'>Click here to download an example CSV file.</a>", 'locatorade');
	$locatorade_delimiter_lang = __("Delimiter", 'locatorade');
	$locatorade_enclosure_lang = __("Enclosure", 'locatorade');
	$locatorade_file_lang = __("File", 'locatorade');
	
	echo "<a href='http://locatorade.com/' target='_top'><img src='https://locatorade.com/wp-content/uploads/2011/12/locatorade_logo_bright_bg.png' /></a><div class='wrap'>";
	echo "<div id='icon-edit' class='icon32'></div>";
	
	echo <<<EOF
	<h2 class="nav-tab-wrapper">
		<a href="admin.php?page=Locatorade" class="nav-tab">{$dashboard}</a>
		<a href="admin.php?page=locatorade-locations" class="nav-tab">{$locations}</a>
		<a href="admin.php?page=locatorade-import" class="nav-tab nav-tab-active">{$import}</a>
		<a href="options-general.php?page=locatorade/locatorade.php" class="nav-tab">{$settings}</a>
	</h2>
EOF;

	if (!vk(get_option( 'locatorade_product_key' ))) { echo __('Please upgrade to Locatorade PRO to use this feature.'); echo '</div>'; exit(); }
	
	if (isset($_POST['locatorade_import_submit'])) {
		require_once('classes/parsecsv.lib.php');
		$locations = new parseCSV();
		$locations->delimiter = $_POST['locatorade_delimiter'];
		$locations->enclosure = $_POST['locatorade_enclosure'];
		$locations->parse($_FILES['locatorade_file']['tmp_name']);

		echo <<<EOF
		<table class='widefat'>
			<thead>
				<tr>
					<th>Store Name</th>
					<th>Status</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th>Store Name</th>
					<th>Status</th>
				</tr>
			</tfoot>
			<tbody>
EOF;
		
		if (count($locations->data) > 1) {
			set_time_limit(300);
			foreach ($locations->data as $location) {
				$result = add_location($location);
				
				if ($result['error']) {
					echo "<tr><td>{$location['name']}</td><td>Error: {$result['error_message']} (Google Maps Code: {$result['code']})</td></tr>";
				} else {
					echo "<tr><td>{$location['name']}</td><td>Success! (Google Maps Code: {$result['code']})</td></tr>";
				}
			}
		} else {
			echo "<tr><td>Couldn't read the CSV file. Please try again.</td></tr>";
		}
		echo "</tbody></table>";
	} else {
		echo <<<EOF
	<h3>Import Locations</h3>
	<span class='description'>
		{$locatorade_import_locations_notice}
	</span>

	<form method='POST' action='admin.php?page=locatorade-import' id='locatorade' enctype='multipart/form-data'>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><label for="locatorade_delimiter" class="locatorade_req">{$locatorade_delimiter_lang}</label></th>
				<td>
					<input name="locatorade_delimiter" type="text" id="locatorade_delimiter" value="," class="regular-text" style='width:20px;' />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_enclosure" class="locatorade_req">{$locatorade_enclosure_lang}</label></th>
				<td>
					<input name="locatorade_enclosure" type="text" id="locatorade_enclosure" value='&quot;' class="regular-text" style='width:20px;' />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_file" class="locatorade_req">{$locatorade_file_lang}</label></th>
				<td>
					<input name="locatorade_file" type="file" id="locatorade_file" value="" class="regular-text" />
				</td>
			</tr>
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="locatorade_import_submit" id="submit" class="button-primary" value="Import Locations"></p>
	</form>
EOF;
	}
	
	echo "</div>";
}

function locatorade_settings_page() {
	global $wpdb;

	if (isset($_POST['locatorade_update_settings'])) {
		// Let's get the crap out of the post
		$_POST = array_map( 'stripslashes_deep', $_POST );
		
		// Check for new pin upload error
		if ($_FILES['locatorade_default_pin_image_file']['error'] > 0 && $_FILES['locatorade_default_pin_image_file']['name'] != "") {
			wp_redirect( admin_url() . 'options-general.php?page=locatorade/locatorade.php&update=fail&msg=' . urlencode('There was an error updating your pin image: ' . $_FILES['locatorade_default_pin_image_file']['error']));
			exit();
		} else {
			if (file_exists($_FILES['locatorade_default_pin_image_file']['tmp_name'])) {
				$pin_info = wp_handle_upload( $_FILES['locatorade_default_pin_image_file'], array('test_form' => FALSE) );
				$locatorade_default_pin_image = $pin_info['url'];
			} else {
				$locatorade_default_pin_image = get_option( 'locatorade_default_pin_image' );
			}
			
			if (isset($_POST['locatorade_template'])) update_option('locatorade_template', $_POST['locatorade_template']);
			if (isset($locatorade_default_pin_image)) update_option('locatorade_default_pin_image', $locatorade_default_pin_image);
			if (isset($_POST['locatorade_bubble_layout'])) update_option('locatorade_bubble_layout', $_POST['locatorade_bubble_layout']);
			if (isset($_POST['locatorade_loop_layout'])) update_option('locatorade_loop_layout', $_POST['locatorade_loop_layout']);
			if (isset($_POST['locatorade_default_location'])) update_option('locatorade_default_location', $_POST['locatorade_default_location']);
			if (isset($_POST['locatorade_google_maps_api_key'])) update_option('locatorade_google_maps_api_key', $_POST['locatorade_google_maps_api_key']);
			if (isset($_POST['locatorade_distance_type'])) update_option('locatorade_distance_type', $_POST['locatorade_distance_type']);
			
			if (trim($_POST['locatorade_product_key']) == '') {  // If the product key is blank, just let it update as blank
				update_option('locatorade_product_key', $_POST['locatorade_product_key']);
			} elseif (vk($_POST['locatorade_product_key'])) { // If product key isn't blank, attempt to validate as is
				update_option('locatorade_product_key', $_POST['locatorade_product_key']);
			} else { // If product key isn't blank and it doesn't validate, check to see if we're validating around a firewall
				$firewall_validate = explode('|',$_POST['locatorade_product_key']);
	
				if (count($firewall_validate) == 2) {
					if (vk($firewall_validate[0], $firewall_validate[1])) {
						update_option('locatorade_product_key', $firewall_validate[0] . '|' . $firewall_validate[1]);
						update_option('2flj89w8923hadsjfhiuhe7', $firewall_validate[1]);
						wp_redirect( admin_url() . 'options-general.php?page=locatorade/locatorade.php&update=success');
						exit();
					}
				}
				
				wp_redirect( admin_url() . 'options-general.php?page=locatorade/locatorade.php&update=fail&msg=' . urlencode(__('Unable to validate product key. Please try again.')) );
				exit();
			}
			wp_redirect( admin_url() . 'options-general.php?page=locatorade/locatorade.php&update=success');
			exit();
		}
	}
	
	$locatorade_template = get_option( 'locatorade_template' ) == '' ? 'default.css' : get_option( 'locatorade_template' );
	$locatorade_bubble_layout = get_option( 'locatorade_bubble_layout' ) == '' ? "[[location_name]]<br />\r\n[[location_address1]], [[location_address2]]<br />\r\n[[location_city]], [[location_state]]  [[location_zip_code]]<br />\r\n<br />\r\nPH: [[location_phone]]<br />\r\nFX: [[location_fax]]" : get_option( 'locatorade_bubble_layout' );
	$locatorade_loop_layout = get_option( 'locatorade_loop_layout' ) == '' ? "<div class='locatorade_search_number'>[[search_number]]</div>\r\n<div class='locatorade_location_information'>\r\n<div class='locatorade_location_name'>[[location_name]]</div>\r\n<div class='locatorade_distance'>Distance: [[location_distance]]</div>\r\n<div class='locatorade_location_address'>\r\n<span class='locatorade_address1'>[[location_address1]]</span> \r\n<span class='locatorade_address2'>[[location_address2]]</span>\r\n<span class='locatorade_address3'><span class='locatorade_city'>[[location_city]]</span>, <span class='locatorade_state'>[[location_state]]</span> <span class='locatorade_zip_code'>[[location_zip_code]]</span></span>\r\n</div>\r\n<div class='locatorade_contact'>\r\n<span class='locatorade_phone'>[[location_phone]]</span>\r\n<span class='locatorade_fax'>[[location_fax]]</span>\r\n</div>\r\n<div class='locatorade_directions'>\r\n<a href='http://maps.google.com/maps?saddr=[[user_address]]&daddr=[[location_directions_address]]'>Directions</a>\r\n</div>\r\n</div>\r\n<div class='locatorade_business_hours'>[[location_business_hours]]</div>\r\n<div class='locatorade_clear'></div>" : get_option( 'locatorade_loop_layout' );
	$locatorade_default_location = get_option( 'locatorade_default_location' ) == '1' ? ' CHECKED' : '';
	$locatorade_distance_type = get_option( 'locatorade_distance_type' ) == '' ? 'Miles' : get_option( 'locatorade_distance_type' );
	$locatorade_distance_type_mile_default = $locatorade_distance_type == 'Miles' ? ' CHECKED' : '';
	$locatorade_distance_type_km_default = $locatorade_distance_type == 'Kilometres' ? ' CHECKED' : '';
	$locatorade_product_key = get_option( 'locatorade_product_key' );
	$locatorade_google_maps_api_key = get_option( 'locatorade_google_maps_api_key' );
	$locatorade_distance_type = get_option( 'locatorade_distance_type' ) == '' ? 'mile' : get_option( 'locatorade_distance_type' );
	$locatorade_default_pin_image = get_option( 'locatorade_default_pin_image' ) == '' ? plugins_url() . '/locatorade/images/default_pin.png' : get_option( 'locatorade_default_pin_image' );
	
	echo "<a href='http://locatorade.com/' target='_top'><img src='https://locatorade.com/wp-content/uploads/2011/12/locatorade_logo_bright_bg.png' /></a><div class='wrap'>";
	echo "<div id='icon-tools' class='icon32'></div>";

	$dashboard = __('Dashboard', 'locatorade');
	$locations = __('Locations', 'locatorade');
	$import = __('Import', 'locatorade');
	$settings = __('Settings', 'locatorade');

	echo <<<EOF
	<h2 class="nav-tab-wrapper">
		<a href="admin.php?page=Locatorade" class="nav-tab">{$dashboard}</a>
		<a href="admin.php?page=locatorade-locations" class="nav-tab">{$locations}</a>
		<a href="admin.php?page=locatorade-import" class="nav-tab">{$import}</a>
		<a href="options-general.php?page=locatorade/locatorade.php" class="nav-tab nav-tab-active">{$settings}</a>
	</h2>
EOF;

	// Success / failure messages
	if ($_GET['update'] == 'success') echo "<p class='locatorade_success'>" . __('Hooray! Your settings have been updated.', 'locatorade') . "</p>";
	elseif ($_GET['update'] == 'fail') echo "<p class='locatorade_error'>" . __('Updating settings has failed.', 'locatorade') . " {$_GET['msg']}</p>";
	
	$locatorade_product_key_title_lang = __("Product Key", 'locatorade');
	$locatorade_google_maps_api_key_lang = __("Google Maps API Key", 'locatorade');
	$locatorade_template_lang = __("Template", 'locatorade');
	$locatorade_bubble_layout_lang = __("Bubble Layout", 'locatorade');
	$locatorade_loop_layout_lang = __("Location Listing Layout", 'locatorade');
	$locatorade_distance_type_lang = __("Distance Measurement", 'locatorade');
	$locatorade_default_pin_image_lang = __("Default Map Pin", 'locatorade');
	
	$locatorade_product_key_desc = __("PRO users only. <a href='http://locatorade.com/' target='_blank'>Click here to purchase a license.", 'locatorade');
	$locatorade_google_maps_api_key_desc = __("Required. <a href='http://code.google.com/apis/maps/signup.html' target='_blank'>Click here to get one.</a>", 'locatorade');
	$locatorade_bubble_layout_desc = __("This is a template which lays out the \"Information\" or \"Bubble\" windows in Google Maps. If you don't know what to do here, just stick with the default.", 'locatorade');
	$locatorade_loop_layout_desc = __("This is a template which lays out the location listing when a user initiates a search. If you don't know what to do here, just stick with the default.", 'locatorade');
	$locatorade_default_location_option_desc = __("YES!", 'locatorade');
	$locatorade_distance_measurement_mile = __("Miles", 'locatorade');
	$locatorade_distance_measurement_km = __("Kilometres", 'locatorade');
	$locatorade_save_changes = __("Save Changes", 'locatorade');
	
	// Let's pull all of the templates available
    $templates_array = array();

    if ($dh = opendir(WP_PLUGIN_DIR . '/locatorade/styles/templates/')) {
        while (($file = readdir($dh)) !== false) {
            if (!is_dir($file) && preg_match("/\.(css)$/", $file)) {
                array_push($templates_array, $file);
            }
        }
        closedir($dh);
		
		foreach ($templates_array as $template_file) {
			$template_list .= "<option>{$template_file}</option>";
		}
    } else {
        $template_list = "<option>default</option>";
    }

	
	echo <<<EOF
	<form method='POST' action='options-general.php?page=locatorade/locatorade.php&noheader=true' id='locatorade' enctype='multipart/form-data'>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><label for="locatorade_product_key">{$locatorade_product_key_title_lang}</label></th>
				<td>
					<input name="locatorade_product_key" type="text" id="locatorade_product_key" value="{$locatorade_product_key}" class="regular-text" />
					<span class="description">{$locatorade_product_key_desc}</a></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_google_maps_api_key" class="locatorade_req">{$locatorade_google_maps_api_key_lang}</label></th>
				<td>
					<input name="locatorade_google_maps_api_key" type="text" id="locatorade_google_maps_api_key" value="{$locatorade_google_maps_api_key}" class="regular-text" />
					<span class="description">{$locatorade_google_maps_api_key_desc}</span>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">{$locatorade_distance_type_lang}</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span>Distance Measurement</span></legend>
						<label for="locatorade_distance_type_mile">
							<input name="locatorade_distance_type" type="radio" id="locatorade_distance_type_mile" value="Miles"{$locatorade_distance_type_mile_default} />
							{$locatorade_distance_measurement_mile}
						</label><br />
						<label for="locatorade_distance_type_km">
							<input name="locatorade_distance_type" type="radio" id="locatorade_distance_type_km" value="Kilometres"{$locatorade_distance_type_km_default} />
							{$locatorade_distance_measurement_km}
						</label>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_bubble_layout">{$locatorade_bubble_layout_lang}</label></th>
				<td>
					<textarea name="locatorade_bubble_layout" id="locatorade_bubble_layout" style="width:500px;height:200px;">{$locatorade_bubble_layout}</textarea><br />
					<span class="description">{$locatorade_bubble_layout_desc}</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_loop_layout">{$locatorade_loop_layout_lang}</label></th>
				<td>
					<textarea name="locatorade_loop_layout" id="locatorade_loop_layout" style="width:500px;height:200px;">{$locatorade_loop_layout}</textarea><br />
					<span class="description">{$locatorade_loop_layout_desc}</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="locatorade_default_pin_image">Default Map Pin</label><img src='{$locatorade_default_pin_image}' style='float:right' /></th>
				<td>
					<input type="file" name="locatorade_default_pin_image_file" id="locatorade_default_pin_image_file" class="regular-text" /> 
				</td>
			</tr>
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="locatorade_update_settings" id="submit" class="button-primary" value="{$locatorade_save_changes}"></p>
	</form>
	<h1>Styles</h1>
	<form method='POST' action='options-general.php?page=locatorade/locatorade.php&noheader=true' id='locatorade' enctype='multipart/form-data'>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><label for="locatorade_template">{$locatorade_template_lang}</label></th>
				<td>
					<select name="locatorade_template" id="locatorade_template">
						<option selected="selected">{$locatorade_template}</option>
						<option disabled>----</option>
						{$template_list}
					</select>
				</td>
			</tr>
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="locatorade_update_settings" id="submit" class="button-primary" value="{$locatorade_save_changes}"></p>
	</form>
EOF;
	echo "</div>";
}

// WIDGET(S)!
class LocatoradeSearchWidget extends WP_Widget {
    function LocatoradeSearchWidget() {
        parent::WP_Widget(false, $name = 'Locatorade Search');
    }

    function widget($args, $instance) {
		global $wpdb;
		
        extract( $args );
        $locatorade_search_title = apply_filters('widget_title', $instance['locatorade_search_title']);
		
		$locatorade_radius_options = apply_filters('widget_title', $instance['locatorade_radius_options']) == '' ? '10,20,50,100,200' : apply_filters('widget_title', $instance['locatorade_radius_options']);
		
        $locatorade_radius_options_array = explode(',', $locatorade_radius_options);
		
		// Generate radius list
		foreach ($locatorade_radius_options_array as $radius)
			$radius_options .= "<option value='{$radius}'>{$radius} " . get_option( 'locatorade_distance_type' ) . "</option>";

		if (vk(get_option( 'locatorade_product_key' ))) {
			
			// Generate tags list
			$locations_tags_array = $wpdb->get_col("SELECT tags FROM {$wpdb->prefix}locatorade_locations");
			$tags_array = array();
			
			foreach ($locations_tags_array as $locations_tags) {
				$tags = explode(',', $locations_tags);
				
				foreach ($tags as $tag) {
					$tags_array[] = $tag;
				}
			}
			
			$tags_array = array_unique($tags_array);
			
			foreach ($tags_array as $tag) {
				$tags_options .= "<option>{$tag}</option>";
			}
			
			$tags_select = "<select name='locatorade_tags' id='locatorade_tags'><option value=''>All Types</option>{$tags_options}</select>";
		}
		
		$user_address_lang = __("Address, City, State / Postal Code", 'locatorade');
		$locatorade_search_lang = __("Search Locations", 'locatorade');
		
		// Check to see if there's a locatorade page we can use. If not, create one.
		if (!$locatorade_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->prefix}posts WHERE post_content LIKE '%[locatorade_search]%' AND post_status = 'publish' LIMIT 1")) {
			$locatorade_page = array(
				'post_name' => 'locationsearch',
				'post_title' => 'Location Search',
				'post_content' => '[locatorade_search]',
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_category' => array(0),
				'comment_status' => 'closed'
			);

			$locatorade_page_id = wp_insert_post( $locatorade_page );
		}
		
		$locatorade_search_url = get_page_uri($locatorade_page_id);
		
		echo <<<EOF
		{$before_widget}
			{$before_title}{$locatorade_search_title}{$after_title}
			<form method='post' action='{$locatorade_search_url}' name='locatorade_search' id='locatorade_search'>
				<label for='locatorade_address'>{$user_address_lang}</label>
				<input type='text' name='locatorade_address' id='locatorade_address' value='' /> 
				<select name='locatorade_radius' id='locatorade_radius'>
					{$radius_options}
				</select>
				{$tags_select}
				<input type='submit' name='locatorade_search' id='locatorade_search' value='{$locatorade_search_lang}' />
			</form>
		{$after_widget}
EOF;
    }

    function update($new_instance, $old_instance) {
	$instance = $old_instance;
	$instance['locatorade_search_title'] = strip_tags($new_instance['locatorade_search_title']);
	$instance['locatorade_radius_options'] = strip_tags($new_instance['locatorade_radius_options']);
        return $instance;
    }

    function form($instance) {
        $locatorade_search_title = esc_attr($instance['locatorade_search_title']);
        $locatorade_radius_options = esc_attr($instance['locatorade_radius_options']) == '' ? '10,50,100,200' : esc_attr($instance['locatorade_radius_options']);
        ?>
		<p>
			<label for="<?php echo $this->get_field_id('locatorade_search_title'); ?>"><?php _e('Title:'); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id('locatorade_search_title'); ?>" name="<?php echo $this->get_field_name('locatorade_search_title'); ?>" type="text" value="<?php echo $locatorade_search_title; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('locatorade_radius_options'); ?>"><?php _e('Radius Options:'); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id('locatorade_radius_options'); ?>" name="<?php echo $this->get_field_name('locatorade_radius_options'); ?>" type="text" value="<?php echo $locatorade_radius_options; ?>" />
		</p>
        <?php 
    }
}

function add_location($location) {
	global $wpdb;
	
	// Calculate coordinates
	require_once('classes/geocoder.php');
	$geocodeable_address = trim($location['address1']) != "" ? $location['address1'].", " : null;
	$geocodeable_address .= trim($location['address2']) != "" ? $location['address2'].", " : null;
	$geocodeable_address .= trim($location['city']) != "" ? $location['city'].", " : null;
	$geocodeable_address .= trim($location['state']) != "" ? $location['state'].", " : null;
	$geocodeable_address .= trim($location['zip_code']) != "" ? $location['zip_code'].", " : null;

	$geocode_result = geocodeThis($geocodeable_address);
	$result['code'] = $geocode_result['coordinates']['code'];
	
	if ($geocode_result['error']) {
		$result['error'] = true;
		$result['error_message'] = $geocode_result['error_message'];
	} else {
		$coordinates = $geocode_result['coordinates'];
		if ($coordinates['code'] != 200) {
			$result['error'] = true;
			$result['error_message'] = "Failed to access coordinates from Google Maps. ";
		} else {
			$sql = "INSERT INTO {$wpdb->prefix}locatorade_locations (name, address1, address2, city, state, zip_code, phone, fax, business_hours, tags, extras, latitude, longitude)
						VALUES (
						'{$wpdb->escape($location['name'])}',
						'{$wpdb->escape($location['address1'])}',
						'{$wpdb->escape($location['address2'])}',
						'{$wpdb->escape($location['city'])}',
						'{$wpdb->escape($location['state'])}',
						'{$wpdb->escape($location['zip_code'])}',
						'{$wpdb->escape($location['phone'])}',
						'{$wpdb->escape($location['fax'])}',
						'{$wpdb->escape($location['business_hours'])}',
						'{$wpdb->escape($location['tags'])}',
						'{$wpdb->escape($location['extras'])}',
						'{$coordinates['latitude']}',
						'{$coordinates['longitude']}'
						)";
			
			if (!$wpdb->query($sql)) {
				$result['error'] = true;
				$result['error_message'] = "Unable to insert location into the database.";
			}
		}
	}
	
	return $result;
}

function vk($x0b = null, $x0c = null) { 
	$x0b = $x0b == null ? get_option('locatorade_product_key') : $x0b;
	$x0c = $x0c == null ? get_option('2flj89w8923hadsjfhiuhe7') : $x0c;
	$firewall = explode('|', $x0b);
	if (md5($x0b.'UDLRLRSS') == $x0c || md5($firewall[0].'UDLRLRSS') == $firewall[1]) {
		return 1;
	} else {
		$x0d = file_get_contents("http://locatorade.com/validate.php?p={$x0b}&s=" . urlencode(site_url()));
		if ($x0d == '1') {
			update_option('locatorade_product_key', $x0b);
			update_option('2flj89w8923hadsjfhiuhe7', md5($x0b.'UDLRLRSS'));
			return 1;
		}
	}
	return 0;
}
?>