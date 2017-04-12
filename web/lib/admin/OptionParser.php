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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

class OptionParser {

    private $validator;
    
    function __construct() {
        $this->validator = new \web\lib\common\InputValidation();
    }
    
    private function postProcessValidAttributes($options, &$good, &$bad) {
        foreach ($options as $index => $iterateOption) {
            foreach ($iterateOption as $name => $optionPayload) {
                switch ($name) {
                    case "eap:ca_url": // eap:ca_url becomes eap:ca_file by downloading the file
                        if (empty($optionPayload['content'])) {
                            break;
                        }
                        $content = \core\OutsideComm::downloadFile($optionPayload['content']);
                        unset($options[$index]);
                        if (check_upload_sanity("eap:ca_file", $content)) {
                            $content = base64_encode($content);
                            $options[] = ["eap:ca_file" => ['lang' => NULL, 'content' => $content]];
                            $good[] = $name;
                        } else {
                            $bad[] = $name;
                        }
                        break;
                    case "eap:ca_file": // CA files get split (PEM files can contain more than one CA cert)
                        // the data being processed here is always "good": 
                        // if it was eap:ca_file initially then its sanity was checked in step 1;
                        // if it was eap:ca_url then it was checked after we downloaded it
                        if (empty($optionPayload['content']) || preg_match('/^ROWID-/', $optionPayload['content'])) {
                            break;
                        }
                        $content = base64_decode($optionPayload['content']);
                        unset($options[$index]);
                        $x509 = new \core\X509();
                        $cAFiles = $x509->splitCertificate($content);
                        foreach ($cAFiles as $cAFile) {
                            $options[] = ["eap:ca_file" => ['lang' => NULL, 'content' => base64_encode($x509->pem2der($cAFile))]];
                        }
                        $good[] = $name;
                        break;
                    case "general:logo_url": // logo URLs become logo files by downloading the file
                        if (empty($optionPayload['content'])) {
                            break;
                        }
                        $bindata = \core\OutsideComm::downloadFile($optionPayload['content']);
                        unset($options[$index]);
                        if (check_upload_sanity("general:logo_file", $bindata)) {
                            $good[] = $name;
                            $options[] = ["general:logo_file" => ['lang' => NULL, 'content' => base64_encode($bindata)]];
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

    private function postProcessCoordinates($options, &$good) {
        if (!empty($_POST['geo_long']) && !empty($_POST['geo_lat'])) {

            $lat = $this->validator->coordinate($_POST['geo_lat']);
            $lon = $this->validator->coordinate($_POST['geo_long']);

            $options[] = ["general:geo_coordinates" => ['lang' => NULL, 'content' => serialize(["lon" => $lon, "lat" => $lat])]];
            $good[] = ("general:geo_coordinates");
        }
        return $options;
    }

    private function displaySummaryInUI($good, $bad, $mlAttribsWithC) {
        $retval = "";
        // don't do your own table - only the <tr>s here
        // list all attributes that were set correctly
        $listGood = array_count_values($good);
        $uiElements = new UIElements();
        foreach ($listGood as $name => $count) {
            /// number of times attribute is present, and its name
            /// Example: "5x Support E-Mail"
            $retval .= UI_okay(sprintf(_("%dx %s"), $count, $uiElements->displayName($name)));
        }
        // list all atributes that had errors
        $listBad = array_count_values($bad);
        foreach ($listBad as $name => $count) {
            $retval .= UI_error(sprintf(_("%dx %s"), $count, $uiElements->displayName($name)));
        }
        // list multilang without default
        foreach ($mlAttribsWithC as $attribName => $isitsetornot) {
            if ($isitsetornot == FALSE) {
                $retval .= UI_warning(sprintf(_("You did not set a 'default language' value for %s. This means we can only display this string for installers which are <strong>exactly</strong> in the language you configured. For the sake of all other languages, you may want to edit the profile again and populate the 'default/other' language field."), $uiElements->displayName($attribName)));
            }
        }
        return $retval;
    }

    private function collateOptionArrays($postArray, $filesArray) {

        $optionarray = $postArray['option'] ?? [];
        $valuearray = $postArray['value'] ?? [];
        $filesarray = $filesArray['value']['tmp_name'] ?? [];

        $iterator = array_merge($optionarray, $valuearray, $filesarray);

        return $iterator;
    }

    /**
     * 
     * @param mixed $object The object for which attributes were submitted
     * @param array $postArray incoming attribute names and values as submitted with $_POST
     * @param array $filesArray incoming attribute names and values as submitted with $_FILES
     * @param array $pendingattributes object's attributes stored by-reference in the DB which are tentatively marked for deletion
     * @param int $eaptype for eap-specific attributes (only used where $object is a ProfileRADIUS instance)
     * @param string $device for device-specific attributes (only used where $object is a ProfileRADIUS instance)
     * @param boolean $silent determines whether a HTML form with the result of processing should be output or not
     * @return array subset of $pendingattributes: the list of by-reference entries which are definitely to be deleted
     * @throws Exception
     */
    public function processSubmittedFields($object, $postArray, $filesArray, $pendingattributes, $eaptype = 0, $device = NULL, $silent = FALSE) {

// construct new array with all non-empty options for later feeding into DB

        $optionsStep1 = [];
        $multilangAttrsWithC = [];
        $good = [];
        $bad = [];

        $killlist = $pendingattributes;

        $optioninfoObject = \core\Options::instance();

        // Step 1: collate option names, option values and uploaded files (by 
        // filename reference) into one array for later handling

        $iterator = $this->collateOptionArrays($postArray, $filesArray);

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
                $lang = NULL;
                if ($optioninfo["flag"] == "ML") {
                    if (isset($iterator["$objId-lang"])) {
                        if (!isset($multilangAttrsWithC[$objValue])) { // on first sight, initialise the attribute as "no C language set"
                            $multilangAttrsWithC[$objValue] = FALSE;
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
                        $multilangAttrsWithC[$objValue] = TRUE;
                    }
                }

                switch ($optioninfo["type"]) {
                    case "string":
                        if (!empty($iterator["$objId-0"])) {
                            switch ($objValue) {
                                case "media:consortium_OI":
                                    $content = $this->validator->consortium_oi($iterator["$objId-0"]);
                                    if ($content === FALSE) {
                                        $bad[] = $objValue;
                                        continue 3;
                                    }
                                    break;
                                case "media:remove_SSID":
                                    $content = $this->validator->string($iterator["$objId-0"]);
                                    if ($content == "eduroam") {
                                        $bad[] = $objValue;
                                        continue 3;
                                    }
                                    break;
                                default:
                                    $content = $this->validator->string($iterator["$objId-0"]);
                                    break;
                            }
                            break;
                        }
                        continue 2;
                    case "text":
                        if (!empty($iterator["$objId-1"])) {
                            $content = $this->validator->string($iterator["$objId-1"], TRUE);
                            break;
                        }
                        continue 2;
                    case "coordinates":
                        if (!empty($iterator["$objId-1"])) {
                            $content = $this->validator->coordSerialized($iterator["$objId-1"]);
                            break;
                        }
                        continue 2;
                    case "file":
// echo "In file processing ...<br/>";
                        if (!empty($iterator["$objId-1"])) { // was already in, by ROWID reference, extract
                            // ROWID means it's a multi-line string (simple strings are inline in the form; so allow whitespace)
                            $content = $this->validator->string(urldecode($iterator["$objId-1"]), TRUE);
                            break;
                        } else if (isset($iterator["$objId-2"]) && ($iterator["$objId-2"] != "")) { // let's do the download
// echo "Trying to download file:///".$a["$obj_id-2"]."<br/>";
                            $content = \core\OutsideComm::downloadFile("file:///" . $iterator["$objId-2"]);
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
                            $content = $this->validator->boolean($iterator["$objId-3"]);
                            break;
                        }
                        continue 2;
                    case "integer":
                        if (!empty($iterator["$objId-4"])) {
                            $content = $this->validator->integer($iterator["$objId-4"]);
                            break;
                        }
                        continue 2;
                    default:
                        throw new Exception("Internal Error: Unknown option type " . $objValue . "!");
                }
                // lang can be NULL here, if it's not a multilang attribute, or a ROWID reference. Never mind that.
                $optionsStep1[] = ["$objValue" => ["lang" => $lang, "content" => $content]];
            }
        }

// Step 2: now we have clean input data. Some attributes need special care:
// URL-based attributes need to be downloaded to get their actual content
// CA files may need to be split (PEM can contain multiple CAs 

        $optionsStep2 = $this->postProcessValidAttributes($optionsStep1, $good, $bad);


// Step 3: coordinates do not follow the usual POST array as they are two values forming one attribute

        $options = $this->postProcessCoordinates($optionsStep2, $good);

        foreach ($options as $iterateOption) {
            foreach ($iterateOption as $name => $optionPayload) {
                $optiontype = $optioninfoObject->optionType($name);
                // some attributes are in the DB and were only called by reference
                // keep those which are still referenced, throw the rest away
                if ($optiontype["type"] == "file" && preg_match("/^ROWID-.*-([0-9]+)/", $optionPayload['content'], $retval)) {
                    unset($killlist[$retval[1]]);
                    continue;
                }
                switch (get_class($object)) {
                    case 'core\\ProfileRADIUS':
                        if ($device !== NULL) {
                            $object->addAttributeDeviceSpecific($name, $optionPayload['lang'], $optionPayload['content'], $device);
                        } elseif ($eaptype != 0) {
                            $object->addAttributeEAPSpecific($name, $optionPayload['lang'], $optionPayload['content'], $eaptype);
                        } else {
                            $object->addAttribute($name, $optionPayload['lang'], $optionPayload['content']);
                        }
                        break;
                    case 'core\\IdP':
                    case 'core\\User':
                    case 'core\\Federation':
                        $object->addAttribute($name, $optionPayload['lang'], $optionPayload['content']);
                        break;
                    default:
                        throw new Exception("This type of object can't have options that are parsed by this file!");
                }
            }
        }

        if ($silent === FALSE) {
            echo $this->displaySummaryInUI($good, $bad, $multilangAttrsWithC);
        }
        return $killlist;
    }

}
