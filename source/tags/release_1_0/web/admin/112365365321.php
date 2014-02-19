<?php
/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once("inc/admin_header.php");
require_once("CAT.php");
require_once("User.php");
require_once("Federation.php");
require_once('devices/devices.php');
require_once("DBConnection.php");
require_once("inc/auth.inc.php");
require_once("inc/common.inc.php");

function rrmdir($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file))
            rrmdir($file);
        else
            unlink($file);
    }
    rmdir($dir);
}

$user = new User($_SESSION['user']);

/* echo $user->identifier;
  echo "<pre>";
  print_r(Config::$SUPERADMINS);
  echo "</pre>";
 */
if (!in_array($user->identifier, Config::$SUPERADMINS) && !in_array("I do not care about security!", Config::$SUPERADMINS))
    header("Location: overview_user.php");

$cat = defaultPagePrelude("By. Your. Command.");
?>
</head>
<body>
    <?php
    productheader();
    ?>
    <h1>By. Your. Command.</h1>
    <form action="112365365321.php" method="POST">
        <fieldset class="option_container">
            <legend>
                <strong>Configuration Check</strong>
            </legend>
            <table>
                <?php
                if (in_array("I do not care about security!", Config::$SUPERADMINS))
                    echo UI_warning("You do not care about security. This page should be made accessible to the CAT admin only! See config.php 'Superadmins'!");

                if (version_compare(phpversion(), '5.3', '>='))
                    echo UI_okay("<strong>PHP</strong> is sufficiently recent. You are running " . phpversion() . ".");
                else
                    echo UI_error("<strong>PHP</strong> is too old. We need at least 5.3");

                if (function_exists('gettext'))
                    echo UI_okay("PHP extension <strong>GNU Gettext</strong> is installed.");
                else
                    echo UI_error("PHP extension <strong>GNU Gettext</strong> not found!");

                if (function_exists('openssl_sign'))
                    echo UI_okay("PHP extension <strong>OpenSSL</strong> is installed.");
                else
                    echo UI_error("PHP extension <strong>OpenSSL</strong> not found!");

                if (class_exists('Imagick'))
                    echo UI_okay("PHP extension <strong>Imagick</strong> is installed.");
                else
                    echo UI_error("PHP extension <strong>Imagick</strong> not found! Get it from your distribution or <a href='http://pecl.php.net/package/imagick'>here</a>.");

                if (function_exists('geoip_record_by_name'))
                    echo UI_okay("PHP extension <strong>GeoIP</strong> is installed.");
                else
                    echo UI_error("PHP extension <strong>GeoIP</strong> not found! Get it from your distribution or <a href='http://pecl.php.net/package/geoip'>here</a>.");

                if (function_exists('mysql_connect'))
                    echo UI_okay("PHP extension <strong>MySQL</strong> is installed.");
                else
                    echo UI_error("PHP extension <strong>MySQL</strong> not found!");

                $openssl_path = "";
                $openssl_is = "IMPLICIT";
                if (isset(Config::$PATHS['openssl']) && Config::$PATHS['openssl'] != "") {
                    $openssl_path = exec("which " . Config::$PATHS['openssl']);
                    if ($openssl_path == Config::$PATHS['openssl'])
                        $openssl_is = "EXPLICIT";
                    else
                        $openssl_is = "IMPLICIT";
                } else {
                    $openssl_is = "IMPLICIT";
                    $openssl_path = exec("which openssl");
                }

                if ($openssl_path != "") {
                    $openssl_version = exec($openssl_path . " version");
                    if ($openssl_is == "EXPLICIT")
                        echo UI_okay("'<strong>$openssl_version</strong>' was found and is configured explicitly in your config.");
                    else
                        echo UI_warning("'<strong>$openssl_version</strong>' was found, but is not configured with an absolute path in your config.");
                } else
                    echo UI_error("<strong>openssl</strong> was not found on your system!");

                if (exec("which makensis") != "")
                    echo UI_okay("'<strong>makensis</strong>' binary found.");
                else
                    echo UI_error("'<strong>makensis</strong>' not found in your \$PATH!");

                if (exec("which qrencode") != "")
                    echo UI_okay("'<strong>qrencode</strong>' binary found.");
                else
                    echo UI_error("'<strong>qrencode</strong>' not found in your \$PATH!");

                if (exec("which zip") != "")
                    echo UI_okay("'<strong>zip</strong>' binary found.");
                else
                    echo UI_error("'<strong>zip</strong>' not found in your \$PATH!");

                if (exec("which rad_eap_test") != "")
                    echo UI_okay("'<strong>rad_eap_test</strong>' script found.");
                else
                    echo UI_error("'<strong>rad_eap_test</strong>' not found in your \$PATH!");

                if (fopen(Config::$PATHS['logdir'] . "/debug.log", "a") == FALSE)
                    echo UI_warning("Log files in <strong>" . Config::$PATHS['logdir'] . "</strong> are not writable!");
                else
                    echo UI_okay("Log directory is writable.");

                if (fopen(dirname(dirname(__FILE__)) . "/downloads/write.test", "a") == FALSE)
                    echo UI_warning("Download directory is not writable!");
                else
                    echo UI_okay("Download directory is writable.");

                $locales = shell_exec("locale -a");
                $allthere = "";
                foreach (Config::$LANGUAGES as $onelanguage)
                    if (preg_match("/" . $onelanguage['locale'] . "/", $locales) == 0)
                        $allthere .= $onelanguage['locale'] . " ";

                if ($allthere == "")
                    echo UI_okay("All of your configured locales are available on your system.");
                else
                    echo UI_warning("Some of your configured locales (<strong>$allthere</strong>) are not installed and will not be displayed correctly!");

                $defaultvalues = "";
                if (Config::$APPEARANCE['from-mail'] == "cat-invite@your-cat-installation.example")
                    $defaultvalues .="APPEARANCE/from-mail ";
                if (Config::$APPEARANCE['admin-mail'] == "admin@your-cat-installation.example")
                    $defaultvalues .="APPEARANCE/admin-mail ";
                if (isset(Config::$RADIUSTESTS['UDP-hosts'][0]) && Config::$RADIUSTESTS['UDP-hosts'][0]['ip'] == "192.0.2.1")
                    $defaultvalues .="RADIUSTESTS/UDP-hosts ";
                if (Config::$DB['INST']['host'] == "db.host.example")
                    $defaultvalues .="DB/INST ";
                if (Config::$DB['INST']['user'] == "db.host.example")
                    $defaultvalues .="DB/USER ";
                $files = array();
                foreach (Config::$RADIUSTESTS['TLS-clientcerts'] as $cadata) {
                    foreach ($cadata['certificates'] as $cert_files) {
                        $files[] = $cert_files['public'];
                        $files[] = $cert_files['private'];
                    }
                }

                foreach ($files as $file) {
                    $handle = fopen(CAT::$root . "/config/cli-certs/" . $file, 'r');
                    if (!$handle)
                        $defaultvalues .="CERTIFICATE/$file ";
                    else
                        fclose($handle);
                }
                if ($defaultvalues != "")
                    echo UI_warning("Your configuration in config/config.php contains unchanged default values or links to inexistent files: <strong>$defaultvalues</strong>!");
                else
                    echo UI_okay("On a first superficial look, your configuration appears fine.");
                ?>
            </table>
        </fieldset>
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
                        $downloads = dirname(dirname(__FILE__)) . "/downloads";
                        $tm = time();
                        $i = 0;

                        $Cache = array();
                        $result = DBConnection::exec("INST", "SELECT download_path FROM downloads");
                        while ($r = mysqli_fetch_row($result)) {
                            $e = explode('/', $r[0]);
                            $Cache[$e[1]] = 1;
                        }


                        if ($handle = opendir($downloads)) {

                            /* This is the correct way to loop over the directory. */
                            while (false !== ($entry = readdir($handle))) {
                                if ($entry === '.' || $entry === '..' || $entry === '.htaccess')
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
                </tr>
                <tr>
                    <td>
                        <?php
                        echo count(Federation::listAllIdentityProviders());
                        ?>
                    </td>
                    <td>
                        <?php
                        echo count(Federation::listAllIdentityProviders(1));
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
    </form>
    <?php
    include "inc/admin_footer.php";
    ?>

