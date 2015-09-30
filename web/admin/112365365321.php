<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
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
}

$user = new User((!in_array("I do not care about security!", Config::$SUPERADMINS) ? $_SESSION['user'] : "UNIDENTIFIED"));

/* echo $user->identifier;
  echo "<pre>";
  print_r(Config::$SUPERADMINS);
  echo "</pre>";
 */
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
            <table>
                <?php
                if (!is_file(CONFIG::$AUTHENTICATION['ssp-path-to-autoloader']))
                    echo UI_error ("<strong>simpleSAMLphp</strong> not found!");
                else 
                    echo UI_okay("<strong>simpleSAMLphp</strong> autoloader found.");
                
                if (in_array("I do not care about security!", Config::$SUPERADMINS))
                    echo UI_warning("You do not care about security. This page should be made accessible to the CAT admin only! See config.php 'Superadmins'!");

                $needversion = "5.5.14";
                if (version_compare(phpversion(), $needversion, '>='))
                    echo UI_okay("<strong>PHP</strong> is sufficiently recent. You are running " . phpversion() . ".");
                else
                    echo UI_error("<strong>PHP</strong> is too old. We need at least $needversion, but you only have ".phpversion(). ".");

                if (function_exists('idn_to_ascii'))
                    echo UI_okay("PHP can handle internationalisation.");
                else
                    echo UI_error("PHP can <strongNOT</strong> handle internationalisation (idn_to_ascii() from php5-intl).");
                
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

                if (function_exists('ImageCreate'))
                    echo UI_okay("PHP extension <strong>GD</strong> is installed.");
                else
                    echo UI_error("PHP extension <strong>GD</strong> not found!</a>.");

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
                }
                else
                    echo UI_error("<strong>openssl</strong> was not found on your system!");

                if (exec("which makensis") != "") {
                    echo UI_okay("'<strong>makensis</strong>' binary found.");
                    $pathname = 'downloads' . '/' . md5(time() . rand());
                    $tmp_dir = dirname(dirname(__FILE__)) . '/' . $pathname;
                    if (!mkdir($tmp_dir, 0700, true)) {
                        error("unable to create temporary directory (eap test): $tmp_dir\n");
                    } else {
                      chdir($tmp_dir);
                      $NSIS_Modules = [
                         "NSISArray.nsh",
                         "FileFunc.nsh",
                         "ZipDLL.nsh",
                      ];
                      $exe= '/tt.exe';
                      $NSIS_Module_status = [];
                      foreach ($NSIS_Modules as $module) {
                         unset($out);
                         exec("makensis -V1 '-X!include $module' '-XOutFile $exe' '-XSection X' '-XSectionEnd'", $out, $retval);
                         if($retval > 0) {
                            $o = preg_grep('/include.*'.$module.'/',$out);
                            if(count($o) > 0)
                              $NSIS_Module_status[$module] = 0;
                            else
                              $NSIS_Module_status[$module] = 1;
                         }
                      }
                      if(is_file($exe))
                         unlink($exe);

                      foreach ($NSIS_Module_status as $module => $status) {
                        if($status == 1)
                           echo UI_okay("NSIS module '<strong>$module</strong>' found.");
                        else
                           echo UI_error("NSIS module '<strong>$module</strong>' not found.");

                      }
                   }
                }
                else
                    echo UI_error("'<strong>makensis</strong>' not found in your \$PATH!");

                if (exec("which zip") != "")
                    echo UI_okay("'<strong>zip</strong>' binary found.");
                else
                    echo UI_error("'<strong>zip</strong>' not found in your \$PATH!");

                 unset($out);
                 exec(Config::$PATHS['eapol_test'], $out, $retval);
                 if($retval == 255 ) {
                    $o = preg_grep('/-o<server cert/',$out);
                    if(count($o) > 0)
                       echo UI_okay("'<strong>eapol_test</strong>' script found.");
                    else
                       echo UI_error("'<strong>eapol_test</strong>' found, but is too old!");
                }
                else
                    echo UI_error("'<strong>eapol_test</strong>' not found!");

                if (fopen(Config::$PATHS['logdir'] . "/debug.log", "a") == FALSE)
                    echo UI_warning("Log files in <strong>" . Config::$PATHS['logdir'] . "</strong> are not writable!");
                else
                    echo UI_okay("Log directory is writable.");

                $name = dirname(dirname(__FILE__)) .'/downloads'.'/'.md5(time().rand()).'.test';
                if (fopen($name, "a") == FALSE)
                    echo UI_error("Download directory is not writable!");
                else {
                    echo UI_okay("Download directory is writable.");
                    unlink($name);
                }

                $name = dirname(dirname(__FILE__)) .'/downloads/logos'.'/'.md5(time().rand()).'.test';
                if (fopen($name, "a") == FALSE)
                    echo UI_error("Logos cache directory is not writable.");
                else{
                    echo UI_okay("Logos cache directory is writable.");
                    unlink($name);
                }

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
                $files = [];
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

                        $Cache = [];
                        $result = DBConnection::exec("INST", "SELECT download_path FROM downloads WHERE download_path IS NOT NULL");
                        while ($r = mysqli_fetch_row($result)) {
                            $e = explode('/', $r[0]);
                            $Cache[$e[1]] = 1;
                        }


                        if ($handle = opendir($downloads)) {

                            /* This is the correct way to loop over the directory. */
                            while (false !== ($entry = readdir($handle))) {
                                if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === 'logos')
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
    </form>
    <?php
    footer();
    ?>

