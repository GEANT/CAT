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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

/**
 * This class provides map display functionality
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class MapOpenLayers extends AbstractMap {

    public function __construct($inst, $readonly) {
        parent::__construct($inst, $readonly);
        return $this;
    }
    
    public function htmlHeadCode() {
        $cat = new \core\CAT();
        return "
        <link href='../external/OpenLayers/ol.css' rel='stylesheet' type='text/css'>
        <script src='../external/OpenLayers/ol.js'></script>
        <script src='../lib/admin/ol_drag.js'></script>
        <script>
        var addressService = 'https://nominatim.openstreetmap.org/search'; // the address search service
        var map; // the main map
        var markersArray = new Array(); // holds  all saved locations
        var extent; // the boundng box for locations
        var selectedMarker; // used to pass information about market to be identified
        var jmarkers; // set in the sorrounding PHP script as a json array to pass saved locations
        var markersSource = new ol.source.Vector(); // the vector source for locations
        var tmpSource = new ol.source.Vector(); // the vector source for temporaty markers
        var icon = new ol.style.Icon({ // the main location icon
            opacity: 1,
            src: '../resources/images/icons/location_marker.png'
        });

        var icon_selected = new ol.style.Icon({ // the main icon highlighted
            opacity: 1,
            src: '../resources/images/icons/location_marker_highlighted.png'
        });        

        var circle =  new ol.style.Circle({ // the temporatu icon
          radius: 10,
          stroke: new ol.style.Stroke({
            color: 'white',
            width: 2
          }),
          fill: new ol.style.Fill({
            color: 'green'
          })
        });
        
// use HTML5 geolocation
        function locateMe() {
            $('#address').val(\"" . _("locating") . "\");
            navigator.geolocation.getCurrentPosition(locate_succes,locate_fail,{maximumAge:3600000, timeout:5000});
        }
        
// on geolocation success set variables and show the temporaty marker
        function locate_succes(p) {
            $('#address').val('');
            $('#geo_long').val(p.coords.longitude);
            $('#geo_lat').val(p.coords.latitude);
            showTmpPointer(p.coords.longitude, p.coords.latitude);
        }
        
//geolocation failure
        function locate_fail(p) {
            $('#address').val('');
            alert('failure: '+p.message);
        }
        
// highlight a saved location pointed by the index
        function show_location(j) {
            m = markersArray[j];
            selectedMarker = j;
            m.setStyle(new ol.style.Style({image: icon_selected}));
            setTimeout('clear_icon(selectedMarker)', 1000);
        }
        
// remove location highlighting
        function clear_icon(j) {
            m = markersArray[j];
            m.setStyle(new ol.style.Style({image: icon}));
        }

// used to set locations icons
        function markersStyle(feature) {
            var style = new ol.style.Style({
                image: icon});
            return [style];
        }
        
// devine the markers layer
        var markersLayer = new ol.layer.Vector({
            source: markersSource,
            style: markersStyle
        });
        
// used to set temorary icons
        function tmpStyle(feature) {
            var style = new ol.style.Style({
                image: circle});
            return [style];
        }
// the temporary layer        
        var tmpLayer = new ol.layer.Vector({
            source: tmpSource,
            style: tmpStyle
        });
                
// Declare a Tile layer with an OSM source
        var osmLayer = new ol.layer.Tile({
            source: new ol.source.OSM()
        });
        
// set the markers for saved locations
        function addMarkers(jm) {
            locations = JSON.parse(jm);
            var locArray = new Array();
            var i = 0;
            var loc;
            var marker;
            for (i = 0; i < locations.length; i++) {
                loc = ol.proj.transform([Number(locations[i].lon), Number(locations[i].lat)], 'EPSG:4326', 'EPSG:3857');
                marker = new ol.Feature({geometry: new ol.geom.Point(loc)});
                markersSource.addFeature(marker);
                markersArray.push(marker);
                locArray.push(loc);
            }
            extent = ol.extent.boundingExtent(locArray);
        }
        
// set and display a temporary pointer clearing any old ones first
        function showTmpPointer(lon, lat) {
            tmpSource.clear()
            loc = ol.proj.transform([Number(lon), Number(lat)], 'EPSG:4326', 'EPSG:3857');
            marker = new ol.Feature({geometry: new ol.geom.Point(loc)});
            tmpSource.addFeature(marker);
            view = map.getView();
            view.setCenter(loc);
            view.setZoom(16);
        }
        
        function setTmpPointer(coord) {
            var lonlat = ol.proj.transform(coord, 'EPSG:3857', 'EPSG:4326');
            $('#geo_long').val(lonlat[0]);
            $('#geo_lat').val(lonlat[1]);
        }
        
        function MapOpenLayersDeleteCoord(j) {
        markersSource.removeFeature(markersArray[j - 1]);
        }

// the main map display funtion
        function generateMap(mapName) {
        // Instanciate a Map, set the object target to the map DOM id
            map = new ol.Map({
                controls: ol.control.defaults().extend([
                    new ol.control.FullScreen()
                ]),
                interactions: ol.interaction.defaults().extend([new app.Drag()]),
                target: mapName
            });
            var view = new ol.View();
            map.addLayer(osmLayer);
            map.addLayer(markersLayer);
            map.addLayer(tmpLayer);
            if (jmarkers !== undefined) { // no locations saved
                addMarkers(jmarkers, markersSource);
                view.setMaxZoom(14);
                view.fit(extent, {padding: [10, 0, 10, 0]});
                map.setView(view);
                view.fit(extent, {padding: [10, 0, 10, 0]});
            } else {
                view.setCenter([0,0]);
                locate_country('" . $cat->knownFederations[strtoupper($this->fedName)] . "'); // use the federation code to locate the country
                map.setView(view);
            }
            view.setMaxZoom(20);
        } 
        
// get the country center from the location service
        function locate_country(country) {
            $.get(addressService, {format: 'json', country: country, addressdetails: 0}, function(data) {
                if (data[0] === undefined) {
                    alert('Sorry, this error in locating your country should not have happened, please report it.');
                    return;
                }
                showTmpPointer(data[0].lon, data[0].lat);
                map.getView().setZoom(7);
            }, 'json');
        }

// get the location form the geocodig service
        function getAddressLocation() {
            var city = $('#address').val();
            if(city == '') {
                alert(\"" . _("nothing entered in the address field") . "\");
                return false;
            }
            city = city.replace(/\s*,\s*/g,',+');
            city = city.replace(/ +/,'+');
            $.get(addressService+'?format=json&addressdetails=0&q='+city, '',  function(data) {
                if (data[0] === undefined) {
                    alert('" . _("Address not found, perhaps try another form, like putting the street number to the front.") . "');
                    return;
                }
                showTmpPointer(data[0].lon, data[0].lat);
                map.getView().setZoom(16);
                $('#geo_long').val(data[0].lon);
                $('#geo_lat').val(data[0].lat);
            }, 'json');
        }
        
        "  .
        '$(document).ready(function () {
            $(".location_button").click(function (event) {
                event.preventDefault();
                marker_index = $(this).attr("id").substr(11) - 1;
                show_location(marker_index);
            });

            $("#address").keypress(function (event) {
                if (event.which === 13) {
                    event.preventDefault();
                    getAddressLocation();
                }

            });
        });' .
        "</script>
        ";
    }

    public function htmlBodyCode() {

        return "";
    }

    public function htmlShowtime($wizard = FALSE, $additional = FALSE) {
        if ($this->readOnly) {
            return "<div id='map' class='locationmap'></div><script>generateMap('map')</script>";
        } else {
            return $this->htmlPreEdit($wizard, $additional) . $this->findLocationHtml() . "<div id='map' class='locationmap'></div><script>generateMap('map')</script>" . $this->htmlPostEdit(FALSE);
        }
    }

    public function bodyTagCode() {
        // your magic here
        return "";
    }

    public static function optionListDisplayCode($coords, $number) {
        return "<button id='location_b_" . $number . "' class='location_button'>" . _("Click to see location") . " $number</button>";
    }
    
    private function findLocationHtml() {
        return "<p>" . _("Address:") . " <input name='address' id='address' /><button type='button' onclick='getAddressLocation()'>" . _("Find address") . "</button> <button type='button' onclick='locateMe()'>" . _("Locate Me!") . "</button></p>";
    }
}
