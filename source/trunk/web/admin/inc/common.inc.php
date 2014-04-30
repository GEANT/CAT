<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
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

define ("BUTTON_CLOSE", 0);
define ("BUTTON_CONTINUE", 1);
define ("BUTTON_DELETE", 2);
define ("BUTTON_SAVE", 3);
define ("BUTTON_EDIT", 4);
define ("BUTTON_TAKECONTROL", 5);
define ("BUTTON_PURGECACHE", 6);

$global_location_count = 0;

function display_name($input) {
    $DisplayNames = array(_("Support: Web") => "support:url",
        _("Support: EAP Types") => "support:eap_types",
        _("Support: Phone") => "support:phone",
        _("Support: E-Mail") => "support:email",
        _("Institution Name") => "general:instname",
        _("Location") => "general:geo_coordinates",
        _("Logo URL") => "general:logo_url",
        _("Logo image") => "general:logo_file",
        _("Configure Wired Ethernet") => "general:wired",
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
        _("Wired 802.1X?") => "media:wired",
        _("Remove/Disable SSID") => "media:remove_SSID",
    );

    if(count(Config::$CONSORTIUM['ssid']) > 0) {
      $DisplayNames[_("Additional SSID")] = "media:SSID";
      $DisplayNames[_("Additional SSID (with WPA/TKIP)")] = "media:SSID_with_legacy";
    } else {
      $DisplayNames[_("SSID")] = "media:SSID";
      $DisplayNames[_("SSID (with WPA/TKIP)")] = "media:SSID_with_legacy";  
    }

    if (count(Config::$CONSORTIUM['interworking-consortium-oi']) > 0)
    $DisplayNames[_("Additional HS20 Consortium OI")] = "media:consortium_OI";
    $DisplayNames[_("HS20 Consortium OI")] = "media:consortium_OI";
    
    $find = array_search($input, $DisplayNames);

    if ($find === FALSE) {
        return $input;
    } else {
        return $find;
    }
}

function tooltip($input) {
    $descriptions = array ();
    if (count(Config::$CONSORTIUM['ssid']) > 0) 
        $descriptions[sprintf(_("This attribute can be set if you want to configure an additional SSID besides the default SSIDs for %s. It is almost always a bad idea not to use the default SSIDs. The only exception is if you have premises with an overlap of the radio signal with another %s hotspot. Typical misconceptions about additional SSIDs include: I want to have a local SSID for my own users. It is much better to use the default SSID and separate user groups with VLANs. That approach has two advantages: 1) your users will configure %s properly because it is their everyday SSID; 2) if you use a custom name and advertise this one as extra secure, your users might at some point roam to another place which happens to have the same SSID name. They might then be misled to believe that they are connecting to an extra secure network while they are not."), Config::$CONSORTIUM['name'], Config::$CONSORTIUM['name'],  Config::$CONSORTIUM['name'])] = "general:SSID";

    $find = array_search($input,$descriptions);

    if ( $find === FALSE ) {
        return "";
    }
    else {
        return "<span class='tooltip' onclick='alert(\"".$find."\")'><img src='../resources/images/icons/question-mark-icon.png"."'></span>";
    }

}

function UI_okay($text = 0, $caption = 0, $omittabletags = FALSE) {
    $retval = "";
    if (!$omittabletags)
        $retval .= "<tr><td>";
    $retval .= "<img class='icon' src='../resources/images/icons/Checkmark-lg-icon.png' alt='" . ($caption !== 0 ? $caption : _("OK!")) . "' title='" . ($caption !== 0 ? $caption : _("OK!")) . "'/>";
    if (!$omittabletags)
        $retval .= "</td><td>";
    if ($text !== 0) $retval .= $text;
    if (!$omittabletags)
        $retval .= "</td></tr>";
    return $retval;
}

function UI_warning($text = 0, $caption = 0, $omittabletags = FALSE) {
    $retval = "";
    if (!$omittabletags)
        $retval .= "<tr><td>";
    $retval .= "<img class='icon' src='../resources/images/icons/Exclamation-yellow-icon.png' alt='" . ($caption !== 0 ? $caption : _("Warning!")) . "' title='" . ($caption !== 0 ? $caption : _("Warning!")) . "'/>";
    if (!$omittabletags)
        $retval .= "</td><td>";
    if ($text !== 0) $retval .= $text;
    if (!$omittabletags)
        $retval .= "</td></tr>";
    return $retval;
}

