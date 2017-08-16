<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\admin;

?>
<?php

// embed this into a page which should display the geo widget
// needs to be called twice:
//   in <head>, insert javascript voodoo
//   in <body>, insert <div>
// and should have $inst_country, $inst_name set to a meaningful name of a site to locate
?>
<?php

/**
 * helper class which defines Google Maps geo widgets and their integration into
 * the admin-side UI.
 */
class GeoWidget {

    /**
     * generates JavaScript code to be embedded in <head> of pages which need a
     * GeoWidget.
     * @param string $inst_country two-digit country identifier where the IdP is in
     * @param string $inst_name name of institution
     * @return string the code for <head>
     */
    public function insertInHead($inst_country, $inst_name) {
        $cat = new \core\CAT();
        return "<script type='text/javascript' src='https://maps.googleapis.com/maps/api/js?key=".CONFIG['APPEARANCE']['google_maps_api_key']."'></script>
    <script type='text/javascript'>
        // some global variables;
        var center_lat=49.6114885608729;
        var center_lng=6.135778427124023;
        var zoom=15;
        var mode;
        var map;
        var marker;
        var geocoder;
        var icon;
        var icon_red;
        var center_map=true;
        var marks = [];
        var markers;
        var marker_index;

        /**
         * xml parser function
         * replaces a Google v2 builtin
         */
        function parse_xml(s) { 
            if (window.DOMParser)
            {
                parser=new DOMParser();
                xml=parser.parseFromString(s,'text/xml');
            }
            else // Internet Explorer
            {
                xml=new ActiveXObject('Microsoft.XMLDOM');
                xml.async=false;
                xml.loadXML(s);
            }
            return xml;
        }


        /**
         *  Loceate country center
         */

        function locate_country(country) {
            geocoder.geocode({'address':country},function(r,status) {
                if(status != google.maps.GeocoderStatus.OK) {
                    alert('Sorry, this error in locating your country should not have happened, please report it.');
                } else {
                    addMarker(r[0].geometry.location,0,r[0].geometry.bounds);
                }
            });
        }

        /**
         * Guess location based on ist name and country
         *
         */
        function locator_magic() {
            geocoder.geocode({'address':\"" . preg_replace("/\"/", "&quot;", $inst_name) . "\", 'region':\"" . strtolower($inst_country) . "\"},
            function(r,status) {
                if(status != google.maps.GeocoderStatus.OK) {
                    locate_country(\"" . $cat->knownFederations[strtoupper($inst_country)] . "\");
                } else {
                    var i;
                    for(i = 0; i < r.length; i++) {
                        Addr = getAddressElements(r[i].address_components);
                        if(Addr.country == \"" . strtoupper($inst_country) . "\")
                        break;
                    }
                    if(Addr.country != \"" . strtoupper($inst_country) . "\")
                    locate_country(\"" . $cat->knownFederations[strtoupper($inst_country)] . "\");
                    else {
                        addMarker(r[i].geometry.location,15,null);
                    }
                }
            });
        }

        /**
         * click event listener
         */
        function markerClicked(m) {
            info_window.close();
            var t = \"" . _("This is location ") . "\"+m.info;
            info_window.setContent(t);
            info_window.setPosition(m.getPosition());
            info_window.open(map,m);
        }

        function getAddressElements(addr) {
            var A = new Object();
            var l1 ='';
            A.locality = '';
            A.country = '';
            for(i=0;i< addr.length;i++) {
                if(addr[i].types[0] == 'locality')
                    A.locality = addr[i].short_name;
                if(addr[i].types[0] == 'administrative_area_level_3' )
                    l1 = addr[i].short_name;
                if(addr[i].types[0] == 'country' ){
                    A.country = addr[i].short_name;
                    A.country_long = addr[i].long_name;
                }
            }
            if(A.locality == '')
                A.locality = l1;
            return(A);
        }

        /**
         * get geo cordinates and address data
         * update coordinate and address text fields
         */
        function updateLocation(latlng) {
            if(latlng == null)
                latlng = map.getCenter();
            geocoder.geocode({'location':latlng},function(r,status){
                if (status != google.maps.GeocoderStatus.OK) 
                    return;
                var z = map.getZoom();
                var addr;
                var addr_string;
                $('#geo_long').val(latlng.lng());
                $('#geo_lat').val(latlng.lat());
                var Addr = getAddressElements(r[0].address_components);
                if (z> 8 && Addr.locality != '')
                    $('#address').val(Addr.locality+', '+Addr.country);
                else
                    $('#address').val(Addr.country_long);
            });
        }

        /**
         * add new draggeable marker
         */

        function addMarker(latlng,z,bounds) {
            if(marker != undefined)
                marker.setMap(null);
            marker = new google.maps.Marker({position: latlng, map: map,draggable: true});
            google.maps.event.addListener(marker,'dragend', function() {
                updateLocation(marker.getPosition());
            });
            if(bounds == null) {
                map.setCenter(latlng);
                map.setZoom(z);
                updateLocation(latlng);
            } else {
                google.maps.event.addListenerOnce(map, 'bounds_changed', function(event) {
                    updateLocation(null);
                });
                map.fitBounds(bounds);
            }
        }

        /**
         * create a marker from the address entered
         * by the user
         */
        function getAddressLocation() {
            var city = $('#address').val();
            if(city == '') {
                alert(\"" . _("nothing entered in the address field") . "\");
                return false;
            }
            geocoder.geocode( { 'address': city}, function(results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    position = results[0].geometry.location;
                } else {
                    position = center_pl;
                    alert('Unable to locate address: ' + status);
                    zoom = 6;
                }
                addMarker(position,zoom,null);
                return false;
            });
        }

        /**
         * trigger geolocation
         */
        function locateMe() {
            $('#address').val(\"" . _("locating") . "\");
            navigator.geolocation.getCurrentPosition(locate_succes,locate_fail,{maximumAge:3600000, timeout:5000});
        }

        function locate_succes(p) {
            var point = new google.maps.LatLng(p.coords.latitude,p.coords.longitude);
            addMarker(point,15,null);
        }
        function locate_fail(p) {
            alert('failure: '+p.message);
        }


        /**
         * add locations from the markers structure
         */
        function addLocations() {
            if(markers == undefined)
                return(0);
            google.maps.event.addListenerOnce(map, 'bounds_changed', function(event) {
                z = map.getZoom();
                if(mode == 0)
                    map.setZoom(z+1);
                map.setOptions({maxZoom: 21})
            });
            xml = parse_xml(markers);
            var mrk = xml.documentElement.getElementsByTagName('marker');
            var i = 0;
            var  area = new google.maps.LatLngBounds();
            for (i = 0; i < mrk.length; i++) {
                var point = new google.maps.LatLng(parseFloat(mrk[i].getAttribute('lat')),
                parseFloat(mrk[i].getAttribute('lng')));
                m = new google.maps.Marker({position: point,icon: icon, map: map});
                m.info = mrk[i].getAttribute('name');
                marks.push(m)
                google.maps.event.addListener(m,'click', function() {
                    markerClicked(this);
                });
                area.extend(point);
            }
            if(mode == 1)
                map.setOptions({maxZoom: 18})
            else
                map.setOptions({maxZoom: 15})
            map.fitBounds(area);
        }

        /**
         * Google maps innitialize function
         * if mode == 1 then enable editing;
         */
        function load(l) {
            mode = l;
            if(mode == 0 && markers == undefined)
                return;
            geocoder = new google.maps.Geocoder();
            var myOptions = {
                zoom: 3,
                center: new google.maps.LatLng(center_lat,center_lng),
                streetViewControl: false,
                mapTypeControl: false,
                zoomControl: false,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            }

            icon = new google.maps.MarkerImage('../resources/images/icons/location_marker.png',
            new google.maps.Size(40,24),  new google.maps.Point(0,0),  new google.maps.Point(14,0));
            icon_red = new google.maps.MarkerImage('../resources/images/icons/location_marker_highlighted.png',
            new google.maps.Size(40,24),  new google.maps.Point(0,0),  new google.maps.Point(14,0));
            info_window = new google.maps.InfoWindow({disableAutoPan: true,});
            map = new google.maps.Map(document.getElementById('map'),myOptions);
            if(mode == 1) 
                map.setOptions({zoomControl: true});

            if(addLocations() == 0)
                locator_magic();
        }
    </script>";
    }

    /**
     * generates HTML code to display a geo widget. Needs preceding code in <head>,
     * see above.
     * @param boolean $wizard Are we in wizard mode? Be more talkative then.
     * @param boolean $additional is this about an additional (non-first) location?
     * @return string the HTML code
     */
    public function insertInBody($wizard, $additional) {
        $retval = "<fieldset class='option_container'>
        <legend><strong>" . _("Location") . "</strong></legend>";

        if ($wizard) {
            $retval .= "<p>" .
            _("The user download interface (see <a href='../'>here</a>), uses geolocation to suggest possibly matching IdPs to the user. The more precise you define the location here, the easier your users will find you.") .
            "</p>
                     <ul>" .
            _("<li>Drag the marker in the map to your place, or</li>
<li>enter your street address in the field below for lookup, or</li>
<li>use the 'Locate Me!' button</li>") .
            "</ul>
                     <strong>" .
            _("We will use the coordinates as indicated by the marker for geolocation.") .
            "</strong>";
        }
        if ($additional) {
            $retval .= _("You can enter an <strong>additional</strong> location here. You can see the already defined locations in the 'General Information' field.");
        }
        $retval .= "<p>" . _("Address:") . " <input name='address' id='address' /><button type='button' onclick='getAddressLocation()'>" . _("Find address") . "</button> <button type='button' onclick='locateMe()'>" . _("Locate Me!") . "</button></p>";

        $retval .= "            <div class='googlemap' id='map'></div>";
        $retval .= "<br/>" . _("Latitude:") . " <input style='width:80px' name='geo_lat' id='geo_lat' readonly>" . _("Longitude:") . " <input name='geo_long' id='geo_long' style='width:80px' readonly>";
        $retval .= "        </fieldset>";
        
        return $retval;
    }

}
