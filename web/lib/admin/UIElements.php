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

/**
 * This class provides various HTML snippets and other UI-related convenience functions.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class UIElements {

    /**
     * the custom displayable variant of the term 'federation'
     * 
     * @var string
     */
    public $nomenclature_fed;

    /**
     * the custom displayable variant of the term 'institution'
     * 
     * @var string
     */
    public $nomenclature_inst;

    /**
     * Initialises the class.
     * 
     * Mainly fetches various nomenclature from the config and attempts to translate those into local language. Needs pre-loading some terms.
     */
    public function __construct() {
        // some config elements are displayable. We need some dummies to 
        // translate the common values for them. If a deployment chooses a 
        // different wording, no translation, sorry

        $dummy_NRO = _("National Roaming Operator");
        $dummy_inst1 = _("identity provider");
        $dummy_inst2 = _("organisation");
        // and do something useless with the strings so that there's no "unused" complaint
        $dummy_NRO = $dummy_NRO . $dummy_inst1 . $dummy_inst2;

        $this->nomenclature_fed = _(CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation']);
        $this->nomenclature_inst = _(CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution']);
    }

    /**
     * provides human-readable text for the various option names as stored in DB.
     * 
     * @param string $input raw text in need of a human-readable display variant
     * @return string the human-readable variant
     * @throws Exception
     */
    public function displayName($input) {

        $ssidText = _("SSID");
        $ssidLegacyText = _("SSID (with WPA/TKIP)");
        $passpointOiText = _("HS20 Consortium OI");

        if (count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) > 0) {
            $ssidText = _("Additional SSID");
            $ssidLegacyText = _("Additional SSID (with WPA/TKIP)");
        }
        if (!empty(CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-consortium-oi']) && count(CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-consortium-oi']) > 0) {
            $passpointOiText = _("Additional HS20 Consortium OI");
        }

        $displayNames = [_("Support: Web") => "support:url",
            _("Support: EAP Types") => "support:eap_types",
            _("Support: Phone") => "support:phone",
            _("Support: E-Mail") => "support:email",
            sprintf(_("Name of %s"), $this->nomenclature_inst) => "general:instname",
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
            _("Admin Accepted Terms of Use") => 'hiddenprofile:tou_accepted',
            _("Extra text on downloadpage for device") => "device-specific:customtext",
            _("Redirection Target") => "device-specific:redirect",
            _("Extra text on downloadpage for EAP method") => "eap-specific:customtext",
            _("Turn on selection of EAP-TLS User-Name") => "eap-specific:tls_use_other_id",
            _("Profile Description") => "profile:description",
            _("Custom Installer Name Suffix") => "profile:customsuffix",
            sprintf(_("%s Administrator"), $this->nomenclature_fed) => "user:fedadmin",
            _("Real Name") => "user:realname",
            _("E-Mail Address") => "user:email",
            _("Remove/Disable SSID") => "media:remove_SSID",
            _("Custom CSS file for User Area") => "fed:css_file",
            sprintf(_("%s Logo"), $this->nomenclature_fed) => "fed:logo_file",
            _("Preferred Skin for User Area") => "fed:desired_skin",
            _("Include NRO branding in installers") => "fed:include_logo_installers",
            sprintf(_("%s Name"), $this->nomenclature_fed) => "fed:realname",
            _("Custom text in IdP Invitations") => "fed:custominvite",
            sprintf(_("Enable %s"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet",
            sprintf(_("%s: Do not terminate EAP"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet-noterm",
            sprintf(_("%s: max users per profile"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet-maxusers",
            $ssidText => "media:SSID",
            $ssidLegacyText => "media:SSID_with_legacy",
            $passpointOiText => "media:consortium_OI",
        ];

        $find = array_keys($displayNames, $input, TRUE);

        if (count($find) == 0) { // this is an error! throw an Exception
            throw new \Exception("The translation of an option name was requested, but the option is not known to the system: " . htmlentities($input));
        }
        return $find[0];
    }

    public function tooltip($input) {
        $descriptions = [];
        if (count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) > 0) {
            $descriptions[sprintf(_("This attribute can be set if you want to configure an additional SSID besides the default SSIDs for %s. It is almost always a bad idea not to use the default SSIDs. The only exception is if you have premises with an overlap of the radio signal with another %s hotspot. Typical misconceptions about additional SSIDs include: I want to have a local SSID for my own users. It is much better to use the default SSID and separate user groups with VLANs. That approach has two advantages: 1) your users will configure %s properly because it is their everyday SSID; 2) if you use a custom name and advertise this one as extra secure, your users might at some point roam to another place which happens to have the same SSID name. They might then be misled to believe that they are connecting to an extra secure network while they are not."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'])] = "media:SSID";
        }

        $find = array_search($input, $descriptions);

        if ($find === FALSE) {
            return "";
        }
        return "<span class='tooltip' onclick='alert(\"" . $find . "\")'><img src='../resources/images/icons/question-mark-icon.png" . "'></span>";
    }

    /**
     * creates an HTML information block with a list of options from a given category and level
     * @param array $optionlist list of options
     * @param string $class option class of interest
     * @param string $level option level of interest
     * @return string HTML code
     */
    public function infoblock(array $optionlist, string $class, string $level) {
        $googleMarkers = [];
        $retval = "";
        $optioninfo = \core\Options::instance();

        foreach ($optionlist as $option) {
            $type = $optioninfo->optionType($option['name']);
            if (preg_match('/^' . $class . '/', $option['name']) && $option['level'] == "$level") {
                // all non-multilang attribs get this assignment ...
                $language = "";
                $content = $option['value'];
                // ... override them with multilang tags if needed
                if ($type["flag"] == "ML") {
                    $language = _("default/other languages");
                    if ($option['lang'] != 'C') {
                        $language = CONFIG['LANGUAGES'][$option['lang']]['display'] ?? "(unsupported language)";
                    }
                }

                switch ($type["type"]) {
                    case "coordinates":
                        $coords = json_decode($option['value'], true);
                        $googleMarkers[] = $coords;
                        break;
                    case "file":
                        $retval .= "<tr><td>" . $this->displayName($option['name']) . "</td><td>$language</td><td>";
                        switch ($option['name']) {
                            case "general:logo_file":
                            case "fed:logo_file":
                                $retval .= $this->previewImageinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                                break;
                            case "eap:ca_file":
                                $retval .= $this->previewCAinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                                break;
                            case "support:info_file":
                                $retval .= $this->previewInfoFileinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                                break;
                            default:
                        }
                        break;
                    case "boolean":
                        $retval .= "<tr><td>" . $this->displayName($option['name']) . "</td><td>$language</td><td><strong>" . ($content == "on" ? _("on") : _("off") ) . "</strong></td></tr>";
                        break;
                    default:
                        $retval .= "<tr><td>" . $this->displayName($option['name']) . "</td><td>$language</td><td><strong>$content</strong></td></tr>";
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

    /**
     * creates HTML code to display all information boxes for an IdP
     * 
     * @param \core\IdP $myInst the IdP in question
     * @return string HTML code
     */
    public function instLevelInfoBoxes(\core\IdP $myInst) {
        $idpoptions = $myInst->getAttributes();
        $retval = "<div class='infobox'>
        <h2>" . sprintf(_("General %s details"), $this->nomenclature_inst) . "</h2>
        <table>
            <tr>
                <td>
                    " . _("Country:") . "
                </td>
                <td>
                </td>
                <td>
                    <strong>";
        $myFed = new \core\Federation($myInst->federation);
        $retval .= $myFed->name;
        $retval .= "</strong>
                </td>
            </tr>" . $this->infoblock($idpoptions, "general", "IdP") . "
        </table>
    </div>";

        $blocks = [["support", _("Global Helpdesk Details")], ["media", _("Media Properties")]];
        foreach ($blocks as $block) {
            $retval .= "<div class='infobox'>
            <h2>" . $block[1] . "</h2>
            <table>" .
                    $this->infoblock($idpoptions, $block[0], "IdP") .
                    "</table>
        </div>";
        }
        return $retval;
    }

    /**
     * pretty-prints a file size number in SI "bi" units
     * @param int $number the size of the file
     * @return string the pretty-print representation of the file size
     */
    private function displaySize(int $number) {
        if ($number > 1024 * 1024) {
            return round($number / 1024 / 1024, 2) . " MiB";
        }
        if ($number > 1024) {
            return round($number / 1024, 2) . " KiB";
        }
        return $number . " B";
    }

    public static function getBlobFromDB($ref, $checkpublic) {
        $validator = new \web\lib\common\InputValidation();
        $reference = $validator->databaseReference($ref);

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
            $owners = \core\EntityWithDBProperties::isDataRestricted($reference["table"], $reference["rowindex"]);

            $ownersCondensed = [];

            if ($owners !== FALSE) { // restricted datam see if we're authenticated and owners of the data
                $auth = new \web\lib\admin\Authentication();
                if (!$auth->isAuthenticated()) {
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


        $blob = \core\EntityWithDBProperties::fetchRawDataByIndex($reference["table"], $reference["rowindex"]);
        if (!$blob) {
            return FALSE;
        }
        return $blob;
    }

    /**
     * creates HTML code to display a nice UI representation of a CA
     * 
     * @param string $cAReference ROWID pointer to the CA to display
     * @return string HTML code
     */
    public function previewCAinHTML($cAReference) {
        $found = preg_match("/^ROWID-.*/", $cAReference);
        if (!$found) {
            return "<div>" . _("Error, ROWID expected.") . "</div>";
        }

        $cAblob = base64_decode(UIElements::getBlobFromDB($cAReference, FALSE));

        $func = new \core\common\X509;
        $details = $func->processCertificate($cAblob);
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

    /**
     * creates HTML code to display a nice UI representation of an image
     * 
     * @param string $imageReference ROWID pointer to the image to display
     * @return string HTML code
     */
    public function previewImageinHTML($imageReference) {
        $found = preg_match("/^ROWID-.*/", $imageReference);
        if (!$found) {
            return "<div>" . _("Error, ROWID expected.") . "</div>";
        }
        return "<img style='max-width:150px' src='inc/filepreview.php?id=" . $imageReference . "' alt='" . _("Preview of logo file") . "'/>";
    }

    /**
     * creates HTML code to display a nice UI representation of a TermsOfUse file
     * 
     * @param string $fileReference ROWID pointer to the file to display
     * @return string HTML code
     */
    public function previewInfoFileinHTML($fileReference) {
        $found = preg_match("/^ROWID-.*/", $fileReference);
        if (!$found) {
            return _("<div>Error, ROWID expected, got $fileReference.</div>");
        }

        $fileBlob = UIElements::getBlobFromDB($fileReference, FALSE);
        $decodedFileBlob = base64_decode($fileBlob);
        $fileinfo = new \finfo();
        return "<div class='ca-summary'>" . _("File exists") . " (" . $fileinfo->buffer($decodedFileBlob, FILEINFO_MIME_TYPE) . ", " . $this->displaySize(strlen($decodedFileBlob)) . ")<br/><a href='inc/filepreview.php?id=$fileReference'>" . _("Preview") . "</a></div>";
    }

    /**
     * creates HTML code for a UI element which informs the user about something.
     * 
     * @param int $level what kind of information is to be displayed?
     * @param string $text the text to display
     * @param string $caption the caption to display
     * @param bool $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string
     */
    public function boxFlexible(int $level, string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {

        $uiMessages = [
            \core\common\Entity::L_OK => ['icon' => '../resources/images/icons/Quetto/check-icon.png', 'text' => _("OK")],
            \core\common\Entity::L_REMARK => ['icon' => '../resources/images/icons/Quetto/info-icon.png', 'text' => _("Remark")],
            \core\common\Entity::L_WARN => ['icon' => '../resources/images/icons/Quetto/danger-icon.png', 'text' => _("Warning!")],
            \core\common\Entity::L_ERROR => ['icon' => '../resources/images/icons/Quetto/no-icon.png', 'text' => _("Error!")],
        ];

        $retval = "";
        if (!$omittabletags) {
            $retval .= "<tr><td>";
        }
        $finalCaption = ($caption !== NULL ? $caption : $uiMessages[$level]['text']);
        $retval .= "<img class='icon' src='" . $uiMessages[$level]['icon'] . "' alt='" . $finalCaption . "' title='" . $finalCaption . "'/>";
        if (!$omittabletags) {
            $retval .= "</td><td>";
        }
        if ($text !== NULL) {
            $retval .= $text;
        }
        if (!$omittabletags) {
            $retval .= "</td></tr>";
        }
        return $retval;
    }

    /**
     * creates HTML code to display an "all is okay" message
     * 
     * @param string $text the text to display
     * @param string $caption the caption to display
     * @param bool $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return type
     */
    public function boxOkay(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_OK, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "smartass comment" message
     * 
     * @param string $text the text to display
     * @param string $caption the caption to display
     * @param bool $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return type
     */
    public function boxRemark(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_REMARK, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "something's a bit wrong" message
     * 
     * @param string $text the text to display
     * @param string $caption the caption to display
     * @param bool $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return type
     */
    public function boxWarning(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_WARN, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "Whoa! Danger, Will Robinson!" message
     * 
     * @param string $text the text to display
     * @param string $caption the caption to display
     * @param bool $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return type
     */
    public function boxError(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_ERROR, $text, $caption, $omittabletags);
    }

}
