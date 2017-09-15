<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(__DIR__)) . "/config/_config.php");

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
    $my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);
    $my_profile = $validator->Profile($_GET['profile_id'], $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("Installer fine-tuning can only be called for RADIUS profiles!");
    }
    $inst_name = $my_inst->name;
    $profile_name = $my_profile->name;


    $preflist = $my_profile->getEapMethodsinOrderOfPreference();
    ?>
    <h1><?php printf(_("Device compatiblity matrix for %s of %s "), $profile_name, $inst_name); ?></h1>
    <table class="compatmatrix">
        <tr>
            <th></th>
            <th><?php echo _("Device"); ?></th>

            <?php            
            foreach ($preflist as $method) {
                $escapedMethod = $method->getIntegerRep();
                echo "<th style='min-width:200px'>" . $method->getPrintableRep() . "<br/>
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

        $devices = \devices\Devices::listDevices();
        $footnotes = [];
        $num_footnotes = 0;

        foreach ($devices as $index => $description) {

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
                $display_footnote = FALSE;
                $langObject = new \core\common\Language();
                $downloadform = "<form action='" . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . "/user/API.php?action=downloadInstaller&profile=$my_profile->identifier&lang=" . $langObject->getLang() . "' method='post' accept-charset='UTF-8'>
                                       <input type='hidden' name='device' value='$index'/>
                                       <input type='hidden' name='generatedfor'  value='admin'/>
                                       <button class='download'>" . _("Download") . "</button>
                                     ";
                // first of all: if redirected, indicate by color

                $redirectAttribs = [];
                foreach ($my_profile->getAttributes("device-specific:redirect") as $oneRedirect) { //device-specific attributes always have the array key 'device' set
                    if ($oneRedirect['device'] == $index) {
                        $redirectAttribs[] = $oneRedirect;
                    }
                }

                if (count($redirectAttribs) > 0) {
                    echo "<td class='compat_redirected'>";
                    if (in_array($method->getArrayRep(), $factory->device->supportedEapMethods) && $my_profile->isEapTypeDefinitionComplete($method->getArrayRep()) === true && ($method->getArrayRep() === $preflist[0] || $defaultisset === FALSE)) {
                        echo "$downloadform</form>";
                        $defaultisset = TRUE;
                    }
                    echo "</td>";
                } else
                if (in_array($method->getArrayRep(), $factory->device->supportedEapMethods)) {
                    if ($my_profile->isEapTypeDefinitionComplete($method) !== true) {
                        echo "<td class='compat_incomplete'></td>";
                    } elseif ($method->getArrayRep() === $preflist[0] || $defaultisset === FALSE) {
                        // see if we want to add a footnote: anon_id
                        $anon = $my_profile->getAttributes("internal:use_anon_outer")[0]["value"];
                        if ($anon !== "" && isset($factory->device->specialities['anon_id'])) {
                            if (isset($factory->device->specialities['anon_id'][serialize($method->getArrayRep())])) {
                                $footnotetext = $factory->device->specialities['anon_id'][serialize($method->getArrayRep())];
                                $display_footnote = TRUE;
                            } else if (!is_array($factory->device->specialities['anon_id'])) {
                                $footnotetext = $factory->device->specialities['anon_id'];
                                $display_footnote = TRUE;
                            }
                        }
                        echo "<td class='compat_default'>$downloadform";
                        if ($display_footnote) {
                            $isfootnoteknown = array_search($footnotetext, $footnotes);
                            if ($isfootnoteknown !== FALSE) {
                                $thefootnote = $isfootnoteknown;
                            } else {
                                $num_footnotes = $num_footnotes + 1;
                                $thefootnote = $num_footnotes;
                                $footnotes[$num_footnotes] = $footnotetext;
                            }
                            echo "($thefootnote)";
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
    <p><strong><?php echo _("Legend:"); ?></strong></p>
    <table class="compatmatrix">
        <tr><td class="compat_redirected">&nbsp;&nbsp;&nbsp;</td> <td><?php echo _("redirection is set"); ?></td></tr>
        <tr><td class="compat_default">&nbsp;&nbsp;&nbsp;</td>    <td><?php echo _("will be offered on download site"); ?></td></tr>
        <tr><td class="compat_secondary">&nbsp;&nbsp;&nbsp;</td>  <td><?php echo _("configured, but not preferred EAP type"); ?></td></tr>
        <tr><td class="compat_incomplete">&nbsp;&nbsp;&nbsp;</td> <td><?php echo _("incomplete configuration"); ?></td></tr>
        <tr><td class="compat_unsupported">&nbsp;&nbsp;&nbsp;</td><td><?php echo _("not supported by CAT"); ?></td></tr>
    </table>
    <?php
    if (count($footnotes)) {
        echo "<p><strong>" . _("Footnotes:") . "</strong></p><table>";
        foreach ($footnotes as $number => $text) {
            echo "<tr><td>($number) - </td><td>$text</td></tr>";
        }
        echo "</table>";
    }
    ?>
    <form method='post' action='overview_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
        <button type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_CLOSE; ?>'>
            <?php echo _("Return to dashboard"); ?>
        </button>
    </form>
    <?php
    echo $deco->footer();
    