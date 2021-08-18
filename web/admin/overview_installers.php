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

require_once dirname(dirname(__DIR__)) . "/config/_config.php";

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();

echo $deco->defaultPagePrelude(_("Device Compatibility matrix"));
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    echo $deco->productheader("ADMIN-IDP");
    $my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);
    $my_profile = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("Installer fine-tuning can only be called for RADIUS profiles!");
    }
    $inst_name = $my_inst->name;
    $profile_name = $my_profile->name;


    $preflist = $my_profile->getEapMethodsinOrderOfPreference();
    ?>
    <h1><?php $tablecaption = sprintf(_("Device compatiblity matrix for %s of %s "), $profile_name, $inst_name); echo $tablecaption;?></h1>
    <table class="compatmatrix">
        <caption><?php echo $tablecaption;?></caption>
        <tr>
            <th scope='col'></th>
            <th scope='col'><?php echo _("Device"); ?></th>

            <?php
            foreach ($preflist as $method) {
                $escapedMethod = $method->getIntegerRep();
                echo "<th  scope='col' style='min-width:200px'>" . $method->getPrintableRep() . "<br/>
                        <form method='post' action='inc/toggleRedirect.inc.php?inst_id=$my_inst->identifier&amp;profile_id=$my_profile->identifier' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                        <input type='hidden' name='eaptype' value='$escapedMethod'>
                        <button class='redirect' type='submit'>" . _("EAP-Type-specific options...") . "</button>
                        </form></th>";
            }
            ?>

        </tr>
        <?php
        // okay, input is valid. Now create a table: columns are the EAP types supported in the profile,
        // rows are known devices

        $distinctFootnotes = [];
        $num_footnotes = 0;

        foreach (\devices\Devices::listDevices() as $index => $description) {

            echo "<tr>";
            echo "<td align='center'><img src='../resources/images/vendorlogo/" . $description['group'] . ".png' alt='logo'></td><td>" . $description['display'] . "<br/>
                        <form method='post' action='inc/toggleRedirect.inc.php?inst_id=$my_inst->identifier&amp;profile_id=$my_profile->identifier' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                        <input type='hidden' name='device' value='$index'>
                        <button class='redirect' type='submit'>" . _("Device-specific options...") . "</button>
                        </form>
                        </td>";
            $factory = new \core\DeviceFactory($index);                       
            $defaultisset = FALSE;
            foreach ($preflist as $method) {
                $footnotesForDevEapCombo = [];
                $display_footnote = FALSE;
                $langObject = new \core\common\Language();
                $downloadform = "<form action='" . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . "/user/API.php?action=downloadInstaller&profile=$my_profile->identifier&lang=" . $langObject->getLang() . "' method='post' accept-charset='UTF-8'>
                                       <input type='hidden' name='device' value='$index'/>
                                       <input type='hidden' name='generatedfor'  value='admin'/>
                                       <button class='download'>" . sprintf(_("%s<br/>Installer"), config\ConfAssistant::CONSORTIUM['display_name']) . "</button>
                                     ";
                if (sizeof($my_profile->getAttributes("media:openroaming")) > 0 && isset($factory->device->options['hs20']) && $factory->device->options['hs20'] == 1) {
                $downloadform .= "<form action='" . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . "/user/API.php?action=downloadInstaller&profile=$my_profile->identifier&openroaming=1&lang=" . $langObject->getLang() . "' method='post' accept-charset='UTF-8'>
                                       <input type='hidden' name='device' value='$index'/>
                                       <input type='hidden' name='generatedfor'  value='admin'/>
                                       <button class='download'>" . sprintf(_("%s + OpenRoaming<br/>Installer"), config\ConfAssistant::CONSORTIUM['display_name']) . "</button>
                                     ";
                }
                
                // first of all: if redirected, indicate by color

                $redirectAttribs = [];
                foreach ($my_profile->getAttributes("device-specific:redirect") as $oneRedirect) { //device-specific attributes have the array key 'device' set only if they pertain to an individual device, not if they apply profile-wide
                    if (isset($oneRedirect['device']) && $oneRedirect['device'] == $index) {
                        $redirectAttribs[] = $oneRedirect;
                    }
                }

                if (count($redirectAttribs) > 0) {
                    echo "<td class='compat_redirected'>";
                    if (in_array($method->getArrayRep(), $factory->device->supportedEapMethods) && $my_profile->isEapTypeDefinitionComplete($method) === true && ($method->getArrayRep() === $preflist[0] || $defaultisset === FALSE)) {
                        echo "$downloadform</form>";
                        $defaultisset = TRUE;
                    }
                    echo "</td>";
                } elseif (in_array($method->getArrayRep(), $factory->device->supportedEapMethods)) {
                    if ($my_profile->isEapTypeDefinitionComplete($method) !== true) {
                        echo "<td class='compat_incomplete'></td>";
                    } elseif ($method->getArrayRep() === $preflist[0] || $defaultisset === FALSE) {
                        // see if we want to add a footnote - iterate through all available attributes and see if we have something in the buffer
                        $optionlist = core\Options::instance();
                        foreach ($optionlist->availableOptions() as $oneOption) {
                            $value = $my_profile->getAttributes($oneOption)[0] ?? FALSE;
                            if (
                                    // next line: we DO want loose comparison; no matter if "" or FALSE or a 0 - if something's not set, don't add the footnote
                                    // look for the attribute either in the Profile properties or in the device properties
                                    ($value != FALSE || isset($factory->device->attributes[$oneOption])) 
                                    && isset($factory->device->specialities[$oneOption])
                                ) {
                                if (isset($factory->device->specialities[$oneOption][serialize($method->getArrayRep())])) {
                                    $footnotesForDevEapCombo[] = $factory->device->specialities[$oneOption][serialize($method->getArrayRep())];
                                } else if (!is_array($factory->device->specialities[$oneOption])) {
                                    $footnotesForDevEapCombo[] = $factory->device->specialities[$oneOption];
                                }
                            }
                        }
                        echo "<td class='compat_default'>$downloadform";
                        if (count($footnotesForDevEapCombo) > 0) {
                            foreach ($footnotesForDevEapCombo as $oneFootnote) {
                                // if that particular text is not already a numbered footnote, assign it a number
                                if (array_search($oneFootnote, $distinctFootnotes) === FALSE) {
                                    $num_footnotes = $num_footnotes + 1;
                                    $distinctFootnotes[$num_footnotes] = $oneFootnote;
                                }
                                $numberToDisplay = array_keys($distinctFootnotes, $oneFootnote);
                                echo "(".$numberToDisplay[0].")";
                            }
                            
                        }
                        echo "</form></td>";
                        $defaultisset = TRUE;
                    } else {
                        echo "<td class='compat_secondary'></td>";
                    }
                } else {
                    echo "<td class='compat_unsupported'></td>";
                }
            }
            echo "</tr>";
        }
        ?>
    </table>
    <p><strong><?php $tablecaption2 = _("Legend:"); echo $tablecaption2; ?></strong></p>
    <table class="compatmatrix">
        <caption><?php echo $tablecaption2;?></caption>
        <tr><th scope="col"><?php echo _("Colour");?></th><th scope='col'><?php echo _("Meaning");?></th></tr>
        <tr><td class="compat_redirected">&nbsp;&nbsp;&nbsp;</td> <td><?php echo _("redirection is set"); ?></td></tr>
        <tr><td class="compat_default">&nbsp;&nbsp;&nbsp;</td>    <td><?php echo _("will be offered on download site"); ?></td></tr>
        <tr><td class="compat_secondary">&nbsp;&nbsp;&nbsp;</td>  <td><?php echo _("configured, but not preferred EAP type"); ?></td></tr>
        <tr><td class="compat_incomplete">&nbsp;&nbsp;&nbsp;</td> <td><?php echo _("incomplete configuration"); ?></td></tr>
        <tr><td class="compat_unsupported">&nbsp;&nbsp;&nbsp;</td><td><?php echo _("not supported by CAT"); ?></td></tr>
    </table>
    <?php
    if (count($distinctFootnotes)) {
        echo "<p><strong>" . _("Footnotes:") . "</strong></p><table>";
        foreach ($distinctFootnotes as $number => $text) {
            echo "<tr><td>($number) - </td><td>$text</td></tr>";
        }
        echo "</table>";
    }
    ?>
    <form method='post' action='overview_org.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
        <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_CLOSE; ?>'>
            <?php echo _("Return to dashboard"); ?>
        </button>
    </form>
    <?php
    echo $deco->footer();
    