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

namespace web\lib\user;

/**
 * these constants live in the global space just to ease their use - with class
 * prefix, the names simply get too long for comfort
 */

const WELCOME_ABOARD_PAGEHEADING = 1000;
const WELCOME_ABOARD_DOWNLOAD = 1001;
const WELCOME_ABOARD_HEADING = 1002;  
const WELCOME_ABOARD_USAGE = 1003;
const WELCOME_ABOARD_PROBLEMS = 1004;
const WELCOME_ABOARD_TERMS = 1006;
const NETWORK_TERMS_AND_PRIV = 1007;
const WELCOME_ABOARD_BACKTODOWNLOADS = 1005;
const HEADING_TOPLEVEL_GREET = 1010;
const HEADING_TOPLEVEL_PURPOSE = 1011;
const FRONTPAGE_ROLLER_EASY = 1020;
const FRONTPAGE_ROLLER_CUSTOMBUILT = 1021;
const FRONTPAGE_ROLLER_SIGNEDBY = 1022;
const FRONTPAGE_BIGDOWNLOADBUTTON = 1023;
const FRONTPAGE_EDUROAM_AD = 1024;
const INSTITUTION_SELECTION = 1030;
const PROFILE_SELECTION = 1040;
const DOWNLOAD_CHOOSE = 1049;
const DOWNLOAD_CHOOSE_ANOTHER = 1050;
const DOWNLOAD_ALLPLATFORMS = 1051;
const DOWNLOAD_MESSAGE = 1052;
const DOWNLOAD_REDIRECT = 1053;
const DOWNLOAD_REDIRECT_CONTINUE = 1054;
const SB_GO_AWAY = 1060;
const SB_FRONTPAGE_BIGDOWNLOADBUTTON = 1061;
const SB_FRONTPAGE_ROLLER_CUSTOMBUILT= 1062;


/**
 * some of the texts we write are consortium-specific.
 */
const EDUROAM_WELCOME_ADVERTISING = 2000;

