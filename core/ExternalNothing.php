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
 * This file contains the ExternalNothing class. It contains dummy methods for
 * the lack of an external entity database.
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
 * This class interacts with the external DB to fetch operational data for
 * read-only purposes.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class ExternalNothing implements ExternalLinkInterface
{

    /**
     * constructor, gives us access to the DB handle we need for queries
     */
    public function __construct()
    {
        
    }

    /**
     * retrieves the list of all service providers from the eduroam database
     * 
     * @return array list of providers
     */
    public function listAllServiceProviders()
    {
        return [];
    }

    /**
     * nothing to count here, please move along
     * 
     * @return int
     */
    public function countAllServiceProviders(): int
    {
        return 0;
    }
    
    public function listExternalEntitiesByRealm($realm, $fields = []): array
    {
        return [];
    }
    /**
     * retrieves entity information from the eduroam database. Choose whether to get all entities with an SP role, an IdP role, or only those with both roles
     * 
     * @param string      $tld  the top-level domain from which to fetch the entities
     * @param string|NULL $type type of entity to retrieve
     * @return array list of entities
     */
    public function listExternalEntities($tld, $type)
    {
        unset($tld); // not needed
        unset($type); // not needed
        return [];
    }
 
    /**
     * get all the realms from the external DB
     * 
     * @return array
     */
    public function listExternalRealms()
    {
        return $this->listExternalEntitiesByRealm("");
    }
}