<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Helper.php");
require_once("Options.php");
require_once("CAT.php");
require_once("X509.php");
require_once("EAP.php");
require_once("DBConnection.php");

require_once("input_validation.inc.php");
require_once("auth.inc.php"); // no authentication here, but we need to check if authenticated

define("BUTTON_CLOSE", 0);
define("BUTTON_CONTINUE", 1);
define("BUTTON_DELETE", 2);
define("BUTTON_SAVE", 3);
define("BUTTON_EDIT", 4);
define("BUTTON_TAKECONTROL", 5);
define("BUTTON_PURGECACHE", 6);
define("BUTTON_FLUSH_AND_RESTART", 7);
define("BUTTON_SANITY_TESTS", 8);

$global_location_count = 0;

function display_name($input) {
    $displayNames = [_("Support: Web") => "support:url",
        _("Support: EAP Types") => "support:eap_types",
        _("Support: Phone") => "support:phone",
        _("Support: E-Mail") => "support:email",
        _("Institution Name") => "general:instname",
        _("Location") => "general:geo_coordinates",
        _("Logo URL") => "general:logo_url",
        _("Logo image") => "general:logo_file",
        _("Configure Wired Ethernet") => "media:wired",
        _("Name (CN) of Authentication Server") => "eap:server_name",
        _("Enable device assessment") => "eap:enable_nea",
        _("Terms of Use") => "support:info_file",
        _("CA Certificate URL") => "eap:ca_url",
        _("CA Certificate File") => "eap:ca_file",
        _("Profile Display Name") => "profile:name",
        _("Production-Ready") => "profile:production",
        _("Extra text on downloadpage for device") => "device-specific:customtext",
        _("Redirection Target") => "device-specific:redirect",
        _("Extra text on downloadpage for EAP method") => "eap-specific:customtext",
        _("Turn on selection of EAP-TLS User-Name") => "eap-specific:tls_use_other_id",
        _("Profile Description") => "profile:description",
        _("Federation Administrator") => "user:fedadmin",
        _("Real Name") => "user:realname",
        _("E-Mail Address") => "user:email",
        _("PEAP-MSCHAPv2") => EAP::$PEAP_MSCHAP2,
        _("TLS") => EAP::$TLS,
        _("TTLS-PAP") => EAP::$TTLS_PAP,
        _("TTLS-MSCHAPv2") => EAP::$TTLS_MSCHAP2,
        _("TTLS-GTC") => EAP::$TTLS_GTC,
        _("FAST-GTC") => EAP::$FAST_GTC,
        _("EAP-pwd") => EAP::$PWD,
        _("eduroam-as-a-service") => EAP::$SILVERBULLET,
        _("Remove/Disable SSID") => "media:remove_SSID",
        _("Custom CSS file for User Area") => "fed:css_file",
        _("Federation Logo") => "fed:logo_file",
        _("Preferred Skin for User Area") => "fed:desired_skin",
        _("Federation Operator Name") => "fed:realname",
        _("Custom text in IdP Invitations") => "fed:custominvite",
        _("Enable Silver Bullet") => "fed:silverbullet",
        _("Silver Bullet: Do not terminate EAP") => "fed:silverbullet-noterm",
        _("Silver Bullet: max users per profile") => "fed:silverbullet-maxusers",
    ];

    if (count(Config::$CONSORTIUM['ssid']) > 0) {
        $displayNames[_("Additional SSID")] = "media:SSID";
        $displayNames[_("Additional SSID (with WPA/TKIP)")] = "media:SSID_with_legacy";
    } else {
        $displayNames[_("SSID")] = "media:SSID";
        $displayNames[_("SSID (with WPA/TKIP)")] = "media:SSID_with_legacy";
    }

    if (!empty(Config::$CONSORTIUM['interworking-consortium-oi']) && count(Config::$CONSORTIUM['interworking-consortium-oi']) > 0) {
        $displayNames[_("Additional HS20 Consortium OI")] = "media:consortium_OI";
    } else {
        $displayNames[_("HS20 Consortium OI")] = "media:consortium_OI";
    }

    $find = array_search($input, $displayNames);

    if ($find === FALSE) { // sending back the original if we didn't find a better name
        $find = $input;
    }
    return $find;
}

function tooltip($input) {
    $descriptions = [];
    if (count(Config::$CONSORTIUM['ssid']) > 0) {
        $descriptions[sprintf(_("This attribute can be set if you want to configure an additional SSID besides the default SSIDs for %s. It is almost always a bad idea not to use the default SSIDs. The only exception is if you have premises with an overlap of the radio signal with another %s hotspot. Typical misconceptions about additional SSIDs include: I want to have a local SSID for my own users. It is much better to use the default SSID and separate user groups with VLANs. That approach has two advantages: 1) your users will configure %s properly because it is their everyday SSID; 2) if you use a custom name and advertise this one as extra secure, your users might at some point roam to another place which happens to have the same SSID name. They might then be misled to believe that they are connecting to an extra secure network while they are not."), Config::$CONSORTIUM['name'], Config::$CONSORTIUM['name'], Config::$CONSORTIUM['name'])] = "media:SSID";
    }

    $find = array_search($input, $descriptions);

    if ($find === FALSE) {
        return "";
    }
    return "<span class='tooltip' onclick='alert(\"" . $find . "\")'><img src='../resources/images/icons/question-mark-icon.png" . "'></span>";
}