/**
 * provides various translated texts which are hopefully of common interest for
 * a number of skins.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class TextTemplates extends \core\common\Entity {
    
    /**
     * An array with lots of template texts. 
     * 
     * HTML markup is used sparingly. Expect <br> <a> <b> <span> but nothing else. 
     * Remember that you can get plain text by using strip_tags()
     * 
     * @var array
     */
    public $templates;
    
    /**
     * Initialises the texts.
     */
    public function __construct() {
        \core\common\Entity::intoThePotatoes();
        $this->templates[WELCOME_ABOARD_PAGEHEADING] = sprintf(_("Welcome aboard the %s user community!"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[WELCOME_ABOARD_DOWNLOAD] = _("Your download will start shortly. In case of problems with the automatic download please use this direct <a href=''>link</a>.");
        $this->templates[WELCOME_ABOARD_HEADING] = sprintf(_("Dear user from %s,"), "<span class='inst_name'></span>");
        $this->templates[WELCOME_ABOARD_USAGE] = sprintf(_("Now that you have downloaded and installed a client configurator, all you need to do is find an %s hotspot in your vicinity and enter your user credentials (this is our fancy name for 'username and password' or 'personal certificate') - and be online!"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[WELCOME_ABOARD_PROBLEMS] = sprintf(_("Should you have any problems using this service, please always contact the helpdesk of %s. They will diagnose the problem and help you out. You can reach them via the means shown above."), "<span class='inst_name'></span>");
        $this->templates[NETWORK_TERMS_AND_PRIV] = [
            "eduroam" => [
                "TOU_LINK" => "https://wiki.geant.org/display/H2eduroam/Terms+and+Conditions",
                "TOU_TEXT" => _("eduroam Terms and Conditions"),
                "PRIV_LINK" => "https://www.eduroam.org/privacy/",
                "PRIV_TEXT" => _("eduroam Privacy Policy"),
            ],
            "OpenRoaming" => [
                "TOU_LINK" => "https://wballiance.com/openroaming/toc-2020/",
                "TOU_TEXT" => _("OpenRoaming Terms and Conditions"),
                "PRIV_LINK" => "https://wballiance.com/openroaming/privacy-policy-2020/",
                "PRIV_TEXT" => _("OpenRoaming Privacy Policy"),
            ],
        ];
        $this->templates[WELCOME_ABOARD_TERMS] = "";
        foreach ($this->templates[NETWORK_TERMS_AND_PRIV] as $consortium => $terms) {
            $this->templates[WELCOME_ABOARD_TERMS] .= sprintf("<p>" . _("Please remember that when connecting to %s hotspots, the following <a href='%s'>Terms and Conditions</a> and <a href='%s'>Privacy Notice</a> apply.") . "</p>", $consortium, $terms['TOU_LINK'], $terms['PRIV_LINK']);
        }
    //    $this->templates[WELCOME_ABOARD_TERMS] .= "<p>"._("I agree to be bound by these Terms and Conditions.")."</p>";
        $this->templates[WELCOME_ABOARD_BACKTODOWNLOADS] = _("Back to downloads");
        $this->templates[EDUROAM_WELCOME_ADVERTISING] = sprintf(_("We would like to warmly welcome you among the several million users of %s! From now on, you will be able to use internet access resources on thousands of universities, research centres and other places all over the globe. All of this completely free of charge!"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[HEADING_TOPLEVEL_GREET] = sprintf(_("Welcome to %s"), \config\Master::APPEARANCE['productname']);
        $this->templates[HEADING_TOPLEVEL_PURPOSE] = sprintf(_("Connect your device to %s"),\config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[FRONTPAGE_ROLLER_EASY] = sprintf(_("%s installation made easy:"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[FRONTPAGE_ROLLER_CUSTOMBUILT] = _("Custom built for your organisation");
        $this->templates[FRONTPAGE_BIGDOWNLOADBUTTON] = sprintf(_("Click here to download your %s installer"), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[FRONTPAGE_EDUROAM_AD] = sprintf(_("%s provides access to thousands of Wi-Fi hotspots around the world, free of charge. <a href='%s'>Learn more</a>"), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['homepage']);
        $this->templates[PROFILE_SELECTION] = _("Select the user group");
        $this->templates[INSTITUTION_SELECTION] = _("select another");
        $this->templates[DOWNLOAD_CHOOSE_ANOTHER] = _("Choose another installer to download");
        $this->templates[DOWNLOAD_CHOOSE] = _("Choose an installer to download");
        $this->templates[DOWNLOAD_ALLPLATFORMS] = _("All platforms");
        $this->templates[DOWNLOAD_MESSAGE] = sprintf(_("Download your %s installer"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[DOWNLOAD_REDIRECT] = _("Your local administrator has specified a redirect to a local support page.<br>When you click <b>Continue</b> this support page will be opened in a new window/tab.");
        $this->templates[DOWNLOAD_REDIRECT_CONTINUE] = _("Continue");
        $this->templates[FRONTPAGE_ROLLER_SIGNEDBY] = sprintf(_("Digitally signed by the organisation that coordinates %s"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[SB_GO_AWAY] = sprintf(_("You can download your %s installer via a personalised invitation link sent from your IT support. Please talk to the IT department to get this link."), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[SB_FRONTPAGE_BIGDOWNLOADBUTTON] = sprintf(_("This site provides %s installers for many organisations, click here to see if yours is on the list."), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->templates[SB_FRONTPAGE_ROLLER_CUSTOMBUILT] = _("Custom built for you");
        if (isset(\config\ConfAssistant::CONSORTIUM['signer_name'])) {
            $this->templates[FRONTPAGE_ROLLER_SIGNEDBY] = sprintf(_("Digitally signed by the organisation that coordinates %s: %s"), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['signer_name']);
        }
        \core\common\Entity::outOfThePotatoes();
    }
}
