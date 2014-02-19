<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Options.php");
require_once("common.inc.php");

function add_option($class, $prepopulate = 0) { // no GET class ? we've been called directly:
    // this can mean either a new object (list all options with empty values)
    // or that an object is to be edited. In that case, $prepopulated has to
    // contain the array of existing variables
    // we expect the variable $class to contain the class of options
    // print_r($prepopulate);
    $optioninfo = Options::instance();
    if (is_array($prepopulate) && count($prepopulate) > 0) { // editing... fill with values
        $a = 0;
        foreach ($prepopulate as $option)
            if (preg_match("/$class:/", $option['name']) && !preg_match("/(profile:QR-user|user:fedadmin)/", $option['name'])) {
                $optiontypearray = $optioninfo->optionType($option['name']);
                debug(5, "About to execute optiontext with PREFILL!\n");
                echo optiontext($a, array($option['name']), ($optiontypearray["type"] == "file" ? 'ROWID-' . $option['level'] . '-' . $option['row'] : $option['value']));
            }
    } else { // new: add empty list
        $list = $optioninfo->availableOptions($class);
        if ($class == "general") {
            $blacklist_item = array_search("general:geo_coordinates", $list);
            if ($blacklist_item !== FALSE) {
                unset($list[$blacklist_item]);
                $list = array_values($list);
            }
        } else if ($class == "profile") {
            $blacklist_item = array_search("profile:QR-user", $list);
            if ($blacklist_item !== FALSE) {
                unset($list[$blacklist_item]);
                $list = array_values($list);
            }
        } else if ($class == "user") {
            $blacklist_item = array_search("user:fedadmin", $list);
            if ($blacklist_item !== FALSE) {
                unset($list[$blacklist_item]);
                $list = array_values($list);
            }
        }
        /* echo "<pre>";
        print_r($list);
        echo "</pre>"; */
        // add as many options as there are different option types

        foreach (array_keys($list) as $key)
            echo optiontext($key, $list);
    }
}

