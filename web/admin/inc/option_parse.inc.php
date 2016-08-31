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

function postProcessValidAttribtues($options, &$good, &$bad) {
    foreach ($options as $index => $iterateOption) {
        foreach ($iterateOption as $name => $value) {
            switch ($name) {
                case "eap:ca_url": // eap:ca_url becomes eap:ca_file by downloading the file
                    if (empty($value)) {
                        break;
                    }
                    $content = downloadFile($value);
                    unset($options[$index]);
                    if (check_upload_sanity("eap:ca_file", $content)) {
                        $content = base64_encode($content);
                        $options[] = ["eap:ca_file" => $content];
                        $good[] = $name;
                    } else {
                        $bad[] = $name;
                    }
                    break;
                case "eap:ca_file": // CA files get split (PEM files can contain more than one CA cert)
                    // the data being processed here is always "good": 
                    // if it was eap:ca_file initially then its sanity was checked in step 1;
                    // if it was eap:ca_url then it was checked after we downloaded it
                    if (empty($value) || preg_match('/^ROWID-/', $value)) {
                        break;
                    }
                    $content = base64_decode($value);
                    unset($options[$index]);
                    $cAFiles = X509::splitCertificate($content);
                    foreach ($cAFiles as $ca_file) {
                        $options[] = ["eap:ca_file" => base64_encode(X509::pem2der($ca_file))];
                    }
                    $good[] = $name;
                    break;
                case "general:logo_url": // logo URLs become logo files by downloading the file
                    if (empty($value)) {
                        break;
                    }
                    $bindata = downloadFile($value);
                    unset($options[$index]);
                    if (check_upload_sanity("general:logo_file", $bindata)) {
                        $good[] = $name;
                        $options[] = ["general:logo_file" => base64_encode($bindata)];
                    } else {
                        $bad[] = $name;
                    }
                    break;
                default:
                    $good[] = $name; // all other options were checked and are sane in step 1 already
                    break;
            }
        }
    }
    return $options;
}

function postProcessCoordinates($options, &$good) {
    if (!empty($_POST['geo_long']) && !empty($_POST['geo_lat'])) {

        $lat = valid_coordinate($_POST['geo_lat']);
        $lon = valid_coordinate($_POST['geo_long']);

        $options[] = ["general:geo_coordinates" => serialize(["lon" => $lon, "lat" => $lat])];
        $good[] = ("general:geo_coordinates");
    }
    return $options;
}

function displaySummaryInUI($good, $bad, $multilangAttribsWithC) {
    $retval = "";
    // don't do your own table - only the <tr>s here
    // list all attributes that were set correctly
    $listGood = array_count_values($good);
    foreach ($listGood as $name => $count) {
        /// number of times attribute is present, and its name
        /// Example: "5x Support E-Mail"
        $retval .= UI_okay(sprintf(_("%dx %s"), $count, display_name($name)));
    }
    // list all atributes that had errors
    $listBad = array_count_values($bad);
    foreach ($listBad as $name => $count) {
        $retval .= UI_error(sprintf(_("%dx %s"), $count, display_name($name)));
    }
    // list multilang without default
    foreach ($multilangAttribsWithC as $attribName => $isitsetornot) {
        if ($isitsetornot == FALSE) {
            $retval .= UI_warning(sprintf(_("You did not set a 'default language' value for %s. This means we can only display this string for installers which are <strong>exactly</strong> in the language you configured. For the sake of all other languages, you may want to edit the profile again and populate the 'default/other' language field."), display_name($attribName)));
        }
    }
    return $retval;
}

function collatePostArrays() {
    $iterator = [];
    if (!empty($_POST['option'])) {
        foreach ($_POST['option'] as $optId => $optname) {
            $iterator[$optId] = $optname;
        }
        if (!empty($_POST['value'])) {
            foreach ($_POST['value'] as $optId => $optvalue) {
                $iterator[$optId] = $optvalue;
            }
        }
    }
    if (!empty($_FILES['value']['tmp_name'])) {
        foreach ($_FILES['value']['tmp_name'] as $optId => $optfileref) {
            $iterator[$optId] = $optfileref;
        }
    }
    return $iterator;
}

