google.load("maps", "3", {other_params: "sensor=false"});
function initialize() {
	var myOptions = {
		mapTypeId: google.maps.MapTypeId.ROADMAP
    };
	
    map = new google.maps.Map(document.getElementById("locatorade_map_canvas"), myOptions);
}

function getFormattedLocation() {
	if (google.loader.ClientLocation.address.country_code == "US" && google.loader.ClientLocation.address.region) {
		return google.loader.ClientLocation.address.city + ", " + google.loader.ClientLocation.address.region.toUpperCase();
	} else {
		return  google.loader.ClientLocation.address.city + ", " + google.loader.ClientLocation.address.country_code;
	}
}

jQuery(document).ready(function() {
	locationID = null;
	jQuery(".locatorade_frontend_location").mouseover(function() {
		var newLocationID = jQuery(this).attr('marker_id');
		if (jQuery("#locatorade_map_canvas").length > 0 && locationID != newLocationID) {
			infoWindow.setContent(eval('contentString' + newLocationID));
			infoWindow.open(map,eval('marker' + newLocationID));
			locationID = newLocationID;
		}
	});
	
	// Autofill location search
    if (google.loader.ClientLocation) {
		jQuery("#locatorade_address").val(getFormattedLocation());
    }
	
	// Setup map
	// Afix map to top of page once user scrolls
	if (jQuery("#locatorade_map_canvas").length > 0) {
		initialize();
		
		jQuery(window).scroll(function() {
			if (jQuery(window).scrollTop() - jQuery("#locatorade_map_wrapper").offset().top < 0) {
				jQuery("#locatorade_map_canvas_fixed").css('top', (jQuery(window).scrollTop() - jQuery("#locatorade_map_wrapper").offset().top)*-1 + "px");
			} else {
				jQuery("#locatorade_map_canvas_fixed").css('top', '0px');
			}
		});
	}
});