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
 * When used with an external DB such as the official eduroam DB, this interface
 * must be implemented by the corresponding class
 */
interface ExternalLinkInterface
{

    /**
     * gets a list of all hotspots from the external DB
     * 
     * @return array
     */
    public function listAllServiceProviders();

    /**
     * counts the SPs
     * 
     * @return int
     */
    public function countAllServiceProviders();

    /**
     * enumerates all participating entities in the external DB
     * 
     * @param string $tld  the country to list
     * @param string $type the type to list (see IdP TYPE_ constants)
     * 
     * @return array
     */
    public function listExternalEntities($tld, $type);

    /**
     * find an institution by its realm
     * 
     * @param string      $realm  the realm
     * @param array       $fields list of fields
     * @return array list of entities
     */    
    public function listExternalEntitiesByRealm($realm, $fields = []);
                
    /**
     * get all the realms we know about
     * 
     * @return array
     */
    public function listExternalRealms();
}