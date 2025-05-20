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
 * This file contains the ExternalEduroamDBData class. It contains methods for
 * querying the external database.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
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
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class ExternalEduroamDBData extends common\Entity implements ExternalLinkInterface {

    /**
     * List of all service providers. Fetched only once by allServiceProviders()
     * and then stored in this property for efficiency
     * 
     * @var array
     */
    private $SPList = [];

    /**
     * total number of hotspots, cached here for efficiency
     * 
     * @var int
     */
    private $counter = -1;

    /**
     * our handle to the external DB
     * 
     * @var DBConnection
     */
    private $db;
    
    /**
     * our handle to the local DB
     * 
     * @var DBConnection
     */
    private $localDb;
    
    
    /**
     * constructor, gives us access to the DB handle we need for queries
     */
    public function __construct() {
        parent::__construct();
        $connHandle = DBConnection::handle("EXTERNAL");
        if (!$connHandle instanceof DBConnection) {
            throw new Exception("Frontend DB is never an array, always a single DB object.");
        }
        $this->db = $connHandle;
        $localConnHandle = DBConnection::handle("INST");
        if (!$localConnHandle instanceof DBConnection) {
            throw new Exception("Frontend DB is never an array, always a single DB object.");
        }
        $this->localDb = $localConnHandle;
    }

    /**
     * eduroam DB delivers a string with all name variants mangled in one. Pry
     * it apart.
     * 
     * @param string $nameRaw the string with all name variants coerced into one
     * @return array language/name pair
     * @throws Exception
     */
    private function splitNames($nameRaw) {
        $variants = explode('#', $nameRaw);
        $submatches = [];
        $returnArray = [];
        foreach ($variants as $oneVariant) {
            if ($oneVariant == NULL) {
                continue;
            }
            if (!preg_match('/^(..):\ (.*)/', $oneVariant, $submatches) || !isset($submatches[2])) {
                $this->loggerInstance->debug(2, "[$nameRaw] We expect 'xx: bla but found '$oneVariant'.");
                continue;
            }
            $returnArray[$submatches[1]] = $submatches[2];
        }
        return $returnArray;
    }

    /**
     * retrieves the list of all service providers from the eduroam database
     * 
     * @return array list of providers
     */
    public function listAllServiceProviders() {
        if (count($this->SPList) == 0) {
            $query = $this->db->exec("SELECT country, inst_name, sp_location FROM view_active_SP_location_eduroamdb");
            while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $query)) {
                $this->SPList[] = ["country" => $iterator->country, "instnames" => $this->splitNames($iterator->inst_name), "locnames" => $this->splitNames($iterator->sp_location)];
            }
        }
        return $this->SPList;
    }

    public function countAllServiceProviders() {
        if ($this->counter > -1) {
            return $this->counter;
        }

        $cachedNumber = @file_get_contents(ROOT . "/var/tmp/cachedSPNumber.serialised");
        if ($cachedNumber !== FALSE) {
            $numberData = unserialize($cachedNumber);
            $now = new \DateTime();
            $cacheDate = $numberData["timestamp"]; // this is a DateTime object
            $diff = $now->diff($cacheDate);
            if ($diff->y == 0 && $diff->m == 0 && $diff->d == 0) {
                $this->counter = $numberData["number"];
                return $this->counter;
            }
        } else { // data in cache is too old or doesn't exist. We really need to ask the database
            $list = $this->listAllServiceProviders();
            $this->counter = count($list);
            file_put_contents(ROOT . "/var/tmp/cachedSPNumber.serialised", serialize(["number" => $this->counter, "timestamp" => new \DateTime()]));
            return $this->counter;
        }
    }

    public const TYPE_IDPSP = "3";
    public const TYPE_SP = "2";
    public const TYPE_IDP = "1";
    private const TYPE_MAPPING = [
        IdP::TYPE_IDP => ExternalEduroamDBData::TYPE_IDP,
        IdP::TYPE_IDPSP => ExternalEduroamDBData::TYPE_IDPSP,
        IdP::TYPE_SP => ExternalEduroamDBData::TYPE_SP,
    ];
    
    /**
     * separate institution names as written in the eduroam DB into array
     * 
     * @param string $collapsed - '#' separated list of names - each name has
     *      two-letter language prefic followed by ':'
     * @return array $nameList - tle list contains both separate per-lang entires
     *        and a joint one, just names no lang info - this used for comparison with CAT institution names
     */
    public static function dissectCollapsedInstitutionNames($collapsed) {
        $names = explode('#', $collapsed);
        $nameList = [
            'joint' => [],
            'perlang' => [],
        ];
        foreach ($names as $name) {
            $perlang = explode(': ', $name, 2);
            $nameList['perlang'][$perlang[0]] = $perlang[1];
            if (!in_array($perlang[1], $nameList['joint'])) {
                $nameList['joint'][] = mb_strtolower(preg_replace('/^..: /', '', $name), 'UTF-8');
            }
        } 
        return $nameList;
    }

    /**
     * separate institution realms as written in the eduroam DB into array
     * 
     * @param string $collapsed
     * @return array $realmList
     */
    public static function dissectCollapsedInstitutionRealms($collapsed) {
        if ($collapsed === '' || $collapsed === NULL) {
            return [];
        }
        $realmList = explode(',', $collapsed);
        return $realmList;        
    }
    
    /**
     * 
     * @param string $collapsed the blob with contact info from eduroam DB
     * @return array of contacts represented as array name,mail,phone
     */
    public static function dissectCollapsedContacts($collapsed) {
        $contacts = explode('#', $collapsed);
        $contactList = [];
        foreach ($contacts as $contact) {
            $matches = [];
            preg_match("/^n: *([^ ].*), e: *([^ ].*), p: *([^ ].*)$/", $contact, $matches);
            if (!isset($matches[1])) {
                continue;
            }
            $contactList[] = [
                "name" => $matches[1],
                "mail" => $matches[2],
                "phone" => $matches[3]
            ];
        }
        return $contactList;
    }  

    /**
     * retrieves entity information from the eduroam database. Choose whether to get all entities with an SP role, an IdP role, or only those with both roles
     * 
     * @param string      $tld  the top-level domain from which to fetch the entities
     * @param string|NULL $type type of entity to retrieve
     * @return array list of entities
     */
    public function listExternalEntities($tld, $type) {
        if ($type === NULL) {
            $eduroamDbType = NULL;
        } else {
            $eduroamDbType = self::TYPE_MAPPING[$type]; // anything
        }
        $returnarray = [];
        $query = "SELECT instid AS id, country, inst_realm as realmlist, name AS collapsed_name, contact AS collapsed_contact, type FROM view_active_institution WHERE country = ?";
        if ($eduroamDbType !== NULL) {
            $query .= " AND ( type = '" . ExternalEduroamDBData::TYPE_IDPSP . "' OR type = '" . $eduroamDbType . "')";
        }
        $externals = $this->db->exec($query, "s", $tld);
        // was a SELECT query, so a resource and not a boolean
        while ($externalQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $externals)) {
            $names = $this->splitNames($externalQuery->collapsed_name);
            $thelanguage = $names[$this->languageInstance->getLang()] ?? $names["en"] ?? array_shift($names);
            $contacts = $this::dissectCollapsedContacts($externalQuery->collapsed_contact);
            $mails = [];
            foreach ($contacts as $contact) {
                // extracting real names is nice, but the <> notation
                // really gets screwed up on POSTs and HTML safety
                // so better not do this; use only mail addresses
                $mails[] = $contact['mail'];
            }
            $convertedType = array_search($externalQuery->type, self::TYPE_MAPPING);
            $returnarray[] = ["ID" => $externalQuery->id, "name" => $thelanguage, "contactlist" => implode(", ", $mails), "country" => $externalQuery->country, "realmlist" => $externalQuery->realmlist, "type" => $convertedType];
        }
        usort($returnarray, array($this, "usortInstitution"));
        return $returnarray;
    }

    /**
     * retrieves entity information from the eduroam database having the given realm in the inst_realm field
     * Choose which fields to get or get default
     * 
     * @param string      $realm  the realm
     * @param array       $fields list of fields
     * @return array list of entities
     */
    public function listExternalEntitiesByRealm($realm, $fields = []) {
        $returnArray = [];
        $defaultFields = ['instid', 'country', 'inst_realm', 'name', 'contact', 'type'];
        if (empty($fields)) {
            $fields = $defaultFields;
        }
        $forSelect = join(', ', $fields);
        $query = "SELECT $forSelect FROM view_active_institution WHERE inst_realm like '%$realm%'";
        $externals = $this->db->exec($query);
        // was a SELECT query, so a resource and not a boolean
        while ($externalQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $externals)) {
            $record = [];
            foreach ($fields as $field) {
                $record[$field] = $externalQuery->$field;
            }
            $returnArray[] = $record;
        }
        return $returnArray;
    }
    
    /**
     * retrieves the list of identifiers (external and local) of all institutions
     * which have the admin email listed in the externam DB, thos that are synced to an
     * existing CAT institution will also have the local identifier (else NULL)
     * 
     * @param string $userEmail
     * @return array
     */
    
    public function listExternalEntitiesByUserEmail($userEmail){
        $out = [];
        $cat = $this->localDb->dbName;
        $query = "SELECT DISTINCT view_institution_admins.instid, $cat.institution.inst_id,
            UPPER(view_active_institution.country), view_active_institution.name,
            view_active_institution.inst_realm, view_active_institution.type
            FROM view_institution_admins
            JOIN view_active_institution
                ON view_institution_admins.instid = view_active_institution.instid
                AND view_institution_admins.ROid=view_active_institution.ROid
            LEFT JOIN $cat.institution
                ON view_institution_admins.instid=$cat.institution.external_db_id
            WHERE view_active_institution.type != 2 AND view_institution_admins.email= ?";
        $externals = $this->db->exec($query, 's', $userEmail);
        while ($row = $externals->fetch_array()) {
            $external_db_id =  $row[0];
            $inst_id = $row[1];
            $country = $row[2];
            $name = $row[3];
            $realm = $row[4];
            $type = $row[5];
            if (!isset($out[$country])) {
                $out[$country] = [];
            }
            $out[$country][] = ['external_db_id'=>$external_db_id, 'inst_id'=>$inst_id, 'name'=>$name, 'realm'=>$realm, 'type'=>$type];
        }
        return $out;
    }
    
    /**
     * Test if a given external institution exists and if userEmail is provided also
     * check if this mail is listed as the admin for this institutution
     * 
     * @param string $ROid
     * @param string $extId
     * @param string $userEmail
     * @return int 1 if found 0 if not
     */
    public function verifyExternalEntity($ROid, $extId, $userEmail = NULL) {
        $query = "SELECT * FROM view_institution_admins JOIN view_active_institution ON view_institution_admins.instid=view_active_institution.instid AND view_institution_admins.ROid=view_active_institution.ROid WHERE view_active_institution. ROid='$ROid' AND view_institution_admins.instid='$extId'";
        if ($userEmail != NULL) {
            $query .= " AND email='$userEmail'";
        }
        $result = $this->db->exec($query);
        if (mysqli_num_rows(/** @scrutinizer ignore-type */ $result) > 0) {
            return 1;
        } else {
            return 0;
        }
    }  
    
    /**
     * 
     * @return array
     */
    public function listExternalRealms() {
        return $this->listExternalEntitiesByRealm(""); // leaing realm empty gets *all*
    }

    /**
     * helper function to sort institutions by their name
     * 
     * @param array $a an array with institution a's information
     * @param array $b an array with institution b's information
     * @return int the comparison result
     */
    private function usortInstitution($a, $b) {
        return strcasecmp($a["name"], $b["name"]);
    }

    /**
     * get all RADIUS/TLS servers for a given federation, with contacts
     * [ hostnames => contacts ]
     * (hostnames are comma-separated)
     * 
     * @return array
     */
    public function listExternalTlsServersFederation($tld) {
        $retval = [];
        // this includes servers of type "staging", which is fine
        $query = "SELECT servers, contacts FROM view_tls_ro WHERE country = ? AND servers IS NOT NULL AND contacts IS NOT NULL";
        $roTldServerTransaction = $this->db->exec($query, "s", $tld);
        while ($roServerResponses = mysqli_fetch_object(/** @scrutinizer ignore-type */ $roTldServerTransaction)) {
            // there is only one row_id
            $retval[$roServerResponses->servers] = $this::dissectCollapsedContacts($roServerResponses->contacts);
        }
        return $retval;
    }

    /**
     * get all RADIUS/TLS servers for all institutions within a given federation
     * including their contact details
     * 
     * "ROid-instid" => [type, inst_name, servers, contacts]
     * 
     * (hostnames are comma-separated)
     * 
     * @return array
     */
    public function listExternalTlsServersInstitution($tld, $include_not_ready=FALSE) {
        $retval = [];
        // this includes servers of type "staging", which is fine
        $query = "SELECT ROid, instid, type, inst_name, servers, contacts, ts FROM view_tls_inst WHERE country = ?";
        if (!$include_not_ready) {
            $query = $query . " AND servers IS NOT NULL AND contacts IS NOT NULL";
        }
        $instServerTransaction = $this->db->exec($query, "s", $tld);
        while ($instServerResponses = mysqli_fetch_object(/** @scrutinizer ignore-type */ $instServerTransaction)) {
            $contactList = $this::dissectCollapsedContacts($instServerResponses->contacts);
            $names = $this->splitNames($instServerResponses->inst_name);
            $thelanguage = $names[$this->languageInstance->getLang()] ?? $names["en"] ?? array_shift($names);
            $retval[$instServerResponses->ROid . "-". $instServerResponses->instid] = [
                "names" => $names,
                "name" => $thelanguage,
                "type" => array_search($instServerResponses->type, self::TYPE_MAPPING),
                "servers" => $instServerResponses->servers,
                "contacts" => $contactList,
                "ts" => $instServerResponses->ts];
        }
        uasort($retval, array($this, "usortInstitution"));
        return $retval;        
    }     
}
