<?php
function geocodeThis($address) {
	// Geocode address
	$url = "http://maps.google.com/maps/geo?q=".urlencode($address)."&output=csv&key=".get_option('locatorade_google_maps_api_key');
	
	if ($data = file_get_contents($url)) {
		$result['coordinates'] = parseGeocode($data);
	} else {
		$result['error'] = true;
		$result['error_message'] = "Unable to contact Google Maps servers. Please try again later.";
	}
	
	$result['data'] = $data;
	// Get the distance and send back to browser
	return $result;
}

function parseGeocode($data) {
	$geocode_array = explode(",", $data);
	return array("code" => $geocode_array[0], "latitude" => $geocode_array[2], "longitude" => $geocode_array[3]);
}

function getDistance($coordinates) {
	// Convert coordinates to radians.
	$coordinates[0]['latitude'] /= 57.29577951; 
	$coordinates[0]['longitude'] /= 57.29577951; 
	$coordinates[1]['latitude'] /= 57.29577951; 
	$coordinates[1]['longitude'] /= 57.29577951; 
	
	return round(3963.1* acos(sin($coordinates[0]['latitude'])*sin($coordinates[1]['latitude'])+ cos($coordinates[0]['latitude'])* cos($coordinates[1]['latitude'])* cos($coordinates[0]['longitude']-$coordinates[1]['longitude'])),2);
}
?>