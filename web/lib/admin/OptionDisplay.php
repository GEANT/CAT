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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

/**
 * We need to display previously set options in various forms. This class covers
 * the ways to do that; the generated page content can then be parsed with 
 * OptionParser.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class OptionDisplay {

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
     * @var int
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
     * @var int
     */
    private $optionIterator;

    /**
     * Which attributes are we talking about?
     * @param array $options the options of interest
     * @param string $level the level on which these options were defined by the user
     */
    public function __construct(array $options, string $level) {
        $this->listOfOptions = $options;
        $this->level = $level;
        $this->allLocationCount = 0;
    }

    /**
     * creates a table with all the set options prefilled. Only displays options
     * of the category indicated.
     * @param string $attributePrefix category of option to display
     * @return string HTML code <table>
     */
    public function prefilledOptionTable(string $attributePrefix) {
        $retval = "<table id='expandable_$attributePrefix" . "_options'>";

        $prepopulate = [];
        foreach ($this->listOfOptions as $existingAttribute) {
            if ($existingAttribute['level'] == $this->level) {
                $prepopulate[] = $existingAttribute;
            }
        }
        $retval .= $this->addOption($attributePrefix, $prepopulate);
        $retval .= "</table>";
        return $retval;
    }

    /**
     * Displays options for a given option class.
     * 
     * @param string $class the class of options that is to be displayed
     * @param array $prepopulate should an empty set of fillable options be displayed, or do we have existing data to prefill with
     */
    private function addOption(string $class, array $prepopulate = []) { // no GET class ? we've been called directly:
        // this can mean either a new object (list all options with empty values)
        // or that an object is to be edited. In that case, $prepopulated has to
        // contain the array of existing variables
        // we expect the variable $class to contain the class of options
        $retval = "";

        $optioninfo = \core\Options::instance();

        if (is_array($prepopulate) && ( count($prepopulate) > 1 || $class == "device-specific" || $class == "eap-specific")) { // editing... fill with values
            foreach ($prepopulate as $option) {
                if (preg_match("/$class:/", $option['name']) && !preg_match("/(user:fedadmin)/", $option['name'])) {
                    $optiontypearray = $optioninfo->optionType($option['name']);
                    $loggerInstance = new \core\common\Logging();
                    $loggerInstance->debug(5, "About to execute optiontext with PREFILL!\n");
                    $retval .= $this->optiontext([$option['name']], ($optiontypearray["type"] == "file" ? 'ROWID-' . $option['level'] . '-' . $option['row'] : $option['value']), $option['lang']);
                }
            }
        } else { // not editing exist, this in new: add empty list
            $list = $optioninfo->availableOptions($class);
            switch ($class) {
                case "general":
                    $blacklistItem = array_search("general:geo_coordinates", $list);
                    break;
                case "profile":
                    $blacklistItem = array_search("profile:QR-user", $list);
                    break;
                case "user":
                    $blacklistItem = array_search("user:fedadmin", $list);
                    break;
                default:
                    $blacklistItem = FALSE;
            }
            if ($blacklistItem !== FALSE) {
                unset($list[$blacklistItem]);
                $list = array_values($list);
            }

            // add as many options as there are different option types
            $numberOfOptions = count($list);
            for ($this->optionIterator = 0; $this->optionIterator < $numberOfOptions; $this->optionIterator++) {
                $retval .= $this->optiontext($list);
            }
        }
        return $retval;
    }

    /**
     * HTML code to display a "fresh" option (including type selector and JavaScript to show/hide relevant input fields)
     * @param int $rowid the HTML field base name of the option to be displayed
     * @param array $list the list of option names to include in the type selector
     * @return string HTML code
     * @throws Exception
     */
    private function noPrefillText(int $rowid, array $list) {
        $retval = "";
        $optioninfo = \core\Options::instance();
        $jsmagic = "onchange='
                               if (/#ML#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                   document.getElementById(\"S$rowid-input-langselect\").style.display = \"block\";
                                   } else {
                                   document.getElementById(\"S$rowid-input-langselect\").style.display = \"none\";
                                   }";
        $dataTypes = ["file", "text", "string", "boolean", "integer"];
        foreach ($dataTypes as $oneDataType) {
            // TODO make this a $jsmagic .= after the update of cat-pilot
            $jsmagic .= "if (/#$oneDataType#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"" . ($oneDataType == "file" ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"" . ($oneDataType == "text" ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"" . ($oneDataType == "string" ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"" . ($oneDataType == "boolean" ? "block" : "none") . "\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"" . ($oneDataType == "integer" ? "block" : "none") . "\";
                             }
                             ";
        }
        $jsmagic .= "'";
        $retval .= "<td><select id='option-S$rowid-select' name='option[S$rowid]' $jsmagic>";
        $iterator = 0;
        $uiElements = new UIElements();
        foreach ($list as $value) {
            $listtype = $optioninfo->optionType($value);
            $retval .= "<option id='option-S$rowid-v-$value' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ";
            if ($iterator == $this->optionIterator) {
                $retval .= "selected='selected'";
                $activelisttype = $listtype;
            }
            $retval .= ">" . $uiElements->displayName($value) . "</option>";
            $iterator++;
        }
        if (!isset($activelisttype)) {
            throw new \Exception("We should have found the active list type by now!");
        }
        $retval .= "</select></td>";
        $retval .= "<td>
          <select style='display:" . ($activelisttype["flag"] == "ML" ? "block" : "none") . "' name='value[S$rowid-lang]' id='S" . $rowid . "-input-langselect'>
            <option value='' name='select_language' selected>" . _("select language") . "</option>
            <option value='C' name='all_languages'>" . _("default/other languages") . "</option>";
        foreach (CONFIG['LANGUAGES'] as $langindex => $possibleLang) {
            $thislang = $possibleLang['display'];
            $retval .= "<option value='$langindex' name='$langindex'>$thislang</option>";
        }
        $retval .= "</select></td><td>
            <input type='text'     style='display:" . ($activelisttype["type"] == "string" ? "block" : "none") . "' name='value[S$rowid-0]'  id='S" . $rowid . "-input-string'>
            <textarea cols='30' rows='3'     style='display:" . ($activelisttype["type"] == "text" ? "block" : "none") . "' name='value[S$rowid-1]'  id='S" . $rowid . "-input-text'></textarea>
            <input type='file'     style='display:" . ($activelisttype["type"] == "file" ? "block" : "none") . "' name='value[S$rowid-2]'  id='S" . $rowid . "-input-file' size='10'>
            <input type='checkbox' style='display:" . ($activelisttype["type"] == "boolean" ? "block" : "none") . "' name='value[S$rowid-3]'  id='S" . $rowid . "-input-boolean'>
            <input type='number' style='display:" . ($activelisttype["type"] == "integer" ? "block" : "none") . "' name='value[S$rowid-4]'  id='S" . $rowid . "-input-integer'>";
        $retval .= "</td>";

        return $retval;
    }

    const TYPECODE_STRING = 0;
    const TYPECODE_INTEGER = 4;
    const TYPECODE_TEXT = 1;
    const TYPECODE_BOOLEAN = 3;

    /**
     * generates HTML code that displays an already set option.
     * 
     * @param int $rowid the HTML field base name of the option to be displayed
     * @param string $optionName the name of the option to display
     * @param string $optionValue the value of the option to display
     * @param mixed $optionLang the language of the option to display
     * @return string HTML code
     * @throws Exception
     */
    private function prefillText(int $rowid, string $optionName, string $optionValue, $optionLang) {
        $retval = "";
        $optioninfo = \core\Options::instance();
        $loggerInstance = new \core\common\Logging();
        $loggerInstance->debug(5, "Executed with PREFILL $optionValue!\n");
        $retval .= "<td>";
        $uiElements = new UIElements();
        $listtype = $optioninfo->optionType($optionName);
        $retval .= $uiElements->displayName($optionName);
        $retval .= $uiElements->tooltip($optionName);
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
        $intCode = -1;
        $displayedVariant = "";
        switch ($listtype["type"]) {
            case "coordinates":
                $this->allLocationCount = $this->allLocationCount + 1;
                $link = "<button id='location_b_" . $this->allLocationCount . "' class='location_button'>" . _("Click to see location") . " $this->allLocationCount</button>";
                $retval .= "<input readonly style='display:none' type='text' name='value[S$rowid-" . self::TYPECODE_TEXT . "]' id='S$rowid-input-text' value='$optionValue'>$link";
                break;
            case "file":
                $retval .= "<input readonly type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-string' style='display:none' value='" . urlencode($optionValue) . "'>";
                $uiElements = new UIElements();
                switch ($optionName) {
                    case "eap:ca_file":
                        $retval .= $uiElements->previewCAinHTML($optionValue);
                        break;
                    case "general:logo_file":
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
            case "string":
                if ($intCode == -1) {
                    $intCode = self::TYPECODE_STRING;
                }
            // fall-thorugh is intentional; mostly identical HTML code for the three types
            case "integer":
                if ($intCode == -1) {
                    $intCode = self::TYPECODE_INTEGER;
                }
            // fall-thorugh is intentional; mostly identical HTML code for the three types
            case "text":
                if ($intCode == -1) {
                    $intCode = self::TYPECODE_TEXT;
                }
                $displayedVariant = $optionValue; // for all three types, value tag and actual display are identical
            case "boolean":
                if ($intCode == -1) {
                    $intCode = self::TYPECODE_BOOLEAN;
                }
                if ($displayedVariant == "") { // a fall-through has set this before
                    $displayedVariant = _("off");
                    if ($optionValue == "on") {
                        /// Device assessment is "on"
                        $displayedVariant = _("on");
                    }
                }
                $retval .= "<strong>$displayedVariant</strong><input type='hidden' name='value[S$rowid-$intCode]' id='S" . $rowid . "-input-" . $listtype["type"] . "' value=\"" . htmlspecialchars($optionValue) . "\" style='display:block'>";
                break;
            default:
                // this should never happen!
                throw new Exception("Internal Error: unknown attribute type $listtype!");
        }
        $retval .= "</td>";
        return $retval;
    }

    /**
     * Displays a container for options. Either with prefilled data or empty; if
     * empty then has HTML <input> tags with clever javaScript to allow selection
     * of different option names and types
     * @param array $list options which should be displayed; can be only exactly one if existing option, or multiple if new option type
     * @param string $prefillValue for an existing option, it's value to be displayed
     * @param string $prefillLang for an existing option, the language of the value to be displayed
     * @return string HTML code <tr>
     */
    public function optiontext(array $list, string $prefillValue = NULL, string $prefillLang = NULL) {
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
          <button type='button' class='delete' onclick='deleteOption(" . ( $prefillValue !== NULL && $item == "general:geo_coordinates" ? $this->allLocationCount : 0 ) . ",\"option-S" . $rowid . "\")'>-</button>
       </td>
    </tr>";
        return $retval;
    }

}
