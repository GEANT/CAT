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
require_once(__DIR__ . "/common.inc.php");

/**
 * We need to display previously set options in various forms. This class covers
 * the ways to do that; the generated page content can then be parsed with 
 * OptionParser.
 */
class OptionDisplay {

    /**
     *
     * @var array
     */
    private $listOfOptions;

    /**
     *
     * @var string
     */
    private $level;

    /**
     * Which attributes are we talking about?
     * @param array $options the options of interest
     * @param string $level the level on which these options were defined by the user
     */
    public function __construct($options, $level) {
        $this->listOfOptions = $options;
        $this->level = $level;
    }

    /**
     * creates a table with all the set options prefilled. Only displays options
     * of the category indicated.
     * @param string $attributePrefix category of option to display
     * @return string HTML code <table>
     */
    public function prefilledOptionTable($attributePrefix) {
        $retval = "<table id='expandable_$attributePrefix" . "_options'>";

        $prepopulate = [];
        foreach ($this->listOfOptions as $existingAttribute) {
            if ($existingAttribute['level'] == $this->level) {
                $prepopulate[] = $existingAttribute;
            }
        }
        $retval .= $this->add_option($attributePrefix, $prepopulate);
        $retval .= "</table>";
        return $retval;
    }

    /**
     * 
     * @param string $class the class of options that is to be displayed
     * @param array $prepopulate should an empty set of fillable options be displayed, or do we have existing data to prefill with
     */
    private function add_option($class, $prepopulate = []) { // no GET class ? we've been called directly:
        // this can mean either a new object (list all options with empty values)
        // or that an object is to be edited. In that case, $prepopulated has to
        // contain the array of existing variables
        // we expect the variable $class to contain the class of options
        $retval = "";

        $optioninfo = \core\Options::instance();

        if (is_array($prepopulate) && ( count($prepopulate) > 1 || $class == "device-specific" || $class == "eap-specific")) { // editing... fill with values
            $number = 0;
            foreach ($prepopulate as $option) {
                if (preg_match("/$class:/", $option['name']) && !preg_match("/(profile:QR-user|user:fedadmin)/", $option['name'])) {
                    $optiontypearray = $optioninfo->optionType($option['name']);
                    $loggerInstance = new \core\Logging();
                    $loggerInstance->debug(5, "About to execute optiontext with PREFILL!\n");
                    $retval .= $this->optiontext($number, [$option['name']], ($optiontypearray["type"] == "file" ? 'ROWID-' . $option['level'] . '-' . $option['row'] : $option['value']), $option['lang']);
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

            foreach (array_keys($list) as $key) {
                $retval .= $this->optiontext($key, $list);
            }
        }
        return $retval;
    }

    private function noPrefillText($rowid, $list, $defaultselect) {
        $retval = "";
        $optioninfo = \core\Options::instance();
        $jsmagic = "onchange='
                               if (/#ML#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                   document.getElementById(\"S$rowid-input-langselect\").style.display = \"block\";
                                   } else {
                                   document.getElementById(\"S$rowid-input-langselect\").style.display = \"none\";
                                   }
                               if (/#file#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"block\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"none\";
                             }
                               if (/#string#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"block\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"none\";
                               }
                                  if (/#text#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"block\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"none\";    
                               }
                                  if (/#boolean#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"block\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"none\";
                               }
                                  if (/#integer#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-integer\").style.display = \"block\";
                               }
    '";

        $retval .= "<td><select id='option-S$rowid-select' name='option[S$rowid]' $jsmagic>";
        $iterator = 0;
        foreach ($list as $value) {
            $listtype = $optioninfo->optionType($value);
            $retval .= "<option id='option-S$rowid-v-$value' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ";
            if ($iterator == $defaultselect) {
                $retval .= "selected='selected'";
                $activelisttype = $listtype;
            }
            $retval .= ">" . display_name($value) . "</option>";
            $iterator++;
        }
        if (!isset($activelisttype)) {
            throw new Exception("We should have found the active list type by now!");
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

    private function prefillText($rowid, $list, $prefill, $prefillLang, &$locationIndex, &$allLocationCount) {
        $retval = "";
        $optioninfo = \core\Options::instance();
        $loggerInstance = new \core\Logging();
        $loggerInstance->debug(5, "Executed with PREFILL $prefill!\n");
        $retval .= "<td>";
        // prefill is always only called with a list with exactly one element.
        // if we see anything else here, get excited.
        if (count($list) != 1) {
            throw new Exception("Optiontext prefilled display only can work with exactly one option!");
        }
        $value = $list[0];

        $listtype = $optioninfo->optionType($value);
        $retval .= display_name($value);
        $retval .= tooltip($value);
        $retval .= "<input type='hidden' id='option-S$rowid-select' name='option[S$rowid]' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ></td>";

        // language tag if any
        $retval .= "<td>";
        if ($listtype["flag"] == "ML") {

            $language = "(" . strtoupper($prefillLang) . ")";
            if ($prefillLang == 'C') {
                $language = _("(default/other languages)");
            }
            $retval .= $language;
            $retval .= "<input type='hidden' name='value[S$rowid-lang]' id='S" . $rowid . "-input-langselect' value='" . $prefillLang . "' style='display:block'>";
        }
        $retval .= "</td>";
// attribute content
        $retval .= "<td>";
        $intCode = 0;
        $displayedVariant = "";
        switch ($listtype["type"]) {
            case "coordinates":
                $allLocationCount++;
                $locationIndex = $allLocationCount;
                $link = "<button id='location_b_$allLocationCount' class='location_button'>" . _("Click to see location") . " $allLocationCount</button>";
                $retval .= "<input readonly style='display:none' type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-text' value='$prefill'>$link";
                break;
            case "file":
                $retval .= "<input readonly type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-string' style='display:none' value='" . urlencode($prefill) . "'>";
                switch ($value) {
                    case "eap:ca_file":
                        $retval .= previewCAinHTML($prefill);
                        break;
                    case "general:logo_file":
                    case "fed:logo_file":
                        $retval .= previewImageinHTML($prefill);
                        break;
                    case "support:info_file":
                        $retval .= previewInfoFileinHTML($prefill);
                        break;
                    default:
                        $retval .= _("file content");
                }
                break;
            case "string":
                $intCode = self::TYPECODE_STRING;
            // fall-thorugh is intentional; mostly identical HTML code for the three types
            case "integer":
                $intCode = self::TYPECODE_INTEGER;
            // fall-thorugh is intentional; mostly identical HTML code for the three types
            case "text":
                $intCode = self::TYPECODE_TEXT;
                $displayedVariant = $prefill; // for all three types, value tag and actual display are identical
            case "boolean":
                $intCode = self::TYPECODE_BOOLEAN;
                if ($displayedVariant != "") { // a fall-through has set this before
                    $displayedVariant = _("off");
                    if ($prefill == "on") {
                        /// Device assessment is "on"
                        $displayedVariant = _("on");
                    }
                }
                $retval .= "<strong>$displayedVariant</strong><input type='hidden' name='value[S$rowid-$intCode]' id='S" . $rowid . "-input-" . $listtype["type"] . "' value=\"" . htmlspecialchars($prefill) . "\" style='display:block'>";
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
     * @param int $defaultselect for new options, which of the data types should be preselected
     * @param array $list options which should be displayed; can be only exactly one if existing option, or multiple if new option type
     * @param string $prefill for existing option, it's value to be displayed
     * @param string $prefillLang for existing option, the language of the value to be displayed
     * @return string HTML code <tr>
     */
    public function optiontext($defaultselect, $list, $prefill = 0, $prefillLang = 0) {
        $allLocationCount = 0;
        $locationIndex = 0;
        $rowid = mt_rand();

        $retval = "<tr id='option-S$rowid' style='vertical-align:top'>";

        if (!$prefill) {
            $retval .= $this->noPrefillText($rowid, $list, $defaultselect);
        }

        if ($prefill) {
            $retval .= $this->prefillText($rowid, $list, $prefill, $prefillLang, $locationIndex, $allLocationCount);
        }
        $retval .= "

       <td>
          <button type='button' class='delete' onclick='deleteOption(" . $locationIndex . ",\"option-S" . $rowid . "\")'>-</button>
       </td>
    </tr>";
        return $retval;
    }

}
