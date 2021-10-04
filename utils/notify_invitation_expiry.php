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

// please run this as a cron job every hour

require_once dirname(dirname(__FILE__)) . "/config/_config.php";

// iterate through all federations and see if there are recently expired 
// invitations for any of them

$mgmt_object = new \core\UserManagement();
$invitation_list = $mgmt_object->listRecentlyExpiredInvitations();

$cat = new \core\CAT();

foreach ($cat->knownFederations as $federation => $federation_name) {
    // construct list of invitations in this federation
    $thisfedlist = [];
    $listofinstnames = [];
    $numberofexistingidps = 0;
    foreach ($invitation_list as $invitation) {
        if (strtoupper($invitation["country"]) == strtoupper($federation)) {
            $thisfedlist[] = $invitation;
            if ($invitation["name"] != "Existing idP") {
                $listofinstnames[] = $invitation["name"];
            } else {
                $numberofexistingidps += 1;
            }
        }
    }

    if (empty($thisfedlist)) { // nothing to do
        return;
    }

    $this_fed = new \core\Federation(reset($thisfedlist)["country"]);
    $admins = $this_fed->listFederationAdmins();
    $mailtext = "Hello,

invitation tokens for the following new ". \config\ConfAssistant::CONSORTIUM['nomenclature_participant'] ." have recently expired:

";
    foreach ($listofinstnames as $instname) {
        $mailtext .= "$instname\n";
    }

    if ($numberofexistingidps > 0) {
        $mailtext .= "

Additionally, $numberofexistingidps invitations for an existing ". \config\ConfAssistant::CONSORTIUM['nomenclature_participant']." have expired.
        ";
    }
    $mailtext .= "
We thought you might like to know.

Greetings,

A humble " . \config\Master::APPEARANCE['productname'] . " cron job
";

    foreach ($admins as $admin) {
        $user = new \core\User($admin);
        $user->sendMailToUser("Expired Invitations in the last hour", $mailtext);
    }
}
