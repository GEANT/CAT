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

require_once dirname(dirname(__DIR__)) . '/config/_config.php';

$uiElements = new web\lib\admin\UIElements();

$security = 0;
if (!in_array("I do not care about security!", \config\Master::SUPERADMINS)) {
    $auth = new \web\lib\admin\Authentication();
    $auth->authenticate();
    $security = 1;
    $user = new \core\User($_SESSION['user']);
    if (!in_array($user->userName, \config\Master::SUPERADMINS)) {
        header("Location: overview_user.php");
        exit;
    }
}

$deco = new \web\lib\admin\PageDecoration();

echo $deco->pageheader("By. Your. Command.", "SUPERADMIN", FALSE); // no auth in pageheader; we did our own before

$dbHandle = \core\DBConnection::handle("FRONTEND");
?>
<h1>By. Your. Command.</h1>
<form action="112365365321.php" method="POST" accept-charset="UTF-8">
    <fieldset class="option_container">
        <legend>
            <strong>Configuration Check</strong>
        </legend>
        <?php
        if (isset($_POST['admin_action']) && ($_POST['admin_action'] == web\lib\common\FormElements::BUTTON_SANITY_TESTS)) {
            include "sanity_tests.php";
        }
        ?>
        <button type="submit" name="admin_action" value="<?php echo web\lib\common\FormElements::BUTTON_SANITY_TESTS; ?>">Run configuration check</button>
    </fieldset>
    <?php
    if ($security == 0) {
        print "<h2 style='color: red'>In order to do more you need to configure the SUPERADMIN section  in config/Master.php and login as one.</h2>";
    } else {
        ?>
        <fieldset class="option_container">
            <legend>
                <strong>Administrative actions</strong>
            </legend>
            <?php
            if (isset($_POST['admin_action'])) {
                switch ($_POST['admin_action']) {
                    case web\lib\common\FormElements::BUTTON_PURGECACHE:
                        // delete all rows which were already marked as obsolete in a previous run
                        $deleteStale = $dbHandle->exec("DELETE FROM downloads WHERE download_path = NULL");
                        // and now obsolete those which were previously possibly still valid
                        $result = $dbHandle->exec("UPDATE downloads SET download_path = NULL");
                        // we do NOT break here - after the DB deletion comes the normal
                        // filesystem cleanup
                    case web\lib\common\FormElements::BUTTON_DELETE:
                        $i = web\lib\admin\Maintenance::deleteObsoleteTempDirs();
                        echo "<div class='ca-summary'><table>" . $uiElements->boxRemark(sprintf("Deleted %d cache directories.", $i), "Cache deleted") . "</table></div>";
                        break;
                    default:
                        break;
                }
            }
            ?>
            <p>Use this button to delete old temporary directories inside 'downloads'. Cached installers which are still valid will not be deleted.</p>
            <button type="submit" name="admin_action" value="<?php echo web\lib\common\FormElements::BUTTON_DELETE; ?>">Delete OBSOLETE download directories</button>
            <p>Use this button to delete all directories inside 'downloads', including valid cached installers. Usually, this is only necessary when updating the product or one of the device modules.</p>
            <button type="submit" name="admin_action" value="<?php echo web\lib\common\FormElements::BUTTON_PURGECACHE; ?>">Delete ALL download directories</button>
        </fieldset>

        <fieldset class="option_container">
            <legend>
                <strong>Registered Identity Providers</strong>
            </legend>
            <table>
                <caption>Global IdP Statistics</caption>
                <tr>
                    <th scope="col">Total</th>
                    <th scope="col">Configured</th>
                    <th scope="col">Public Download</th>
                </tr>
                <?php
                $cat = new \core\CAT();
                ?>
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
                <caption>Global Download Statistics</caption>
                <tr>
                    <th scope="col">Device</th>
                    <th scope="col">Admin Downloads</th>
                    <th scope="col">User Downloads (classic)</th>
                    <th scope="col">User Downloads (<?php echo \core\ProfileSilverbullet::PRODUCTNAME; ?>)</th>
                    <th scope="col">User Downloads (total)</th>
                </tr>
                <?php
                $gross_admin = 0;
                $gross_user = 0;
                $gross_silverbullet = 0;
                foreach (\devices\Devices::listDevices() as $index => $device_array) {
                    echo "<tr>";
                    $admin_query = $dbHandle->exec("SELECT SUM(downloads_admin) AS admin, SUM(downloads_user) AS user, SUM(downloads_silverbullet) as silverbullet FROM downloads WHERE device_id = '$index'");
                    // SELECT -> mysqli_result, not boolean
                    while ($a = mysqli_fetch_object(/** @scrutinizer ignore-type */ $admin_query)) {
                        echo "<td>" . $device_array['display'] . "</td><td>" . $a->admin . "</td><td>" . $a->user . "</td><td>" . $a->silverbullet . "</td><td>" . sprintf("%s", $a->user + $a->silverbullet) . "</td>";
                        $gross_admin = $gross_admin + $a->admin;
                        $gross_user = $gross_user + $a->user;
                        $gross_silverbullet = $gross_silverbullet + $a->silverbullet;
                    }
                    echo "</tr>";
                }
                ?>
                <tr>
                    <td><strong>TOTAL</strong></td>
                    <td><strong><?php echo $gross_admin; ?></strong></td>
                    <td><strong><?php echo $gross_user; ?></strong></td>
                    <td><strong><?php echo $gross_silverbullet; ?></strong></td>
                    <td><strong><?php echo $gross_user + $gross_silverbullet; ?></strong></td>
                </tr>
            </table>
        </fieldset>
    <?php } ?>
</form>
<?php
echo $deco->footer();