function optiontext($defaultselect, $list, $prefill = 0) {
    global $global_location_count;
    $location_index = 0;
    $rowid = mt_rand();
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
                             }
                               if (/#string#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"block\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"none\";
                               }
                                  if (/#text#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"block\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"none\";
                               }

                               if (/#boolean#/.test(document.getElementById(\"option-S" . $rowid . "-select\").value)) {
                                  document.getElementById(\"S$rowid-input-file\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-text\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-string\").style.display = \"none\";
                                  document.getElementById(\"S$rowid-input-boolean\").style.display = \"block\";
                               }
    '";

    $retval = "<tr id='option-S$rowid' style='vertical-align:top'>";

    if (!$prefill) {
        $retval .= "<td><select id='option-S$rowid-select' name='option[S$rowid]' $jsmagic>";
        $a = 0;
        foreach ($list as $key => $value) {
            $listtype = $optioninfo->optionType($value);
            $retval .="<option id='option-S$rowid-v-$value' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ";
            if ($a == $defaultselect) {
                $retval .= "selected='selected'";
                $activelisttype = $listtype;
            }
            $retval .=">" . display_name($value). "</option>";
            $a++;
        }
        $retval .="</select></td>";
        $retval .="<td>
          <select style='display:" . ($activelisttype["flag"] == "ML" ? "block" : "none") . "' name='value[S$rowid-lang]' id='S" . $rowid . "-input-langselect'>
            <option value='' name='select_language' selected>" . _("select language") . "</option>
            <option value='C' name='all_languages'>" . _("default/other languages") . "</option>";
        foreach (Config::$LANGUAGES as $langindex => $possible_lang) {
            $thislang = $possible_lang['display'];
            $retval .= "<option value='$langindex' name='$langindex'>$thislang</option>";
        }
        $retval .= "</select></td><td>
            <input type='text'     style='display:" . ($activelisttype["type"] == "string" ? "block" : "none") . "' name='value[S$rowid-0]'  id='S" . $rowid . "-input-string'>
            <textarea cols='30' rows='3'     style='display:" . ($activelisttype["type"] == "text" ? "block" : "none") . "' name='value[S$rowid-1]'  id='S" . $rowid . "-input-text'></textarea>
            <input type='file'     style='display:" . ($activelisttype["type"] == "file" ? "block" : "none") . "' name='value[S$rowid-2]'  id='S" . $rowid . "-input-file' size='10'>
            <input type='checkbox' style='display:" . ($activelisttype["type"] == "boolean" ? "block" : "none") . "' name='value[S$rowid-3]'  id='S" . $rowid . "-input-boolean'>";
        $retval .= "</td>";
    }

    if ($prefill) {
        debug(5, "Executed with PREFILL!\n");
        $retval .= "<td>";
        foreach ($list as $key => $value) {
            $listtype = $optioninfo->optionType($value);
            $retval .= display_name($value);
            $retval .= tooltip($value);
            $retval .= "<input type='hidden' id='option-S$rowid-select' name='option[S$rowid]' value='$value#" . $listtype["type"] . "#" . $listtype["flag"] . "#' ></td>";
        }

// language tag if any
        $content;
        $retval .= "<td>";
        if ($listtype["flag"] == "ML") {

            if (preg_match('/^ROWID/', $prefill) == 0) { // this is direct content, not referral from DB
                $taggedarray = unserialize($prefill);
                if ($taggedarray === FALSE) {
                    echo "INTERNAL ERROR: unable to unserialize multilang attribute!<p>$prefill";
                    // exit(1);
                }
                $content = $taggedarray["content"];
            } else {
                $taggedarray = unserialize(getBlobFromDB($prefill));
                $content = $prefill;
            }
            $language;
            if ($taggedarray['lang'] == 'C')
                $language = _("(default/other languages)");
            else
                $language = "(" . strtoupper($taggedarray['lang']) . ")";
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
                $global_location_count++;
                $location_index = $global_location_count;
                $link = "<button id='location_b_$global_location_count' class='location_button'>" . _("Click to see location") . " $global_location_count</button>";
                $retval .="<input readonly style='display:none' type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-text' value='$prefill'>$link";
                break;
            case "file":
                $retval .= "<input readonly type='text' name='value[S$rowid-1]' id='S" . $rowid . "-input-string' style='display:none' value='" . urlencode($content) . "'>";
                switch ($value) {
                    case "eap:ca_file":
                        $retval .= previewCAinHTML($content);
                        break;
                    case "general:logo_file":
                        $retval .= previewImageinHTML($content);
                        break;
                    case "support:info_file":
                        $retval .= previewInfoFileinHTML($content);
                        break;
                    default:
                        $retval .= _("file content");
                };
                break;
            case "string":
                $retval .= "<strong>$content</strong><input type='hidden' name='value[S$rowid-0]' id='S" . $rowid . "-input-string' value=\"".htmlspecialchars($content)."\" style='display:block'>";
                break;
            case "text":
                $retval .= "<strong>$content</strong><input type='hidden' name='value[S$rowid-1]' id='S" . $rowid . "-input-text' value=\"".htmlspecialchars($content)."\" style='display:block'>";
                break;
            case "boolean":
                if ($content == "on")
                /// Device assessment is "on"
                    $display_option = _("on");
                else
                /// Device assessment is "off"
                    $display_option = _("off");
                $retval .= "<strong>$display_option</strong><input type='hidden' name='value[S$rowid-3]' id='S" . $rowid . "-input-boolean' value='$content' style='display:block'>";
                break;
            default:
// this should never happen!
                echo "Internal Error: unknown attribute type $listtype!";
                exit(1);
                break;
        };
        $retval .= "</td>";
    }
    $retval .="

       <td>
          <button type='button' class='delete' onclick='deleteOption(" . $location_index . ",\"option-S" . $rowid . "\")'>-</button>
       </td>
    </tr>";
    return $retval;
}

?>