function UI_error($text = 0, $caption = 0, $omittabletags = FALSE) {
    $retval = "";
    if (!$omittabletags)
        $retval .= "<tr><td>";
    $retval .= "<img class='icon' src='../resources/images/icons/Exclamation-orange-icon.png' alt='" . ($caption !== 0 ? $caption : _("Error!")) . "' title='" . ($caption !== 0 ? $caption : _("Error!")) . "'/>";
    if (!$omittabletags)
        $retval .= "</td><td>";
    if ($text !== 0) $retval .= $text;
    if (!$omittabletags)
        $retval .= "</td></tr>";
    return $retval;
}

function UI_remark($text = 0, $caption = 0, $omittabletags = FALSE) {
    $retval = "";
    if (!$omittabletags)
        $retval .= "<tr><td>";
    $retval .= "<img class='icon' src='../resources/images/icons/Star-blue.png' alt='" . ($caption !== 0 ? $caption : _("Remark")) . "' title='" . ($caption !== 0 ? $caption : _("Remark")) . "'/>";
    if (!$omittabletags)
        $retval .= "</td><td>";
    if ($text !== 0) $retval .= $text;
    if (!$omittabletags)
        $retval .= "</td></tr>";
    return $retval;
}

function check_upload_sanity($optiontype, $filename) {
//echo "check_upload_sanity:$optiontype:$filename<br>\n";
// we check logo_file with ImageMagick

    if ($optiontype == "general:logo_file") {
        $image = new Imagick();
        try {
            $image->readImageBlob($filename);
        } catch (ImagickException $e) {
            echo "Error" . $e->getMessage();
            return FALSE;
        }
// echo "Image survived the sanity check.";
        return TRUE;
    };

// imported logos from URL are present as binary string, not filename

    if ($optiontype == "internal:logo_from_url") {
        $image = new Imagick();
        try {
            $image->readImageBlob($filename);
        } catch (ImagickException $e) {
            echo "Error" . $e->getMessage();
            return FALSE;
        }
// echo "Image survived the sanity check.";
        return TRUE;
    };

// we check CA files with X.509 routines
// TODO this needs to be fixed
    if ($optiontype == "eap:ca_file") {
 // echo "Checking $optiontype with file $filename";
        $cert = X509::processCertificate($filename);
        if ($cert)
            return TRUE;
 // echo "Error! The certificate seems broken!";
        return FALSE;
    }

// ToU files are checked by guessing the mime type of the file content
// some mime types are white-listed, the rest is rejected

    if ($optiontype == "support:info_file") {
        $info = new finfo();
        $filetype = $info->buffer($filename, FILEINFO_MIME_TYPE);

        // we only take plain text files!
        if (    /* $filetype == "application/rtf"
                   || $filetype == "text/rtf"
                   ||
                */ 
                $filetype == "text/plain"
// || $filetype == "application/rtf"
        )
            return TRUE;
    }

    return FALSE;
}

function getBlobFromDB($ref) {

    $table = "";
    $rowindex = "";

    if (preg_match("/IdP/", $ref))
        $table = "institution_option";
    if (preg_match("/Profile/", $ref))
        $table = "profile_option";
    preg_match("/.*-([0-9]*)/", $ref, $rowindexmatch);
    $rowindex = $rowindexmatch[1];

    if ($table == "" || $rowindex == "")
        return;

    $blob = DBConnection::fetchRawDataByIndex($table, $rowindex);
    if (!$blob)
        return FALSE;
    return $blob;
}

function display_size($number) {
    if ($number > 1024 * 1024)
        return round($number / 1024 / 1024, 2) . " MiB";
    if ($number > 1024)
        return round($number / 1024, 2) . " KiB";
    return $number . " B";
}

