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

namespace web\lib\common;

use \Exception;

/**
 * performs validation of user inputs
 */
class InputValidation extends \core\common\Entity {

    /**
     * returns a simple HTML <p> element with basic explanations about what was
     * wrong with the input
     * 
     * @param string $customtext explanation provided by the validator function
     * @return string
     */
    private function inputValidationError($customtext) {
        \core\common\Entity::intoThePotatoes();
        $retval = "<p>" . _("Input validation error: ") . $customtext . "</p>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * Is this a known Federation? Optionally, also check if the authenticated
     * user is a federation admin of that federation
     * @param mixed       $input the ISO code of the federation
     * @param string|NULL $owner the authenticated username, optional
     * @return \core\Federation
     * @throws Exception
     */
    public function Federation($input, $owner = NULL) {

        $cat = new \core\CAT(); // initialises Entity static members
        $fedIdentifiers = array_keys($cat->knownFederations);
        if (!in_array(strtoupper($input), $fedIdentifiers)) {
            throw new Exception($this->inputValidationError(sprintf("This %s does not exist!", \core\common\Entity::$nomenclature_fed)));
        }
        // totally circular, but this hopefully *finally* make Scrutinizer happier
        $correctIndex = array_search(strtoupper($input), $fedIdentifiers);
        $postFed = $fedIdentifiers[$correctIndex];

        $temp = new \core\Federation($postFed);
        if ($owner === NULL) {
            return $temp;
        }

        foreach ($temp->listFederationAdmins() as $oneowner) {
            if ($oneowner == $owner) {
                return $temp;
            }
        }
        throw new Exception($this->inputValidationError(sprintf("User is not %s administrator!", \core\common\Entity::$nomenclature_fed)));
    }

    /**
     * Is this a known IdP? Optionally, also check if the authenticated
     * user is an admin of that IdP
     * @param mixed  $input the numeric ID of the IdP in the system
     * @param string $owner the authenticated username, optional
     * @return \core\IdP
     * @throws Exception
     */
    public function IdP($input, $owner = NULL) {
        $clean = $this->integer($input);
        if ($clean === FALSE) {
            throw new Exception($this->inputValidationError("Value for IdP is not an integer!"));
        }

        $temp = new \core\IdP($input); // constructor throws an exception if NX, game over

        if ($owner !== NULL) { // check if the authenticated user is allowed to see this institution
            foreach ($temp->listOwners() as $oneowner) {
                if ($oneowner['ID'] == $owner) {
                    return $temp;
                }
            }
            throw new Exception($this->inputValidationError("This IdP identifier is not accessible!"));
        }
        return $temp;
    }

    /**
     * Checks if the input refers to a known Profile. Optionally also takes an
     * IdP identifier and then checks if the Profile belongs to the refernced 
     * IdP
     * 
     * @param mixed    $input         the numeric ID of the Profile in the system
     * @param int|NULL $idpIdentifier the numeric ID of the IdP in the system, optional
     * @return \core\AbstractProfile
     * @throws Exception
     */
    public function Profile($input, $idpIdentifier = NULL) {
        $clean = $this->integer($input);
        if ($clean === FALSE) {
            throw new Exception("Non-integer was passed to Profile validator!");
        }
        $temp = \core\ProfileFactory::instantiate($clean); // constructor throws an exception if NX, game over

        if ($idpIdentifier !== NULL && $temp->institution != $idpIdentifier) {
            throw new Exception($this->inputValidationError("The profile does not belong to the IdP!"));
        }
        return $temp;
    }

    /**
     * Checks if this is a device known to the system
     * @param mixed $input the name of the device (index in the Devices.php array)
     * @return string returns the same string on success, throws an Exception on failure
     * @throws Exception
     */
    public function Device($input) {
        $devicelist = \devices\Devices::listDevices();
        $keyArray = array_keys($devicelist);
        if (!isset($devicelist[$input])) {
            throw new Exception($this->inputValidationError("This device does not exist!"));
        }
        $correctIndex = array_search($input, $keyArray);
        return $keyArray[$correctIndex];
    }

    /**
     * Checks if the input was a valid string.
     * 
     * @param mixed   $input           a string to be made SQL-safe
     * @param boolean $allowWhitespace whether some whitespace (e.g. newlines should be preserved (true) or redacted (false)
     * @return string the massaged string
     */
    public function string($input, $allowWhitespace = FALSE) {
    // always chop out invalid characters, and surrounding whitespace
    $retvalStep0 =  iconv("UTF-8", "UTF-8//TRANSLIT", $input);
    if ($retvalStep0 === FALSE) {
        throw new Exception("iconv failure for string sanitisation. With TRANSLIT, this should never happen!");
    }
    $retvalStep1 = trim($retvalStep0);
    // if some funny person wants to inject markup tags, remove them
    $retval = filter_var($retvalStep1, FILTER_SANITIZE_STRING, ["flags" => FILTER_FLAG_NO_ENCODE_QUOTES]);
    if ($retval === FALSE) {
        throw new Exception("filter_var failure for string sanitisation.");
    }
    // unless explicitly wanted, take away intermediate disturbing whitespace
    // a simple "space" is NOT disturbing :-)
    if ($allowWhitespace === FALSE) {
        $afterWhitespace = preg_replace('/(\0|\r|\x0b|\t|\n)/', '', $retval);
    } else {
        // even if we allow whitespace, not pathological ones!
        $afterWhitespace = preg_replace('/(\0|\r|\x0b)/', '', $retval);
    }
    if (is_array($afterWhitespace)) {
        throw new Exception("This function has to be given a string and returns a string. preg_replace has generated an array instead!");
    }
    return (string) $afterWhitespace;
}

/**
 * Is this an integer, or a string that represents an integer?
 * 
 * @param mixed $input the raw input
 * @return boolean|int returns the input, or FALSE if it is not an integer-like value
 */
public function integer($input) {
    if (is_numeric($input)) {
        return (int) $input;
    }
    return FALSE;
}

/**
 * Checks if the input is the hex representation of a Consortium OI (i.e. three
 * or five bytes)
 * 
 * @param mixed $input the raw input
 * @return boolean|string returns the input, or FALSE on validation failure
 */
public function consortiumOI($input) {
    $shallow = $this->string($input);
    if (strlen($shallow) != 6 && strlen($shallow) != 10) {
        return FALSE;
    }
    if (!preg_match("/^[a-fA-F0-9]+$/", $shallow)) {
        return FALSE;
    }
    return $shallow;
}

/**
 * Is the input an NAI realm? Throws HTML error and returns FALSE if not.
 * 
 * @param mixed $input the input to check
 * @return boolean|string returns the realm, or FALSE if it was malformed
 */
public function realm($input) {
    \core\common\Entity::intoThePotatoes();
    if (strlen($input) == 0) {
        echo $this->inputValidationError(_("Realm is empty!"));
        \core\common\Entity::outOfThePotatoes();
        return FALSE;
    }

    // basic string checks
    $check = $this->string($input);
    // list of things to check, and the error they produce
    $pregCheck = [
        "/@/" => _("Realm contains an @ sign!"),
        "/^\./" => _("Realm begins with a . (dot)!"),
        "/\.$/" => _("Realm ends with a . (dot)!"),
        "/ /" => _("Realm contains spaces!"),
    ];

    // bark on invalid constructs
    foreach ($pregCheck as $search => $error) {
        if (preg_match($search, $check) == 1) {
            echo $this->inputValidationError($error);
            \core\common\Entity::outOfThePotatoes();
            return FALSE;
        }
    }

    if (preg_match("/\./", $check) == 0) {
        echo $this->inputValidationError(_("Realm does not contain at least one . (dot)!"));
        \core\common\Entity::outOfThePotatoes();
        return FALSE;
    }

    // none of the special HTML entities should be here. In case someone wants
    // to mount a CSS attack by providing something that matches the realm constructs
    // below but has interesting stuff between, mangle the input so that these
    // characters do not do any harm.
    \core\common\Entity::outOfThePotatoes();
    return htmlentities($check, ENT_QUOTES);
}

/**
 * could this be a valid username? 
 * 
 * Only checks correct form, not if the user actually exists in the system.
 * 
 * @param mixed $input the username
 * @return string echoes back the input string, or throws an Exception if bogus
 * @throws Exception
 */
public function User($input) {
    $retvalStep0 = iconv("UTF-8", "UTF-8//TRANSLIT", $input);
    if ($retvalStep0 === FALSE) {
        throw new Exception("iconv failure for string sanitisation. With TRANSLIT, this should never happen!");
    }
    $retvalStep1 = trim($retvalStep0);

    $retval = preg_replace('/(\0|\r|\x0b|\t|\n)/', '', $retvalStep1);
    if ($retval != "" && !ctype_print($retval)) {
        throw new Exception($this->inputValidationError("The user identifier is not an ASCII string!"));
    }

    return $retval;
}

/**
 * could this be a valid token? 
 * 
 * Only checks correct form, not if the token actually exists in the system.
 * @param mixed $input the raw input
 * @return string echoes back the input string, or throws an Exception if bogus
 * @throws Exception
 */
public function token($input) {
    $retval = $input;
    if ($input != "" && preg_match('/[^0-9a-fA-F]/', $input) != 0) {
        throw new Exception($this->inputValidationError("Token is not a hexadecimal string!"));
    }
    return $retval;
}

/**
 * Is this be a valid coordinate vector on one axis?
 * 
 * @param mixed $input a numeric value in range of a geo coordinate [-180;180]
 * @return string returns back the input if all is good; throws an Exception if out of bounds or not numeric
 * @throws Exception
 */
public function coordinate($input) {
    $oldlocale = setlocale(LC_NUMERIC, 0);
    setlocale(LC_NUMERIC, "en_GB");
    if (!is_numeric($input)) {
        throw new Exception($this->inputValidationError("Coordinate is not a numeric value!"));
    }
    setlocale(LC_NUMERIC, $oldlocale);
    // lat and lon are always in the range of [-180;+180]
    if ($input < -180 || $input > 180) {
        throw new Exception($this->inputValidationError("Coordinate is out of bounds. Which planet are you from?"));
    }
    return $input;
}

/**
 * Is this a valid coordinate pair in JSON encoded representation?
 * 
 * @param mixed $input the string to be checked: is this a serialised array with lat/lon keys in a valid number range?
 * @return string returns $input if checks have passed; throws an Exception if something's wrong
 * @throws Exception
 */
public function coordJsonEncoded($input) {
    $tentative = json_decode($input, true);
    if (is_array($tentative)) {
        if (isset($tentative['lon']) && isset($tentative['lat']) && $this->coordinate($tentative['lon']) && $this->coordinate($tentative['lat'])) {
            return $input;
        }
    }
    throw new Exception($this->inputValidationError("Wrong coordinate encoding (2.0 uses JSON, not serialize)!"));
}

/**
 * This checks the state of a HTML GET/POST "boolean".
 * 
 * If not checked, no value is submitted at all; if checked, has the word "on". 
 * Anything else is a big error.
 * 
 * @param mixed $input the string to test
 * @return boolean TRUE if the input was "on". It is not possible in HTML to signal "off"
 * @throws Exception
 */
public function boolean($input) {
    if ($input != "on") {
        throw new Exception($this->inputValidationError("Unknown state of boolean option!"));
    }
    return TRUE;
}

const TABLEMAPPING = [
    "IdP" => "institution_option",
    "Profile" => "profile_option",
    "FED" => "federation_option",
];

/**
 * Is this a valid database reference? Has the form <tablename>-<rowID> and there
 * needs to be actual data at that place
 * 
 * @param string $input the reference to check
 * @return boolean|array the reference split up into "table" and "rowindex", or FALSE
 */
public function databaseReference($input) {
    $pregMatches = [];
    if (preg_match("/^ROWID-(IdP|Profile|FED)-([0-9]+)$/", $input, $pregMatches) != 1) {
        return FALSE;
    }
    $rownumber = $this->integer($pregMatches[2]);
    if ($rownumber === FALSE) {
        return FALSE;
    }
    return ["table" => self::TABLEMAPPING[$pregMatches[1]], "rowindex" => $rownumber];
}

/**
 * is this a valid hostname?
 * 
 * @param mixed $input the raw input
 * @return boolean|string echoes the hostname, or FALSE if bogus
 */
public function hostname($input) {
    // is it a valid IP address (IPv4 or IPv6), or a hostname?
    if (filter_var($input, FILTER_VALIDATE_IP) || filter_var($input, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        // if it's a verified IP address or hostname then it does not contain
        // rubbish of course. But just to be sure, run htmlspecialchars around it
        return htmlspecialchars($input, ENT_QUOTES);
    }
    return FALSE;
}

/**
 * is this a valid email address?
 * 
 * @param mixed $input the raw input
 * @return boolean|string echoes the mail address, or FALSE if bogus
 */
public function email($input) {

    if (filter_var($this->string($input), FILTER_VALIDATE_EMAIL)) {
        return $input;
    }
    // if we get here, it's bogus
    return FALSE;
}

/**
 * is this a well-formed SMS number? Light massaging - leading + will be removed
 * @param string $input the raw input
 * @return boolean|string
 */
public function sms($input) {
    $number = str_replace(' ', '', str_replace(".", "", str_replace("+", "", $input)));
    if (!is_numeric($number)) {
        return FALSE;
    }
    return $number;
}

/**
 * Is this is a language we support? If not, sanitise to our configured default language.
 * 
 * @param mixed $input the candidate language identifier
 * @return string
 */
public function supportedLanguage($input) {
    if (!array_key_exists($input, CONFIG['LANGUAGES'])) {
        return CONFIG['APPEARANCE']['defaultlocale'];
    }
    // otherwise, use the inversion trick to convince Scrutinizer that this is
    // a vetted value
    $retval = array_search(CONFIG['LANGUAGES'][$input], CONFIG['LANGUAGES']);
    if ($retval === FALSE) {
        throw new Exception("Impossible: the value we are searching for does exist, because we reference it directly.");
    }
    return $retval;
}

/**
 * Makes sure we are not receiving a bogus option name. The called function throws
 * an assertion if the name is not known.
 * 
 * @param mixed $input the unvetted option name
 * @return string
 */
public function optionName($input) {
    $object = \core\Options::instance();
    return $object->assertValidOptionName($input);
}

/**
 * Checks to see if the input is a valid image of sorts
 * 
 * @param mixed $binary blob that may or may not be a parseable image
 * @return boolean
 */
public function image($binary) {
    $image = new \Imagick();
    try {
        $image->readImageBlob($binary);
    } catch (\ImagickException $exception) {
        echo "Error" . $exception->getMessage();
        return FALSE;
    }
    // image survived the sanity check
    return TRUE;
}

}
