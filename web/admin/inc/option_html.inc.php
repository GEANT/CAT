<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Options.php");
require_once("common.inc.php");
require_once("Logging.php");

function prefilledOptionTable($existing_options, $attributePrefix, $level) {
    $retval = "<table id='expandable_$attributePrefix" . "_options'>";

    $prepopulate = [];
    foreach ($existing_options as $existing_attribute) {
        if ($existing_attribute['level'] == $level) {
            $prepopulate[] = $existing_attribute;
        }
    }
    $retval .= add_option($attributePrefix, $prepopulate);
    $retval .= "</table>";
    return $retval;
}

/**
 * 
 * @param string $class the class of options that is to be displayed
 * @param array $prepopulate should an empty set of fillable options be displayed, or do we have existing data to prefill with
 */
function add_option($class, $prepopulate = []) { // no GET class ? we've been called directly:
    // this can mean either a new object (list all options with empty values)
    // or that an object is to be edited. In that case, $prepopulated has to
    // contain the array of existing variables
    // we expect the variable $class to contain the class of options
    $retval = "";

    $optioninfo = Options::instance();

    if (is_array($prepopulate) && ( count($prepopulate) > 1 || $class == "device-specific" || $class == "eap-specific")) { // editing... fill with values
        $number = 0;
        foreach ($prepopulate as $option) {
            if (preg_match("/$class:/", $option['name']) && !preg_match("/(profile:QR-user|user:fedadmin)/", $option['name'])) {
                $optiontypearray = $optioninfo->optionType($option['name']);
                $loggerInstance = new Logging();
                $loggerInstance->debug(5, "About to execute optiontext with PREFILL!\n");
                $retval .= optiontext($number, [$option['name']], ($optiontypearray["type"] == "file" ? 'ROWID-' . $option['level'] . '-' . $option['row'] : $option['value']));
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
            $retval .= optiontext($key, $list);
        }
    }
    return $retval;
}

function noPrefillText($rowid, $defaultselect) {
    $retval = "";
    $optioninfo = Options::instance();
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

function prefillText($rowid, $prefill, $list, &$locationIndex, &$allLocationCount) {
    $retval = "";
    $optioninfo = Options::instance();
    $loggerInstance = new Logging();
    $loggerInstance->debug(5, "Executed with PREFILL!\n");
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

        if (preg_match('/^ROWID/', $prefill) == 0) { // this is direct content, not referral from DB
            $taggedarray = unserialize($prefill);
            if ($taggedarray === FALSE) {
                throw new Exception("INTERNAL ERROR: unable to unserialize multilang attribute!<p>$prefill");
            }
            $content = $taggedarray["content"];
        } else {
            $taggedarray = unserialize(getBlobFromDB($prefill, FALSE));
            $content = $prefill;
        }
        $language = "(" . strtoupper($taggedarray['lang']) . ")";
        if ($taggedarray['lang'] == 'C') {
            $language = _("(default/other languages)");
        }
        $retval .= $language;
        $retval .= "<input type='hidden' name='value[S$rowid-lang]' id='S" . $rowid . "-input-langselect' value='" . $taggedarray["lang"] . "' style='display:block'>";
    } else {
        $content = $prefill;
    }
    $retval .= "</td>";
// attribute content
    $retval .= "<td>";
    switch ($listtype["type"]) {
        case "coordinates":
            $allLocationCount++;
            $locationIndex = $allLocationCount;
            $link = "<button id='location_b_$allLocationCount' class='location_button'>" . _("Click to see location") . " $allLocationCount</button>";
            $retval .= "<input readonly style='display:none' type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-text' value='$prefill'>$link";
            break;
        case "file":
            $retval .= "<input readonly type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-string' style='display:none' value='" . urlencode($content) . "'>";
            switch ($value) {
                case "eap:ca_file":
                    $retval .= previewCAinHTML($content);
                    break;
                case "general:logo_file":
                case "fed:logo_file":
                    $retval .= previewImageinHTML($content);
                    break;
                case "support:info_file":
                    $retval .= previewInfoFileinHTML($content);
                    break;
                default:
                    $retval .= _("file content");
            }
            break;
        case "string":
            $retval .= "<strong>$content</strong><input type='hidden' name='value[S$rowid-0]' id='S" . $rowid . "-input-string' value=\"" . htmlspecialchars($content) . "\" style='display:block'>";
            break;
        case "integer":
            $retval .= "<strong>$content</strong><input type='hidden' name='value[S$rowid-4]' id='S" . $rowid . "-input-integer' value=\"" . htmlspecialchars($content) . "\" style='display:block'>";
            break;
        case "text":
            $retval .= "<strong>$content</strong><input type='hidden' name='value[S$rowid-1]' id='S" . $rowid . "-input-text' value=\"" . htmlspecialchars($content) . "\" style='display:block'>";
            break;
        case "boolean":
            $displayOption = _("off");
            if ($content == "on") {
                /// Device assessment is "on"
                $displayOption = _("on");
            }
            $retval .= "<strong>$displayOption</strong><input type='hidden' name='value[S$rowid-3]' id='S" . $rowid . "-input-boolean' value='$content' style='display:block'>";
            break;
        default:
            // this should never happen!
            throw new Exception("Internal Error: unknown attribute type $listtype!");
    }
    $retval .= "</td>";
    return $retval;
}

function optiontext($defaultselect, $list, $prefill = 0) {
    $allLocationCount = 0;
    $locationIndex = 0;
    $rowid = mt_rand();

    $retval = "<tr id='option-S$rowid' style='vertical-align:top'>";

    if (!$prefill) {
        $retval .= noPrefillText($rowid, $defaultselect);
    }

    if ($prefill) {
        prefillText($rowid, $prefill, $list, $locationIndex, $allLocationCount);
    }
    $retval .= "

       <td>
          <button type='button' class='delete' onclick='deleteOption(" . $locationIndex . ",\"option-S" . $rowid . "\")'>-</button>
       </td>
    </tr>";
    return $retval;
}
