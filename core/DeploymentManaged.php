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
 * This file contains the AbstractProfile class. It contains common methods for
 * both RADIUS/EAP profiles and SilverBullet profiles
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

/**
 * This class represents an EAP Profile.
 * Profiles can inherit attributes from their IdP, if the IdP has some. Otherwise,
 * one can set attribute in the Profile directly. If there is a conflict between
 * IdP-wide and Profile-wide attributes, the more specific ones (i.e. Profile) win.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class DeploymentManaged extends AbstractDeployment
{

    /**
     * This is the limit for dual-stack hosts. Single stack uses half of the FDs
     * in FreeRADIUS and take twice as many. initialise() takes this into
     * account.
     */
    const MAX_CLIENTS_PER_SERVER = 200;
    const PRODUCTNAME = "Managed SP";

    /**
     * the primary RADIUS server port for this SP instance
     * 
     * @var integer
     */
    public $port1;

    /**
     * the backup RADIUS server port for this SP instance
     * 
     * @var integer
     */
    public $port2;

    /**
     * the shared secret for this SP instance
     * 
     * @var string
     */
    public $secret;

    /**
     * the IPv4 address of the primary RADIUS server for this SP instance 
     * (can be NULL)
     * 
     * @var string
     */
    public $host1_v4;

    /**
     * the IPv6 address of the primary RADIUS server for this SP instance 
     * (can be NULL)
     * 
     * @var string
     */
    public $host1_v6;

    /**
     * the IPv4 address of the backup RADIUS server for this SP instance 
     * (can be NULL)
     * 
     * @var string
     */
    public $host2_v4;

    /**
     * the IPv6 address of the backup RADIUS server for this SP instance 
     * (can be NULL)
     * 
     * @var string
     */
    public $host2_v6;

    /**
     * the primary RADIUS server instance for this SP instance
     * 
     * @var string
     */
    public $radius_instance_1;

    /**
     * the backup RADIUS server instance for this SP instance
     * 
     * @var string
     */
    public $radius_instance_2;

    /**
     * the primary RADIUS server hostname - for sending configuration requests
     * 
     * @var string
     */
    public $radius_hostname_1;

    /**
     * the backup RADIUS server hostname - for sending configuration requests
     * 
     * @var string
     */
    public $radius_hostname_2;

    /**
     * the primary RADIUS server status - last configuration request result
     * 
     * @var string
     */
    public $radius_status_1;

    /**
     * the backup RADIUS server status - last configuration request result
     * 
     * @var string
     */
    public $radius_status_2;
    
    /**
     * the consortium this deployment is attached to
     * 
     * @var string
     */
    public $consortium;

    /**
     * the T&C of this service
     */
    public $termsAndConditions;
    
    /**
     * Class constructor for existing deployments (use 
     * IdP::newDeployment() to actually create one). Retrieves all 
     * attributes from the DB and stores them in the priv_ arrays.
     * 
     * @param IdP        $idpObject       optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     * @param string|int $deploymentIdRaw identifier of the deployment in the DB
     * @param string     $consortium      identifier of the consortium to attach to (only relevant when initialising a new deployment for the first time
     * @throws Exception
     */
    public function __construct($idpObject, $deploymentIdRaw, $consortium = 'eduroam')
    {
        parent::__construct($idpObject, $deploymentIdRaw); // we now have access to our INST database handle and logging
        $this->entityOptionTable = "deployment_option";
        $this->entityIdColumn = "deployment_id";
        $this->type = AbstractDeployment::DEPLOYMENTTYPE_MANAGED;
        if (!is_numeric($deploymentIdRaw)) {
            throw new Exception("Managed SP instances have to have a numeric identifier");
        }
        $propertyQuery = "SELECT consortium, status,port_instance_1,port_instance_2,secret,radius_instance_1,radius_instance_2,radius_status_1,radius_status_2,consortium FROM deployment WHERE deployment_id = ?";
        $queryExec = $this->databaseHandle->exec($propertyQuery, "i", $deploymentIdRaw);
        if (mysqli_num_rows(/** @scrutinizer ignore-type */ $queryExec) == 0) {
            throw new Exception("Attempt to construct an unknown DeploymentManaged!");
        }
        $this->identifier = $deploymentIdRaw;
        while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $queryExec)) {
            if ($iterator->secret == NULL && $iterator->radius_instance_1 == NULL) {
                // we are instantiated for the first time, or all previous init attempts failed - so initialise us
                // first time: note the consortium, permanently
                if ($iterator->consortium === NULL) {
                    $this->consortium = $consortium;
                    $consortiumQuery = "UPDATE deployment SET consortium = '$this->consortium' WHERE deployment_id = ?";
                    $this->databaseHandle->exec($consortiumQuery, "i", $deploymentIdRaw);
                }
                $details = $this->initialise();
                $this->port1 = $details["port_instance_1"];
                $this->port2 = $details["port_instance_2"];
                $this->secret = $details["secret"];
                $this->radius_instance_1 = $details["radius_instance_1"];
                $this->radius_instance_2 = $details["radius_instance_2"];
                $this->radius_status_1 = 1;
                $this->radius_status_2 = 1;
                $this->status = AbstractDeployment::INACTIVE;
            } else {
                $this->port1 = $iterator->port_instance_1;
                $this->port2 = $iterator->port_instance_2;
                $this->secret = $iterator->secret;
                $this->radius_instance_1 = $iterator->radius_instance_1;
                $this->radius_instance_2 = $iterator->radius_instance_2;
                $this->radius_status_1 = $iterator->radius_status_1;
                $this->radius_status_2 = $iterator->radius_status_2;
                $this->status = $iterator->status;
                $this->consortium = $iterator->consortium;
            }
        }
        $server1 = $this->radius_instance_1;
        $server1details = $this->databaseHandle->exec("SELECT mgmt_hostname, radius_ip4, radius_ip6 FROM managed_sp_servers WHERE server_id = ?", "s", $server1);
        while ($iterator2 = mysqli_fetch_object(/** @scrutinizer ignore-type */ $server1details)) {
            $this->host1_v4 = $iterator2->radius_ip4;
            $this->host1_v6 = $iterator2->radius_ip6;
            $this->radius_hostname_1 = $iterator2->mgmt_hostname;
        }
        $server2 = $this->radius_instance_2;
        $server2details = $this->databaseHandle->exec("SELECT mgmt_hostname, radius_ip4, radius_ip6 FROM managed_sp_servers WHERE server_id = ?", "s", $server2);
        while ($iterator3 = mysqli_fetch_object(/** @scrutinizer ignore-type */ $server2details)) {
            $this->host2_v4 = $iterator3->radius_ip4;
            $this->host2_v6 = $iterator3->radius_ip6;
            $this->radius_hostname_2 = $iterator3->mgmt_hostname;
        }
        $thisLevelAttributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = ?  
                                            ORDER BY option_name", "Profile");
        $tempAttribMergedIdP = $this->levelPrecedenceAttributeJoin($thisLevelAttributes, $this->idpAttributes, "IdP");
        $this->attributes = $this->levelPrecedenceAttributeJoin($tempAttribMergedIdP, $this->fedAttributes, "FED");
        
        $this->termsAndConditions = "<h2>Product Definition</h2>
