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
abstract class AbstractDeployment extends EntityWithDBProperties
{

    const INACTIVE = 0;
    const ACTIVE = 1;
    const DEPLOYMENTTYPE_CLASSIC = "RADIUS-SP";
    const DEPLOYMENTTYPE_MANAGED = "MANAGED-SP";
    const RADIUS_OK = 1;
    const RADIUS_FAILURE = 2;

    /**
     * status of this deployment. Defaults to INACTIVE.
     * 
     * @var integer
     */
    public $status = AbstractDeployment::INACTIVE;

    /**
     * which type of deployment is this. Not initialised, done by sub-classes.
     * 
     * @var string
     */
    public $type;

    /**
     * DB identifier of the parent institution of this profile
     * @var integer
     */
    public $institution;

    /**
     * name of the parent institution of this profile in the current language
     * @var string
     */
    public $instName;

    /**
     * number of deployments the IdP this profile is attached to has
     * 
     * @var integer
     */
    protected $idpNumberOfDeployments;

    /**
     * IdP-wide attributes of the IdP this profile is attached to
     * 
     * @var array
     */
    protected $idpAttributes;

    /**
     * Federation level attributes that this profile is attached to via its IdP
     * 
     * @var array
     */
    protected $fedAttributes;

    /**
     * This class also needs to handle frontend operations, so needs its own
     * access to the FRONTEND datbase. This member stores the corresponding 
     * handle.
     * 
     * @var DBConnection
     */
    protected $frontendHandle;

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
        $this->databaseType = "INST";
        parent::__construct(); // we now have access to our INST database handle and logging
        $connHandle = DBConnection::handle("FRONTEND");
        if (!$connHandle instanceof DBConnection) {
            throw new Exception("Frontend DB is never an array, always a single DB object.");
        }
        $this->frontendHandle = $connHandle;
        $idp = $idpObject;
        $this->institution = $idp->identifier;
        if ($deploymentIdRaw !== NULL && is_int($deploymentIdRaw)) {
            $this->identifier = $deploymentIdRaw;
        }
        $this->instName = $idp->name;
        $this->idpNumberOfDeployments = $idp->deploymentCount();
        $this->idpAttributes = $idp->getAttributes();
        $fedObject = new Federation($idp->federation);
        $this->fedAttributes = $fedObject->getAttributes();
        $this->loggerInstance->debug(3, "--- END Constructing new AbstractDeployment object ... ---\n");
    }

    /**
     * update the last_changed timestamp for this deployment
     * 
     * @return void
     */
    abstract public function updateFreshness();

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     * 
     * @return string the date in string form, as returned by SQL
     */
    abstract public function getFreshness();

    /**
     * Deletes the deployment from database
     * 
     * @return void
     */
    abstract public function destroy();

    /**
     * Deactivates the deployment
     * 
     * @return void
     */
    abstract public function deactivate();

    /**
     * activates the deployment
     * 
     * @return void
     */
    abstract public function activate();

    /**
     * check if RADIUS configuration deamon is listening for requests
     *
     * @return array index res[1] indicate primary RADIUS status, index res[2] backup RADIUS status
     */
    abstract public function checkRADIUSHostandConfigDaemon();

    /**
     * prepare request to add/modify RADIUS settings for given deployment
     *
     * @param int $onlyone the flag indicating on which server to conduct modifications
     * @param int $notify  the flag indicating that an admin email should be sent
     * @return array index res[1] indicate primary RADIUS status, index res[2] backup RADIUS status
     */
    abstract public function setRADIUSconfig($onlyone = 0, $notify = 0);
}
