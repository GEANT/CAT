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

/**
 * This page edits a federation.
 * 
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 */
?>
<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();
$cat = new core\CAT();

$auth->authenticate();


/// product name (eduroam CAT), then term used for "federation", then actual name of federation.
echo $deco->defaultPagePrelude(sprintf(_("%s: RADIUS/TLS certificate management for %s"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureFed));
$langObject = new \core\common\Language();
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate.js"></script> 
</head>
<body>

    <?php echo $deco->productheader("FEDERATION"); ?>

    <h1>
        <?php
        /// nomenclature for federation, then actual federation name
        printf(_("RADIUS/TLS certificate management for %s"), $uiElements->nomenclatureFed);
        ?>
    </h1>
    <?php
    $user = new \core\User($_SESSION['user']);
    $mgmt = new \core\UserManagement();
    $isFedAdmin = $user->isFederationAdmin();

// if not, send the user away
    if (!$isFedAdmin) {
        echo _("You do not have the necessary privileges to request server certificates.");
        exit(1);
    }
    $externalDb = new \core\ExternalEduroamDBData();
// okay... we are indeed entitled to "do stuff"
    $feds = $user->getAttributes("user:fedadmin");
    foreach ($feds as $oneFed) {
        $theFed = new \core\Federation($oneFed['value']);
        echo '<p>';
        printf(_("eduroamDB status for %s %s"), $uiElements->nomenclatureFed, $theFed->name);
        echo '</p>';
        echo _('Below you can select an institution and check what information on this institution is present in the eduroam database.');
        echo '<p/>';
        echo _("In the 'Servers' column we show TLS servers names provided for this institution (it means servers having the type 1 - RADIUS over TLS).");
        echo '<br/>';
        echo _("In the 'Contacts' column we show all contacts having type 1, i.e. service contact, other contacts are omitted.");
        echo '<br/>';
        echo _("In the 'Timestamp' column we show last update time.");
        echo '<p/>';
        echo _('To check eduroam database specification see') . 
                ' <a target="_blank" href="https://monitor.eduroam.org/eduroam-database/v2/docs/eduroam-database-ver30112021.pdf">' .
                _('this document') . '</a><p/>';
        echo _('If you cannot find your institution on this list it means that this institiution is not present in your upstream data.');        
        echo '<p/>';
        $allAuthorizedFeds = $user->getAttributes("user:fedadmin");
        $extInsts = $externalDb->listExternalTlsServersInstitution($allAuthorizedFeds[0]['value'], TRUE);
        
        ?>
        
    <select name="INST-list" id="INST-list">
        <option value=""><?php echo _('---PLEASE CHOOSE---');?></option>
        <?php 
        $instdata = array();
        foreach ($extInsts as $iid => $oneInst) {
            print '<option value="' . $iid . '">' . $oneInst['name'] . '</option>';
            $instdata[$iid] = array();
            $instdata[$iid]['name'] = $oneInst['name'];   
            $instdata[$iid]['type'] = _('no data');
            if ($oneInst['type'] != '') {
                $instdata[$iid]['type'] = preg_replace('/IdPSP/', 'IdP and SP', $oneInst['type']);
            }
            $instdata[$iid]['servers'] = _('no data');
            if ($oneInst['servers'] != '') {
                $instdata[$iid]['servers'] = preg_replace('/,/', '<br>', $oneInst['servers']);
            }
            $contactdata = '';
            foreach ($oneInst['contacts'] as $oneContact) {
                if ($contactdata != '') {
                    $contactdata = $contactdata . '<br>';
                }
                if ($oneContact['name']) {
                    $contactdata = $contactdata . $oneContact['name'];
                }
                if ($contactdata != '') {
                    $contactdata = $contactdata . '<br>';
                }
                if ($oneContact['mail']) {
                    $contactdata = $contactdata . $oneContact['mail'];
                }
                if ($contactdata != '') {
                    $contactdata = $contactdata . '<br>';
                }
                if ($oneContact['phone']) {
                    $contactdata = $contactdata . $oneContact['phone'];
                }        
            }
            if ($contactdata == '') {
                $contactdata = _('no data');
            }
            $instdata[$iid]['contacts'] = $contactdata;
            $instdata[$iid]['ts'] = $oneInst['ts'];
        }
        
        ?>
        <script type="text/javascript">
            var instservers = [];
            var instname = [];
            var insttype = [];
            var instcontact = [];
            var instts = [];
            <?php
            foreach (array_keys($instdata) as $iid) {
                echo "instservers['" . $iid . "']='" . $instdata[$iid]['servers']. "';\n";
                echo "instname['" . $iid . "']='" . $instdata[$iid]['name']. "';\n";
                echo "insttype['" . $iid . "']='" . $instdata[$iid]['type']. "';\n";
                echo "instcontact['" . $iid . "']='" . $instdata[$iid]['contacts']. "';\n";
                echo "instts['" . $iid . "']='" . $instdata[$iid]['ts']. "';\n";
            }
            ?>
            $(document).ready(function(){
                $("#instdata_area").hide();
            });
            $(document).on('change', '#INST-list' , function() {
                if ($(this).val() == '') {
                    $("#instdata_area").hide();
                } else {
                    //alert($(this).val());
                    row = '<td>' + instname[$(this).val()] + '</td><td>' + insttype[$(this).val()] + '</td><td>' + instservers[$(this).val()] + '</td><td>' + instcontact[$(this).val()] + '</td><td>' + instts[$(this).val()] + '</td>';
                    //alert(row);
                    $("#toshow").html(row);
                    $("#instdata_area").show();
                }
            });
        </script>
    </select>
    <div id="instdata_area">
        <table>
            <tr><th align="left" width="350">
                    <?php echo _('Name');?>
                </th><th align="left" width="100">
                    <?php echo _('Type');?>
                </th>
                <th align="left" width="200">
                    <?php echo _('Servers');?>
                </th>
                <th align="left" width="200">
                    <?php echo _('Contact data');?>
                </th>
                <th align="left" width="100">
                    <?php echo _('Timestamp');?>
                </th>
            </tr>
            <tr id="toshow"></tr>
        </table>
    </div>
    <?php   
    }
    echo $deco->footer();
    