<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
require_once(dirname(dirname(dirname(dirname(__FILE__))))."/config/_config.php");

require_once("IdP.php");
require_once("Profile.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("auth.inc.php");
require_once("common.inc.php");

$Cat = new CAT();
$Cat->set_locale("web_admin");

header("Content-Type:text/html;charset=utf-8");
$my_inst = valid_IdP($_GET['inst_id']);

$my_profile = valid_Profile($_GET['profile_id'],$my_inst->identifier);

function prepareCheck($profile) {
    $attribs = $profile->getCollapsedAttributes();
    // these are still arrays
    $cas = $attribs['eap:ca_file'];
    $names = $attribs['eap:server_name'];
    // store all CAs in temp files
    $name = rtrim(dirname(dirname(dirname(__FILE__))),'/')."/downloads/".md5(time().rand());
    debug(4,"temp file for certificates: $name\n");
    if(! mkdir($name,0700, true)) {
        error("unable to create temporary directory: $name\n");
        exit;
    }
    chdir($name);
    $filename = "certs.pem";
    $handle = fopen($filename,"w");
    foreach ($cas as $ca) {
        fwrite($handle, $ca . "\n");
    }
    // calculate common suffix of all host names
    $suffix = calculateCommonHostSuffix($names);
    // echo $suffix;

    return " -a $filename -x $suffix";
}

function evalResult($flow,$eap,$host) {
    $result = array_pop($flow);
    if ($result == 2)
        return UI_okay(sprintf(_("<strong>%s</strong> from <strong>%s</strong>"),display_name($eap),$host['display_name']));
    else
        return UI_error(sprintf(_("Login with <strong>%s</strong> from <strong>%s</strong> failed!"),display_name($eap),$host['display_name'])."<br/>Resultcode: $result");
}

if ($my_profile->realm != "") { // doing the checks

    $eaps = $my_profile->getEapMethodsinOrderOfPreference(1);
    $anon_id = "";
    if ($my_profile->use_anon_outer) $anon_id = "-A $my_profile->realm";
    echo "<table>";
    if (count(Config::$RADIUSTESTS['UDP-hosts']) == 0) echo _("This check is not configured.");
    foreach ($eaps as $pref => $eap) {
        foreach (Config::$RADIUSTESTS['UDP-hosts'] as $host) {
            if ($eap['OUTER'] == PEAP) {
                // we need a username and password
                if (!isset($_POST['username']) || $_POST['username'] == "") {
                    echo UI_error(sprintf(_("Could not check <strong>%s</strong> from <strong>%s</strong> - no username submitted!"),display_name($eap), $host['display_name']), _("Username missing"));
                    continue;
                }
                if (!isset($_POST['password']) || $_POST['password'] == "") {
                    echo UI_error(sprintf(_("Could not check <strong>%s</strong> from <strong>%s</strong> - no password submitted!"),display_name($eap), $host['display_name']), _("Password missing"));
                    continue;
                }
                if ($eap['INNER'] != MSCHAP2) {
                    echo UI_warning(sprintf(_("Sorry, we don't know how to handle the inner EAP method in <strong>%s</strong> from <strong>%s</strong>!"),display_name($eap), $host['display_name']),"Unknown inner method");
                    continue;
                }

                $certopts = prepareCheck($my_profile);

                $cmdline = Config::$PATHS['rad_eap_test']." -c -H ".$host['ip']." -P 1812 -S ".$host['secret']." -M 22:44:66:CA:20:01 $anon_id -u ".escapeshellarg($_POST['username'])." -p ".escapeshellarg($_POST['password'])." -e PEAP -m WPA-EAP $certopts -t ".$host['timeout']." | grep 'RADIUS message:' | cut -d ' ' -f 3 | cut -d '=' -f 2";
                // debug(4,"Thorough reachability check: $cmdline.\n");
                $packetflow = array();
                exec($cmdline,$packetflow);

                echo evalResult($packetflow,$eap,$host);

            }
            else if ($eap['OUTER'] == TTLS) {
                // we need a username and password
                if (!isset($_POST['username']) || $_POST['username'] == "") {
                    echo UI_error(sprintf(_("Could not check <strong>%s</strong> from <strong>%s</strong> - no username submitted!"),display_name($eap), $host['display_name']), _("Username missing"));
                    continue;
                }
                if (!isset($_POST['password']) || $_POST['password'] == "") {
                    echo UI_error(sprintf(_("Could not check <strong>%s</strong> from <strong>%s</strong> - no password submitted!"),display_name($eap), $host['display_name']), _("Password missing"));
                    continue;
                }
                $inner_eap = "";
                if ($eap['INNER'] == MSCHAP2)
                    $inner_eap = "MSCHAPV2";
                if ($eap['INNER'] == NONE)
                    $inner_eap = "PAP";

                if ($inner_eap == "") {
                    echo UI_warning(sprintf(_("Sorry, we don't know how to handle the inner EAP method in <strong>%s</strong> from <strong>%s</strong>!"),display_name($eap), $host['display_name']),_("Unknown inner method"));
                    continue;
                }

                $certopts = prepareCheck($my_profile);

                $cmdline = Config::$PATHS['rad_eap_test']." -c -H ".$host['ip']." -P 1812 -S ".$host['secret']." -M 22:44:66:CA:20:01 $anon_id -u ".escapeshellarg($_POST['username'])." -p ".escapeshellarg($_POST['password'])." -e TTLS -2 $inner_eap -m WPA-EAP $certopts -t ".$host['timeout']." | grep 'RADIUS message:' | cut -d ' ' -f 3 | cut -d '=' -f 2";
                $packetflow = array();
                exec($cmdline,$packetflow);

                echo evalResult($packetflow,$eap,$host);

            }
            else if ($eap['OUTER'] == TLS) {
                // we need a certificate file
                // check TBD

                $cmdline = Config::$PATHS['rad_eap_test']." -c -H ".$host['ip']." -P 1812 -S ".$host['secret']." -M 22:44:66:CA:20:01 $anon_id -e TLS -m WPA-EAP -t ".$host['timeout']." | grep 'RADIUS message:' | cut -d ' ' -f 3 | cut -d '=' -f 2";
                // echo UI_remark("INCOMPLETE I would execute this:<br/>$cmdline","not implemented");
                echo UI_remark(sprintf(_("Sorry... we don't know how to test the EAP type %s from %s."),display_name($eap),$host['display_name']), _("Test not implemented"));
            }
            else
                echo UI_remark(sprintf(_("Sorry... we don't know how to test the EAP type %s from %s."),display_name($eap),$host['display_name']), _("Unknown outer method"));
                
        }
    }
    echo "</table>";

    /*                               <tr><td>Username:</td><td><input type='text' id='username' name='username'/></td></tr>";
                                <tr><td>Password:</td><td><input type='text' id='password' name='password'/></td></tr>";
                              <tr><td>Certificate file:</td><td><input type='file' id='cert' name='cert'/></td></tr>
                              <tr><td>Private key, if any:</td><td><input type='text' id='privkey' name='privkey'/></td></tr>";
    */

} else {
    echo "<p>$failimage"._("Your profile does not contain a valid realm name. We can only check the validity of a realm if you let us know the realm name. Please consider going to your profile properties and enter the realm name.")."</p>";
}
?>
