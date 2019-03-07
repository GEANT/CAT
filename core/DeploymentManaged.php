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
class DeploymentManaged extends AbstractDeployment {

    const MAX_CLIENTS_PER_SERVER = 1000;

    const PRODUCTNAME = "Managed SP";
    /**
     * the RADIUS port for this SP instance
     * 
     * @var int
     */
    public $port;

    /**
     * the shared secret for this SP instance
     * 
     * @var string
     */
    public $secret;

    /**
     * the IPv4 address of the RADIUS server for this SP instance (can be NULL)
     * 
     * @var string
     */
    public $host4;
    
    /**
     * the IPv6 address of the RADIUS server for this SP instance (can be NULL)
     * 
     * @var string
     */
    public $host6;
    /**
     * the RADIUS server instance for this SP instance
     * 
     * @var string
     */
    public $radius_instance;

    /**
     * Class constructor for existing deployments (use 
     * IdP::newDeployment() to actually create one). Retrieves all 
     * attributes from the DB and stores them in the priv_ arrays.
     * 
     * @param IdP        $idpObject       optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     * @param string|int $deploymentIdRaw identifier of the deployment in the DB
     */
    public function __construct($idpObject, $deploymentIdRaw) {
        parent::__construct($idpObject, $deploymentIdRaw); // we now have access to our INST database handle and logging
        $this->entityOptionTable = "deployment_option";
        $this->entityIdColumn = "deployment_id";
        $this->type = AbstractDeployment::DEPLOYMENTTYPE_MANAGED;
        if (!is_numeric($deploymentIdRaw)) {
            throw new Exception("Managed SP instances have to have a numeric identifier");
        }
        $propertyQuery = "SELECT status,port,secret,radius_instance FROM deployment WHERE deployment_id = ?";
        $queryExec = $this->databaseHandle->exec($propertyQuery, "i", $deploymentIdRaw);
        while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $queryExec)) {
            if ($iterator->secret == NULL && $iterator->radius_instance == NULL) {
                // we are instantiated for the first time, initialise us
                $details = $this->initialise();
                $this->port = $details["port"];
                $this->secret = $details["secret"];
                $this->radius_instance = $details["radius_instance"];
                $this->status = AbstractDeployment::INACTIVE;
            } else {
                $this->port = $iterator->port;
                $this->secret = $iterator->secret;
                $this->radius_instance = $iterator->radius_instance;
                $this->status = $iterator->status;
            }
        }
        $serverdetails = $this->databaseHandle->exec("SELECT radius_ip4, radius_ip6 FROM managed_sp_servers WHERE server_id = '$this->radius_instance'");
        while ($iterator2 = mysqli_fetch_object(/** @scrutinizer ignore-type */ $serverdetails)) {
            $this->host4 = $iterator2->radius_ip4;
            $this->host6 = $iterator2->radius_ip6;
        }
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = ?  
                                            ORDER BY option_name", "Profile");
    }

    /**
     * initialises a new SP
     * 
     * @return array details of the SP as generated during initialisation
     * @throws Exception
     */
    private function initialise() {
        // find a server near us (list of all servers, ordered by distance)
        $servers = $this->databaseHandle->exec("SELECT server_id, location_lon, location_lat FROM managed_sp_servers");
        $ourserver = [];
        // TODO: for ease of prototyping, no particular order - add location-based selection later
        while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $servers)) {
            $clientCount = $this->databaseHandle->exec("SELECT count(port) AS tenants FROM deployment WHERE radius_instance = '$iterator->server_id'");
            while ($iterator2 = mysqli_fetch_object(/** @scrutinizer ignore-type */ $clientCount)) {
                $clients = $iterator2->tenants;
                if ($clients < DeploymentManaged::MAX_CLIENTS_PER_SERVER) {
                    $ourserver[] = $iterator->server_id;
                }
                if ($clients > DeploymentManaged::MAX_CLIENTS_PER_SERVER * 0.9) {
                    $this->loggerInstance->debug(1, "A RADIUS server for Managed SP (" . $iterator->server_id . ") is serving at more than 90% capacity!");
                }
            }
        }
        if (count($ourserver) == 0) {
            throw new Exception("No available server found for new SP!");
        }
        // now, find an unused port in our preferred server
        $foundFreePort = 0;
        while ($foundFreePort == 0) {
            $portCandidate = random_int(1025, 65535);
            $check = $this->databaseHandle->exec("SELECT port FROM deployment WHERE radius_instance = '" . $ourserver[0] . "' AND port = $portCandidate");
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $check) == 0) {
                $foundFreePort = $portCandidate;
            }
        };
        // and make up a shared secret that is halfways readable
        $futureSecret = $this->randomString(16, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ");
        $this->databaseHandle->exec("UPDATE deployment SET radius_instance = '".$ourserver[0]."', port = $foundFreePort, secret = '$futureSecret' WHERE deployment_id = $this->identifier");
        return ["port" => $foundFreePort, "secret" => $futureSecret, "radius_instance" => $ourserver[0]];
    }

    /**
     * update the last_changed timestamp for this deployment
     * 
     * @return void
     */
    public function updateFreshness() {
        $this->databaseHandle->exec("UPDATE deployment SET last_change = CURRENT_TIMESTAMP WHERE deployment_id = $this->identifier");
    }

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     * 
     * @return string the date in string form, as returned by SQL
     */
    public function getFreshness() {
        $execLastChange = $this->databaseHandle->exec("SELECT last_change FROM deployment WHERE deployment_id = $this->identifier");
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
    public function destroy() {
        $this->databaseHandle->exec("DELETE FROM deployment_option WHERE deployment_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM deployment WHERE deployment_id = $this->identifier");
    }

    /**
     * deactivates the deployment.
     * TODO: needs to call the RADIUS server reconfiguration routines...
     * 
     * @return void
     */
    public function deactivate() {
        $this->databaseHandle->exec("UPDATE deployment SET status = ".DeploymentManaged::INACTIVE." WHERE deployment_id = $this->identifier");
    }
    
    /**
     * activates the deployment.
     * TODO: needs to call the RADIUS server reconfiguration routines...
     * 
     * @return void
     */
    public function activate() {
        $this->databaseHandle->exec("UPDATE deployment SET status = ".DeploymentManaged::ACTIVE." WHERE deployment_id = $this->identifier");
    }
}
