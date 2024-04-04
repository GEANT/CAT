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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$deco = new \web\lib\admin\PageDecoration();
$uiElements = new web\lib\admin\UIElements();
$validator = new \web\lib\common\InputValidation();

$iconsPath = '../resources/images/icons/';
//$OpenRoamingSymbol = "<img src='../resources/images/icons/or.svg' alt='OpenRoaming' title='OpenRoaming'>";
$OpenRoamingSymbol = "OR";
$stausIcons = [
    \core\IdP::PROFILES_SHOWTIME => ['img' => 'Tabler/checks-green.svg', 'text' => _("At least one profile is fully configured and visible in the user interface")],
    \core\IdP::PROFILES_CONFIGURED => ['img' => 'Tabler/check-green.svg', 'text' => _("At least one profile is fully configured but none are set as production-ready therefore the institution is not visible in the user interface")],
];

$certStatusIcons = [
    \core\AbstractProfile::CERT_STATUS_OK => ['img' => 'Tabler/certificate-green.svg', 'text' => _("All certificates are valid long enough")],
    \core\AbstractProfile::CERT_STATUS_WARN => ['img' => 'Tabler/certificate-red.svg', 'text' => _("At least one certificate is close to expiry")],
    \core\AbstractProfile::CERT_STATUS_ERROR => ['img' => 'Tabler/certificate-off.svg', 'text' => _("At least one certificate either has expired or is very close to expiry")],
];


$operoamingStatusIcons = [
    \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_GOOD => ['img' => 'Tabler/square-rounded-check-green.svg', 'text' => _("OpenRoaming appears to be configured properly")],
    \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_NOTE => ['img' => 'Tabler/info-square-rounded-blue.svg', 'text' => _("There are some minor OpenRoaming configuration issues")],
    \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_WARN => ['img' => 'Tabler/info-square-rounded-blue.svg', 'text' => _("There are some avarage level OpenRoaming configuration issues")],
    \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_ERROR => ['img' => 'Tabler/alert-square-rounded-red.svg', 'text' => _("There are some critical OpenRoaming configuration issues")],
    
];

