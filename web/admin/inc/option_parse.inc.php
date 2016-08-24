<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Options.php");

require_once("input_validation.inc.php");

function processSubmittedFields($object, $pendingattributes, $eaptype = 0, $device = 0, $silent = 0) {

// construct new array with all non-empty options for later feeding into DB

    $options = [];
    $good = [];
    $bad = [];

    $killlist = $pendingattributes;

    $a = [];

    $optioninfo_object = Options::instance();
// Step 1a: parse the arrays for text-based input

    if (isset($_POST)) {
        if (isset($_POST['option']))
            foreach ($_POST['option'] as $opt_id => $optname)
                $a[$opt_id] = $optname;
        if (isset($_POST['value']))
            foreach ($_POST['value'] as $opt_id => $optvalue)
                $a[$opt_id] = $optvalue;
    }
    if (isset($_FILES) && isset($_FILES['value']) && isset($_FILES['value']['tmp_name']))
        foreach ($_FILES['value']['tmp_name'] as $opt_id => $optfileref)
            $a[$opt_id] = $optfileref;

    /*    ksort($a);
      echo "<pre>This is what we got:";
      print_r($a);
      echo "</pre>"; */

    // following is a helper array to keep track of multilang options that were set in a specific language
    // but are not accompanied by a "default" language setting
    // if the array isn't empty by the end of processing, we need to warn the admin that this attribute
    // is "invisible" in certain languages
    // attrib_name -> boolean

    $multilang_attribs_with_C = [];

    foreach ($a as $obj_id => $obj_value_raw) {
// pick those without dash - they indicate a new value        
        if (preg_match('/^S[0123456789]*$/', $obj_id)) {
            $obj_value = preg_replace('/#.*$/', '', $obj_value_raw);
            $optioninfo = $optioninfo_object->optionType($obj_value);
            if ($optioninfo["flag"] == "ML") {
                if (isset($a["$obj_id-lang"])) {
                    $raw_lang = $a["$obj_id-lang"];
                    if ($raw_lang == "") // user forgot to select a language
                        $lang = "C";
                    else
                        $lang = $raw_lang;
                }
                else {
                    $bad[] = $obj_value;
                    continue;
                }
                // did we get a C language? set corresponding value to TRUE
                if ($lang == "C")
                    $multilang_attribs_with_C[$obj_value] = TRUE;
                else // did we get a C earlier - fine, don't touch the array. Otherwise, set FALSE
                if (!isset($multilang_attribs_with_C[$obj_value]) || $multilang_attribs_with_C[$obj_value] != TRUE)
                    $multilang_attribs_with_C[$obj_value] = FALSE;
            } else
                $lang = "";
            $content = "";
            switch ($optioninfo["type"]) {
                case "string":
                    if (isset($a["$obj_id-0"]) && $a["$obj_id-0"] != "")
                        if ($obj_value == "media:consortium_OI") {
                            $content = valid_consortium_oi($a["$obj_id-0"]);
                            if ($content === FALSE) {
                                $bad[] = $obj_value;
                                continue 2;
                            }
                        } elseif ($obj_value == "media:remove_SSID") {
                            $content = valid_string_db($a["$obj_id-0"]);
                            if ($content == "eduroam") {
                                $bad[] = $obj_value;
                                continue 2;
                            }
                        }
                            else {
                            $content = valid_string_db($a["$obj_id-0"]);
                        }
                    else {
                        continue 2;
                    }
                    break;
                case "text":
                    if (isset($a["$obj_id-1"]) && $a["$obj_id-1"] != "")
                        $content = valid_string_db($a["$obj_id-1"], 1);
                    else {
                        continue 2;
                    }
                    break;
                case "coordinates":
                    if (isset($a["$obj_id-1"]) && $a["$obj_id-1"] != "")
                        $content = valid_coord_serialized($a["$obj_id-1"]);
                    else {
                        continue 2;
                    }
                    break;
                case "file":
// echo "In file processing ...<br/>";
                    if (isset($a["$obj_id-1"]) && $a["$obj_id-1"] != "") { // was already in, by ROWID reference, extract
                        // ROWID means it's a multi-line string (simple strings are inline in the form; so allow whitespace)
                        $content = valid_string_db(urldecode($a["$obj_id-1"]), 1);
                    } else if (isset($a["$obj_id-2"]) && ($a["$obj_id-2"] != "")) { // let's do the download
// echo "Trying to download file:///".$a["$obj_id-2"]."<br/>";
                        $content = downloadFile("file:///" . $a["$obj_id-2"]);
                        if (!check_upload_sanity($obj_value, $content)) {
                            $bad[] = $obj_value;
                            continue 2;
                        }
                        $content = base64_encode($content);
                    } else
                        continue 2;
                    break;
                case "boolean":
                    if (isset($a["$obj_id-3"]) && $a["$obj_id-3"] != "")
                        $content = valid_boolean($a["$obj_id-3"]);
                    else {
                        continue 2;
                    }
                    break;
                default:
                    echo "Internal Error: Unknown option type " . $obj_value . "!";
                    exit(1);
            }
            if ($lang != "" && preg_match("/^ROWID-.*-([0-9]+)/", $content) == 0) { // new value, encode as language array
                // add the new option with lang 
                $options[] = ["$obj_value" => serialize(["lang" => $lang, "content" => $content])];
            } else // just store it (could be a literal value or a ROWID reference)
                $options[] = ["$obj_value" => $content];
        }
    }

    foreach ($options as $option)
        foreach ($option as $optname => $optvalue)
            if ($optname != "eap:ca_url" && $optname != "general:logo_url")
                $good[] = $optname;

    /*
      echo "<pre>";
      print_r($options);
      echo "</pre>";
     */

// Step 2: now we have clean input data. Some attributes need special care
//         2a: if we got eap:ca_url, convert it to eap:ca_file

    foreach ($options as $k => $iterate_option)
        foreach ($iterate_option as $name => $value) {
            if ($name == "eap:ca_url" && $value != "") {
                $content = downloadFile($value);
                unset($options[$k]);
                if (check_upload_sanity("eap:ca_file", $content)) {
                    $content = base64_encode($content);
                    $options[] = ["eap:ca_file" => $content];
                    $good[] = "eap:ca_url";
                } else {
                    $bad[] = $name;
                }
            }
        }

//       2aa: spliteap:ca_file into components
    foreach ($options as $k => $iterate_option)
        foreach ($iterate_option as $name => $value) {
            if ($name == "eap:ca_file" && $value != "" && !preg_match('/^ROWID-/', $value)) {
                $content = base64_decode($value);
                unset($options[$k]);
                $ca_files = X509::splitCertificate($content);
                foreach ($ca_files as $ca_file) {
                    $options[] = ["eap:ca_file" => base64_encode(X509::pem2der($ca_file))];
                }
            }
        }

//          2b:if we got general:logo_url, convert it to general:logo_file
    foreach ($options as $k => $iterate_option)
        foreach ($iterate_option as $name => $value) {
            if ($name == "general:logo_url" && $value != "") {
                $bindata = downloadFile($value);
                unset($options[$k]);
                if (check_upload_sanity("general:logo_file", $bindata)) {
                    $good[] = "general:logo_url";

                    $options[] = ["general:logo_file" => base64_encode($bindata)];
                } else
                    $bad[] = "general:logo_url";
            }
        }

// 3b: convert geo_lat and geo_long to geo_coordinate array

    if (isset($_POST['geo_long']) && isset($_POST['geo_lat']) && $_POST['geo_long'] != "" && $_POST['geo_lat'] != "") {

        $lat = valid_coordinate($_POST['geo_lat']);
        $lon = valid_coordinate($_POST['geo_long']);

        $options[] = ["general:geo_coordinates" => serialize(["lon" => $lon, "lat" => $lat])];
        $good[] = ("general:geo_coordinates");
    }
    /*
      echo "<pre>";
      print_r($options);
      echo "</pre>";
     */

// finally, some attributes are in the DB and were only called by reference
// keep those which are still referenced, throw the rest away

    foreach ($options as $iterate_option)
        foreach ($iterate_option as $name => $value) {
            $optiontype = $optioninfo_object->optionType($name);
            if ($optiontype["type"] == "file" && preg_match("/^ROWID-.*-([0-9]+)/", $value, $retval)) {
                unset($killlist[$retval[1]]);
                continue;
            }
            if ($object instanceof IdP || $object instanceof User || $object instanceof Federation) {
                $object->addAttribute($name, $value);
            } elseif ($object instanceof Profile) {
                if ($device === 0)
                    $object->addAttribute($name, $value, $eaptype);
                else
                    $object->addAttribute($name, $value, $eaptype, $device);
            }
        }

    if ($silent == 0) {
        // don't do your own table - only the <tr>s here
        // list all attributes that were set correctly
        $list = array_count_values($good);
        foreach ($list as $name => $count)
        /// number of times attribute is present, and its name
        /// Example: "5x Support E-Mail"
            echo UI_okay(sprintf(_("%dx %s"), $count, display_name($name)));
        // list all atributes that had errors
        $list = array_count_values($bad);
        foreach ($list as $name => $count)
            echo UI_error(sprintf(_("%dx %s"), $count, display_name($name)));
        // list multilang without default
        foreach ($multilang_attribs_with_C as $attrib_name => $isitsetornot)
            if ($isitsetornot == FALSE)
                echo UI_warning(sprintf(_("You did not set a 'default language' value for %s. This means we can only display this string for installers which are <strong>exactly</strong> in the language you configured. For the sake of all other languages, you may want to edit the profile again and populate the 'default/other' language field."), display_name($attrib_name)));
    }
    return $killlist;
}