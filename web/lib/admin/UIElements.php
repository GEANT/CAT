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
    public function displayName($input) {
        \core\common\Entity::intoThePotatoes();
        $ssidText = _("SSID");
        $passpointOiText = _("HS20 Consortium OI");

        if (count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0) {
            $ssidText = _("Additional SSID");
        }
        if (!empty(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']) && count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']) > 0) {
            $passpointOiText = _("Additional HS20 Consortium OI");
        }

        $displayNames = [_("Support: Web") => "support:url",
            _("Support: EAP Types") => "support:eap_types",
            _("Support: Phone") => "support:phone",
            _("Support: E-Mail") => "support:email",
            sprintf(_("%s Name"), $this->nomenclatureParticipant) => "general:instname",
            sprintf(_("%s Acronym"), $this->nomenclatureParticipant) => "general:instshortname",
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
            _("Admin Accepted IdP Terms of Use") => 'hiddenprofile:tou_accepted',
            _("Admin Accepted SP Terms of Use") => 'hiddenmanagedsp:tou_accepted',
            _("Extra text on downloadpage for device") => "device-specific:customtext",
            _("Redirection Target") => "device-specific:redirect",
            _("Extra text on downloadpage for EAP method") => "eap-specific:customtext",
            _("Turn on selection of EAP-TLS User-Name") => "eap-specific:tls_use_other_id",
            _("Use GEANTlink for TTLS (Windows 8 and 10)") => "device-specific:geantlink",
            _("Profile Description") => "profile:description",
            _("Custom Installer Name Suffix") => "profile:customsuffix",
            _("OpenRoaming") => "media:openroaming",
            sprintf(_("%s Administrator"), $this->nomenclatureFed) => "user:fedadmin",
            _("Real Name") => "user:realname",
            _("E-Mail Address") => "user:email",
            _("Remove/Disable SSID") => "media:remove_SSID",
            _("Mandatory Content Filtering Proxy") => "media:force_proxy",
            _("Custom CSS file for User Area") => "fed:css_file",
            sprintf(_("%s Logo"), $this->nomenclatureFed) => "fed:logo_file",
            _("Preferred Skin for User Area") => "fed:desired_skin",
            sprintf(_("Include %s branding in installers"), $this->nomenclatureFed) => "fed:include_logo_installers",
            sprintf(_("%s Name"), $this->nomenclatureFed) => "fed:realname",
            sprintf(_("%s Homepage"), $this->nomenclatureFed) => "fed:url",
            sprintf(_("Custom text in %s Invitations"), $this->nomenclatureParticipant) => "fed:custominvite",
            sprintf(_("Enable %s"), \config\ConfAssistant::SILVERBULLET['product_name']) => "fed:silverbullet",
            sprintf(_("%s: Do not terminate EAP"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet-noterm",
            sprintf(_("%s: max users per profile"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet-maxusers",
            sprintf(_("Mint %s with CA on creation"), $this->nomenclatureIdP) => "fed:minted_ca_file",
            sprintf(_("OpenRoaming: Allow %s Opt-In"),$this->nomenclatureParticipant) => "fed:openroaming",
            _("OpenRoaming: Custom NAPTR Target") => "fed:openroaming_customtarget",
            $ssidText => "media:SSID",
            $passpointOiText => "media:consortium_OI",
            _("VLAN for own users") => "managedsp:vlan",
            _("Realm to be considered own users") => "managedsp:realmforvlan",
            _("Custom Operator-Name attribute") => "managedsp:operatorname",
        ];

        $find = array_keys($displayNames, $input, TRUE);

        if (count($find) == 0) { // this is an error! throw an Exception
            throw new \Exception("The translation of an option name was requested, but the option is not known to the system: " . htmlentities($input));
        }
        \core\common\Entity::outOfThePotatoes();
        // none of the strings have HTML in them, only translators can provide own text for it -> no threat, but complained about by the security review
        return htmlspecialchars($find[0]);
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
                                $retval .= $this->previewImageinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                                break;
                            case "eap:ca_file":
                            // fall-through intended: display both the same way
                            case "fed:minted_ca_file":
                                $retval .= $this->previewCAinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
                                break;
                            case "support:info_file":
                                $retval .= $this->previewInfoFileinHTML('ROWID-' . $option['level'] . '-' . $option['row']);
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
     * @param integer $rowindex    the database row
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
        if ($details['ca'] == 0 && $details['root'] != 1) {
            $retval = "<div class='ca-summary' style='background-color:red'><div style='position:absolute; right: 0px; width:20px; height:20px; background-color:maroon;  border-radius:10px; text-align: center;'><div style='padding-top:3px; font-weight:bold; color:#ffffff;'>S</div></div>" . _("This is a <strong>SERVER</strong> certificate!") . "<br/>" . $details['name'] . "</div>";
            \core\common\Entity::outOfThePotatoes();
            return $retval;
        }
        $retval = "<div class='ca-summary'                                ><div style='position:absolute; right: 0px; width:20px; height:20px; background-color:#0000ff; border-radius:10px; text-align: center;'><div title='$certTooltip' style='padding-top:3px; font-weight:bold; color:#ffffff;'>$certstatus</div></div>" . $details['name'] . "</div>";
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
                    $message .= "<br>Some of the errors prevented running addional tests so rerun after fixing.";
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

}
