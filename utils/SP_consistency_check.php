<?php
require_once dirname(dirname(__FILE__)) . "/config/_config.php";
/**
    * check if URL responds with 200
    *
    * @param string $srv server name
    * @return integer or NULL
*/
function checkConfigRADIUSDaemon ($srv) {
    $ch = curl_init();
    if ($ch === FALSE) {
        return NULL;
    }
    $timeout = 10;
    curl_setopt ( $ch, CURLOPT_URL, $srv );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt ( $ch, CURLOPT_TIMEOUT, $timeout );
    curl_exec($ch);
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close($ch);
    if ($http_code == 200) {
        return 1;
    }
    return 0;
}

$dbLink = \core\DBConnection::handle("INST");
$allProblems = $dbLink->exec("SELECT deployment_id, inst_id, status, radius_status_1, radius_status_2, radius_instance_1, radius_instance_2 from deployment where radius_status_1=2 or radius_status_2=2");
if (!$allProblems) {
    exit;
}
$brokenDeployments = array();
while ($problemRow = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allProblems)) {
    foreach (array(1, 2) as $id) {
        $fld_s = "radius_status_$id";
        $fld_i = "radius_instance_$id";
        if ($problemRow->$fld_s == 2) {
            if (!isset($brokenDeployments[$problemRow->$fld_i])) {
                $brokenDeployments[$problemRow->$fld_i] = array();
            }
            $brokenDeployments[$problemRow->$fld_i][$problemRow->inst_id] = $problemRow->deployment_id;
        }
    }
}
if (empty($brokenDeployments)) {
    exit;
}
$allServers = $dbLink->exec("SELECT server_id, mgmt_hostname from managed_sp_servers");
$radiusSites = array();

while ($siteRow = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allServers)) {
    $radiusSite[$siteRow->server_id] = $siteRow->mgmt_hostname;
}
$siteStatus = array();
foreach (array_keys($brokenDeployments) as $server_id) {
    print "check $server_id " . $radiusSite[$server_id] . "\n";
    $siteStatus[$server_id]  = checkConfigRADIUSDaemon('http://' . $radiusSite[$server_id]);
    if ($siteStatus[$server_id]) {
        echo "\ncheck radius\n";
        echo \config\Diagnostics::RADIUSSPTEST['port']."\n";
        $statusServer = new \core\diag\RFC5997Tests($radiusSite[$server_id], \config\Diagnostics::RADIUSSPTEST['port'], \config\Diagnostics::RADIUSSPTEST['secret']);
        if ($statusServer->statusServerCheck() === \core\diag\AbstractTest::RETVAL_OK) {
            $siteStatus[$server_id] = 1;
        } else {
            $siteStatus[$server_id] = 0;
        }
    }
} 
if (!in_array(1, array_values($siteStatus))) { 
    exit;
}
foreach (array_keys($brokenDeployments) as $server_id) {
    if (isset($siteStatus[$server_id]) && $siteStatus[$server_id]) {
        foreach ($brokenDeployments[$server_id] as $inst_id => $deployment_id) {            
            $idp = new \core\IdP($inst_id);
            $hotspotProfiles = $idp->listDeployments();
            $deployment = $hotspotProfiles[0];
            $idx = 1;
            if ($deployment->radius_instance_2 == $server_id) {
                $idx = 2;
            }
            echo "\nfix $deployment_id of $inst_id on server $server_id index $idx\n";
            /** @scrutinizer ignore-call */
            $response = $deployment->setRADIUSconfig($idx, 1);
            if (isset($response["res[$idx]"]) && $response["res[$idx]"] = 'OK') {
                echo "FIXED\n";
            }
        }
    }
}

