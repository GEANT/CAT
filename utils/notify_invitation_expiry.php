<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

// please run this as a cron job every hour

require_once(dirname(dirname(__FILE__)) . "/config/_config.php");

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

invitation tokens for the following new ". CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'] ." have recently expired:

";
    foreach ($listofinstnames as $instname) {
        $mailtext .= "$instname\n";
    }

    if ($numberofexistingidps > 0) {
        $mailtext .= "

Additionally, $numberofexistingidps invitations for an existing ". CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution']." have expired.
        ";
    }
    $mailtext .= "
We thought you might like to know.

Greetings,

A humble " . CONFIG['APPEARANCE']['productname'] . " cron job
";

    foreach ($admins as $admin) {
        $user = new User($admin);
        $user->sendMailToUser("Expired Invitations in the last hour", $mailtext);
    }
}