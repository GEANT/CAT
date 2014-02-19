<?php
/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
/**
 * This file contains the Federation class.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package Developer
 * 
 */

/**
 * necessary includes
 */
require_once("CAT.php");
require_once('IdP.php');

/**
 * This class represents an consortium federation.
 * It is semantically a country(!). Do not confuse this with a TLD; a federation
 * may span more than one TLD, and a TLD may be distributed across multiple federations.
 *
 * Example: a federation "fr" => "France" may also contain other TLDs which
 *              belong to France in spite of their different TLD
 * Example 2: Domains ending in .edu are present in multiple different
 *              federations
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class Federation {

    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $DB_TYPE = "INST";

    /**
     * all known federation, in an array with ISO short name as an index, and localised version of the pretty-print name as value.
     * The static value is only filled with meaningful content after the first object has been instantiated. That is because it is not
     * possible to define static properties with function calls like _().
     * 
     * @var array of all known federations
     */
    public static $FederationList = array();

    /**
     *
     * Constructs a Federation object.
     *
     * @param string $fedname - textual representation of the Federation object
     *        Example: "lu" (for Luxembourg)
     */
    public function __construct($fedname = 0) {
        /* Federations are created in DB with bootstrapFederation, and listed via listFederations
         */
        $oldlocale = CAT::set_locale('core');
        $this->identifier = $fedname;
        Federation::$FederationList = array(
            'AD' => _("Andorra"),
            'AT' => _("Austria"),
            'BE' => _("Belgium"),
            'BG' => _("Bulgaria"),
            'CY' => _("Cyprus"),
            'CZ' => _("Czech Republic"),
            'DK' => _("Denmark"),
            'EE' => _("Estonia"),
            'FI' => _("Finland"),
            'FR' => _("France"),
            'DE' => _("Germany"),
            'GR' => _("Greece"),
            'HR' => _("Croatia"),
            'IE' => _("Ireland"),
            'IS' => _("Iceland"),
            'IT' => _("Italy"),
            'HU' => _("Hungary"),
            'LU' => _("Luxembourg"),
            'LV' => _("Latvia"),
            'LT' => _("Lithuania"),
            'MK' => _("Macedonia"),
            'RS' => _("Serbia"),
            'NL' => _("Netherlands"),
            'NO' => _("Norway"),
            'PL' => _("Poland"),
            'PT' => _("Portugal"),
            'RO' => _("Romania"),
            'SI' => _("Slovenia"),
            'ES' => _("Spain"),
            'SE' => _("Sweden"),
            'SK' => _("Slovakia"),
            'CH' => _("Switzerland"),
            'TR' => _("Turkey"),
            'UK' => _("United Kingdom"),
            'TEST' => 'TEST Country',
            'AU' => _("Australia"),
            'CA' => _("Canada"),
            'IL' => _("Israel"),
            'JP' => _("Japan"),
            'NZ' => _("New Zealand"),
            'US' => _("U.S.A."),
            'BR' => _("Brazil"),
            'CL' => _("Chile"),
            'PE' => _("Peru"),
            'VE' => _("Venezuela"),
            'DEFAULT' => _("Default"),
        );

        CAT::set_locale($oldlocale);
    }

    /**
     * Creates a new IdP inside the federation.
     * 
     * @param string $owner_id Persistent identifier of the user for whom this IdP is created (first administrator)
     * @param string $level Privilege level of the first administrator (was he blessed by a federation admin or a peer?)
     * @param string $mail e-mail address with which the user was invited to administer (useful for later user identification if the user chooses a "funny" real name)
     * @return int identifier of the new IdP
     */
    public function newIdP($owner_id, $level, $mail) {
        DBConnection::exec(Federation::$DB_TYPE, "INSERT INTO institution (country) VALUES('$this->identifier')");
        $identifier = DBConnection::lastID(Federation::$DB_TYPE);
        if ($identifier == 0 || !CAT::writeAudit($owner_id, "NEW", "IdP $identifier")) {
            echo "<p>" . _("Could not create a new Institution!") . "</p>";
            exit(1);
        }
        DBConnection::exec(Federation::$DB_TYPE, "INSERT INTO ownership (user_id,institution_id, blesslevel, orig_mail) VALUES('$owner_id', $identifier, '$level', '$mail')");
        return $identifier;
    }

    /**
     * Textual short-hand representation of this Federation
     *
     * @var string (Example: "fr")
     *
     */
    public $identifier;

    /**
     * Lists all Identity Providers in this federation
     *
     * @param int $active_only if set to non-zero will list only those institutions which have some valid profiles defined.
     * @return array (Array of IdP instances)
     *
     */
    public function listIdentityProviders($active_only = 0) {
        if ($active_only) {
            $allIDPs = DBConnection::exec(Federation::$DB_TYPE, "SELECT distinct institution.inst_id AS inst_id
               FROM institution
               JOIN profile ON institution.inst_id = profile.inst_id
               WHERE institution.country = '$this->identifier' 
               AND profile.showtime = 1
               ORDER BY inst_id");
        } else {
            $allIDPs = DBConnection::exec(Federation::$DB_TYPE, "SELECT inst_id FROM institution
               WHERE country = '$this->identifier' ORDER BY inst_id");
        }

        $returnarray = array();
        while ($a = mysqli_fetch_object($allIDPs)) {
            $idp = new IdP($a->inst_id);
            $name = $idp->name;
            $A = array('entityID' => $idp->identifier,
                'title' => $name,
                'country' => strtoupper($idp->federation),
                'instance' => $idp);
            $returnarray[$idp->identifier] = $A;
        }
        return $returnarray;
    }


    public function listFederationAdmins() {
        $returnarray = Array();
        if (Config::$CONSORTIUM['name'] == "eduroam" && Config::$DB['INST']['host'] == "monitor.eduroam.org") // SW: sigh. eduroam DB needs different query. APPROVED
            $admins = DBConnection::exec("USER", "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = '".strtolower ($this->identifier)."'");
        else
            $admins = DBConnection::exec("USER", "SELECT user_id FROM user_options WHERE option_name = 'user:fedadmin' AND option_value = '".strtoupper($this->identifier)."'");
            
       while ($a = mysqli_fetch_object($admins))
           $returnarray[] = $a->user_id;
       return $returnarray;
    }
    
    public function listUnmappedExternalEntities() {
        $returnarray = array();
        if (Config::$CONSORTIUM['name'] == "eduroam") { // SW: APPROVED
        $usedarray = array();
        $externals = DBConnection::exec("EXTERNAL", "SELECT id_institution AS id, name AS collapsed_name 
                                                                                FROM view_active_idp_institution 
                                                                                WHERE country = '" . strtolower($this->identifier) . "'");
        $already_used = DBConnection::exec(Federation::$DB_TYPE, "SELECT DISTINCT external_db_id FROM institution 
                                                                                                     WHERE external_db_id IS NOT NULL 
                                                                                                     AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
        $pending_invite = DBConnection::exec(Federation::$DB_TYPE, "SELECT DISTINCT external_db_uniquehandle FROM invitations 
                                                                                                      WHERE external_db_uniquehandle IS NOT NULL 
                                                                                                      AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) 
                                                                                                      AND used = 0");
        while ($a = mysqli_fetch_object($already_used))
            $usedarray[] = $a->external_db_id;
        while ($a = mysqli_fetch_object($pending_invite))
            if (!in_array($a->external_db_uniquehandle,$usedarray))
                    $usedarray[] = $a->external_db_uniquehandle;
        while ($a = mysqli_fetch_object($externals)) {
            if (in_array($a->id, $usedarray))
                continue;
            $names = explode('#', $a->collapsed_name);
            foreach ($names as $name) {
                $perlang = explode(': ', $name, 2);
                $returnarray[] = array("ID" => $a->id, "lang" => $perlang[0], "name" => $perlang[1]);
            }
        }
        }
        return $returnarray;
    }

    public static function getExternalDBEntityDetails($external_id) {
        $list = array();
        if (Config::$CONSORTIUM['name'] == "eduroam") { // SW: APPROVED
            $info_list = DBConnection::exec("EXTERNAL", "SELECT name AS collapsed_name, contact AS collapsed_contact, country FROM view_active_idp_institution WHERE id_institution = $external_id");
            // split names and contacts into proper pairs
            while ($a = mysqli_fetch_object($info_list)) {
                $names = explode('#', $a->collapsed_name);
                foreach ($names as $name) {
                    $perlang = explode(': ', $name, 2);
                    $list['names'][$perlang[0]] = $perlang[1];
                }
                $contacts = explode('#', $a->collapsed_contact);
                foreach ($contacts as $contact) {
                    $email_1 = explode('e: ', $contact);
                    $email_2 = explode(',', $email_1[1]);
                    $list['admins'][] = array("email" => $email_2[0]);
                }
                $list['country'] = $a->country;
            }
            
        }
        return $list;
    }
    
    /**
     * Lists all identity providers in the database
     * adding information required by DiscoJuice.
     * @param int $active_only if and set to non-zero will
     * cause listing of only those institutions which have some valid profiles defined.
     *
     */
    public static function listAllIdentityProviders($active_only = 0, $country = 0) {
        $query = "SELECT distinct institution.inst_id AS inst_id FROM institution JOIN profile 
                          ON institution.inst_id = profile.inst_id" .
                ($active_only ? " WHERE profile.showtime = 1" : "") ;
        if($country) {
            $query .= ($active_only ? " AND" : " WHERE");
            $query .= " institution.country = '$country'";
        }
         $query .= " ORDER BY inst_id";
        $allIDPs = DBConnection::exec(Federation::$DB_TYPE, $query);
        $returnarray = array();
        while ($a = mysqli_fetch_object($allIDPs)) {
            $idp = new IdP($a->inst_id);
            $name = $idp->name;
            if (!$name)
                continue;
            $A = array('entityID' => $idp->identifier,
                'title' => $name, 'country' => strtoupper($idp->federation));
            $at = $idp->getAttributes('general:geo_coordinates');
            if ($at) {
                if (count($at) > 1) {
                    $at1 = array();
                    foreach ($at as $a)
                        $at1[] = unserialize($a['value']);
                } else
                    $at1 = unserialize($at[0]['value']);
                $A['geo'] = $at1;
            }
            $at = $idp->getAttributes('general:logo_file');
            if ($at) {
                $A['icon'] = $idp->identifier;
            }
            $returnarray[] = $A;
        }
        return $returnarray;
    }

}

?>
