<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

namespace web\lib\admin;

use Exception;

?>
<?php

/**
 * This class parses HTML field input from POST and FILES and extracts valid and authorized options to be set.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class OptionParser extends \core\common\Entity {

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
     * @param string $optiontype     for which option was the data uploaded
     * @param string $incomingBinary the uploaded data
     * @return boolean whether the data was valid
     */
    private function checkUploadSanity(string $optiontype, string $incomingBinary) {
        switch ($optiontype) {
            case "general:logo_file":
            case "fed:logo_file":
            case "internal:logo_from_url":
                // we check logo_file with ImageMagick
                return $this->validator->image($incomingBinary);
            case "eap:ca_file":
            // fall-through intended: both CA types are treated the same
            case "fed:minted_ca_file":
                // echo "Checking $optiontype with file $filename";
                $cert = (new \core\common\X509)->processCertificate($incomingBinary);
                if ($cert !== FALSE) { // could also be FALSE if it was incorrect incoming data
                    return TRUE;
                }
                // the certificate seems broken
                return FALSE;
            case "support:info_file":
                $info = new \finfo();
                $filetype = $info->buffer($incomingBinary, FILEINFO_MIME_TYPE);

                // we only take plain text files in UTF-8!
                if ($filetype == "text/plain" && iconv("UTF-8", "UTF-8", $incomingBinary) !== FALSE) {
                    return TRUE;
                }
                return FALSE;
            case "media:openroaming": // and any other enum_* data type actually
                $optionClass = \core\Options::instance();
                $optionProps = $optionClass->optionType($optiontype);
                $allowedValues = explode(',', substr($optionProps["flags"], 7));
                if (in_array($incomingBinary,$allowedValues))  {
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
     * @param array $good    by-reference: the future list of actually imported options
     * @param array $bad     by-reference: the future list of submitted but rejected options
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
                    case "eap:ca_file":
                    case "fed:minted_ca_file":
                        // CA files get split (PEM files can contain more than one CA cert)
                        // the data being processed here is always "good": 
                        // if it was eap:ca_file initially then its sanity was checked in step 1;
                        // if it was eap:ca_url then it was checked after we downloaded it
                        if (empty($optionPayload['content'])) {
                            break;
                        }
                        if (preg_match('/^ROWID-/', $optionPayload['content'])) {
                            // accounted for, already in DB
                            $good[] = $name;
                            break;
                        }
                        $content = base64_decode($optionPayload['content']);
                        unset($options[$index]);
                        $x509 = new \core\common\X509();
                        $cAFiles = $x509->splitCertificate($content);
                        foreach ($cAFiles as $cAFile) {
                            $options[] = [$name => ['lang' => NULL, 'content' => base64_encode($x509->pem2der($cAFile))]];
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
     * @param array $postArray data as sent by POST
     * @param array $good      options which have been successfully parsed
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
     * @param array $good           list of actually imported options
     * @param array $bad            list of submitted but rejected options
     * @param array $mlAttribsWithC list of language-variant options
     * @return string HTML code
     */
    private function displaySummaryInUI(array $good, array $bad, array $mlAttribsWithC) {
        \core\common\Entity::intoThePotatoes();
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
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * Incoming data is in $_POST and possibly in $_FILES. Collate values into 
     * one array according to our name and numbering scheme.
     * 
     * @param array $postArray  _POST
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
     * @param mixed  $object            for which object are the options
     * @param array  $options           the options to store
     * @param array  $pendingattributes list of attributes which are already stored but may need to be deleted
     * @param string $device            when the $object is Profile, this indicates device-specific attributes
     * @param int    $eaptype           when the $object is Profile, this indicates eap-specific attributes
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
                if ($optiontype["type"] == \core\Options::TYPECODE_FILE && preg_match("/^ROWID-.*-([0-9]+)/", $optionPayload['content'], $retval)) {
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
                    case 'core\\DeploymentManaged':
                        $object->addAttribute($name, $optionPayload['lang'], $optionPayload['content']);
                        break;
                    default:
                        throw new Exception("This type of object can't have options that are parsed by this file!");
                }
            }
        }
        return $pendingattributes;
    }

    /** many of the content check cases in sanitiseInputs condense due to
     *  identical treatment except which validator function to call and 
     *  where in POST the content is.
     * 
     * This is a map between datatype and validation function.
     * 
     * @var array
     */
    private const VALIDATOR_FUNCTIONS = [
        \core\Options::TYPECODE_TEXT => ["function" => "string", "field" => \core\Options::TYPECODE_TEXT, "extraarg" => [TRUE]],
        \core\Options::TYPECODE_COORDINATES => ["function" => "coordJsonEncoded", "field" => \core\Options::TYPECODE_TEXT, "extraarg" => []],
        \core\Options::TYPECODE_BOOLEAN => ["function" => "boolean", "field" => \core\Options::TYPECODE_BOOLEAN, "extraarg" => []],
        \core\Options::TYPECODE_INTEGER => ["function" => "integer", "field" => \core\Options::TYPECODE_INTEGER, "extraarg" => []],
    ];

    /**
     * filters the input to find syntactically correctly submitted attributes
     * 
     * @param array $listOfEntries list of POST and FILES entries
     * @return array sanitised list of options
     * @throws Exception
     */
    private function sanitiseInputs(array $listOfEntries) {
        $retval = [];
        $bad = [];
        $multilangAttrsWithC = [];
        foreach ($listOfEntries as $objId => $objValueRaw) {
// pick those without dash - they indicate a new value        
            if (preg_match('/^S[0123456789]*$/', $objId) != 1) { // no match
                continue;
            }
            $objValue = $this->validator->optionName(preg_replace('/#.*$/', '', $objValueRaw));
            $optioninfo = $this->optioninfoObject->optionType($objValue);
            $languageFlag = NULL;
            if ($optioninfo["flag"] == "ML") {
                if (!isset($listOfEntries["$objId-lang"])) {
                    $bad[] = $objValue;
                    continue;
                }
                $languageFlag = $this->validator->string($listOfEntries["$objId-lang"]);
                $this->determineLanguages($objValue, $listOfEntries["$objId-lang"], $multilangAttrsWithC);
            }

            switch ($optioninfo["type"]) {
                case \core\Options::TYPECODE_TEXT:
                case \core\Options::TYPECODE_COORDINATES:
                case \core\Options::TYPECODE_INTEGER:
                    $varName = $listOfEntries["$objId-" . self::VALIDATOR_FUNCTIONS[$optioninfo['type']]['field']];
                    if (!empty($varName)) {
                        $content = call_user_func_array([$this->validator, self::VALIDATOR_FUNCTIONS[$optioninfo['type']]['function']], array_merge([$varName], self::VALIDATOR_FUNCTIONS[$optioninfo['type']]['extraarg']));
                        break;
                    }
                    continue 2;
                case \core\Options::TYPECODE_BOOLEAN:
                    $varName = $listOfEntries["$objId-" . \core\Options::TYPECODE_BOOLEAN];
                    if (!empty($varName)) {
                        $contentValid = $this->validator->boolean($varName);
                        if ($contentValid) {
                            $content = "on";
                        } else {
                            $bad[] = $objValue;
                            continue 2;
                        }
                        break;
                    }
                    continue 2;
                case \core\Options::TYPECODE_STRING:
                    $previsionalContent = $listOfEntries["$objId-" . \core\Options::TYPECODE_STRING];
                    if (!empty($previsionalContent)) {
                        $content = $this->furtherStringChecks($objValue, $previsionalContent, $bad);
                        if ($content === FALSE) {
                            continue 2;
                        }
                        break;
                    }
                    continue 2;
                    
                case \core\Options::TYPECODE_ENUM_OPENROAMING:
                    $previsionalContent = $listOfEntries["$objId-" . \core\Options::TYPECODE_ENUM_OPENROAMING];
                    if (!empty($previsionalContent)) {
                        $content = $this->furtherStringChecks($objValue, $previsionalContent, $bad);
                        if ($content === FALSE) {
                            continue 2;
                        }
                        break;
                    }
                    continue 2;    
                case \core\Options::TYPECODE_FILE:
                    // this is either actually an uploaded file, or a reference to a DB entry of a previously uploaded file
                    $reference = $listOfEntries["$objId-" . \core\Options::TYPECODE_STRING];
                    if (!empty($reference)) { // was already in, by ROWID reference, extract
                        // ROWID means it's a multi-line string (simple strings are inline in the form; so allow whitespace)
                        $content = $this->validator->string(urldecode($reference), TRUE);
                        break;
                    }
                    $fileName = $listOfEntries["$objId-" . \core\Options::TYPECODE_FILE] ?? "";
                    if ($fileName != "") { // let's do the download
                        $rawContent = \core\common\OutsideComm::downloadFile("file:///" . $fileName);

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
            $retval[] = ["$objValue" => ["lang" => $languageFlag, "content" => $content]];
        }
        return [$retval, $multilangAttrsWithC, $bad];
    }

    /**
     * find out which languages were submitted, and whether a default language was in the set
     * @param string $attribute           the name of the attribute we are looking at
     * @param string $languageFlag        which language flag was submitted
     * @param array  $multilangAttrsWithC by-reference: add to this if we found a C language variant
     * @return void
     */
    private function determineLanguages($attribute, $languageFlag, &$multilangAttrsWithC) {
        if (!isset($multilangAttrsWithC[$attribute])) { // on first sight, initialise the attribute as "no C language set"
            $multilangAttrsWithC[$attribute] = FALSE;
        }
        if ($languageFlag == "") { // user forgot to select a language
            $languageFlag = "C";
        }
        // did we get a C language? set corresponding value to TRUE
        if ($languageFlag == "C") {
            $multilangAttrsWithC[$attribute] = TRUE;
        }
    }

    /**
     * 
     * @param string $attribute          which attribute was sent?
     * @param string $previsionalContent which content was sent?
     * @param array  $bad                list of malformed attributes, by-reference
     * @return string|false FALSE if value is not in expected format, else the content itself
     */
    private function furtherStringChecks($attribute, $previsionalContent, &$bad) {
        $content = FALSE;
        switch ($attribute) {
            case "media:consortium_OI":
                $content = $this->validator->consortiumOI($previsionalContent);
                if ($content === FALSE) {
                    $bad[] = $attribute;
                    return FALSE;
                }
                break;
            case "media:remove_SSID":
                $content = $this->validator->string($previsionalContent);
                if ($content == "eduroam") {
                    $bad[] = $attribute;
                    return FALSE;
                }
                break;
            case "media:force_proxy":
                $content = $this->validator->string($previsionalContent);
                $serverAndPort = explode(':', strrev($content), 2);
                if (count($serverAndPort) != 2) {
                    $bad[] = $attribute;
                    return FALSE;
                }
                $port = strrev($serverAndPort[0]);
                if (!is_numeric($port)) {
                    $bad[] = $attribute;
                    return FALSE;
                }
                break;
            case "support:url":
                $content = $this->validator->string($previsionalContent);
                if (preg_match("/^http/", $content) != 1) {
                    $bad[] = $attribute;
                    return FALSE;
                }
                break;
            case "support:email":
                $content = $this->validator->email($previsionalContent);
                if ($content === FALSE) {
                    $bad[] = $attribute;
                    return FALSE;
                }
                break;
            case "managedsp:operatorname":
                $content = $previsionalContent;
                if (!preg_match("/^1.*\..*/", $content)) {
                    $bad[] = $attribute;
                    return FALSE;
                }
                break;
            default:
                $content = $this->validator->string($previsionalContent);
                break;
        }
        return $content;
    }

    /**
     * The main function: takes all HTML field inputs, makes sense of them and stores valid data in the database
     * 
     * @param mixed  $object     The object for which attributes were submitted
     * @param array  $postArray  incoming attribute names and values as submitted with $_POST
     * @param array  $filesArray incoming attribute names and values as submitted with $_FILES
     * @param int    $eaptype    for eap-specific attributes (only used where $object is a ProfileRADIUS instance)
     * @param string $device     for device-specific attributes (only used where $object is a ProfileRADIUS instance)
     * @return string text to be displayed in UI with the summary of attributes added
     * @throws Exception
     */
    public function processSubmittedFields($object, array $postArray, array $filesArray, int $eaptype = NULL, string $device = NULL) {
        $good = [];
        // Step 1: collate option names, option values and uploaded files (by 
        // filename reference) into one array for later handling

        $iterator = $this->collateOptionArrays($postArray, $filesArray);

        // Step 2: sieve out malformed input
        // $multilangAttrsWithC is a helper array to keep track of multilang 
        // options that were set in a specific language but are not 
        // accompanied by a "default" language setting
        // if there are some without C by the end of processing, we need to warn
        // the admin that this attribute is "invisible" in certain languages
        // attrib_name -> boolean
        // $bad contains the attributes which failed input validation

        list($cleanData, $multilangAttrsWithC, $bad) = $this->sanitiseInputs($iterator);

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
            $killlist = $this->sendOptionsToDatabase($object, $options, $remaining, NULL, $eaptype);
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
