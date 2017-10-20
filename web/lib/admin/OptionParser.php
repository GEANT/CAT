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

/**
 * This class parses HTML field input from POST and FILES and extracts valid and authorized options to be set.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class OptionParser {

    /**
     * an instance of the InputValidation class which we use heavily for syntax checks.
     * 
     * @var \web\lib\common\InputValidation
     */
    private $validator;

    /**
     * an instance of the UIElements() class to draw some UI widgets from.
     * 
     * @var UIElements
     */
    private $uiElements;

    /**
     * a handle for the Options singleton
     * 
     * @var \core\Options
     */
    private $optioninfoObject;

    /**
     * initialises the various handles.
     */
    public function __construct() {
        $this->validator = new \web\lib\common\InputValidation();
        $this->uiElements = new UIElements();
        $this->optioninfoObject = \core\Options::instance();
    }

    /**
     * Verifies whether an incoming upload was actually valid data
     * 
     * @param string $optiontype for which option was the data uploaded
     * @param string $incomingBinary the uploaded data
     * @return boolean whether the data was valid
     */
    private function checkUploadSanity(string $optiontype, string $incomingBinary) {
        switch ($optiontype) {
            case "general:logo_file":
            case "fed:logo_file":
            case "internal:logo_from_url":
                // we check logo_file with ImageMagick
                $image = new \Imagick();
                try {
                    $image->readImageBlob($incomingBinary);
                } catch (\ImagickException $exception) {
                    echo "Error" . $exception->getMessage();
                    return FALSE;
                }
                // image survived the sanity check
                return TRUE;
            case "eap:ca_file":
                // echo "Checking $optiontype with file $filename";
                $func = new \core\common\X509;
                $cert = $func->processCertificate($incomingBinary);
                if ($cert) {
                    return TRUE;
                }
                // echo "Error! The certificate seems broken!";
                return FALSE;
            case "support:info_file":
                $info = new \finfo();
                $filetype = $info->buffer($incomingBinary, FILEINFO_MIME_TYPE);

                // we only take plain text files in UTF-8!
                if ($filetype == "text/plain" && iconv("UTF-8", "UTF-8", $incomingBinary) !== FALSE) {
                    return TRUE;
                }
                return FALSE;
            default:
                return FALSE;
        }
    }

    /**
     * Known-good options are sometimes converted, this function takes care of that.
     * 
     * Cases in point:
     * - CA import by URL reference: fetch cert from URL and store it as CA file instead
     * - Logo import by URL reference: fetch logo from URL and store it as logo file instead
     * - CA file: mangle the content so that *only* the valid content remains (raw input may contain line breaks or spaces which are valid, but some supplicants choke upon)
     * 
     * @param array $options the list of options we got
     * @param array $good by-reference: the future list of actually imported options
     * @param array $bad by-reference: the future list of submitted but rejected options
     * @return array the options, post-processed
     */
    private function postProcessValidAttributes(array $options, array &$good, array &$bad) {
        foreach ($options as $index => $iterateOption) {
            foreach ($iterateOption as $name => $optionPayload) {
                switch ($name) {
                    case "eap:ca_url": // eap:ca_url becomes eap:ca_file by downloading the file
                        $finalOptionname = "eap:ca_file";
                    // intentional fall-through, treatment identical to logo_url
                    case "general:logo_url": // logo URLs become logo files by downloading the file
                        $finalOptionname = $finalOptionname ?? "general:logo_file";
                        if (empty($optionPayload['content'])) {
                            break;
                        }
                        $bindata = \core\common\OutsideComm::downloadFile($optionPayload['content']);
                        unset($options[$index]);
                        if ($bindata === FALSE) {
                            $bad[] = $name;
                            break;
                        }
                        if ($this->checkUploadSanity($finalOptionname, $bindata)) {
                            $good[] = $name;
                            $options[] = [$finalOptionname => ['lang' => NULL, 'content' => base64_encode($bindata)]];
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
                        $x509 = new \core\common\X509();
                        $cAFiles = $x509->splitCertificate($content);
                        foreach ($cAFiles as $cAFile) {
                            $options[] = ["eap:ca_file" => ['lang' => NULL, 'content' => base64_encode($x509->pem2der($cAFile))]];
                        }
                        $good[] = $name;
                        break;
                    default:
                        $good[] = $name; // all other options were checked and are sane in step 1 already
                        break;
                }
            }
        }

        return $options;
    }

    /**
     * extracts a coordinate pair from _POST (if any) and returns it in our 
     * standard attribute notation
     * 
     * @param array $postArray
     * @param array $good
     * @return array
     */
    private function postProcessCoordinates(array $postArray, array &$good) {
        if (!empty($postArray['geo_long']) && !empty($postArray['geo_lat'])) {

            $lat = $this->validator->coordinate($postArray['geo_lat']);
            $lon = $this->validator->coordinate($postArray['geo_long']);
            $good[] = ("general:geo_coordinates");
            return [0 => ["general:geo_coordinates" => ['lang' => NULL, 'content' => json_encode(["lon" => $lon, "lat" => $lat])]]];
        }
        return [];
    }

    /**
     * creates HTML code for a user-readable summary of the imports
     * @param array $good list of actually imported options
     * @param array $bad list of submitted but rejected options
     * @param array $mlAttribsWithC list of language-variant options
     * @return string HTML code
     */
    private function displaySummaryInUI(array $good, array $bad, array $mlAttribsWithC) {
        $retval = "";
        // don't do your own table - only the <tr>s here
        // list all attributes that were set correctly
        $listGood = array_count_values($good);
        $uiElements = new UIElements();
        foreach ($listGood as $name => $count) {
            /// number of times attribute is present, and its name
            /// Example: "5x Support E-Mail"
            $retval .= $this->uiElements->boxOkay(sprintf(_("%dx %s"), $count, $uiElements->displayName($name)));
        }
        // list all atributes that had errors
        $listBad = array_count_values($bad);
        foreach ($listBad as $name => $count) {
            $retval .= $this->uiElements->boxError(sprintf(_("%dx %s"), (int) $count, $uiElements->displayName($name)));
        }
        // list multilang without default
        foreach ($mlAttribsWithC as $attribName => $isitsetornot) {
            if ($isitsetornot == FALSE) {
                $retval .= $this->uiElements->boxWarning(sprintf(_("You did not set a 'default language' value for %s. This means we can only display this string for installers which are <strong>exactly</strong> in the language you configured. For the sake of all other languages, you may want to edit the profile again and populate the 'default/other' language field."), $uiElements->displayName($attribName)));
            }
        }
        return $retval;
    }

    /**
     * Incoming data is in $_POST and possibly in $_FILES. Collate values into 
     * one array according to our name and numbering scheme.
     * 
     * @param array $postArray _POST
     * @param array $filesArray _FILES
     * @return array
     */
    private function collateOptionArrays(array $postArray, array $filesArray) {

        $optionarray = $postArray['option'] ?? [];
        $valuearray = $postArray['value'] ?? [];
        $filesarray = $filesArray['value']['tmp_name'] ?? [];

        $iterator = array_merge($optionarray, $valuearray, $filesarray);

        return $iterator;
    }

    /**
     * The very end of the processing: clean input data gets sent to the database
     * for storage
     * 
     * @param mixed $object for which object are the options
     * @param array $options the options to store
     * @param array $pendingattributes list of attributes which are already stored but may need to be deleted
     * @param string $device when the $object is Profile, this indicates device-specific attributes
     * @param int $eaptype when the $object is Profile, this indicates eap-specific attributes
     * @return array list of attributes which were previously stored but are to be deleted now
     * @throws Exception
     */
    private function sendOptionsToDatabase($object, array $options, array $pendingattributes, string $device = NULL, int $eaptype = NULL) {
        $retval = [];
        foreach ($options as $iterateOption) {
            foreach ($iterateOption as $name => $optionPayload) {
                $optiontype = $this->optioninfoObject->optionType($name);
                // some attributes are in the DB and were only called by reference
                // keep those which are still referenced, throw the rest away
                if ($optiontype["type"] == "file" && preg_match("/^ROWID-.*-([0-9]+)/", $optionPayload['content'], $retval)) {
                    unset($pendingattributes[$retval[1]]);
                    continue;
                }
                switch (get_class($object)) {
                    case 'core\\ProfileRADIUS':
                        if ($device !== NULL) {
                            $object->addAttributeDeviceSpecific($name, $optionPayload['lang'], $optionPayload['content'], $device);
                        } elseif ($eaptype !== NULL) {
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
        return $pendingattributes;
    }

    /**
     * filters the input to find syntactically correctly submitted attributes
     * 
     * @param array $listOfEntries list of POST and FILES entries
     * @param array $multilangAttrsWithC by-reference: future list of language-variant options and their "default lang" state
     * @param array $bad by-reference: future list of submitted but rejected options
     * @return array sanitised list of options
     * @throws Exception
     */
    private function sanitiseInputs(array $listOfEntries, array &$multilangAttrsWithC, array &$bad) {
        $retval = [];
        foreach ($listOfEntries as $objId => $objValueRaw) {
// pick those without dash - they indicate a new value        
            if (preg_match('/^S[0123456789]*$/', $objId)) {
                $objValue = $this->validator->optionName(preg_replace('/#.*$/', '', $objValueRaw));
                $optioninfo = $this->optioninfoObject->optionType($objValue);
                $lang = NULL;
                if ($optioninfo["flag"] == "ML") {
                    if (isset($listOfEntries["$objId-lang"])) {
                        if (!isset($multilangAttrsWithC[$objValue])) { // on first sight, initialise the attribute as "no C language set"
                            $multilangAttrsWithC[$objValue] = FALSE;
                        }
                        $lang = $listOfEntries["$objId-lang"];
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

                // many of the cases below condense due to identical treatment
                // except validator function to call and where in POST the
                // content is
                $validators = [
                    "text" => ["function" => "string", "field" => 1, "extraarg" => [TRUE]],
                    "coordinates" => ["function" => "coordJsonEncoded", "field" => 1, "extraarg" => []],
                    "boolean" => ["function" => "boolean", "field" => 3, "extraarg" => []],
                    "integer" => ["function" => "integer", "field" => 4, "extraarg" => []],
                ];

                switch ($optioninfo["type"]) {
                    case "text":
                    case "coordinates":
                    case "integer":
                        $varName = "$objId-" . $validators[$optioninfo['type']]['field'];
                        if (!empty($listOfEntries[$varName])) {
                            $content = call_user_func_array([$this->validator, $validators[$optioninfo['type']]['function']], array_merge([$listOfEntries[$varName]], $validators[$optioninfo['type']]['extraarg']));
                            break;
                        }
                        continue 2;
                    case "boolean":
                        $varName = "$objId-3";
                        if (!empty($listOfEntries[$varName])) {
                            $contentValid = $this->validator->boolean($listOfEntries[$varName]);
                            if ($contentValid) {
                                $content = "on";
                            } else {
                                $bad[] = $objValue;
                                continue 2;
                            }
                            break;
                        }
                        continue 2;
                    case "string":
                        if (!empty($listOfEntries["$objId-0"])) {
                            switch ($objValue) {
                                case "media:consortium_OI":
                                    $content = $this->validator->consortiumOI($listOfEntries["$objId-0"]);
                                    if ($content === FALSE) {
                                        $bad[] = $objValue;
                                        continue 3;
                                    }
                                    break;
                                case "media:remove_SSID":
                                    $content = $this->validator->string($listOfEntries["$objId-0"]);
                                    if ($content == "eduroam") {
                                        $bad[] = $objValue;
                                        continue 3;
                                    }
                                    break;
                                default:
                                    $content = $this->validator->string($listOfEntries["$objId-0"]);
                                    break;
                            }
                            break;
                        }
                        continue 2;
                    case "file":
// echo "In file processing ...<br/>";
                        if (!empty($listOfEntries["$objId-1"])) { // was already in, by ROWID reference, extract
                            // ROWID means it's a multi-line string (simple strings are inline in the form; so allow whitespace)
                            $content = $this->validator->string(urldecode($listOfEntries["$objId-1"]), TRUE);
                            break;
                        } else if (isset($listOfEntries["$objId-2"]) && ($listOfEntries["$objId-2"] != "")) { // let's do the download
// echo "Trying to download file:///".$a["$obj_id-2"]."<br/>";
                            $rawContent = \core\common\OutsideComm::downloadFile("file:///" . $listOfEntries["$objId-2"]);
                            
                            if ($rawContent === FALSE || !$this->checkUploadSanity($objValue, $rawContent)) {
                                $bad[] = $objValue;
                                continue 2;
                            }
                            $content = base64_encode($rawContent);
                            break;
                        }
                        continue 2;
                    default:
                        throw new Exception("Internal Error: Unknown option type " . $objValue . "!");
                }
                // lang can be NULL here, if it's not a multilang attribute, or a ROWID reference. Never mind that.
                $retval[] = ["$objValue" => ["lang" => $lang, "content" => $content]];
            }
        }
        return $retval;
    }

    /**
     * The main function: takes all HTML field inputs, makes sense of them and stores valid data in the database
     * 
     * @param mixed $object The object for which attributes were submitted
     * @param array $postArray incoming attribute names and values as submitted with $_POST
     * @param array $filesArray incoming attribute names and values as submitted with $_FILES
     * @param int $eaptype for eap-specific attributes (only used where $object is a ProfileRADIUS instance)
     * @param string $device for device-specific attributes (only used where $object is a ProfileRADIUS instance)
     * @return array subset of $pendingattributes: the list of by-reference entries which are definitely to be deleted
     * @throws Exception
     */
    public function processSubmittedFields($object, array $postArray, array $filesArray, int $eaptype = NULL, string $device = NULL) {

        // construct new array with all non-empty options for later feeding into DB
        // $multilangAttrsWithC is a helper array to keep track of multilang 
        // options that were set in a specific language but are not 
        // accompanied by a "default" language setting
        // if there are some without C by the end of processing, we need to warn
        // the admin that this attribute is "invisible" in certain languages
        // attrib_name -> boolean

        $multilangAttrsWithC = [];

        // these two variables store which attributes were processed 
        // successfully vs. which were discarded because in some way malformed

        $good = [];
        $bad = [];

        // Step 1: collate option names, option values and uploaded files (by 
        // filename reference) into one array for later handling

        $iterator = $this->collateOptionArrays($postArray, $filesArray);

        // Step 2: sieve out malformed input

        $cleanData = $this->sanitiseInputs($iterator, $multilangAttrsWithC, $bad);

        // Step 3: now we have clean input data. Some attributes need special care:
        // URL-based attributes need to be downloaded to get their actual content
        // CA files may need to be split (PEM can contain multiple CAs 

        $optionsStep2 = $this->postProcessValidAttributes($cleanData, $good, $bad);

        // Step 4: coordinates do not follow the usual POST array as they are 
        // two values forming one attribute; extract those two as an extra step

        $options = array_merge($optionsStep2, $this->postProcessCoordinates($postArray, $good));

        // Step 5: push all the received options to the database. Keep mind of 
        // the list of existing database entries that are to be deleted.
        // 5a: first deletion step: purge all old content except file-based attributes;
        //     then take note of which file-based attributes are now stale
        if ($device === NULL && $eaptype === NULL) {
            $remaining = $object->beginflushAttributes();
            $killlist = $this->sendOptionsToDatabase($object, $options, $remaining);
        } elseif ($device !== NULL) {
            $remaining = $object->beginFlushMethodLevelAttributes(0, $device);
            $killlist = $this->sendOptionsToDatabase($object, $options, $remaining, $device);
        } else {
            $remaining = $object->beginFlushMethodLevelAttributes($eaptype, "");
            $killlist = $this->sendOptionsToDatabase($object, $options, $remaining, "", $eaptype);
        }
        // 5b: finally, kill the stale file-based attributes which are not wanted any more.
        $object->commitFlushAttributes($killlist);

        // finally: return HTML code that gives feedback about what we did. 
        // In some cases, callers won't actually want to display it; so simply
        // do not echo the return value. Reasons not to do this is if we working
        // e.g. from inside an overlay

        return $this->displaySummaryInUI($good, $bad, $multilangAttrsWithC);
    }

}
