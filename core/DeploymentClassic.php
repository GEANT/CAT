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
class DeploymentClassic extends AbstractDeployment
{

    /**
     * Class constructor for existing deployments (use 
     * IdP::newDeployment() to actually create one). Retrieves all 
     * attributes from the DB and stores them in the priv_ arrays.
     * 
     * @param IdP        $idpObject       optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     * @param string|int $deploymentIdRaw identifier of the deployment in the DB, or 
     */
    public function __construct($idpObject, $deploymentIdRaw = NULL)
    {
        parent::__construct($idpObject, $deploymentIdRaw); // we now have access to our INST database handle and logging
        $this->type = AbstractDeployment::DEPLOYMENTTYPE_MANAGED;
        // TODO we need to extract the SP's relevant information from the eduroam DB
        $propertyQuery = "SELECT ... FROM ... WHERE ...";
        $queryExec = $this->databaseHandle->exec($propertyQuery);
        while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $queryExec)) {
            $this->status = $iterator->status;
        }
    }

    /**
     * update the last_changed timestamp for this deployment
     * 
     * @return void
     */
    public function updateFreshness()
    {
        // we are always fresh - data comes from eduroam DB
    }

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     * 
     * @return string the date in string form, as returned by SQL
     */
    public function getFreshness()
    {
        // we are always fresh - data comes from eduroam DB
        $execLastChange = $this->databaseHandle->exec("SELECT NOW() as last_change");
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
    public function destroy()
    {
        // we can't delete data from the eduroam DB
    }

    /**
     * activates a deployment, but that is not how classic works
     * 
     * @return void
     */
    public function activate()
    {
        // nothing to be done, this is managed externally
    }

    /**
     * deactivates a deployment, but that is not how classic works
     * 
     * @return void
     */
    public function deactivate()
    {
        // nothing to be done, this is managed externally
    }
}