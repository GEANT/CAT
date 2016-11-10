<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */

// please run this as a cron job every hour

require_once(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("CAT.php");
require_once("Federation.php");
require_once("UserManagement.php");
require_once("User.php");

// iterate through all federations and see if there are recently expired 
// invitations for any of them

$mgmt_object = new UserManagement();
$invitation_list = $mgmt_object->listRecentlyExpiredInvitations();

$cat = new CAT();

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

    $this_fed = new Federation(reset($thisfedlist)["country"]);
    $admins = $this_fed->listFederationAdmins();
    $mailtext = "Hello,

invitation tokens for the following new institutions have recently expired:

";
    foreach ($listofinstnames as $instname) {
        $mailtext .= "$instname\n";
    }

    if ($numberofexistingidps > 0) {
        $mailtext .= "

Additionally, $numberofexistingidps invitations for existing institutions have expired.
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