<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("IdP.php");
require_once("Profile.php");
require_once("DeviceFactory.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");

require_once("devices/devices.php");

$cat = defaultPagePrelude(_("Device Compatibility matrix"));
?>
<script src="js/option_expand.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    productheader("ADMIN-IDP", $cat->lang_index);
    $my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
    $my_profile = valid_Profile($_GET['profile_id'], $my_inst->identifier);
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
                $escaped_method = htmlspecialchars(serialize($method));
                echo "<th style='min-width:200px'>" . display_name($method) . "<br/>
                        <form method='post' action='inc/toggleRedirect.inc.php?inst_id=$my_inst->identifier&amp;profile_id=$my_profile->identifier' onsubmit='popupRedirectWindow(this); return false;'>
                        <input type='hidden' name='eaptype' value='$escaped_method'>
                        <button class='redirect' type='submit'>" . _("EAP-Type-specific options...") . "</button>
                        </form></th>";
            }
            ?>

        </tr>
        <?php
        // okay, input is valid. Now create a table: columns are the EAP types supported in the profile,
        // rows are known devices

        $devices = Devices::listDevices();
        $footnotes = array();
        $num_footnotes = 0;

        foreach ($devices as $index => $description) {

            echo "<tr>";
            echo "<td align='center'><img src='../resources/images/vendorlogo/" . $description['group'] . ".png' alt='logo'></td><td>" . $description['display'] . "<br/>
                        <form method='post' action='inc/toggleRedirect.inc.php?inst_id=$my_inst->identifier&amp;profile_id=$my_profile->identifier' onsubmit='popupRedirectWindow(this); return false;'>
                        <input type='hidden' name='device' value='$index'>
                        <button class='redirect' type='submit'>" . _("Device-specific options...") . "</button>
                        </form>
                        </td>";
            $factory = new DeviceFactory($index);
            $defaultisset = FALSE;
            foreach ($preflist as $method) {
                $display_footnote = FALSE;
//                $downloadform = "<form action='" . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . "/download.php?profile=$my_profile->identifier&idp=$my_profile->institution&lang=" . CAT::$lang_index . "' method='post'>
                $downloadform = "<form action='" . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . "/user/API.php?action=downloadInstaller&profile=$my_profile->identifier&lang=" . CAT::$lang_index . "' method='post'>
                                       <input type='hidden' name='id' value='$index'/>
                                       <input type='hidden' name='generatedfor'  value='admin'/>
                                       <button class='download'>" . _("Download") . "</button>
                                     ";
                // first of all: if redirected, indicate by color

                $redirect_attribs = $my_profile->getAttributes("device-specific:redirect", 0, $index);

                if (count($redirect_attribs) > 0) {
                    echo "<td class='compat_redirected'>";
                    if (in_array($method, $factory->device->supportedEapMethods) && $my_profile->isEapTypeDefinitionComplete($method) === true && ($method === $preflist[0] || $defaultisset == FALSE)) {
                        echo "$downloadform</form>";
                        $defaultisset = TRUE;
                    }
                    echo "</td>";
                } else
                if (in_array($method, $factory->device->supportedEapMethods)) {
                    if ($my_profile->isEapTypeDefinitionComplete($method) !== true) {
                        echo "<td class='compat_incomplete'></td>";
                    } elseif ($method === $preflist[0] || $defaultisset == FALSE) {
                        // see if we want to add a footnote: anon_id
                        $anon = $my_profile->getAttributes("internal:use_anon_outer");
                        $anon = $anon[0]['value'];
                        if ( $anon !== "" && isset($factory->device->specialities['anon_id'])) {
                            if (isset($factory->device->specialities['anon_id'][serialize($method)])) {
                                $footnotetext = $factory->device->specialities['anon_id'][serialize($method)];
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
                }
                else
                    echo "<td class='compat_unsupported'></td>";
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
        foreach ($footnotes as $number => $text)
            echo "<tr><td>($number) - </td><td>$text</td></tr>";
        echo "</table>";
    }
    ?>
    <form method='post' action='overview_idp.php?inst_id=<?php echo $my_inst->identifier; ?>'>
        <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE;?>'>
            <?php echo _("Return to dashboard"); ?>
        </button>
    </form>
    <?php
    footer();
    ?>