function previewCAinHTML($ca_reference) {
    $found = preg_match("/^ROWID-.*/", $ca_reference);
    if (!$found)
        return "<div>" . _("Error, ROWID expected.") . "</div>";

    $ca_blob = base64_decode(getBlobFromDB($ca_reference));

    $func = new X509;
    $details = $func->processCertificate($ca_blob);
    if ($details === FALSE)
        return _("There was an error processing the certificate!");
    $details['name'] = preg_replace('/(.)\/(.)/', "$1<br/>$2", $details['name']);
    $details['name'] = preg_replace('/\//', "", $details['name']);
    $certstatus = ( $details['root'] == 1 ? "R" : "I");
    if ($details['ca'] == 0 && $details['root'] != 1)
        return "<div class='ca-summary' style='background-color:red'><div style='position:absolute; right: 0px; width:20px; height:20px; background-color:maroon;  border-radius:10px; text-align: center;'><div style='padding-top:3px; font-weight:bold; color:#ffffff;'>S</div></div>" . _("This is a <strong>SERVER</strong> certificate!")."<br/>".$details['name'] . "</div>";
    else
        return "<div class='ca-summary'                                ><div style='position:absolute; right: 0px; width:20px; height:20px; background-color:#0000ff; border-radius:10px; text-align: center;'><div style='padding-top:3px; font-weight:bold; color:#ffffff;'>$certstatus</div></div>" . $details['name'] . "</div>";
}

function previewImageinHTML($image_reference) {
    $found = preg_match("/^ROWID-.*/", $image_reference);
    if (!$found)
        return "<div>"._("Error, ROWID expected.")."</div>";
    return "<img style='max-width:150px' src='inc/filepreview.php?id=" . $image_reference . "' alt='" . _("Preview of logo file") . "'/>";
}

function previewInfoFileinHTML($file_reference) {
    $found = preg_match("/^ROWID-.*/", $file_reference);
    if (!$found)
        return _("<div>Error, ROWID expected, got $file_reference.</div>");

    $file_blob = unserialize(getBlobFromDB($file_reference));
    $file_blob = base64_decode($file_blob['content']);
    $fileinfo = new finfo();
    return "<div class='ca-summary'>" . _("File exists") . " (" . $fileinfo->buffer($file_blob, FILEINFO_MIME_TYPE) . ", " . display_size(strlen($file_blob)) . ")<br/><a href='inc/filepreview.php?id=$file_reference'>" . _("Preview") . "</a></div>";
}

function infoblock($optionlist, $class, $level) {
// echo "<pre>".print_r($optionlist)."</pre>";
    $google_markers = array();
    $retval = "";
    $optioninfo = Options::instance();

    foreach ($optionlist as $option) {
        $type = $optioninfo->optionType($option['name']);
// echo "CLASS $class, OPTIONNAME ".$option['name']." LEVEL $level, TYPE ".$type['type']." FLAG ".$type['flag']."\n";
        if (preg_match('/^' . $class . '/', $option['name']) && $option['level'] == "$level") {
            $language;
// display multilang tags if needed

            if ($type["flag"] == "ML") {
                // echo "processing multi-lang ".$option['name']. "with value ".$option['value'];
                $taggedarray = unserialize($option['value']);
                /* echo "<pre>";
                  print_r($taggedarray);
                  echo "</pre>"; */
                if ($taggedarray['lang'] == 'C')
                    $language = _("default/other languages");
                else
                    $language = Config::$LANGUAGES[$taggedarray['lang']]['display'];
                $content = $taggedarray["content"];
            } else {
                $language = "";
                $content = $option['value'];
            };
            switch ($type["type"]) {
                case "coordinates":
                    $coords = unserialize($option['value']);
                    $google_markers[] = $coords;
                    break;
                case "file":
                    $retval .= "<tr><td>" . display_name($option['name']) . "</td><td>$language</td><td>";
                    switch ($option['name']) {
                        case "general:logo_file":
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
    if (count($google_markers)) {
        $marker = '<markers>';
        $location_count = 0;
        foreach ($google_markers as $g) {
            $location_count++;
            $marker .= '<marker name="' . $location_count . '" lat="' . $g['lat'] . '" lng="' . $g['lon'] . '" />';
        }
        $marker .= '</markers>';
        $retval .= '<tr><td><script>markers=\'' . $marker . '\';</script></td><td></td><td></td></tr>';
    }


    return $retval;
}

?>