function processSubmittedFields($object, $pendingattributes, $eaptype = 0, $device = 0, $silent = 0) {

// construct new array with all non-empty options for later feeding into DB

    $optionsStep1 = [];
    $multilangAttribsWithC = [];
    $good = [];
    $bad = [];

    $killlist = $pendingattributes;

    $optioninfoObject = Options::instance();

    // Step 1: collate option names, option values and uploaded files (by 
    // filename reference) into one array for later handling
    
    $iterator = collatePostArrays();
    
    // TODO brave new PHP7 world would do instead:
    // $optionarray = $_POST['option'] ?? [];
    // $valuearray = $_POST['value'] ?? [];
    // $filesarray = $_FILES['value']['tmp_name'] ?? [];
    // $iterator = array_merge($optionarray, $valuearray, $filesarray);

    // following is a helper array to keep track of multilang options that were set in a specific language
    // but are not accompanied by a "default" language setting
    // if the array isn't empty by the end of processing, we need to warn the admin that this attribute
    // is "invisible" in certain languages
    // attrib_name -> boolean

    foreach ($iterator as $objId => $objValueRaw) {
// pick those without dash - they indicate a new value        
        if (preg_match('/^S[0123456789]*$/', $objId)) {
            $objValue = preg_replace('/#.*$/', '', $objValueRaw);
            $optioninfo = $optioninfoObject->optionType($objValue);
            $lang = "";
            if ($optioninfo["flag"] == "ML") {
                if (isset($iterator["$objId-lang"])) {
                    if (!isset($multilangAttribsWithC[$objValue])) { // on first sight, initialise the attribute as "no C language set"
                        $multilangAttribsWithC[$objValue] = FALSE;
                    }
                    $lang = $iterator["$objId-lang"];
                    if ($lang == "") { // user forgot to select a language
                        $lang = "C";
                    }
                } else {
                    $bad[] = $objValue;
                    continue;
                }
                // did we get a C language? set corresponding value to TRUE
                if ($lang == "C") {
                    $multilangAttribsWithC[$objValue] = TRUE;
                }
            }
            $content = "";
            switch ($optioninfo["type"]) {
                case "string":
                    if (!empty($iterator["$objId-0"])) {
                        switch ($objValue) {
                            case "media:consortium_OI":
                                $content = valid_consortium_oi($iterator["$objId-0"]);
                            if ($content === FALSE) {
                                $bad[] = $objValue;
                                continue 3;
                            }
                            break;
                            case "media:remove_SSID":
                                $content = valid_string_db($iterator["$objId-0"]);
                            if ($content == "eduroam") {
                                $bad[] = $objValue;
                                continue 3;
                            }
                            break;
                            default:
                                $content = valid_string_db($iterator["$objId-0"]);
                                break;
                        }
                        break;
                    }
                    continue 2;
                case "text":
                    if (!empty($iterator["$objId-1"])) {
                        $content = valid_string_db($iterator["$objId-1"], 1);
                        break;
                    }
                    continue 2;
                case "coordinates":
                    if (!empty($iterator["$objId-1"])) {
                        $content = valid_coord_serialized($iterator["$objId-1"]);
                        break;
                    }
                    continue 2;
                case "file":
// echo "In file processing ...<br/>";
                    if (!empty($iterator["$objId-1"])) { // was already in, by ROWID reference, extract
                        // ROWID means it's a multi-line string (simple strings are inline in the form; so allow whitespace)
                        $content = valid_string_db(urldecode($iterator["$objId-1"]), 1);
                        break;
                    } else if (isset($iterator["$objId-2"]) && ($iterator["$objId-2"] != "")) { // let's do the download
// echo "Trying to download file:///".$a["$obj_id-2"]."<br/>";
                        $content = downloadFile("file:///" . $iterator["$objId-2"]);
                        if (!check_upload_sanity($objValue, $content)) {
                            $bad[] = $objValue;
                            continue 2;
                        }
                        $content = base64_encode($content);
                        break;
                    }
                    continue 2;

                case "boolean":
                    if (!empty($iterator["$objId-3"])) {
                        $content = valid_boolean($iterator["$objId-3"]);
                        break;
                    }
                    continue 2;
                case "integer":
                    if (!empty($iterator["$objId-4"])) {
                        $content = valid_integer($iterator["$objId-4"]);
                        break;
                    }
                    continue 2;
                default:
                    throw new Exception("Internal Error: Unknown option type " . $objValue . "!");
            }
            if ($lang != "" && preg_match("/^ROWID-.*-([0-9]+)/", $content) == 0) { // new value, encode as language array
                // add the new option with lang 
                $optionsStep1[] = ["$objValue" => serialize(["lang" => $lang, "content" => $content])];
            } else { // just store it (could be a literal value or a ROWID reference)
                $optionsStep1[] = ["$objValue" => $content];
            }
        }
    }

// Step 2: now we have clean input data. Some attributes need special care:
// URL-based attributes need to be downloaded to get their actual content
// CA files may need to be split (PEM can contain multiple CAs 

    $optionsStep2 = postProcessValidAttributes($optionsStep1, $good, $bad);


// Step 3: coordinates do not follow the usual POST array as they are two values forming one attribute

    $options = postProcessCoordinates($optionsStep2, $good);

    foreach ($options as $iterateOption) {
        foreach ($iterateOption as $name => $value) {
            $optiontype = $optioninfoObject->optionType($name);
            // some attributes are in the DB and were only called by reference
            // keep those which are still referenced, throw the rest away
            if ($optiontype["type"] == "file" && preg_match("/^ROWID-.*-([0-9]+)/", $value, $retval)) {
                unset($killlist[$retval[1]]);
                continue;
            }
            switch (get_class($object)) {
                case Profile:
                    if ($device !== 0) {
                    $object->addAttributeDeviceSpecific($name, $value, $device);
                } elseif ($eaptype != 0) {
                    $object->addAttributeEAPSpecific($name, $value, $eaptype);
                } else {
                    $object->addAttribute($name, $value);
                }
                break;
                case IdP:
                case User:
                case Federation:
                    $object->addAttribute($name, $value);
                    break;
                default:
                    throw new Exception("This type of object can't have options that are parsed by this file!");
            }
        }
    }

    if ($silent == 0) {
        echo displaySummaryInUI($good, $bad, $multilangAttribsWithC);
    }
    return $killlist;
}