function UI_message($level, $text = 0, $caption = 0, $omittabletags = FALSE) {

    $uiMessages = [
        L_OK => ['icon' => '../resources/images/icons/Quetto/check-icon.png', 'text' => _("OK")],
        L_REMARK => ['icon' => '../resources/images/icons/Quetto/info-icon.png', 'text' => _("Remark")],
        L_WARN => ['icon' => '../resources/images/icons/Quetto/danger-icon.png', 'text' => _("Warning!")],
        L_ERROR => ['icon' => '../resources/images/icons/Quetto/no-icon.png', 'text' => _("Error!")],
    ];

    $retval = "";
    if (!$omittabletags)
        $retval .= "<tr><td>";
    $caption = $caption !== 0 ? $caption : $uiMessages[$level]['text'];
    $retval .= "<img class='icon' src='" . $uiMessages[$level]['icon'] . "' alt='" . $caption . "' title='" . $caption . "'/>";
    if (!$omittabletags)
        $retval .= "</td><td>";
    if ($text !== 0)
        $retval .= $text;
    if (!$omittabletags)
        $retval .= "</td></tr>";
    return $retval;
}

function UI_okay($text = 0, $caption = 0, $omittabletags = FALSE) {
    return UI_message(L_OK, $text, $caption, $omittabletags);
}

function UI_remark($text = 0, $caption = 0, $omittabletags = FALSE) {
    return UI_message(L_REMARK, $text, $caption, $omittabletags);
}

function UI_warning($text = 0, $caption = 0, $omittabletags = FALSE) {
    return UI_message(L_WARN, $text, $caption, $omittabletags);
}

function UI_error($text = 0, $caption = 0, $omittabletags = FALSE) {
    return UI_message(L_ERROR, $text, $caption, $omittabletags);
}

function check_upload_sanity($optiontype, $filename) {
    switch ($optiontype) {
        case "general:logo_file":
        case "fed:logo_file":
        case "internal:logo_from_url":
            // we check logo_file with ImageMagick
            $image = new Imagick();
            try {
                $image->readImageBlob($filename);
            } catch (ImagickException $exception) {
                echo "Error" . $exception->getMessage();
                return FALSE;
            }
            // image survived the sanity check
            return TRUE;
        case "eap:ca_file":
            // echo "Checking $optiontype with file $filename";
            $cert = X509::processCertificate($filename);
            if ($cert) {
                return TRUE;
            }
            // echo "Error! The certificate seems broken!";
            return FALSE;
        case "support:info_file":
            $info = new finfo();
            $filetype = $info->buffer($filename, FILEINFO_MIME_TYPE);

            // we only take plain text files in UTF-8!
            if ($filetype == "text/plain" && iconv("UTF-8", "UTF-8", $filename) !== FALSE) {
                return TRUE;
            }
            return FALSE;
        default:
            return FALSE;
    }
}

function getBlobFromDB($ref, $checkpublic) {

    $reference = valid_DB_reference($ref);

    if ($reference == FALSE) {
        return;
    }

    // the data is either public (just give it away) or not; in this case, only
    // release if the data belongs to admin himself
    if ($checkpublic) {
        // we might be called without session context (filepreview) so get the
        // context if needed
        if (session_status() != PHP_SESSION_ACTIVE) {
            session_start();
        }
        $owners = DBConnection::isDataRestricted($reference["table"], $reference["rowindex"]);

        $ownersCondensed = [];

        if ($owners !== FALSE) { // restricted datam see if we're authenticated and owners of the data
            if (!isAuthenticated()) {
                return FALSE; // admin-only, but we are not an admin
            }
            foreach ($owners as $oneowner) {
                $ownersCondensed[] = $oneowner['ID'];
            }
            if (array_search($_SESSION['user'], $ownersCondensed) === FALSE) {
                return FALSE; // wrong guy
            }
            // carry on and get the data
        }
    }

    $blob = DBConnection::fetchRawDataByIndex($reference["table"], $reference["rowindex"]);
    if (!$blob) {
        return FALSE;
    }
    return $blob;
}

function display_size($number) {
    if ($number > 1024 * 1024) {
        return round($number / 1024 / 1024, 2) . " MiB";
    }
    if ($number > 1024) {
        return round($number / 1024, 2) . " KiB";
    }
    return $number . " B";
}

