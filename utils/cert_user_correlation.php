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

// called by ocsp_update.sh (which in turn should run via cron every minute)

/**
 * This script will first trigger an update of all issued certificates which
 * have not expired yet and whose stored OCSP statement is older than a week.
 * 
 * It works on two CAs, the RSA and ECDSA variant. There is a separate temp
 * subdir for both ( temp_ocsp_RSA and temp_ocsp_ECDSA ).
 */
require_once dirname(dirname(__FILE__)) . "/config/_config.php";

/* If the RADIUS server is supposed to generate meaningful (temporally stable,
 * per user, per SP identifiers, then it needs to compute them on the username,
 * not just the opaque individual certificate CN.
 * So let's regularly export the mapping cert -> username for the RADIUS server
 * to import and use as a basis for CUI.
 * The export uses a hash of username rather than cleartext because knowledge
 * of actual cleartext is not required for the RADIUS server - it just needs
 * to see the same value for the same user.
 * 
 * Pushing this regularly (and on user creation) avoids blocking dependencies on
 * the web server part (RADIUS server cluster has and needs higher availability
 * guarantees than web)
 */

$dbConn = core\DBConnection::handle("INST");
$query = $dbConn->exec("SELECT c.cn as cn, u.username as username FROM silverbullet_user u, silverbullet_certificate c WHERE c.silverbullet_user_id = u.id AND c.revocation_status = 'NOT_REVOKED' AND c.expiry > NOW()");
$radiusDbs = core\DBConnection::handle("RADIUS"); // is an array of server conns
foreach (mysqli_fetch_all(/** @scrutinizer ignore-type */ $query, MYSQLI_NUM) as $oneRow) {
    $cn = $oneRow[0];
    $user = $oneRow[1];
    foreach ($radiusDbs as $dbIndex => $oneRadiusDb) {
        $res = $oneRadiusDb->exec("INSERT IGNORE INTO radcheck (username, attribute, op, value) VALUES (?, 'CUI-Source-Username', ':=', ?)", "ss", $cn, $user);
        if ($res === TRUE) {
            echo "Created correlation pair $cn -> $username on $dbIndex.\n";
        }
    }
}