<p>eduroam Managed SP enables eligible institutions to outsource the technical setup of the roaming uplink functions in an eduroam SP to the eduroam Operations Team. eduroam SP administrators use the service instead of a local RADIUS infrastructure. A unique selling point of this service is that the typical limitation of being required to have a static IP address is waived - eduroam SP Wi-Fi infrastructure can be managed by the system even on IP connectivity with changing IP addresses (e.g. DSL, mobile networks).</p>
<p>The service includes:</p>
<ul><li>web-based user management interface where eduroam SP deployment details can be created and deleted;</li>
    <li>web-based institution management interface where institutions are enabled or disabled to use the service;</li>
    <li>technical infrastructure ('RADIUS') which accepts RADIUS/UDP requests independently of client IP addresses, processes them according to eduroam Service Definition best practices, and forwards them to eduroam IdPs via the established eduroam roaming infrastructure.</li>
</ul>
<p>The aspects of eduroam SP operation beyond the RADIUS uplink remain in the responsibility of the eduroam SP administrator, and are subject to the eduroam Service Definition as usual. This includes (but is not limited to) local logging of IP leases to MAC addresses in the Enterprise Wi-Fi session, having sufficient Wi-Fi coverage, and making sure the IP uplink works within expected parameters.</p>
<h2>Terms of Use</h2>
<p>In addition to the provisions in your participation agreement with your NRO and the eduroam Service Definition, you undertake to keep confidential the RADIUS server uplink details you receive here (which consist of the tuple IP address, port and shared secret).";
    }

    /**
     * finds a suitable server which is geographically close to the admin
     * 
     * @param array  $adminLocation      the current geographic position of the admin
     * @param string $federation         the federation this deployment belongs to
     * @param array  $blacklistedServers list of server to IGNORE
     * @return string the server ID
     * @throws Exception
     */
    private function findGoodServerLocation($adminLocation, $federation, $blacklistedServers)
    {
        // find a server near him (list of all servers with capacity, ordered by distance)
        // first, if there is a pool of servers specifically for this federation, prefer it
        // only check the consortium pool group we want to attach to
        $cons = $this->consortium;
        $servers = $this->databaseHandle->exec("SELECT server_id, radius_ip4, radius_ip6, location_lon, location_lat FROM managed_sp_servers WHERE pool = ? AND consortium = ?", "ss", $federation, $cons);

        $serverCandidates = [];
        while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $servers)) {
            $maxSupportedClients = DeploymentManaged::MAX_CLIENTS_PER_SERVER;
            if ($iterator->radius_ip4 == NULL || $iterator->radius_ip6 == NULL) {
                // half the amount of IP stacks means half the amount of FDs in use, so we can take twice as many
                $maxSupportedClients = $maxSupportedClients * 2;
            }
            $serverId = $iterator->server_id;
            $clientCount1 = $this->databaseHandle->exec("SELECT port_instance_1 AS tenants1 FROM deployment WHERE radius_instance_1 = ?", "s", $serverId);
            $clientCount2 = $this->databaseHandle->exec("SELECT port_instance_2 AS tenants2 FROM deployment WHERE radius_instance_2 = ?", "s", $serverId);

            $clients = $clientCount1->num_rows + $clientCount2->num_rows;
            if (in_array($iterator->server_id, $blacklistedServers)) {
                continue;
            }
            if ($clients < $maxSupportedClients) {
                $serverCandidates[IdPlist::geoDistance($adminLocation, ['lat' => $iterator->location_lat, 'lon' => $iterator->location_lon])] = $iterator->server_id;
            }
            if ($clients > $maxSupportedClients * 0.9) {
                $this->loggerInstance->debug(1, "A RADIUS server for Managed SP (" . $iterator->server_id . ") is serving at more than 90% capacity!");
            }
        }
        if (count($serverCandidates) == 0 && $federation != "DEFAULT") {
            // we look in the default pool instead
            // recursivity! Isn't that cool!
            return $this->findGoodServerLocation($adminLocation, "DEFAULT", $blacklistedServers);
        }
        if (count($serverCandidates) == 0) {
            throw new Exception("No available server found for new SP in $federation!");
        }
        // put the nearest server on top of the list
        ksort($serverCandidates);
        $this->loggerInstance->debug(1, $serverCandidates);
        return array_shift($serverCandidates);
    }

    /**
     * retrieves usage statistics for the deployment
     * 
     * @param int $backlog how many seconds back in time should we look
     * @param int $limit   max number of entries to retrieve (if supplied, backlog will be ignored)
     * @return array of arrays: activity_time, realm, mac, result
     * @throws Exception
     */
    public function retrieveStatistics(int $backlog, int $limit = 0)
    {
        // find a server near him (list of all servers with capacity, ordered by distance)
        // first, if there is a pool of servers specifically for this federation, prefer it
        // only check the consortium pool group we want to attach to
        // TODO: if we also collect stats from OpenRoaming hosts, differentiate the logs!
        $opName = $this->getOperatorName();
        if ($limit !== 0) {
            $conditional1 = "";
            $conditional2 = "DESC LIMIT $limit";
        } else {
            $conditional1 = "AND activity_time > DATE_SUB(NOW(), INTERVAL $backlog SECOND)";
            $conditional2 = "";
        }
        $stats = $this->databaseHandle->exec("SELECT activity_time, realm, mac, cui, result FROM activity WHERE operatorname = ? $conditional1 ORDER BY activity_time $conditional2", "s", $opName );
        return mysqli_fetch_all($stats, \MYSQLI_ASSOC);
    }
        
    /**
     * initialises a new SP
     * 
     * @return array details of the SP as generated during initialisation
     * @throws Exception
     */
    private function initialise()
    {
        // find out where the admin is located approximately
        $ourLocation = ['lon' => 0, 'lat' => 0];
        $geoip = DeviceLocation::locateDevice();
        if ($geoip['status'] == 'ok') {
            $ourLocation = ['lon' => $geoip['geo']['lon'], 'lat' => $geoip['geo']['lat']];
        }
        $inst = new IdP($this->institution);
        $ourserver = $this->findGoodServerLocation($ourLocation, $inst->federation, []);
        // now, find an unused port in the preferred server
        $foundFreePort1 = 0;
        while ($foundFreePort1 == 0) {
            $portCandidate = random_int(1200, 65535);
            $check = $this->databaseHandle->exec("SELECT port_instance_1 FROM deployment WHERE radius_instance_1 = ? AND port_instance_1 = ?", "si", $ourserver, $portCandidate);
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $check) == 0) {
                $foundFreePort1 = $portCandidate;
            }
        }
        $ourSecondServer = $this->findGoodServerLocation($ourLocation, $inst->federation, [$ourserver]);
        $foundFreePort2 = 0;
        while ($foundFreePort2 == 0) {
            $portCandidate = random_int(1200, 65535);
            $check = $this->databaseHandle->exec("SELECT port_instance_2 FROM deployment WHERE radius_instance_2 = ? AND port_instance_2 = ?", "si", $ourSecondServer, $portCandidate);
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $check) == 0) {
                $foundFreePort2 = $portCandidate;
            }
        }
        // and make up a shared secret that is halfways readable
        $futureSecret = $this->randomString(16, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ");
        $cons = $this->consortium;
        $id = $this->identifier;
        $this->databaseHandle->exec("UPDATE deployment SET radius_instance_1 = ?, radius_instance_2 = ?, port_instance_1 = ?, port_instance_2 = ?, secret = ?, consortium = ? WHERE deployment_id = ?", "ssiissi", $ourserver, $ourSecondServer, $foundFreePort1, $foundFreePort2, $futureSecret, $cons, $id);
        return ["port_instance_1" => $foundFreePort1, "port_instance_2" => $foundFreePort2, "secret" => $futureSecret, "radius_instance_1" => $ourserver, "radius_instance_2" => $ourSecondServer];
    }

    /**
     * update the last_changed timestamp for this deployment
     * 
     * @return void
     */
    public function updateFreshness()
    {
        $id = $this->identifier;
        $this->databaseHandle->exec("UPDATE deployment SET last_change = CURRENT_TIMESTAMP WHERE deployment_id = ?", "i", $id);
    }

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     * 
     * @return string the date in string form, as returned by SQL
     */
    public function getFreshness()
    {
        $id = $this->identifier;
        $execLastChange = $this->databaseHandle->exec("SELECT last_change FROM deployment WHERE deployment_id = ?", "i", $id);
        // SELECT always returns a resource, never a boolean
        if ($freshnessQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $execLastChange)) {
            return $freshnessQuery->last_change;
        }
    }

    /**
     * Deletes the deployment from database
     * 
     * @return void
     */
    public function remove()
    {
        $id = $this->identifier;
        $this->databaseHandle->exec("DELETE FROM deployment_option WHERE deployment_id = ?", "i", $id);
        $this->databaseHandle->exec("DELETE FROM deployment WHERE deployment_id = ?", "i", $id);
    }

    /**
     * marks the deployment as deactivated 
     * 
     * @return void
     */
    public function deactivate()
    {
        $id = $this->identifier;
        $inactive = DeploymentManaged::INACTIVE;
        $this->databaseHandle->exec("UPDATE deployment SET status = ? WHERE deployment_id = ?", "ii", $inactive, $id);
    }
    
    /**
     * marks the deployment as active.
     * 
     * @return void
     */
    public function activate()
    {
        $id = $this->identifier;
        $active = DeploymentManaged::ACTIVE;
        $this->databaseHandle->exec("UPDATE deployment SET status = ? WHERE deployment_id = ?", "ii", $active, $id);
    }

    /**
     * determines the Operator-Name attribute content to use in the RADIUS config
     * 
     * @return string
     */
    public function getOperatorName()
    {
        $customAttrib = $this->getAttributes("managedsp:operatorname");
        if (count($customAttrib) == 0) {
            return "1sp." . $this->identifier . "-" . $this->institution . \config\ConfAssistant::SILVERBULLET['realm_suffix'];
        }
        return $customAttrib[0]["value"];
    }

    /**
     * send request to RADIUS configuration daemom
     *
     * @param  integer $idx  server index 1 (primary) or 2 (backup)
     * @param  string  $post string to POST 
     * @return string  OK or FAILURE
     */
    private function sendToRADIUS(int $idx, $post)
    {

        $hostname = "radius_hostname_$idx";
        $ch = curl_init("http://" . $this->$hostname);
        if ($ch === FALSE) {
            $res = 'FAILURE';
        } else {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $this->loggerInstance->debug(1, "Posting to http://" . $this->$hostname . ": $post\n");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $exec = curl_exec($ch);
            if (!is_string($exec)) {
                $this->loggerInstance->debug(1, "curl_exec failure");
                $res = 'FAILURE';
            } else {
                $res = $exec;
            }
            curl_close($ch);
            $this->loggerInstance->debug(1, "Response from FR configurator: $res\n");
            $this->loggerInstance->debug(1, $this);
        }
        $this->loggerInstance->debug(1, "Database update");
        $id = $this->identifier;
        $resValue = ($res == 'OK' ? \core\AbstractDeployment::RADIUS_OK : \core\AbstractDeployment::RADIUS_FAILURE);
        $this->databaseHandle->exec("UPDATE deployment SET radius_status_$idx = ? WHERE deployment_id = ?", "ii", $resValue, $id);
        return $res;
    }

    /**
     * prepare and send email message to support mail
     *
     * @param  int    $remove   the flag indicating remove request
     * @param  array  $response setRADIUSconfig result
     * @param  string $status   the flag indicating status (FAILURE or OK)
     * @return void
     * 
     */
    private function sendMailtoAdmin($remove, $response, $status)
    {
        $txt = '';
        if ($status == 'OK') {
            $txt = $remove ? _('Profile deactivation succeeded') : _('Profile activation/modification succeeded');
        } else {
            $txt = $remove ? _('Profile deactivation failed') : _('Profile activation/modification failed');
        }
        $txt = $txt . ' ';
        if (array_count_values($response)[$status] == 2) {
            $txt = $txt . _('on both RADIUS servers: primary and backup') . '.';
        } else {
            if ($response['res[1]'] == $status) {
                $txt = $txt . _('on primary RADIUS server') . '.';
            } else {
                $txt = $txt . _('on backup RADIUS server') . '.';
            }
        }
        $mail = \core\common\OutsideComm::mailHandle();
        $email = $this->getAttributes("support:email")[0]['value'];
        $mail->FromName = \config\Master::APPEARANCE['productname'] . " Notification System";
        $mail->addAddress($email);
        if ($status == 'OK') {
            $mail->Subject = _('RADIUS profile update problem fixed');
        } else {
            $mail->Subject = _('RADIUS profile update problem');
        }
        $mail->Body = $txt;
        $sent = $mail->send();
        if ($sent === FALSE) {
            $this->loggerInstance->debug(1, 'Mailing on RADIUS problem failed');
        }
    }

    /**
     * check if URL responds with 200
     *
     * @param integer $idx server index 1 (primary) or 2 (backup)
     * @return integer or NULL
     */
    private function checkURL($idx)
    {
        $ch = curl_init();
        if ($ch === FALSE) {
            return NULL;
        }
        if ($idx == 1) {
            $host = $this->radius_hostname_1;
        } elseif ($idx == 2) {
            $host = $this->radius_hostname_2;
        } else {
            return NULL;
        }
        $timeout = 10;
        curl_setopt($ch, CURLOPT_URL, 'http://' . $host);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 200) {
            return 1;
        }
        return 0;
    }

    /**
     * check whether the configured RADIUS hosts actually exist
     * 
     * @param integer $idx server index 1 (primary) or 2 (backup)
     * @return integer or NULL
     */
    private function testRADIUSHost($idx)
    {
        if ($idx == 1) {
            $host = $this->radius_hostname_1;
        } elseif ($idx == 2) {
            $host = $this->radius_hostname_2;
        } else {
            return NULL;
        }
        $statusServer = new diag\RFC5997Tests($host, \config\Diagnostics::RADIUSSPTEST['port'], \config\Diagnostics::RADIUSSPTEST['secret']);
        $this->loggerInstance->debug(1, $statusServer);
        if ($statusServer->statusServerCheck() === diag\AbstractTest::RETVAL_OK) {
            return 1;
        }
        return 0;
    }

    /**
     * get institution realms
     * 
     * @return array of strings
     */
    private function getAllRealms()
    {
        $idp = new IdP($this->institution);
        $allProfiles = $idp->listProfiles(TRUE);
        $allRealms = [];
        if (($this->getAttributes("managedsp:realmforvlan") ?? NULL)) {
            $allRealms = array_values(array_unique(array_column($this->getAttributes("managedsp:realmforvlan"), "value")));
        }
        foreach ($allProfiles as $profile) {
            if ($realm = ($profile->getAttributes("internal:realm")[0]['value'] ?? NULL)) {
                if (!in_array($realm, $allRealms)) {
                    $allRealms[] = $realm;
                }
            }
        }
        return $allRealms;
    }

    /**
     * check if RADIUS configuration deamon is listening for requests
     *
     * @return array index res[1] indicate primary RADIUS status, index res[2] backup RADIUS status
     */
    public function checkRADIUSHostandConfigDaemon()
    {
        $res = array();
        if ($this->radius_status_1 == \core\AbstractDeployment::RADIUS_FAILURE) {
            $res[1] = $this->checkURL(1);
            if ($res[1]) {
                $res[1] = $this->testRADIUSHost(1);
            }
        }
        if ($this->radius_status_2 == \core\AbstractDeployment::RADIUS_FAILURE) {
            $res[2] = $this->checkURL(2);
            if ($res[2]) {
                $res[2] = $this->testRADIUSHost(2);
            }
        }
        return $res;
    }

    /**
     * prepare request to add/modify RADIUS settings for given deployment
     *
     * @param int $onlyone the flag indicating on which server to conduct modifications
     * @param int $notify  the flag indicating that an email notification should be sent
     * @return array index res[1] indicate primary RADIUS status, index res[2] backup RADIUS status
     */
    public function setRADIUSconfig($onlyone = 0, $notify = 0)
    {
        $remove = ($this->status == \core\AbstractDeployment::INACTIVE) ? 0 : 1;
        $toPost = ($onlyone ? array($onlyone => '') : array(1 => '', 2 => ''));
        $toPostTemplate = 'instid=' . $this->institution . '&deploymentid=' . $this->identifier . '&secret=' . $this->secret . '&country=' . $this->getAttributes("internal:country")[0]['value'] . '&';
        if ($remove) {
            $toPostTemplate = $toPostTemplate . 'remove=1&';
        } else {
            $toPostTemplate = $toPostTemplate . 'operatorname=' . $this->getOperatorName() . '&'; 
            if ($this->getAttributes("managedsp:vlan")[0]['value'] ?? NULL) {
                $allRealms = $this->getAllRealms();
                if (!empty($allRealms)) {
                    $toPostTemplate = $toPostTemplate . 'vlan=' . $this->getAttributes("managedsp:vlan")[0]['value'] . '&';
                    $toPostTemplate = $toPostTemplate . 'realmforvlan[]=' . implode('&realmforvlan[]=', $allRealms) . '&';
                }
            }
        }
        foreach (array_keys($toPost) as $key) {
            $elem = 'port' . $key;
            $toPost[$key] = $toPostTemplate . 'port=' . $this->$elem;
        }
        $response = array();
        foreach ($toPost as $key => $value) {
            $this->loggerInstance->debug(1, 'toPost ' . $toPost[$key] . "\n");
            $response['res[' . $key . ']'] = $this->sendToRADIUS($key, $toPost[$key]);
        }
        if ($onlyone) {
            $response['res[' . ($onlyone == 1) ? 2 : 1 . ']'] = \core\AbstractDeployment::RADIUS_OK;
        }
        foreach (array('OK', 'FAILURE') as $status) {
            if ( ( ($status == 'OK' && $notify) || ($status == 'FAILURE') ) && ( in_array($status, $response) ) ) {
                $this->sendMailtoAdmin($remove, $response, $status);
            }
        }
        return $response;
    }
}
