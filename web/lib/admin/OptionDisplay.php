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
 * We need to display previously set options in various forms. This class covers
 * the ways to do that; the generated page content can then be parsed with 
 * OptionParser.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class OptionDisplay extends \core\common\Entity
{

    /**
     * stores all the options we are caring about
     * 
     * @var array
     */
    private $listOfOptions;

    /**
     * on which level are we operating?
     * 
     * @var string
     */
    private $level;

    /**
     * a counter storing how many locations are to be displayed
     * 
     * @var integer
     */
    private $allLocationCount;

    /**
     * When "fresh" options are displayed (HTML select/otion fields, optionally
     * with language, and of varying data types) we want to give each option
     * the same prominence and iterate over all options in the list. This
     * variable keeps track how many option HTML code we've already sent, so
     * that we can iterate correctly.
     * 
     * Only used inside noPrefillText variant of the optiontext() call
     * 
     * @var integer
     */
    private $optionIterator;

    private $htmlDatatypeTexts;
    
    private $enumPrettyPrints;
    /**
     * Which attributes are we talking about?
     * @param array  $options the options of interest
     * @param string $level   the level on which these options were defined by the user (not applicable for XHR UI, then it is NULL)
     */
    public function __construct(array $options, string $level = NULL)
    {
        $this->listOfOptions = $options;
        $this->level = $level;
        $this->allLocationCount = 0;

        $this->enumPrettyPrints = [
            "ask" => _("Ask User"),
            "ask-preagreed" => _("Ask User; T&C Pre-Agreed"),
            "always" => _("Always"),
            "always-preagreed" => _("Always; T&C Pre-Agreed"),
        ];
        $openRoamingTail = "";
        foreach ($this->enumPrettyPrints as $optionName => $optionDisplay) {
            $openRoamingTail .= "<option value='$optionName'>$optionDisplay</option>";
        }
        
        $this->htmlDatatypeTexts = [
            \core\Options::TYPECODE_FILE => ["html" => "input type='file'", "tail" => ' size=\'10\''],
            \core\Options::TYPECODE_BOOLEAN => ["html" => "input type='checkbox' checked", "tail" => ''],
            \core\Options::TYPECODE_INTEGER => ["html" => "input type='number'", "tail" => ''],
            \core\Options::TYPECODE_STRING => ["html" => "input type='string'", "tail" => ''],
            \core\Options::TYPECODE_ENUM_OPENROAMING => ["html" => "select", "tail" => ">$openRoamingTail</select"],
            \core\Options::TYPECODE_TEXT => ["html" => "textarea cols='30' rows='3'", "tail" => '></textarea'],
        ];
    }

    /**
     * creates a table with all the set options prefilled. Only displays options
     * of the category indicated.
     * @param string $attributePrefix category of option to display
     * @param string $fed             the federation we are in
     * @return string HTML code <table>
     */
    public function prefilledOptionTable(string $attributePrefix, $fed)
    {
        $retval = "<table id='expandable_$attributePrefix" . "_options'>";

        $prepopulate = [];
        foreach ($this->listOfOptions as $existingAttribute) {
            if ($existingAttribute['level'] == $this->level) {
                $prepopulate[] = $existingAttribute;
            }
        }
        if (is_array($prepopulate) && ( count($prepopulate) > 0 || $attributePrefix == "device-specific" || $attributePrefix == "eap-specific" )) { // editing... fill with values
            $retval .= $this->addOptionEdit($attributePrefix, $prepopulate);
        } else {
            $retval .= $this->addOptionNew($attributePrefix, $fed);
        }
        $retval .= "</table>";
        return $retval;
    }

    /**
     * Displays options for a given option class, in Edit mode.
     * 
     * @param string $class       the class of options that is to be displayed
     * @param array  $prepopulate should an empty set of fillable options be displayed, or do we have existing data to prefill with
     * @return string
     */
    private function addOptionEdit(string $class, array $prepopulate = [])
    { // no GET class ? we've been called directly:
        // this can mean either a new object (list all options with empty values)
        // or that an object is to be edited. In that case, $prepopulated has to
        // contain the array of existing variables
        // we expect the variable $class to contain the class of options
        $retval = "";
        $optioninfo = \core\Options::instance();
        $blackListOnPrefill = "user:fedadmin|managedsp:vlan|managedsp:operatorname";
        if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] != "LOCAL") {
            $blackListOnPrefill .= "|fed:silverbullet";
        }
        foreach ($prepopulate as $option) {
            if (preg_match("/^$class:/", $option['name']) && !preg_match("/($blackListOnPrefill)/", $option['name'])) {
                $optiontypearray = $optioninfo->optionType($option['name']);
                $loggerInstance = new \core\common\Logging();
                $loggerInstance->debug(5, "About to execute optiontext with PREFILL!\n");
                $retval .= $this->optiontext([$option['name']], ($optiontypearray["type"] == "file" ? 'ROWID-' . $option['level'] . '-' . $option['row'] : $option['value']), $option['lang']);
            }
        }
        return $retval;
    }

    /**
     * Find which options to expose to UI and which to hide.
     * Not all options defined in the database are (always) displayed. Some have
     * custom UI not matching the usual dropdown display, some depend on context
     * (e.g. OpenRoaming or not, depending on whether the fed operator wants it
     * 
     * @param string $class the type of options requested
     * @param string $fed   the federation TLD, to determine fed ops prefernce context
     * @return array the list of options to display
     */
    public static function enumerateOptionsToDisplay($class, $fed)
    {
        $optioninfo = \core\Options::instance();

        $list = $optioninfo->availableOptions($class);

        // use federation context to delete more options, if the feds don't like
        // a particular one
        $fedInstance = new \core\Federation($fed);
        switch ($class) {
            case "general":
                unset($list[array_search("general:geo_coordinates", $list)]);
                break;
            case "user":
                unset($list[array_search("user:fedadmin", $list)]);
                break;
            case "managedsp":
                unset($list[array_search("managedsp:vlan", $list)]);
                unset($list[array_search("managedsp:operatorname", $list)]);
                break;
            case "fed":
                //normally, we have nothing to hide on that level
                // if we are a Managed IdP exclusive deployment, do not display or allow
                // to change the "Enable Managed IdP" boolean - it is simply always there
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] != "LOCAL") {
                    unset($list[array_search("fed:silverbullet", $list)]);
                }
                break;
            case "media":
                if ($fedInstance->getAttributes("fed:openroaming") == []) {
                    // no openroaming here
                    unset($list[array_search("media:openroaming_always", $list)]);
                    unset($list[array_search("media:openroaming_ask", $list)]);
                    unset($list[array_search("media:openroaming", $list)]);
                }
                break;
            default:
                break;
        }

        return $list;
    }

    /**
     * Displays options for a given option class, in New mode.
     * 
     * @param string $class           the class of options that is to be displayed
     * @param string $fed             the federation we are in
     * @return string
     */
    private function addOptionNew(string $class, $fed)
    {
        $retval = "";

        $list2 = array_values(OptionDisplay::enumerateOptionsToDisplay($class, $fed));

        // add as many options as there are different option types
        $numberOfOptions = count($list2);
        for ($this->optionIterator = 0; $this->optionIterator < $numberOfOptions; $this->optionIterator++) {
            $retval .= $this->optiontext($list2);
        }
        return $retval;
    }

    /**
     * produce code for a option-specific tooltip
     * @param int     $rowid     the number (nonce during page build) of the option 
     *                           that should get the tooltip
     * @param string  $input     the option name. Tooltip for it will be displayed
     *                           if we have one available.
     * @param boolean $isVisible should the tooltip be visible with the option,
     *                           or are they both currently hidden?
     * @return string
     */
    private function tooltip($rowid, $input, $isVisible)
    {
        \core\common\Entity::intoThePotatoes();
        $descriptions = [];
        if (count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0) {
            $descriptions["media:SSID"] = sprintf(_("This attribute can be set if you want to configure an additional SSID besides the default SSIDs for %s. It is almost always a bad idea not to use the default SSIDs. The only exception is if you have premises with an overlap of the radio signal with another %s hotspot. Typical misconceptions about additional SSIDs include: I want to have a local SSID for my own users. It is much better to use the default SSID and separate user groups with VLANs. That approach has two advantages: 1) your users will configure %s properly because it is their everyday SSID; 2) if you use a custom name and advertise this one as extra secure, your users might at some point roam to another place which happens to have the same SSID name. They might then be misled to believe that they are connecting to an extra secure network while they are not."), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['display_name']);
        }
        $descriptions["media:force_proxy"] = sprintf(_("The format of this option is: IPv4|IPv6|hostname:port . Forcing your users through a content filter of your own is a significant invasion of user self-determination. It also has technical issues. Please throughly read the discussion at %s before specifying a proxy with this option. This feature is currently experimental and only has an effect in Apple installers."), "https://github.com/GEANT/CAT/issues/96");
        $descriptions["managedsp:realmforvlan"] = sprintf(_("If you are also using %s, then your own realm is automatically tagged with the VLAN you choose, there is no need to add it here manually."), \core\ProfileSilverbullet::PRODUCTNAME);
        $descriptions["media:openroaming"] = sprintf(_("By opting in to OpenRoaming, you agree to be bound by the %s."), "eduroam Ecosystem Broker OpenRoaming Identity Provider Policy") .
                " " .
                sprintf(_("Note that your requirement to inform users about the OpenRoaming End User Terms and Conditions is fulfilled when directing your end users to the %s download portal for installer download. Any other means of providing the installers needs to present this information via its own channel."), \config\Master::APPEARANCE['productname']) .
                " " .
                _("You are also aware that for best technical interoperability, you need to add a DNS entry into the DNS zone of your RADIUS realm.") .
                " " .
                _("Read the instructions in the wiki.");
        \core\common\Entity::outOfThePotatoes();
        if (!isset($descriptions[$input])) {
            return "";
        }
        return "<span class='tooltip' id='S$rowid-tooltip-$input' style='display:" . ($isVisible ? "block" : "none") . "' onclick='alert(\"" . $descriptions[$input] . "\")'><img src='../resources/images/icons/question-mark-icon.png" . "'></span>";
    }

    /**
     * 
     * @param int   $rowid the number (nonce during page build) of the option 
     *                     that should get the tooltip
     * @param array $list  elements of the drop-down list
     * @return array HTML code and which option is active
     * @throws \Exception
     */
    private function selectElement($rowid, $list)
    {
        $jsmagic = "onchange='
                               if (/#ML#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                   document.getElementById(\"S$rowid-input-langselect\").style.display = \"block\";
                                   } else {
                                   document.getElementById(\"S$rowid-input-langselect\").style.display = \"none\";
                                   }";
        foreach (array_keys($this->htmlDatatypeTexts) as $key) {
            $jsmagic .= "if (/#" . $key . "#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"" . ($key == \core\Options::TYPECODE_FILE ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"" . ($key == \core\Options::TYPECODE_TEXT ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"" . ($key == \core\Options::TYPECODE_STRING ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-enum_openroaming\").style.display = \"" . ($key == \core\Options::TYPECODE_ENUM_OPENROAMING ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"" . ($key == \core\Options::TYPECODE_BOOLEAN ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"" . ($key == \core\Options::TYPECODE_INTEGER ? "block" : "none") . "\";
                             }
                             ";
            // hide all tooltips (each is a <span>, and there are no other <span>s)
            $jsmagic .= <<< FOO
                    var ourtooltips = document.querySelectorAll(&#34;[id^=&#39;S$rowid-tooltip-&#39;]&#34;);
                    for (var i=0; i<ourtooltips.length; i++) {
                      ourtooltips[i].style.display = "none";
                    }
                    var optionnamefull = document.getElementById("option-S$rowid-select").value;
                    var firstdelimiter = optionnamefull.indexOf("#");
                    var optionname = optionnamefull.substring(0,firstdelimiter);
                    var tooltipifany = document.getElementById("S$rowid-tooltip-"+optionname);
                    if (tooltipifany != null) {
                      tooltipifany.style.display = "block";
                    }
FOO;
        }
        $jsmagic .= "'";

        $optioninfo = \core\Options::instance();
        $retval = "<span style='display:flex';>";
        $iterator = 0;
        $tooltips = "";
        $uiElements = new UIElements();
        $activelisttype = [];
        switch (count($list)) {
            case 1: // if there is only one option available, don't introduce an artificial drop-down for it
                $value = array_shift($list);
                $listtype = $optioninfo->optionType($value);
                $retval .= $uiElements->displayName($value);
                $retval .= "<input type='hidden' name='option[S$rowid]' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#'/>";
                $activelisttype = $listtype;
                $tooltips = $this->tooltip($rowid, $value, TRUE);
                break;
            default:
                $retval .= "<select id='option-S$rowid-select' name='option[S$rowid]' $jsmagic>";
                foreach ($list as $value) {
                    $listtype = $optioninfo->optionType($value);
                    $retval .= "<option id='option-S$rowid-v-$value' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ";
                    if ($iterator == $this->optionIterator) {
                        $retval .= "selected='selected'";
                        $activelisttype = $listtype;
                        $tooltips .= $this->tooltip($rowid, $value, TRUE);
                    } else {
                        $tooltips .= $this->tooltip($rowid, $value, FALSE);
                    }
                    $retval .= ">" . $uiElements->displayName($value) . "</option>";
                    $iterator++;
                }

                if (count($activelisttype) == 0) {
                    throw new \Exception("We should have found the active list type by now!");
                }
                $retval .= "</select>";
        }
        $retval .= $tooltips;
        $retval .= "</span>";

        return ["TEXT" => $retval, "ACTIVE" => $activelisttype];
    }

    /**
     * HTML code to display the language selector
     * 
     * @param int     $rowid       the number (nonce during page build) of the option 
     *                             that should get the tooltip
     * @param boolean $makeVisible is the language selector to be made visible?
     * @return string
     */
    private function selectLanguage($rowid, $makeVisible)
    {
        \core\common\Entity::intoThePotatoes();
        $retval = "<select style='display:" . ($makeVisible ? "block" : "none") . "' name='value[S$rowid-lang]' id='S" . $rowid . "-input-langselect'>
            <option value='' name='select_language' selected>" . _("select language") . "</option>
            <option value='C' name='all_languages'>" . _("default/other languages") . "</option>";
        foreach (\config\Master::LANGUAGES as $langindex => $possibleLang) {
            $thislang = $possibleLang['display'];
            $retval .= "<option value='$langindex' name='$langindex'>$thislang</option>";
        }
        $retval .= "</select>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * HTML code for a given option. Marks the matching datatype as visible, all other datatypes hidden
     * @param int   $rowid      the number (nonce during page build) of the option 
     *                          that should get the tooltip
     * @param array $activetype the active datatype that is to be visible
     * @return string
     */
    private function inputFields($rowid, $activetype)
    {
        $retval = "";
        foreach ($this->htmlDatatypeTexts as $key => $type) {
            $retval .= "<" . $type['html'] . " style='display:" . ($activetype['type'] == $key ? "block" : "none") . "' name='value[S$rowid-$key]' id='S" . $rowid . "-input-" . $key . "'" . $type['tail'] . ">";
        }
        return $retval;
    }

    /**
     * HTML code to display a "fresh" option (including type selector and JavaScript to show/hide relevant input fields)
     * @param int   $rowid the HTML field base name of the option to be displayed
     * @param array $list  the list of option names to include in the type selector
     * @return string HTML code
     * @throws Exception
     */
    private function noPrefillText(int $rowid, array $list)
    {
        // first column: the <select> element with the names of options and their field-toggling JS magic
        $selectorInfo = $this->selectElement($rowid, $list);
        $retval = "<td>" . $selectorInfo["TEXT"] . "</td>";
        // second column: the <select> element for language selection - only visible if the active option is multi-lang
        $retval .= "<td>" . $this->selectLanguage($rowid, $selectorInfo['ACTIVE']['flag'] == "ML") . "</td>";
        // third column: the actual input fields; the data type of the active option is visible, all others hidden
        $retval .= "<td>" . $this->inputFields($rowid, $selectorInfo['ACTIVE']) . "</td>";
        return $retval;
    }

    /**
     * generates HTML code that displays an already set option.
     * 
     * @param int    $rowid       the HTML field base name of the option to be displayed
     * @param string $optionName  the name of the option to display
     * @param string $optionValue the value of the option to display
     * @param mixed  $optionLang  the language of the option to display
     * @return string HTML code
     * @throws Exception
     */
    private function prefillText(int $rowid, string $optionName, string $optionValue, $optionLang)
    {
        \core\common\Entity::intoThePotatoes();
        $retval = "";
        $optioninfo = \core\Options::instance();
        $loggerInstance = new \core\common\Logging();
        $loggerInstance->debug(5, "Executed with PREFILL $optionValue!\n");
        $retval .= "<td>";
        $uiElements = new UIElements();
        $listtype = $optioninfo->optionType($optionName);
        $retval .= "<span style='display:flex;'>" . $uiElements->displayName($optionName);
        $retval .= $this->tooltip($rowid, $optionName, TRUE) . "</span>";
        $retval .= "<input type='hidden' id='option-S$rowid-select' name='option[S$rowid]' value='$optionName#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ></td>";

        // language tag if any
        $retval .= "<td>";
        if ($listtype["flag"] == "ML") {

            $language = "(" . strtoupper($optionLang) . ")";
            if ($optionLang == 'C') {
                $language = _("(default/other languages)");
            }
            $retval .= $language;
            $retval .= "<input type='hidden' name='value[S$rowid-lang]' id='S" . $rowid . "-input-langselect' value='" . $optionLang . "' style='display:block'>";
        }
        $retval .= "</td>";
// attribute content
        $retval .= "<td>";
        $displayedVariant = "";
        switch ($listtype["type"]) {
            case \core\Options::TYPECODE_COORDINATES:
                $this->allLocationCount = $this->allLocationCount + 1;
                // display of the locations varies by map provider
                $classname = "\web\lib\admin\Map" . \config\ConfAssistant::MAPPROVIDER['PROVIDER'];
                $link = $classname::optionListDisplayCode($optionValue, $this->allLocationCount);
                $retval .= "<input readonly style='display:none' type='text' name='value[S$rowid-" . \core\Options::TYPECODE_TEXT . "]' id='S$rowid-input-text' value='$optionValue'>$link";
                break;
            case \core\Options::TYPECODE_FILE:
                $retval .= "<input readonly type='text' name='value[S$rowid-" . \core\Options::TYPECODE_STRING . "]' id='S" . $rowid . "-input-string' style='display:none' value='" . urlencode($optionValue) . "'>";
                $uiElements = new UIElements();
                switch ($optionName) {
                    case "eap:ca_file":
                    // fall-through intentional: display both types the same way
                    case "fed:minted_ca_file":
                        $retval .= $uiElements->previewCAinHTML($optionValue);
                        break;
                    case "general:logo_file":
                    // fall-through intentional: display both types the same way
                    case "fed:logo_file":
                        $retval .= $uiElements->previewImageinHTML($optionValue);
                        break;
                    case "support:info_file":
                        $retval .= $uiElements->previewInfoFileinHTML($optionValue);
                        break;
                    default:
                        $retval .= _("file content");
                }
                break;
            case \core\Options::TYPECODE_ENUM_OPENROAMING: // is a string after all
                $displayedVariant = $this->enumPrettyPrints[$optionValue];
                $retval .= "<strong>$displayedVariant</strong><input type='hidden' name='value[S$rowid-" . $listtype['type'] . "]' id='S" . $rowid . "-input-" . $listtype["type"] . "' value=\"" . htmlspecialchars($optionValue) . "\" style='display:block'>";
                break;
            case \core\Options::TYPECODE_STRING:
            // fall-thorugh is intentional; mostly identical HTML code for the three types
            case \core\Options::TYPECODE_INTEGER:
            // fall-thorugh is intentional; mostly identical HTML code for the three types
            case \core\Options::TYPECODE_TEXT:
                $displayedVariant = $optionValue; // for all three types, value tag and actual display are identical
                $retval .= "<strong>$displayedVariant</strong><input type='hidden' name='value[S$rowid-" . $listtype['type'] . "]' id='S" . $rowid . "-input-" . $listtype["type"] . "' value=\"" . htmlspecialchars($optionValue) . "\" style='display:block'>";
                break;
            case \core\Options::TYPECODE_BOOLEAN:
                $displayedVariant = ($optionValue == "on" ? _("on") : _("off"));
                $retval .= "<strong>$displayedVariant</strong><input type='hidden' name='value[S$rowid-" . $listtype['type'] . "]' id='S" . $rowid . "-input-" . $listtype["type"] . "' value=\"" . htmlspecialchars($optionValue) . "\" style='display:block'>";
                break;
            default:
                // this should never happen!
                throw new Exception("Internal Error: unknown attribute type $listtype!");
        }
        $retval .= "</td>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * Displays a container for options. Either with prefilled data or empty; if
     * empty then has HTML <input> tags with clever javaScript to allow selection
     * of different option names and types
     * @param array  $list         options which should be displayed; can be only exactly one if existing option, or multiple if new option type
     * @param string $prefillValue for an existing option, it's value to be displayed
     * @param string $prefillLang  for an existing option, the language of the value to be displayed
     * @return string HTML code <tr>
     * @throws Exception
     */
    public function optiontext(array $list, string $prefillValue = NULL, string $prefillLang = NULL)
    {
        $rowid = mt_rand();

        $retval = "<tr id='option-S$rowid' style='vertical-align:top'>";

        $item = "MULTIPLE";
        if ($prefillValue === NULL) {
            $retval .= $this->noPrefillText($rowid, $list);
        }

        if ($prefillValue !== NULL) {
            // prefill is always only called with a list with exactly one element.
            // if we see anything else here, get excited.
            if (count($list) != 1) {
                throw new Exception("Optiontext prefilled display only can work with exactly one option!");
            }
            $item = array_pop($list);
            $retval .= $this->prefillText($rowid, $item, $prefillValue, $prefillLang);
        }
        $retval .= "

       <td>
          <button type='button' class='delete' onclick='";
        if ($prefillValue !== NULL && $item == "general:geo_coordinates") {
            $funcname = "Map" . \config\ConfAssistant::MAPPROVIDER['PROVIDER'] . 'DeleteCoord';
            $retval .= 'if (typeof ' . $funcname . ' === "function") { ' . $funcname . '(' . $this->allLocationCount . '); } ';
        }
        $retval .= 'deleteOption("option-S' . $rowid . '")';
        $retval .= "'>-</button>
       </td>
    </tr>";
        return $retval;
    }
}