function previewCAinHTML($cAReference) {
    $found = preg_match("/^ROWID-.*/", $cAReference);
    if (!$found) {
        return "<div>" . _("Error, ROWID expected.") . "</div>";
    }

    $ca_blob = base64_decode(getBlobFromDB($cAReference, FALSE));

    $func = new X509;
    $details = $func->processCertificate($ca_blob);
    if ($details === FALSE) {
        return _("There was an error processing the certificate!");
    }

    $details['name'] = preg_replace('/(.)\/(.)/', "$1<br/>$2", $details['name']);
    $details['name'] = preg_replace('/\//', "", $details['name']);
    $certstatus = ( $details['root'] == 1 ? "R" : "I");
    if ($details['ca'] == 0 && $details['root'] != 1) {
        return "<div class='ca-summary' style='background-color:red'><div style='position:absolute; right: 0px; width:20px; height:20px; background-color:maroon;  border-radius:10px; text-align: center;'><div style='padding-top:3px; font-weight:bold; color:#ffffff;'>S</div></div>" . _("This is a <strong>SERVER</strong> certificate!") . "<br/>" . $details['name'] . "</div>";
    }
    return "<div class='ca-summary'                                ><div style='position:absolute; right: 0px; width:20px; height:20px; background-color:#0000ff; border-radius:10px; text-align: center;'><div style='padding-top:3px; font-weight:bold; color:#ffffff;'>$certstatus</div></div>" . $details['name'] . "</div>";
}

function previewImageinHTML($imageReference) {
    $found = preg_match("/^ROWID-.*/", $imageReference);
    if (!$found) {
        return "<div>" . _("Error, ROWID expected.") . "</div>";
    }
    return "<img style='max-width:150px' src='inc/filepreview.php?id=" . $imageReference . "' alt='" . _("Preview of logo file") . "'/>";
}

function previewInfoFileinHTML($fileReference) {
    $found = preg_match("/^ROWID-.*/", $fileReference);
    if (!$found) {
        return _("<div>Error, ROWID expected, got $fileReference.</div>");
    }

    $fileBlob = unserialize(getBlobFromDB($fileReference, FALSE));
    $decodedFileBlob = base64_decode($fileBlob['content']);
    $fileinfo = new finfo();
    return "<div class='ca-summary'>" . _("File exists") . " (" . $fileinfo->buffer($decodedFileBlob, FILEINFO_MIME_TYPE) . ", " . display_size(strlen($decodedFileBlob)) . ")<br/><a href='inc/filepreview.php?id=$fileReference'>" . _("Preview") . "</a></div>";
}

function infoblock($optionlist, $class, $level) {
// echo "<pre>".print_r($optionlist)."</pre>";
    $googleMarkers = [];
    $retval = "";
    $optioninfo = Options::instance();

    foreach ($optionlist as $option) {
        $type = $optioninfo->optionType($option['name']);
// echo "CLASS $class, OPTIONNAME ".$option['name']." LEVEL $level, TYPE ".$type['type']." FLAG ".$type['flag']."\n";
        if (preg_match('/^' . $class . '/', $option['name']) && $option['level'] == "$level") {
            // all non-multilang attribs get this assignment ...
            $language = "";
            $content = $option['value'];
            // ... override them with multilang tags if needed
            if ($type["flag"] == "ML") {
                // echo "processing multi-lang ".$option['name']. "with value ".$option['value'];
                $taggedarray = unserialize($option['value']);
                $language = _("default/other languages");
                if ($taggedarray['lang'] != 'C') {
                    $language = Config::$LANGUAGES[$taggedarray['lang']]['display'];
                }
                $content = $taggedarray["content"];
            }

            switch ($type["type"]) {
                case "coordinates":
                    $coords = unserialize($option['value']);
                    $googleMarkers[] = $coords;
                    break;
                case "file":
                    $retval .= "<tr><td>" . display_name($option['name']) . "</td><td>$language</td><td>";
                    switch ($option['name']) {
                        case "general:logo_file":
                        case "fed:logo_file":
                            $retval .= previewImageinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                            break;
                        case "eap:ca_file":
                            $retval .= previewCAinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                            break;
                        case "support:info_file":
                            $retval .= previewInfoFileinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                            break;
                        default:
                    }
                    break;
                case "boolean":
                    $retval .= "<tr><td>" . display_name($option['name']) . "</td><td>$language</td><td><strong>" . ($content == "on" ? _("on") : _("off") ) . "</strong></td></tr>";
                    break;
                default:
                    $retval .= "<tr><td>" . display_name($option['name']) . "</td><td>$language</td><td><strong>$content</strong></td></tr>";
            }
        }
    }
    if (count($googleMarkers)) {
        $marker = '<markers>';
        $locationCount = 0;
        foreach ($googleMarkers as $g) {
            $locationCount++;
            $marker .= '<marker name="' . $locationCount . '" lat="' . $g['lat'] . '" lng="' . $g['lon'] . '" />';
        }
        $marker .= '</markers>';
        $retval .= '<tr><td><script>markers=\'' . $marker . '\';</script></td><td></td><td></td></tr>';
    }
    return $retval;
}
