<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once 'Options.php';
require_once 'DBConnection.php';

// validation functions return HTML snippets. Ideally, should be called after
// HTML <head>, for beautiful output even in these error cases

function input_validation_error($customtext) {
    return "<p>" . _("Input validation error: ") . $customtext . "</p>";
}

function valid_Fed($input, $owner) {
    try {
        $temp = new Federation($input);
    } catch (Exception $fail) {
        echo input_validation_error(_("This Federation identifier is not accessible!"));
        exit(1);
    }

    foreach ($temp->listFederationAdmins() as $oneowner)
        if ($oneowner == $owner)
            return $temp;
    echo input_validation_error(_("This Federation identifier is not accessible!"));
    exit(1);
}

function valid_IdP($input, $owner = 0) {
    if (!is_numeric($input)) {
        echo input_validation_error(_("Value for IdP is not an integer!"));
        exit(1);
    }
    try {
        $temp = new IdP($input);
    } catch (Exception $fail) {
        echo input_validation_error(_("This IdP identifier is not accessible!"));
        exit(1);
    }

    if ($owner !== 0) { // check if the authenticated user is allowed to see this institution
        foreach ($temp->owner() as $oneowner)
            if ($oneowner['ID'] == $owner)
                return $temp;
        echo input_validation_error(_("This IdP identifier is not accessible!"));
        exit(1);
    }
    return $temp;
}

function valid_Profile($input, $idp_identifier = NULL) {
    if (!is_numeric($input)) {
        echo input_validation_error(_("Value for profile is not an integer!"));
        exit(1);
    }
    try {
        $temp = new Profile($input);
    } catch (Exception $fail) {
        echo input_validation_error(_("This profile is not accessible!"));
        exit(1);
    }

    if ($idp_identifier !== NULL && $temp->institution != $idp_identifier) {
        echo input_validation_error(_("This profile is not accessible!"));
        exit(1);
    }
    return $temp;
}

function valid_Device($input) {
    $devicelist = Devices::listDevices();
    if (!isset($devicelist[$input]))
        echo input_validation_error(_("This device does not exist!"));
    return $input;
}

function valid_string_db($input, $allow_whitspace = 0) {
    // always chop out invalid characters, and surrounding whitespace
    $retval = trim(iconv("UTF-8", "UTF-8//TRANSLIT", $input));
    // if some funny person wants to inject markup tags, remove them
    $retval = filter_var($retval, FILTER_SANITIZE_STRING, ["flags" => FILTER_FLAG_NO_ENCODE_QUOTES]);
    // unless explicitly wanted, take away intermediate disturbing whitespace
    // a simple "space" is NOT disturbing :-)
    if ($allow_whitspace === 0)
        $retval = preg_replace('/(\0|\r|\x0b|\t|\n)/', '', $retval);
    else // even if we allow whitespace, not pathological ones!
        $retval = preg_replace('/(\0|\r|\x0b)/', '', $retval);

    return $retval;
}

function valid_consortium_oi($input) {
    $shallow = valid_string_db($input);
    if (strlen($shallow) != 6 && strlen($shallow) != 10)
        return FALSE;
    if (!preg_match("/^[a-fA-F0-9]+$/", $shallow))
        return FALSE;
    return $shallow;
}

function valid_Realm($input) {
    // basic string checks
    $check = valid_string_db($input);
    // bark on invalid constructs
    if (preg_match("/@/", $check) == 1) {
        echo input_validation_error(_("Realm contains an @ sign!"));
        return FALSE;
    }
    if (preg_match("/^\./", $check) == 1) {
        echo input_validation_error(_("Realm begins with a . (dot)!"));
        return FALSE;
    }
    if (preg_match("/\.$/", $check) == 1) {
        echo input_validation_error(_("Realm ends with a . (dot)!"));
        return FALSE;
    }
    if (preg_match("/\./", $check) == 0) {
        echo input_validation_error(_("Realm does not contain at least one . (dot)!"));
        return FALSE;
    }
    if (preg_match("/ /", $check) == 1) {
        echo input_validation_error(_("Realm contains spaces!"));
        return FALSE;
    }
    return $check;
}

function valid_user($input) {
    $retval = $input;
    if ($input != "" && !ctype_print($input)) {
        echo input_validation_error(_("The user identifier is not an ASCII string!"));
        exit(1);
    }
    return $retval;
}

function valid_token($input) {
    $retval = $input;
    if ($input != "" && preg_match('/[^0-9a-fA-F]/', $input) != 0) {
        echo input_validation_error(_("Token is not a hexadecimal string!"));
        exit(1);
    }
    return $retval;
}

function valid_coordinate($input) {
    if (!is_numeric($input)) {
        echo input_validation_error(_("Coordinate is not a numeric value!"));
        exit(1);
    }
    // lat and lon are always in the range of [-180;+180]
    if ($input < -180 || $input > 180) {
        echo input_validation_error(_("Coordinate is out of bounds. Which planet are you from?"));
        exit(1);
    }
    return $input;
}

function valid_coord_serialized($input) {
    if (is_array(unserialize($input))) {
        $tentative = unserialize($input);
        if (isset($tentative['lon']) && isset($tentative['lat']) && valid_coordinate($tentative['lon']) && valid_coordinate($tentative['lat']))
            return $input;
    } else {
        echo input_validation_error(_("Wrong coordinate encoding!"));
        exit(1);
    }
}

function valid_multilang($content) {
    if (!is_array($content) || !isset($content["lang"]) || !isset($content["content"])) {
        echo input_validation_error(_("Invalid structure in multi-language attribute!"));
        exit(1);
    }
}

function valid_boolean($input) {
    if ($input != "on") {
        echo input_validation_error(_("Unknown state of boolean option!"));
        exit(1);
    } else
        return $input;
}

function valid_DB_reference($input) {
    $table = "";
    $rowindex = "";
    $rowindexmatch = [];

    if (preg_match("/IdP/", $input)) {
        $table = "institution_option";
    } elseif (preg_match("/Profile/", $input)) {
        $table = "profile_option";
    } elseif (preg_match("/FED/", $input)) {
        $table = "federation_option";
    } else
        return FALSE;
    if (preg_match("/.*-([0-9]*)/", $input, $rowindexmatch)) {
        $rowindex = $rowindexmatch[1];
    } else
        return FALSE;
    return ["table" => $table, "rowindex" => $rowindex];
}

function valid_host($input) {
    // is it a valid IP address (IPv4 or IPv6)?
    if (filter_var($input, FILTER_VALIDATE_IP))
        return $input;
    // if not, it must be a host name. Use email validation by prefixing with a local part
    if (filter_var("stefan@" . $input, FILTER_VALIDATE_EMAIL))
        return $input;
    // if we get here, it's bogus
    return FALSE;
}

?>
