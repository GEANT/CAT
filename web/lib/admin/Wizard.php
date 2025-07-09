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

/**
 * This class contains methods for displaing "wizard" help information for 
 * novice admins. During the first setup the messages are showm in-line.
 * Later only help icons are displayed and corresponding hints are displayed
 * in an overlay window
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Stefan Winter <stefan.winter@restena.lu>
 */

class Wizard extends UIElements {
    private $helpMessage;
    public $wizardStyle;
    private $optionsHelp;
        
    /**
     * Prepare the messages for the current language and set the wizardStyle
     * true means we display inline, false - show icons to call help.
     * 
     * @param boolean $wizardStyle
     */
    public function __construct($wizardStyle) {
        parent::__construct();
        $this->wizardStyle = $wizardStyle;
    }
    
    /**
     * Depending on the wizardStyle setting either display help in a fixed window
     * or display the "i" icon pointing to the help text in a hidden window
     * The text itself is taken from the helppMessage object indexed bu the $ubject
     * 
     * @param string $subject
     * @param array $options
     * @return string the HTML content for the page
     * 
     */
    public function displayHelp($subject, $options = NULL) {
        if (!isset($this->helpMessage[$subject])) {
            return '';
        }
        if ($options !== NULL) {
            if (!isset($options['level'])) {
                return '';
            }
        }
        return $this->displayHelpElement($this->helpMessage[$subject]);
    }

    /**
     * Depending on the wizardStyle setting either display help in a fixed window
     * or display the "i" icon pointing to the help text in a hidden window
     * The text itself is taken from the $input string
     * 
     * @param string $input
     * @return string the HTML content for the page
     * 
     */
    public function displayHelpText($input) {
        return $this->displayHelpElement($input);
    }
    
    /**
     * Create the actual content for displayHelp and displayHelpText
     * 
     * @param string $input
     * @return string the HTML content
     */
    private function displayHelpElement($input) {
        $iconTitle = _("Click to open the help window");
        if ($this->wizardStyle) {
            $wizardClass = "wizard_visible";
            $content = "<div>";
        } else {
            $wizardClass = "wizard_hidden";
            $content = "<div style='min-height:28px'><img src='../resources/images/icons/Tabler/info-square-rounded-filled-blue.svg' class='wizard_icon' title=\"$iconTitle\">";            
        }
        $content .= "<div class='$wizardClass'>".$input."</div></div>";
        return $content;        
    }
    
    
    public function setOptionsHelp($optionsList) {
        $this->optionsHelp = [];
        foreach ($optionsList as $option) {
            $this->optionsHelp[$option] = $this->displayName($option, true);
        }
        array_multisort(array_column($this->optionsHelp,'display'), SORT_ASC, $this->optionsHelp);
    }
    
