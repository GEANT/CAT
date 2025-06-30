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

/**
 * This class provides various HTML snippets and other UI-related convenience functions.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class UIElements extends \core\common\Entity {

    /**
     * the custom displayable variant of the term 'federation'
     * 
     * @var string
     */
    public $nomenclatureFed;

    /**
     * the custom displayable variant of the term 'institution'
     * 
     * @var string
     */
    public $nomenclatureIdP;

    /**
     * the custom displayable variant of the term 'hotspot'
     * 
     * @var string
     */
    public $nomenclatureHotspot;

    /**
     * the custom displayable variant of the term 'hotspot'
     * 
     * @var string
     */
    public $nomenclatureParticipant;    

    /**
     * Initialises the class.
     * 
     * Mainly fetches various nomenclature from the config and attempts to translate those into local language. Needs pre-loading some terms.
     */
    public function __construct() {
        // pick up the nomenclature translations from core - no need to repeat
        // them here in this catalogue
        parent::__construct();
        $this->nomenclatureFed = \core\common\Entity::$nomenclature_fed;
        $this->nomenclatureIdP = \core\common\Entity::$nomenclature_idp;
        $this->nomenclatureHotspot = \core\common\Entity::$nomenclature_hotspot;
        $this->nomenclatureParticipant = \core\common\Entity::$nomenclature_participant;
    }

    /**
     * provides human-readable text for the various option names as stored in DB.
     * 
     * @param string $input raw text in need of a human-readable display variant
     * @return string the human-readable variant
     * @throws \Exception
     */
    public function displayName($input, $fullDisplay = false) {
        \core\common\Entity::intoThePotatoes();
        $ssidText = _("SSID");
        $passpointOiText = _("HS20 Consortium OI");

        if (!empty(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']) && count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']) > 0) {
            $passpointOiText = _("Additional HS20 Consortium OI");
        }
                
        $displayNames = [
            "support:url" => ['display' => _("Support: Web"), 'help' => ""],
            "support:eap_types" => ['display' => _("Support: EAP Types"), 'help' => ""],
            "support:phone" => ['display' => _("Support: Phone"), 'help' => ""],
            "support:email" => ['display' => _("Support: E-Mail"), 'help' => ""],
            "general:instname" => ['display' => _("Organisation Name"), 'help' => ""],
            "general:instshortname" => ['display' => _("Organisation Acronym"), 'help' => ""],
            "general:instaltname" => ['display' => _("Organisation Alt Name"), 'help' => ""],
            "general:geo_coordinates" => ['display' => _("Location"), 'help' => ""],
            "general:logo_url" => ['display' => _("Logo URL"), 'help' => ""],
            "general:logo_file" => ['display' => _("Logo image"), 'help' => ""],
            "media:wired" => ['display' => _("Configure Wired Ethernet"), 'help' => ""],
            "eap:server_name" => ['display' => _("Name (CN) of Authentication Server"), 'help' => ""],
            "eap:ca_vailduntil" => ['display' => _("Valid until"), 'help' => ""],
            "eap:enable_nea" => ['display' => _("Enable device assessment"), 'help' => ""],
            "support:info_file" => ['display' => _("Terms of Use"), 'help' => ""],
            "eap:ca_url" => ['display' => _("CA Certificate URL"), 'help' => ""],
            "eap:ca_file" => ['display' => _("CA Certificate File"), 'help' => ""],
            "profile:name" => ['display' => _("Profile Display Name"), 'help' => ""],
            "profile:production" => ['display' => _("Production-Ready"), 'help' => ""],
            "hiddenprofile:tou_accepted" => ['display' => _("Admin Accepted IdP Terms of Use"), 'help' => ""],
            "hiddenmanagedsp:tou_accepted" => ['display' => _("Admin Accepted SP Terms of Use"), 'help' => ""],
            "device-specific:customtext" => ['display' => _("Extra text on downloadpage for device"), 'help' => ""],
            "device-specific:redirect" => ['display' => _("Redirection Target"), 'help' => ""],
            "eap-specific:customtext" => ['display' => _("Extra text on downloadpage for device"), 'help' => ""],
            "eap-specific:tls_use_other_id" => ['display' => _("Turn on selection of EAP-TLS User-Name"), 'help' => ""],
            "device-specific:geantlink" => ['display' => _("Use GEANTlink for TTLS (Windows 8 and 10)"), 'help' => ""],
            "device-specific:geteduroam" => ['display' => _("Show the dedicated geteduroam download page for this device"), 'help' => ""],
            "profile:description" => ['display' => _("Profile Description"), 'help' => ""],
            "profile:customsuffix" => ['display' => _("Custom Installer Name Suffix"), 'help' => ""],
            "media:openroaming" => ['display' => _("OpenRoaming"), 'help' => ""],
            "user:fedadmin" => ['display' => sprintf(_("%s Administrator"), $this->nomenclatureFed), 'help' => ""],
            "user:realname" => ['display' => _("Real Name"), 'help' => ""],
            "user:email" => ['display' => _("E-Mail Address"), 'help' => ""],
            "media:remove_SSID" => ['display' => _("Remove/Disable SSID"), 'help' => ""],
            "media:force_proxy" => ['display' => _("Mandatory Content Filtering Proxy"), 'help' => ""],
            "fed:css_file" => ['display' => _("Custom CSS file for User Area"), 'help' => "not available"],
            "fed:logo_file" => [
                'display' => sprintf(_("%s Logo"), $this->nomenclatureFed),
                'help' => _("Your federation logo to be shown on CAT download pages and also on Windows installers if"
                . " the option to include branding in installers is set as well.")],
            "fed:desired_skin" => ['display' => _("Preferred Skin for User Area"), 'help' => "not available"],
            "fed:include_logo_installers" => [
                'display' => sprintf(_("Include %s branding in installers"), $this->nomenclatureFed),
                'help' => _("Add your federation logo to Windows installers.")],
            "fed:realname" => [
                'display' => sprintf(_("%s Name"), $this->nomenclatureFed),
                'help' => "The name of your federation."],
            "fed:url" => ['display' => sprintf(_("%s Homepage"), $this->nomenclatureFed), 'help' => ""],
            "fed:custominvite" => [
                'display' => sprintf(_("Custom text in %s Invitations"), $this->nomenclatureParticipant),
                'help' => _("Your text in invitation mails sent for new IdP")],
            "fed:silverbullet" => ['display' => sprintf(_("Enable %s"), \config\ConfAssistant::SILVERBULLET['product_name']), 'help' => ""],
            "fed:silverbullet-noterm" => ['display' => sprintf(_("%s: Do not terminate EAP"), \core\ProfileSilverbullet::PRODUCTNAME), 'help' => ""],
            "fed:silverbullet-maxusers" => ['display' => sprintf(_("%s: max users per profile"), \core\ProfileSilverbullet::PRODUCTNAME), 'help' => ""],
            "fed:minted_ca_file" => [
                'display' => sprintf(_("Mint %s with CA on creation"), $this->nomenclatureIdP),
                'help' => _("Set of default CAs to add to new IdPs on signup")],
            "fed:openroaming" => [
                'display' => sprintf(_("OpenRoaming: Allow %s Opt-In"),$this->nomenclatureParticipant),
                'help' => _("Allow IdP to set OpenRoaming support for its users.")],
            "fed:openroaming_customtarget" => ['display' => _("OpenRoaming: Custom NAPTR Target"), 'help' => ""],
            "fed:autoregister-synced" => [
                'display' => _("Self registration from eduroam DB: add listed admins to CAT institutions"),
                'help' => sprintf(_("With this option turned on if a CAT institution is synced to the eduroam DB it is possible to have automatic enlisting of CAT institution admins under some conditions described <a href='%s'>here</a>."), "https://wiki.eduroam.org/")],
            "fed:autoregister-new-inst" => [
                'display' => _("Self registration from eduroam DB: allow creating new institutions"),
                'help' => sprintf(_("Turn this on and eduroam DB listed institution admins will be allowed to create new institutions under some conditions described <a href='%s'>here</a>."), "https://wiki.eduroam.org/")],
            "fed:autoregister-entitlement" => [
                'display' => _("Self registration based on entitlement: add admins to CAT institutions"),
                'help' => _("With this option turned on the system will verify the eduGAIN login of the potential administrator and propose taking control over institutions which use the realm within the scope defined in the user's oairwise-id attribute.")
            ],
            "fed:entitlement-attr" => [
                'display' => _("Custom entitlement value for self-registration"),
                'help' => _("If you want to use the SAML eduPersonEntitlement based self-registration you may define a value that will be used in your federation for institutions admins. When this is not set the default value geant:eduroam:inst:admin will be used. This option makes sens only if you have 'Self registration based on entitlement' set")
            ],
            "media:SSID" => ['display' => $ssidText, 'help' => ""],
            "media:consortium_OI" => ['display' => $passpointOiText, 'help' => ""],
            "managedsp:guest_vlan" => ['display' => _("VLAN for guests"), 'help' => ""],
            "managedsp:vlan" => ['display' => _("VLAN for own users"), 'help' => ""],
            "managedsp:realmforvlan" => ['display' => _("Realm to be considered own users"), 'help' => ""],
            "managedsp:operatorname" => ['display' => _("Custom Operator-Name attribute"), 'help' => ""],
        ];

        if (!isset($displayNames[$input])) { // this is an error! throw an Exception
            throw new \Exception("The translation of an option name was requested, but the option is not known to the system: " . htmlentities($input));
        }
        \core\common\Entity::outOfThePotatoes();
        // none of the strings have HTML in them, only translators can provide own text for it -> no threat, but complained about by the security review
        if ($fullDisplay) {
            return ['display' => htmlspecialchars($displayNames[$input]['display']), 'help' => $displayNames[$input]['help']];
        } else {
            return htmlspecialchars($displayNames[$input]['display']);
        }
    }

    /**
     * creates an HTML information block with a list of options from a given category and level
     * @param array  $optionlist list of options
     * @param string $class      option class of interest
     * @param string $level      option level of interest
     * @return string HTML code
     */
    public function infoblock(array $optionlist, string $class, string $level) {
        \core\common\Entity::intoThePotatoes();
        $locationMarkers = [];
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
                        $language = \config\Master::LANGUAGES[$option['lang']]['display'] ?? "(unsupported language)";
                    }
                }

                switch ($type["type"]) {
                    case "coordinates":
                        $coords = json_decode($option['value'], true);
                        $locationMarkers[] = $coords;
                        break;
                    case "file":
                        $retval .= "<tr><td>" . $this->displayName($option['name']) . "</td><td>$language</td><td>";
                        switch ($option['name']) {
                            case "general:logo_file":
                            case "fed:logo_file":
                                $retval .= $this->previewImageinHTML('ROWID-' . $option['level'] . '-' . $option['row_id']);
                                break;
                            case "eap:ca_file":
                            // fall-through intended: display both the same way
                            case "fed:minted_ca_file":
                                $retval .= $this->previewCAinHTML('ROWID-' . $option['level'] . '-' . $option['row_id']);
                                break;
                            case "support:info_file":
                                $retval .= $this->previewInfoFileinHTML('ROWID-' . $option['level'] . '-' . $option['row_id']);
                                break;
                            default:
                        }
                        break;
                    case "boolean":
                        if ($option['name'] == "fed:silverbullet" && \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] != "LOCAL") {
                            // do not display the option at all; it gets auto-set by the ProfileSilverbullet constructor and doesn't have to be seen
                            break;
                        }
                        $retval .= "<tr><td>" . $this->displayName($option['name']) . "</td><td>$language</td><td><strong>" . ($content == "on" ? _("on") : _("off") ) . "</strong></td></tr>";
                        break;
                    default:
                        $retval .= "<tr><td>" . $this->displayName($option['name']) . "</td><td>$language</td><td><strong>$content</strong></td></tr>";
                }
            }
        }
        if (count($locationMarkers)) {
            $marker = '<markers>';
            $locationCount = 0;
            foreach ($locationMarkers as $g) {
                $locationCount++;
                $marker .= '<marker name="' . $locationCount . '" lat="' . $g['lat'] . '" lng="' . $g['lon'] . '" />';
            }
            $marker .= '<\/markers>'; // some validator says this should be escaped
            $jMarker = json_encode($locationMarkers);
            $retval .= '<tr><td><script>markers=\'' . $marker . '\'; jmarkers = \'' . $jMarker . '\';</script></td><td></td><td></td></tr>';
        }
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * creates HTML code to display all information boxes for an IdP
     * 
     * @param \core\IdP $myInst the IdP in question
     * @return string HTML code
     */
    public function instLevelInfoBoxes(\core\IdP $myInst) {
        \core\common\Entity::intoThePotatoes();
        $idpoptions = $myInst->getAttributes();
        $retval = "<div class='infobox'>
        <h2>" . sprintf(_("General %s details"), $this->nomenclatureParticipant) . "</h2>
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
        \core\common\Entity::outOfThePotatoes();
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

    /**
     * 
     * @param string  $table       the database table
     * @param integer $rowindex    the database row_id
     * @param boolean $checkpublic should we check if the requested piece of data is public?
     * @return string|boolean the requested data, or FALSE if something went wrong
     */
    public static function getBlobFromDB($table, $rowindex, $checkpublic) {
        // the data is either public (just give it away) or not; in this case, only
        // release if the data belongs to admin himself
        if ($checkpublic) {

            $owners = \core\EntityWithDBProperties::isDataRestricted($table, $rowindex);

            $ownersCondensed = [];

            if ($owners !== FALSE) { // restricted data, see if we're authenticated and owners of the data
                $auth = new \web\lib\admin\Authentication();
                if (!$auth->isAuthenticated()) {
                    return FALSE; // admin-only, but we are not an admin
                }
                // we might be called without session context (filepreview) so get the
                // context if needed
                \core\CAT::sessionStart();

                foreach ($owners as $oneowner) {
                    $ownersCondensed[] = $oneowner['ID'];
                }
                if (array_search($_SESSION['user'], $ownersCondensed) === FALSE) {
                    return FALSE; // wrong guy
                }
                // carry on and get the data
            }
        }

        $blob = \core\EntityWithDBProperties::fetchRawDataByIndex($table, $rowindex);
        return $blob; // this means we might return FALSE here if something was wrong with the original requested reference
    }

    /**
     * creates HTML code to display a nice UI representation of a CA
     * 
     * @param string $cAReference ROWID pointer to the CA to display
     * @return string HTML code
     */
    public function previewCAinHTML($cAReference) {
        \core\common\Entity::intoThePotatoes();
        $validator = new \web\lib\common\InputValidation();
        $ref = $validator->databaseReference($cAReference);
        $caExpiryTrashhold = \config\ConfAssistant::CERT_WARNINGS['expiry_warning'];
        $rawResult = UIElements::getBlobFromDB($ref['table'], $ref['rowindex'], FALSE);
        if (is_bool($rawResult)) { // we didn't actually get a CA!
            $retval = "<div class='ca-summary'>" . _("There was an error while retrieving the certificate from the database!") . "</div>";
            \core\common\Entity::outOfThePotatoes();
            return $retval;
        }
        $cAblob = base64_decode($rawResult);

        $func = new \core\common\X509;
        $details = $func->processCertificate($cAblob);
        if ($details === FALSE) {
            $retval = _("There was an error processing the certificate!");
            \core\common\Entity::outOfThePotatoes();
            return $retval;
        }

        $details['name'] = preg_replace('/(.)\/(.)/', "$1<br/>$2", $details['name']);
        $details['name'] = preg_replace('/\//', "", $details['name']);
        $certstatus = ( $details['root'] == 1 ? "R" : "I");
        $certTooltip = ( $details['root'] == 1 ? _("Root CA") : _("Intermediate CA"));
        $innerbgColor = "#0000ff";
        $leftBorderColor = "#00ff00";
        $message = "";
        if ($details['ca'] == 0 && $details['root'] != 1) {
            $leftBorderColor = "red";
            $message = _("This is a <strong>SERVER</strong> certificate!");
            if (\config\ConfAssistant::CERT_GUIDELINES !== '') {
                $message .= "<br/><a target='_blank' href='".\config\ConfAssistant::CERT_GUIDELINES."'>". _("more info")."</a>";
            }
            $message .= "<br/>";
            $retval = "<div class='ca-summary' style='border-left-color: $leftBorderColor'><div style='position:absolute; right: -15px; width:20px; height:20px; background-color:$innerbgColor; border-radius:10px; text-align: center;'><div style='padding-top:3px; font-weight:bold; color:#ffffff;'>S</div></div>" . $message . $details['name'] . "</div>";
            \core\common\Entity::outOfThePotatoes();
            return $retval;
        }
        $now = time();
        if ($now + \config\ConfAssistant::CERT_WARNINGS['expiry_critical'] > $details['full_details']['validTo_time_t']) {
            $leftBorderColor = "red";
            $message = _("Certificate expired!") . "<br>";
        } elseif($now + \config\ConfAssistant::CERT_WARNINGS['expiry_warning']  > $details['full_details']['validTo_time_t'] - $caExpiryTrashhold) {
            if ($leftBorderColor == "#00ff00") {
                $leftBorderColor = "yellow";
            }
            $message = _("Certificate close to expiry!") . "<br/>";            
        }
   
        if ($details['root'] == 1 && $details['basicconstraints_set'] == 0) {
            if ($leftBorderColor == "#00ff00") {
                $leftBorderColor = "yellow";
            }
            $message .= "<div style='max-width: 25em'><strong>" . _("Improper root certificate, required critical CA extension missing, will not reliably install!") . "</strong>";
            if (\config\ConfAssistant::CERT_GUIDELINES !== '') {
                $message .= "<br/><a target='_blank' href='".\config\ConfAssistant::CERT_GUIDELINES."'>". _("more info")."</a>";
            }
            $message .= "</div><br/>";
        }
        $retval =  "<div class='ca-summary' style='border-left-color: $leftBorderColor'><div style='position:absolute; right: -15px; width:20px; height:20px; background-color:$innerbgColor; border-radius:10px; text-align: center;'><div title='$certTooltip' style='padding-top:3px; font-weight:bold; color:#ffffff;'>$certstatus</div></div>" . $message . $details['name'] . "<br>" . $this->displayName('eap:ca_vailduntil') . " " . gmdate('Y-m-d H:i:s', $details['full_details']['validTo_time_t']) . " UTC</div>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * creates HTML code to display a nice UI representation of an image
     * 
     * @param string $imageReference ROWID pointer to the image to display
     * @return string HTML code
     */
    public function previewImageinHTML($imageReference) {
        \core\common\Entity::intoThePotatoes();
        $retval = "<img style='max-width:150px' src='inc/filepreview.php?id=" . $imageReference . "' alt='" . _("Preview of logo file") . "'/>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * creates HTML code to display a nice UI representation of a TermsOfUse file
     * 
     * @param string $fileReference ROWID pointer to the file to display
     * @return string HTML code
     */
    public function previewInfoFileinHTML($fileReference) {
        \core\common\Entity::intoThePotatoes();
        $validator = new \web\lib\common\InputValidation();
        $ref = $validator->databaseReference($fileReference);
        $fileBlob = UIElements::getBlobFromDB($ref['table'], $ref['rowindex'], FALSE);
        if (is_bool($fileBlob)) { // we didn't actually get a file!
            $retval = "<div class='ca-summary'>" . _("There was an error while retrieving the file from the database!") . "</div>";
            \core\common\Entity::outOfThePotatoes();
            return $retval;
        }
        $decodedFileBlob = base64_decode($fileBlob);
        $fileinfo = new \finfo();
        $retval = "<div class='ca-summary'>" . _("File exists") . " (" . $fileinfo->buffer($decodedFileBlob, FILEINFO_MIME_TYPE) . ", " . $this->displaySize(strlen($decodedFileBlob)) . ")<br/><a href='inc/filepreview.php?id=$fileReference'>" . _("Preview") . "</a></div>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * creates HTML code for a UI element which informs the user about something.
     * 
     * @param int    $level         what kind of information is to be displayed?
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string
     */
    public function boxFlexible(int $level, string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        \core\common\Entity::intoThePotatoes();
        $uiMessages = [
            \core\common\Entity::L_OK => ['img' => 'Tabler/square-rounded-check-filled-green.svg', 'text' => _("OK")],
            \core\common\Entity::L_REMARK => ['img' => 'Tabler/info-square-rounded-filled-blue.svg', 'text' => _("Remark")],
            \core\common\Entity::L_WARN => ['img' => 'Tabler/alert-square-rounded-filled-yellow.svg', 'text' => _("Warning!")],
            \core\common\Entity::L_ERROR => ['img' => 'Tabler/square-rounded-x-filled-red.svg', 'text' => _("Error!")],
            \core\common\Entity::L_CERT_OK => ['img' => 'Tabler/certificate-green.svg', 'text' => _("OK")],
            \core\common\Entity::L_CERT_WARN => ['img' => 'Tabler/certificate-red.svg', 'text' => _("Warning!")],
            \core\common\Entity::L_CERT_ERROR => ['img' => 'Tabler/certificate-off.svg', 'text' => _("Warning!")],
            ];
        
        $retval = "";
        if (!$omittabletags) {
            $retval .= "<tr><td>";
        }
//        $finalCaption = ($caption !== NULL ? $caption : $uiMessages[$level]['text']);
//        $retval .= "<img class='icon cat-icon' src='" . $uiMessages[$level]['icon'] . "' alt='" . $finalCaption . "' title='" . $finalCaption . "'/>";
        $iconData = $uiMessages[$level];
        if ($caption !== NULL) {
            $iconData['text'] = $caption;
        }


        $retval .= $this->catIcon($iconData);

        if (!$omittabletags) {
            $retval .= "</td><td>";
        }
        if ($text !== NULL) {
            $retval .= $text;
        }
        if (!$omittabletags) {
            $retval .= "</td></tr>";
        }
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * creates HTML code to display an "all is okay" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box
     */
    public function boxOkay(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_OK, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "smartass comment" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box
     */
    public function boxRemark(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_REMARK, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "something's a bit wrong" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box
     */
    public function boxWarning(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_WARN, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "Whoa! Danger, Will Robinson!" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box
     */
    public function boxError(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_ERROR, $text, $caption, $omittabletags);
    }

    /**
     * creates HTML code to display a "All fine" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box 
     */
    public function boxCertOK(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_CERT_OK, $text, $caption, $omittabletags);
    }
    
    /**
     * creates HTML code to display a "A certificate close to expiry" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box
     */
    public function boxCertWarning(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_CERT_WARN, $text, $caption, $omittabletags);
    }
    /**
     * creates HTML code to display a "A certificate expired or dangerously close to expiry" message
     * 
     * @param string $text          the text to display
     * @param string $caption       the caption to display
     * @param bool   $omittabletags the output usually has tr/td table tags, this option suppresses them
     * @return string HTML: the box
     */
    public function boxCertError(string $text = NULL, string $caption = NULL, bool $omittabletags = FALSE) {
        return $this->boxFlexible(\core\common\Entity::L_CERT_ERROR, $text, $caption, $omittabletags);
    }    
    
    
    const QRCODE_PIXELS_PER_SYMBOL = 12;

    /**
     * Injects the consortium logo in the middle of a given PNG.
     * 
     * Usually used on QR code PNGs - the parameters inform about the structure of
     * the QR code so that the logo does not prevent parsing of the QR code.
     * 
     * @param string $inputpngstring the PNG to edit
     * @param int    $symbolsize     size in pixels of one QR "pixel"
     * @param int    $marginsymbols  size in pixels of border around the actual QR
     * @return string the image with logo centered in the middle
     */
    public function pngInjectConsortiumLogo(string $inputpngstring, int $symbolsize, int $marginsymbols = 4) {
        $loggerInstance = new \core\common\Logging();
        $inputgd = imagecreatefromstring($inputpngstring);
        if ($inputgd === FALSE) { // source image is bogus; don't do anything
            return "";
        }

        $loggerInstance->debug(4, "Consortium logo is at: " . ROOT . "/web/resources/images/consortium_logo_large.png");
        $logogd = imagecreatefrompng(ROOT . "/web/resources/images/consortium_logo_large.png");
        if ($logogd === FALSE) { // consortium logo is bogus; don't do anything
            return "";
        }
        $sizeinput = [imagesx($inputgd), imagesy($inputgd)];
        $sizelogo = [imagesx($logogd), imagesy($logogd)];
        // Q level QR-codes can sustain 25% "damage"
        // make our logo cover approx 15% of area to be sure; mind that there's a $symbolsize * $marginsymbols pixel white border around each edge
        $totalpixels = ($sizeinput[0] - $symbolsize * $marginsymbols) * ($sizeinput[1] - $symbolsize * $marginsymbols);
        $totallogopixels = ($sizelogo[0]) * ($sizelogo[1]);
        $maxoccupy = $totalpixels * 0.04;
        // find out how much we have to scale down logo to reach 10% QR estate
        $scale = sqrt($maxoccupy / $totallogopixels);
        $loggerInstance->debug(4, "Scaling info: $scale, $maxoccupy, $totallogopixels\n");
        // determine final pixel size - round to multitude of $symbolsize to match exact symbol boundary
        $targetwidth = (int) ($symbolsize * round($sizelogo[0] * $scale / $symbolsize));
        $targetheight = (int) ($symbolsize * round($sizelogo[1] * $scale / $symbolsize));
        // paint white below the logo, in case it has transparencies (looks bad)
        // have one symbol in each direction extra white space
        $whiteimage = imagecreate($targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize);
        if ($whiteimage === FALSE) { // we can't create an empty canvas. Weird. Stop processing.
            return "";
        }
        imagecolorallocate($whiteimage, 255, 255, 255);
        // also make sure the initial placement is a multitude of 12; otherwise "two half" symbols might be affected
        $targetplacementx = (int) ($symbolsize * round(($sizeinput[0] / 2 - ($targetwidth - $symbolsize + 1) / 2) / $symbolsize));
        $targetplacementy = (int) ($symbolsize * round(($sizeinput[1] / 2 - ($targetheight - $symbolsize + 1 ) / 2) / $symbolsize));
        imagecopyresized($inputgd, $whiteimage, $targetplacementx - $symbolsize, $targetplacementy - $symbolsize, 0, 0, $targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize, $targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize);
        imagecopyresized($inputgd, $logogd, $targetplacementx, $targetplacementy, 0, 0, $targetwidth, $targetheight, $sizelogo[0], $sizelogo[1]);
        ob_start();
        imagepng($inputgd);
        return ob_get_clean();
    }

    /**
     * Something went wrong. We display the error cause and then throw an Exception.
     * 
     * @param string $headerDisplay error to put in the page header
     * @param string $uiDisplay     error string to display
     * @return void direct output
     * @throws Exception
     */
    public function errorPage($headerDisplay, $uiDisplay) {
        $decoObject = new PageDecoration();
        echo $decoObject->pageheader($headerDisplay, "ADMIN-IDP");
        echo "<h1>$uiDisplay</h1>";
        echo $decoObject->footer();
        throw new Exception("Error page raised: $headerDisplay - $uiDisplay.");
    }

    /**
     * creates the HTML code displaying the result of a test that was run previously
     * 
     * @param \core\SanityTests $test the test that was run
     * @return string
     * @throws Exception
     */
    public function sanityTestResultHTML($test) {
        $out = '';
        switch ($test->test_result['global']) {
            case \core\common\Entity::L_OK:
                $message = "Your configuration appears to be fine.";
                break;
            case \core\common\Entity::L_WARN:
                $message = "There were some warnings, but your configuration should work.";
                break;
            case \core\common\Entity::L_ERROR:
                $message = "Your configuration appears to be broken, please fix the errors.";
                if ($test->fatalError) {
                    $message .= "<br>Some of the errors prevented running additional tests so rerun after fixing.";
                }
                break;
            case \core\common\Entity::L_REMARK:
                $message = "Your configuration appears to be fine.";
                break;
            default:
                throw new Exception("The result code level " . $test->test_result['global'] . " is not defined!");
        }
        $out .= $this->boxFlexible($test->test_result['global'], "<br><strong>Test Summary</strong><br>" . $message . "<br>See below for details<br><hr>");
        foreach ($test->out as $testValue) {
            foreach ($testValue as $o) {
                $out .= $this->boxFlexible($o['level'], $o['message']);
            }
        }
        return($out);
    }
    /**
     * prepares data for icons
     * 
     * @param string $index
     * @return array
     */
    public function iconData($index) {
        \core\common\Entity::intoThePotatoes();
        $icons = [
            'CERT_STATUS_OK' => ['img' => 'Tabler/certificate-green.svg', 'text' => _("All certificates are valid long enough")],
            'CERT_STATUS_WARN' => ['img' => 'Tabler/certificate-red.svg', 'text' => _("At least one certificate is close to expiry")],
            'CERT_STATUS_ERROR' => ['img' => 'Tabler/certificate-off.svg', 'text' => _("At least one certificate either has expired or is very close to expiry")],
            'OVERALL_OPENROAMING_LEVEL_GOOD' => ['img' => 'Tabler/square-rounded-check-green.svg', 'text' => _("OpenRoaming appears to be configured properly")],
            'OVERALL_OPENROAMING_LEVEL_NOTE' => ['img' => 'Tabler/info-square-rounded-blue.svg', 'text' => _("There are some minor OpenRoaming configuration issues")],
            'OVERALL_OPENROAMING_LEVEL_WARN' => ['img' => 'Tabler/info-square-rounded-blue.svg', 'text' => _("There are some average level OpenRoaming configuration issues")],
            'OVERALL_OPENROAMING_LEVEL_ERROR' => ['img' => 'Tabler/alert-square-rounded-red.svg', 'text' => _("There are some critical OpenRoaming configuration issues")],            
            'PROFILES_SHOWTIME' => ['img' => 'Tabler/checks-green.svg', 'text' => _("At least one profile is fully configured and visible in the user interface")],
            'PROFILES_CONFIGURED' => ['img' => 'Tabler/check-green.svg', 'text' => _("At least one profile is fully configured but none are set as production-ready therefore the institution is not visible in the user interface")],
            'PROFILES_INCOMPLETE' => ['img' => 'Tabler/access-point-off-red.svg', 'text' => _("No configured profiles")],
            'PROFILES_REDIRECTED' => ['img' => 'Tabler/external-link.svg', 'text' => _("All active profiles redirected")],
            'IDP_LINKED' => ['img' => 'Tabler/database-green.svg', 'text' => _("Linked")],
            'IDP_NOT_LINKED' => ['img' => 'Tabler/database-off-red.svg', 'text' => _("NOT linked")],
            'CERTS_NOT_SHOWN' => ['img' => 'Tabler/question-mark-blue.svg', 'text' => _("Not showing cert info if no profiles are visible")],
            ];
            \core\common\Entity::outOfThePotatoes();
        return($icons[$index]);
    }
    
/**
 * the HTML img element produced 0n the basis of a simple [src,title] array
 * @param type array
 * @return string the img element
 */
    public function catIcon($data) {
        return "<img src='../resources/images/icons/".$data['img']."' alt='".$data['text']."' title = '".$data['text']."' class='cat-icon'>";                  
    }
}
