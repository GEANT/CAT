<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
/**
 * The $Tests array lists the config tests to be run
 */
$Tests = [
'ssp',
'security',
'php',
'phpModules',
'openssl',
'makensis',
'makensis=>NSISmodules',
'makensis=>NSIS_GetVersion',
'zip',
'eapol_test',
'directories',
'locales',
'defaults',
'databases',
'device_cache',
'mailer',
];

ini_set('display_errors', '0');
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("CAT.php");
require_once("User.php");
require_once("Federation.php");
require_once('devices/devices.php');
require_once("DBConnection.php");
require_once("inc/common.inc.php");

if (!in_array("I do not care about security!", Config::$SUPERADMINS)) {
    require_once("inc/auth.inc.php");
    authenticate();
    $no_security = 0;
} else {
   $no_security = 1;
}
$user = new User((!in_array("I do not care about security!", Config::$SUPERADMINS) ? $_SESSION['user'] : "UNIDENTIFIED"));

if (!in_array($user->identifier, Config::$SUPERADMINS) && !in_array("I do not care about security!", Config::$SUPERADMINS))
    header("Location: overview_user.php");

$cat = pageheader("By. Your. Command.","SUPERADMIN", FALSE); // no auth in pageheader; we did our own before

?>
    <h1>By. Your. Command.</h1>
  <form action="112365365321.php" method="POST" accept-charset="UTF-8">
        <fieldset class="option_container">
            <legend>
                <strong>Configuration Check</strong>
            </legend>
<?php
            if (isset($_POST['admin_action'])) {
               if($_POST['admin_action'] == BUTTON_SANITY_TESTS)
                        include("sanity_tests.php");
            }
?>
<button type="submit" name="admin_action" value="<?php echo BUTTON_SANITY_TESTS; ?>">Run configuration check</button>
</fieldset>
<?php if($no_security) {
     print "<h2 style='color: red'>In order to do more you need to configure the SUPERADMIN section  in config/config.php and login as one.</h2>";

   } else {
?>
        <fieldset class="option_container">
            <legend>
                <strong>Administrative actions</strong>
            </legend>
            <?php
            if (isset($_POST['admin_action']))
                switch ($_POST['admin_action']) {
                    case BUTTON_PURGECACHE:
                        $result = DBConnection::exec("INST", "UPDATE downloads SET download_path = NULL");
                    // we do NOT break here - after the DB deletion comes the normal
                    // filesystem cleanup
                    case BUTTON_DELETE:
                        $downloads = dirname(dirname(dirname(__FILE__))) . "/var/installer_cache";
                        $tm = time();
                        $i = 0;

                        $Cache = [];
                        $result = DBConnection::exec("INST", "SELECT download_path FROM downloads WHERE download_path IS NOT NULL");
                        while ($r = mysqli_fetch_row($result)) {
                            $e = explode('/', $r[0]);
                            $Cache[$e[count($e) - 2]] = 1;
                        }

                        if ($handle = opendir($downloads)) {

                            /* This is the correct way to loop over the directory. */
                            while (false !== ($entry = readdir($handle))) {
                                if ($entry === '.' || $entry === '..')
                                    continue;
                                $ftime = $tm - filemtime($downloads . '/' . $entry);
                                if ($ftime < 3600)
                                    continue;
                                if (isset($Cache[$entry])) {
//          print "Keep: $entry\n";
                                    continue;
                                }
                                rrmdir($downloads . '/' . $entry);
                                $i++;
                            }

                            closedir($handle);
                        }
                        echo "<div class='ca-summary'><table>" . UI_remark(sprintf("Deleted %d cache directories.", $i), "Cache deleted") . "</table></div>";
                        break;

                    default:
                        break;
                }
            ?>
            <p>Use this button to delete old temporary directories inside 'downloads'. Cached installers which are still valid will not be deleted.</p>
            <button type="submit" name="admin_action" value="<?php echo BUTTON_DELETE; ?>">Delete OBSOLETE download directories</button>
            <p>Use this button to delete all directories inside 'downloads', including valid cached installers. Usually, this is only necessary when updating the product or one of the device modules.</p>
            <button type="submit" name="admin_action" value="<?php echo BUTTON_PURGECACHE; ?>">Delete ALL download directories</button>
        </fieldset>

       <fieldset class="option_container">
            <legend>
                <strong>Registered Identity Providers</strong>
            </legend>
            <table>
                <tr>
                    <th>Total</th>
                    <th>Configured</th>
                    <th>Public Download</th>
                </tr>
                <tr>
                    <td>
                        <?php
                        echo $cat->totalIdPs("ALL");
                        ?>
                    </td>
                    <td>
                        <?php
                        echo $cat->totalIdPs("VALIDPROFILE");
                        ?>
                    </td>
                    <td>
                        <?php
                        echo $cat->totalIdPs("PUBLICPROFILE");
                        ?>
                    </td>
                </tr>
            </table>
        </fieldset>
       <fieldset class="option_container">
            <legend>
                <strong>Total Downloads</strong>
            </legend>
            <table>
                <tr>
                    <th>Device</th>
                    <th>Admin Downloads</th>
                    <th>User Downloads</th>
                </tr>
                <?php
                $gross_admin = 0;
                $gross_user = 0;
                foreach (Devices::listDevices() as $index => $device_array) {
                    echo "<tr>";
                    $admin_query = DBConnection::exec("INST", "SELECT SUM(downloads_admin) AS admin, SUM(downloads_user) AS user FROM downloads WHERE device_id = '$index'");
                    while ($a = mysqli_fetch_object($admin_query)) {
                        echo "<td>" . $device_array['display'] . "</td><td>" . $a->admin . "</td><td>" . $a->user . "</td>";
                        $gross_admin = $gross_admin + $a->admin;
                        $gross_user = $gross_user + $a->user;
                    }
                    echo "</tr>";
                }
                ?>
                <tr>
                    <td><strong>TOTAL</strong></td>
                    <td><strong><?php echo $gross_admin; ?></strong></td>
                    <td><strong><?php echo $gross_user; ?></strong></td>
                </tr>
            </table>
        </fieldset>
<?php } ?>
    </form>
    <?php
    footer();
    ?>