    public function setMessages() {
        // FED general
        $h = "<p><h3>" . _("Here you set federation-level options.") . "</h3><p>";
        $h .= "<i>" . _("The following options are available:") . "</i><p>";
        if (isset($this->optionsHelp)) {
            $h .= "<dl>";
            foreach ($this->optionsHelp as $o) {
                $h .= "<dt>". $o['display'] . "</dt>";
                $h .= "<dd>" . $o['help'] . "</dd>";
            }
            $h .= "</dl>";
        }
        $this->helpMessage['fed_general'] = $h;
        // SUPPORT
        $h = "<p>" . _("This section can be used to upload specific Terms of Use for your users and to display details of how your users can reach your local helpdesk.") . "</p>";
        if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] == "LOCAL") {
            $h .= "<p>" .
            sprintf(_("Do you provide helpdesk services for your users? If so, it would be nice if you would tell us the pointers to this helpdesk."), $this->nomenclatureParticipant) . "</p>" .
            "<p>" .
            _("If you enter a value here, it will be added to the installers for all your users, and will be displayed on the download page. If you operate separate helpdesks for different user groups (we call this 'profiles') specify per-profile helpdesk information later in this wizard. If you operate no help desk at all, just leave these fields empty.") . "</p>";
            if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL") {
                $h .= "<p>" . sprintf(_("For %s deployments, providing at least a local e-mail contact is required."), \config\ConfAssistant::SILVERBULLET['product_name']) . " " . _("This is the contact point for your organisation. It may be displayed publicly.") . "</p>";
            }
        } elseif (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL") {
            $h .= "<p>" . _("Providing at least a local support e-mail contact is required.") . " " . _("This is the contact point for your end users' level 1 support.") . "</p>";
        }
        $this->helpMessage['support'] = $h;

        // MEDIA
        $h = "<p>" .
            sprintf(_("In this section, you define on which media %s should be configured on user devices."), \config\ConfAssistant::CONSORTIUM['display_name']) . "</p><ul>";
        $h .= "<li>";
        $h .= "<strong>" . ( count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0 ? _("Additional SSIDs:") : _("SSIDs:")) . " </strong>";
        if (count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0) {
            $ssidlist = "";
            foreach (\config\ConfAssistant::CONSORTIUM['ssid'] as $ssid) {
                $ssidlist .= ", '<strong>" . $ssid . "</strong>'";
            }
            $ssidlist = substr($ssidlist, 2);
             $h .= sprintf(ngettext("We will always configure this SSID for WPA2/AES: %s.", "We will always configure these SSIDs for WPA2/AES: %s.", count(\config\ConfAssistant::CONSORTIUM['ssid'])), $ssidlist);
             $h .= "<br/>" . sprintf(_("It is also possible to define custom additional SSIDs with the option '%s' below."), $this->displayName("media:SSID"));
        } else {
             $h .=  _("Please configure which SSIDs should be configured in the installers.");
        }
         $h .= " " . _("By default, we will only configure the SSIDs with WPA2/AES encryption. By using the '(with WPA/TKIP)' option you can specify that we should include legacy support for WPA/TKIP where possible.");
         $h .= "</li>";

        $h .= "<li>";
        $h .= "<strong>" . ( count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0 ? _("Additional Hotspot 2.0 / Passpoint Consortia:") : _("Hotspot 2.0 / Passpoint Consortia:")) . " </strong>";
        if (count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']) > 0) {
            $consortiumlist = "";
            foreach (\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'] as $oi) {
                $consortiumlist .= ", '<strong>" . $oi . "</strong>'";
            }
            $consortiumlist = substr($consortiumlist, 2);
            $h .= sprintf(ngettext("We will always configure this Consortium OI: %s.", "We will always configure these Consortium OIs: %s.", count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'])), $consortiumlist);

            $h .= "<br/>" . sprintf(_("It is also possible to define custom additional OIs with the option '%s' below."), $this->displayName("media:consortium_OI"));
        } else {
            $h .= _("Please configure which Consortium OIs should be configured in the installers.");
        }
        $h .= "</li>";
        $h .= "<li><strong>" . _("Support for wired IEEE 802.1X:") . " </strong>"
        . _("If you want to configure your users' devices with IEEE 802.1X support for wired ethernet, please check the corresponding box. Note that this makes the installation process a bit more difficult on some platforms (Windows: needs administrator privileges; Apple: attempting to install a profile with wired support on a device without an active wired ethernet card will fail).") .
        "</li>";
        $h .= "<li><strong>" . _("Removal of bootstrap/onboarding SSIDs:") . " </strong>"
        . _("If you use a captive portal to distribute configurations, you may want to unconfigure/disable that SSID after the bootstrap process. With this option, the SSID will either be removed, or be defined as 'Only connect manually'.")
        . "</li>";
        $h .= "</ul>";
        $this->helpMessage['media'] = $h;
        
        // IDP GENERAL
        $h = "<p>" .
        _("Some properties are valid across all deployment profiles. This is the place where you can describe those properties in a fine-grained way. The solicited information is used as follows:") . "</p>".
            "<ul>".
                "<li>"._("<strong>Logo</strong>: When you submit a logo, we will embed this logo into all installers where a custom logo is possible. We accept any image format, but for best results, we suggest SVG. If you don't upload a logo, we will use the generic logo instead (see top-right corner of this page).") . "</li>".
                "<li>".sprintf(_("<strong>%s</strong>: The organisation may have names in multiple languages. It is recommended to always populate at least the 'default/other' language, as it is used as a fallback if the system does not have a name in the exact language the user requests a download in."), $this->displayName("general:instname"))."</li>".
                "<li>".sprintf(_("<strong>%s</strong>: This acronym will be used as an element of the installer file name instead of one automatically created from first letters of every word in the institution name. You may add acronyms for multiple languages (but only one per language). The acronym will also be used as a keyword for the organisation search on the user's downloads page."), $this->displayName("general:instshortname"))."</li>".
                "<li>".sprintf(_("<strong>%s</strong>: You may add several versions of the organisation name or acronyms which will be used as additional keywords exclusively for the organisation search on the user's downloads page."), $this->displayName("general:instaltname"))."</li>".
            "</ul>";
        $this->helpMessage['idp_general'] = $h;
        
        // PROFILE GENERAL
        $h = "<p>" . _("First of all we need a name for the profile. This will be displayed to end users, so you may want to choose a descriptive name like 'Professors', 'Students of the Faculty of Bioscience', etc.") . "</p>".
            "<p>" . _("Optionally, you can provide a longer descriptive text about who this profile is for. If you specify it, it will be displayed on the download page after the user has selected the profile name in the list.") . "</p>".
            "<p>" . _("You can also tell us your RADIUS realm. ");
            if (\config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] !== NULL) {
               $h .= sprintf(_("This is useful if you want to use the sanity check module later, which tests reachability of your realm in the %s infrastructure. "), \config\ConfAssistant::CONSORTIUM['display_name']);
            }
        $h .= _("It is required to enter the realm name if you want to support anonymous outer identities (see below).") . "</p>";
        $this->helpMessage['profile'] = $h;
        
        // REALM
        $h = "<p>".sprintf(_("Some installers support a feature called 'Anonymous outer identity'. If you don't know what this is, please read <a href='%s'>this article</a>."), "https://confluence.terena.org/display/H2eduroam/eap-types")."</p>".
          "<p>"._("On some platforms, the installers can suggest username endings and/or verify the user input to contain the realm suffix.")."</p>".
          "<p>"._("The realm check feature needs to know an outer ID which actually gets a chance to authenticate. If your RADIUS server lets only select usernames pass, it is useful to supply the information which of those (outer ID) username we can use for testing.")."</p>";
        $this->helpMessage['realm'] = $h;
        
        // REDIRECT
         $h ="<p>"._("The CAT has a download area for end users. There, they will, for example, learn about the support pointers you entered earlier. The CAT can also immediately offer the installers for the profile for download. If you don't want that, you can instead enter a web site location where you want your users to be redirected to. You, as the administrator, can still download the profiles to place them on that page (see the 'Compatibility Matrix' button on the dashboard).") . "</p>";
        $this->helpMessage['redirect'] = $h;
        
        // EAP
        $h = "<p>"._("Now, we need to know which EAP types your IdP supports. If you support multiple EAP types, you can assign every type a priority (1=highest). This tool will always generate an automatic installer for the EAP type with the highest priority; only if the user's device can't use that EAP type, we will use an EAP type further down in the list.") . "</p>";
        $this->helpMessage['eap_support'] = $h;
        
        // LOCATIOM
        $h = "<p>" .
                    _("The user download interface (see <a href='../'>here</a>), uses geolocation to suggest possibly matching IdPs to the user. The more precise you define the location here, the easier your users will find you.") .
                    "</p>
                     <ul>" .
                    _("<li>Drag the marker in the map to your place, or</li>
<li>enter your street address in the field below for lookup, or</li>
<li>use the 'Locate Me!' button</li>") .
                    "</ul>
                     <strong>" .
                    _("We will use the coordinates as indicated by the marker for geolocation.") .
                    "</strong>";
        $this->helpMessage['location'] = $h;
    }
}