echo $deco->defaultPagePrelude(sprintf(_("%s: %s Management"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureFed));
$user = new \core\User($_SESSION['user']);
require_once "inc/click_button_js.php";
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
<script>
var show_downloads = "<?php echo _("Show downloads") ?>";                
var hide_downloads = "<?php echo _("Hide downloads") ?>";
</script>
<script src="js/nro.js" type="text/javascript"></script>
</head>
<body>
    <?php
    echo $deco->productheader("FEDERATION");
    $readonly = \config\Master::DB['INST']['readonly'];
    ?>
    <h1>
        <?php echo sprintf(_("%s Overview"), $uiElements->nomenclatureFed); ?>
    </h1>

    <div class="infobox">
        <h2><?php $tablecaption = _("Your Personal Information"); echo $tablecaption; ?></h2>
        <table>
            <caption><?php echo $tablecaption;?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Language if applicable");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value");?></th>
            </tr>            
            <?php echo $uiElements->infoblock($user->getAttributes(), "user", "User"); ?>
            <tr>
                <td>
                    <?php echo "" . _("Unique Identifier") ?>
                </td>
                <td>
                </td>
                <td>
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo str_replace('\'', '\x27', str_replace('"', '\x22', $_SESSION["user"])); ?>")'><?php echo _("click to display"); ?></span>
                </td>
            </tr>
        </table>
    </div>

    <form action='overview_certificates.php' method='GET' accept-charset='UTF-8'>
        <button type='submit'><?php echo sprintf(_('RADIUS/TLS Certificate management'));?></button>
    </form>

    <?php
    $mgmt = new \core\UserManagement();
    $fed_id = '';
    if ($user->isSuperadmin() && isset($_GET['fed_id'])) {
        $cat = new \core\CAT(); // initialises Entity static members
        $fedIdentifiers = array_keys($cat->knownFederations);
        if (!in_array(strtoupper($_GET['fed_id']), $fedIdentifiers)) {
            throw new Exception($this->inputValidationError(sprintf("This %s does not exist!", \core\common\Entity::$nomenclature_fed)));
        } else {
            $fed_id = $_GET['fed_id'];
        }
        $feds = [['name'=>' user:fedadmin', 'value' => $fed_id]];
    } elseif (!$user->isFederationAdmin()) {
        echo "<p>" . sprintf(_("You are not a %s manager."), $uiElements->nomenclatureFed) . "</p>";
        echo $deco->footer();
        exit(0);
    } else {
        $feds = $user->getAttributes("user:fedadmin");
    }
    foreach ($feds as $onefed) {
        $thefed = new \core\Federation(strtoupper($onefed['value']));
        ?>

        <div class='infobox'><h2>
                <?php $tablecaption2 = sprintf(_("%s Properties: %s"), $uiElements->nomenclatureFed, $thefed->name); echo $tablecaption2; ?>
            </h2>
            <table>
            <caption><?php echo $tablecaption2;?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Language if applicable");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value");?></th>
            </tr>
                <!-- fed properties -->
                <tr>
                    <td>
                        <?php echo "" . _("Country") ?>
                    </td>
                    <td>
                    </td>
                    <td>
                        <strong><?php
                            echo $thefed->name;
                            ?></strong>
                    </td>
                </tr>
                <?php
                echo $uiElements->infoblock($thefed->getAttributes(), "fed", "FED");
                if ($readonly === FALSE) {
                    ?>
                    <tr>
                        <td colspan='3' style='text-align:right;'><form action='edit_federation.php' method='POST'><input type="hidden" name='fed_id' value='<?php echo strtoupper($thefed->tld); ?>'/><button type="submit">Edit</button></form></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </div>
        <div class='infobox'>
            <h2>
                <?php $tablecaption3 = sprintf(_("%s Statistics: %s"), $uiElements->nomenclatureFed, $thefed->name); echo $tablecaption3; ?>
            </h2>
            <table>
                <tbody>
                <!-- idp stats -->
                <tr>
                    <th scope='col' style='text-align:left;'> <?php echo _("IdPs Total"); ?></th>
                    <th scope='col' colspan='3'> <?php echo _("Public Download") ?></th>
                </tr>
                <tr>
                    <td> <?php echo count($thefed->listIdentityProviders(0)); ?></td>
                    <td colspan='3'> <?php echo count($thefed->listIdentityProviders(1)); ?>
                    </td>
                </tr>
                </tbody>
                <tbody style="display:none" class="stat-downloads">
                <!-- download stats -->
                <tr><td colspan='3'></td></tr>
                <tr>
                    <th scope='col' style='text-align:left;'> <?php echo _("Downloads"); ?></th>
                    <th scope='col' style='text-align:left;'> <?php echo _("Admin"); ?></th>
                    <th scope='col' style='text-align:left;'> <?php echo \core\ProfileSilverbullet::PRODUCTNAME ?></th>
                    <th scope='col' style='text-align:left;'> <?php echo _("User"); ?></th>
                </tr>
                <?php echo $thefed->downloadStats("table"); ?>
                </tbody>
            </table>
            <button style="position:absolute; bottom:9px;" class="stat-button"><?php echo _("Show downloads") ?></button>
        </div>
        <?php
    }

    if (isset($_POST['submitbutton']) &&
            $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_DELETE &&
            isset($_POST['invitation_id'])) {
        $mgmt->invalidateToken(filter_input(INPUT_POST, 'invitation_id', FILTER_SANITIZE_STRING));
    }

    if (isset($_GET['invitation'])) {
        echo "<div class='ca-summary' style='position:relative;'><table>";
        $counter = $validator->integer($_GET['successcount']);
        if ($counter === FALSE) {
            $counter = 1;
        }
        switch ($_GET['invitation']) {
            case "SUCCESS":
                $cryptText = "";
                switch ($_GET['transportsecurity']) {
                    case "ENCRYPTED":
                        $cryptText = ngettext("It was sent with transport security (encryption).", "They were sent with transport security (encryption).", $counter);
                        break;
                    case "CLEAR":
                        $cryptText = ngettext("It was sent in clear text (no encryption).", "They were sent in clear text (no encryption).", $counter);
                        break;
                    case "PARTIAL":
                        $cryptText = _("A subset of the mails were sent with transport encryption, the rest in clear text.");
                        break;
                    default:
                        throw new Exception("Error: unknown encryption status of invitation!?!");
                }
                echo $uiElements->boxRemark(ngettext("The invitation email was sent successfully.", "All invitation emails were sent successfully.", $counter) . " " . $cryptText, _("Sent successfully."));
                break;
            case "FAILURE":
                echo $uiElements->boxError(_("No invitation email could be sent!"), _("Sending failure!"));
                break;
            case "PARTIAL":
                $cryptText = "";
                switch ($_GET['transportsecurity']) {
                    case "ENCRYPTED":
                        $cryptText = ngettext("The successful one was sent with transport security (encryption).", "The successful ones were sent with transport security (encryption).", $counter);
                        break;
                    case "CLEAR":
                        $cryptText = ngettext("The successful one was sent in clear text (no encryption).", "The successful ones were sent in clear text (no encryption).", $counter);
                        break;
                    case "PARTIAL":
                        $cryptText = _("A subset of the successfully sent mails were sent with transport encryption, the rest in clear text.");
                        break;
                    default:
                        throw new Exception("Error: unknown encryption status of invitation!?!");
                }
                echo $uiElements->boxWarning(sprintf(_("Some invitation emails were sent successfully (%s in total), the others failed."), $counter) . " " . $cryptText, _("Partial success."));
                break;
            case "INVALIDSYNTAX":
                echo $uiElements->boxError(_("The invitation email address was malformed, no invitation was sent!"), _("The invitation email address was malformed, no invitation was sent!"));
                break;
            default:
                echo $uiElements->boxError(_("Error: unknown result code of invitation!?!"), _("Unknown result!"));
        }
        echo "</table></div>";
    }
    // our own location, to give to diag URLs
    if (isset($_SERVER['HTTPS'])) {
        $link = 'https://';
    } else {
        $link = 'http://';
    }
    $link .= $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
    $link = htmlspecialchars($link);
    if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] == 'LOCAL' && \config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] == 'LOCAL') {
        echo "<table><tr>
                        <td>" . sprintf(_("Diagnose reachability and connection parameters of any %s %s"), \config\ConfAssistant::CONSORTIUM['display_name'], $uiElements->nomenclatureIdP) . "</td>
                        <td><form method='post' action='../diag/action_realmcheck.php' accept-charset='UTF-8'>
                              <input type='hidden' name='comefrom' id='comefrom' value='$link'/>
                              <button id='realmcheck' style='cursor:pointer;' type='submit'>" . _("Go!") . "</button>
                            </form>
                        </td>
                    </tr>
                    </table>";
    }
    if (\config\ConfAssistant::CONSORTIUM['name'] == 'eduroam') {
        $helptext = "<h3>" . sprintf(_("Need help? Refer to the <a href='%s'>%s manual</a>"), "https://wiki.geant.org/x/qJg7Bw", $uiElements->nomenclatureFed) . "</h3>";
    } else {
        $helptext = "";
    }
    ?>
    <table class='user_overview' style='border:0px; width:unset'>
        <caption><?php echo _("Participant Details");?></caption>
        <tr>
            <th scope='col'><?php echo sprintf(_("%s Name"), $uiElements->nomenclatureParticipant); ?></th>
            <th scope='col'><?php echo _("Status") ?></th>
            <th scope='col'><?php echo $OpenRoamingSymbol ?></th>
            <th scope='col'><?php echo _("Cert"); ?></th>
            <?php
            $pending_invites = $mgmt->listPendingInvitations();

            if (\config\Master::DB['enforce-external-sync']) {
                echo "<th scope='col'>" . sprintf(_("%s Database Sync Status"), \config\ConfAssistant::CONSORTIUM['display_name']) . "</th>";
            }
            ?>
            <th scope='col'>
                <?php
                if ($readonly === FALSE) {
                    echo _("Administrator Management");
                }
                ?>
            </th>
        </tr>
        <?php
        $userIdps = $user->listOwnerships();
        foreach ($feds as $onefed) {
            $fedId = strtoupper($onefed['value']);
            $thefed = new \core\Federation($fedId);
            /// nomenclature for 'federation', federation name, nomenclature for 'inst'
            echo "<tr><td colspan='9'><strong>" . sprintf(_("The following %s are in your %s %s:"), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureFed, '<span style="color:green">' . $thefed->name . '</span>') . "</strong></td></tr>";
            echo "<tr><td colspan='4'><strong>". _("Quick search:")." </strong><input style='background:#eeeeee;' type='text' id='qsearch_" . $fedId . "'></td>";
            echo "<td colspan='6' style='border-bottom-style: dotted;border-bottom-width: 1px;'><input type='checkbox' name='unlinked' id='unlinked_ck_" . $fedId . "'> ". _("Only not linked"). "</td>";
            echo "</tr>";
            // extract only pending invitations for *this* fed
            $display_pendings = FALSE;
            foreach ($pending_invites as $oneinvite) {
                if (strtoupper($oneinvite['country']) == strtoupper($thefed->tld)) {
                    // echo "PENDINGS!";
                    $display_pendings = TRUE;
                }
            }
            $idps = $thefed->listIdentityProviders(0);
            $certStatus = $thefed->getIdentityProvidersCertStatus();
            $my_idps = [];
            foreach ($idps as $index => $idp) {
                $my_idps[$idp['entityID']] = mb_strtolower($idp['title']).'==='.$idp['realms'];
            }
            asort($my_idps);

            foreach ($my_idps as $index => $my_idp) {
                $idp_instance = $idps[$index]['instance'];
                $idpLinked = 'nosync';
                // external DB sync, if configured as being necessary
                if (\config\Master::DB['enforce-external-sync']) {
                    switch ($idp_instance->getExternalDBSyncState()) {
                        case \core\IdP::EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING:
                            break;
                        case \core\IdP::EXTERNAL_DB_SYNCSTATE_SYNCED:
                            $idpLinked = 'linked';
                            break;
                        case \core\IdP::EXTERNAL_DB_SYNCSTATE_NOT_SYNCED:
                            $idpLinked = 'notlinked';
                            break;
                    }                
                }         
                // new row_id, with one IdP inside
                echo "<tr class='idp_tr $idpLinked'>";

                // name; and realm of silverbullet profiles if any
                // instantiating all profiles is costly, so we only do this if
                // the deployment at hand has silverbullet enabled
                $listOfSilverbulletRealms = [];
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL") {
                    foreach ($idp_instance->listProfiles() as $oneProfile) {
                        if ($oneProfile instanceof core\ProfileSilverbullet) {
                            $listOfSilverbulletRealms[] = $oneProfile->realm;
                        }
                    }
                }
                echo "<td style='vertical-align:top;' class='inst_td'>
                         <input type='hidden' name='inst' value='" 
                        . $index . "'>"
                        . "<span style='display:none' class='inst_name'>".$my_idp."</span>"
                        . "<span>". $idp_instance->name . "</span>"
                        . " (<a href='overview_org.php?inst_id="
                        . $idp_instance->identifier . "'>" 
                        . (in_array($index, $userIdps) ? _("manage") : _("view"))
                        . "</a>)"
                        . (empty($listOfSilverbulletRealms) ? "" : "<ul><li>" ) 
                        . implode("</li><li>", $listOfSilverbulletRealms) 
                        . (empty($listOfSilverbulletRealms) ? "" : "</li><ul>" )
                        . "</td>";
                // deployment status; need to dive into profiles for this
                // show happy eyeballs if at least one profile is configured/showtime                    
                echo "<td>";
                
                if ($idp_instance->maxProfileStatus() >= \core\IdP::PROFILES_SHOWTIME) {
                    $status = \core\IdP::PROFILES_SHOWTIME;
                    echo "<img src='".$iconsPath.$stausIcons[$status]['img']."' alt='".$stausIcons[$status]['text']."' title='".$stausIcons[$status]['text']."'>";
                } elseif ($idp_instance->maxProfileStatus() >= \core\IdP::PROFILES_CONFIGURED) {
                    $status = \core\IdP::PROFILES_CONFIGURED;
                    echo "<img src='".$iconsPath.$stausIcons[$status]['img']."' alt='".$stausIcons[$status]['text']."' title='".$stausIcons[$status]['text']."'>";
                }
                
/*                
                echo ($idp_instance->maxProfileStatus() >= \core\IdP::PROFILES_CONFIGURED ? "C" : "-" ) 
                        . " " 
                        . ($idp_instance->maxProfileStatus() >= \core\IdP::PROFILES_SHOWTIME ? "V" : "-" )
                        . " ";
 * 
 */
                echo  "</td><td style='text-align: center'>";
                $status = $idp_instance->maxOpenRoamingStatus();
                switch ($status) {
                    case \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_NO:
                        echo "-";
                        break;
                    case \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_GOOD:
                    case \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_NOTE:
                    case \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_WARN:
                    case \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_ERROR:
                        echo "<img src='".$iconsPath.$operoamingStatusIcons[$status]['img']."' alt='".$operoamingStatusIcons[$status]['text']."' title='".$operoamingStatusIcons[$status]['text']."'>";
                        break;
                    default:
                        throw new \Exception("Impossible OpenRoaming status!");
                }
                echo "</td>";
                echo "<td><img src='".$iconsPath.$certStatusIcons[$certStatus[$index]]['img']."' alt='".$certStatusIcons[$certStatus[$index]]['text']."' title = '".$certStatusIcons[$certStatus[$index]]['text']."'></td>";                  
                // external DB sync, if configured as being necessary
                if (\config\Master::DB['enforce-external-sync']) {
                    echo "<td style='display: ruby;'>";
                    if ($readonly === FALSE) {
                        echo "<form method='post' action='inc/manageDBLink.inc.php?inst_id=" . $idp_instance->identifier . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                    <button type='submit'>" . _("Manage DB Link") . "</button>&nbsp;&nbsp;";
                    }
                    switch ($idpLinked) {
                        case 'nosync':
                            break;
                        case 'linked':
//                            echo "<div class='acceptable'>" . _("Linked") . "</div>";
                            break;
                        case 'notlinked':
                            echo "<span class='notacceptable'>" . _("NOT linked") . "</span>";
                            break;
                    }
                    echo "</form>";
                }

                // admin management
                echo "<td style='vertical-align: top;'>";
                if ($readonly === FALSE) {
                    echo "<div style='white-space: nowrap;'>
                                  <form method='post' action='inc/manageAdmins.inc.php?inst_id=" . $index . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                      <button type='submit'>" .
                    _("Add/Remove Administrators") . "
                                      </button>
                                  </form>
                                </div>";
                }
                echo "</td>";
                // end of entry
                echo "</tr>";
            }
            if ($display_pendings) {
                echo "<tr>
                            <td colspan='2'>
                               <strong>" .
                sprintf(_("Pending invitations in the %s:"), $uiElements->nomenclatureFed) . "
                               </strong>
                            </td>
                         </tr>";
                foreach ($pending_invites as $oneinvite) {
                    if (strtoupper($oneinvite['country']) == strtoupper($thefed->tld)) {
                        echo "<tr>
                                    <td>" .
                        $oneinvite['name'] . "
                                    </td>
                                    <td>" .
                        $oneinvite['mail'] . "
                                    </td>
                                    <td colspan=2>";
                        if ($readonly === FALSE) {
                            echo "<form method='post' action='overview_federation.php' accept-charset='UTF-8'>
                                <input type='hidden' name='invitation_id' value='" . $oneinvite['token'] . "'/>
                                <button class='delete' type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_DELETE . "'>" . _("Revoke Invitation") . "</button> "
                            . sprintf(_("(expires %s)"), $oneinvite['expiry'])
                            . "</form>";
                        }
                        echo "      </td>";                          
                        echo "         </tr>";
                    }
                }
            }
        }
        ?>
    </table>
    
    <?php
    
    if ($readonly === FALSE) {
        ?>
        <hr/>
        <br/>
        <form method='post' action='inc/manageNewInst.inc.php' onsubmit='popupRedirectWindow(this);
                    return false;' accept-charset='UTF-8'>
            <button type='submit' class='download'>
                <?php echo sprintf(_("Register a new %s!"), $uiElements->nomenclatureParticipant); ?>
            </button>
        </form>
        <br/>
        <?php
    }
    echo "<hr/>$helptext";
    echo $deco->footer();
    
