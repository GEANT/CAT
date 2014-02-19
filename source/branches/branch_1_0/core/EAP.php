<?php

/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the EAP class and some constants for EAP types.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 * 
 */
/**
 * some constants. Will PHPDoc render these nicely?
 */
define("PEAP", 25);
define("MSCHAP2", 26);
define("TTLS", 21);
define("TLS", 13);
define("NONE", 0);
define("GTC", 6);
define("FAST", 43);
define("PWD", 52);

require_once('DBConnection.php');

/**
 * Convenience functions for EAP types
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class EAP {
    
    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $DB_TYPE = "INST";
    
    /* constants only work for simple types. So these arrays need to be variables. :-(
      Don't ever change them though. */

    /**
     * PEAP-MSCHAPv2: Outer EAP Type = 25, Inner EAP Type = 26
     *
     * @var array of EAP type IDs that describe PEAP-MSCHAPv2
     */
    public static $PEAP_MSCHAP2 = array("OUTER" => PEAP, "INNER" => MSCHAP2);

    /**
     * EAP-TLS: Outer EAP Type = 13, no inner EAP
     *
     * @var array of EAP type IDs that describe EAP-TLS
     */
    public static $TLS = array("OUTER" => TLS, "INNER" => NONE);

    /**
     * TTLS-PAP: Outer EAP type = 21, no inner EAP
     * 
     * @var array of EAP type IDs that describe TTLS-PAP
     */
    public static $TTLS_PAP = array("OUTER" => TTLS, "INNER" => NONE);

    /**
     * TTLS-MSCHAP-v2: Outer EAP type = 21, Inner EAP Type = 26
     * 
     * @var array of EAP type IDs that describe TTLS-MSCHAPv2
     */
    public static $TTLS_MSCHAP2 = array("OUTER" => TTLS, "INNER" => MSCHAP2);

    /**
     * TTLS-GTC: Outer EAP type = 21, Inner EAP Type = 6
     * 
     * @var array of EAP type IDs that describe TTLS-GTC
     */
    public static $TTLS_GTC = array("OUTER" => TTLS, "INNER" => GTC);

    /**
     * EAP-FAST (GTC): Outer EAP type = 43, Inner EAP Type = 6
     * 
     * @var array of EAP type IDs that describe EAP-FAST (GTC)
     */
    public static $FAST_GTC = array("OUTER" => FAST, "INNER" => GTC);

    /**
     * PWD: Outer EAP type = 52, no inner EAP
     * 
     * @var array of EAP type IDs that describe EAP-PWD
     */
    public static $PWD = array("OUTER" => PWD, "INNER" => NONE);

    /**
     * NULL: no outer EAP, no inner EAP
     * 
     * @var array of EAP type IDs that describes the NULL EAP Method
     */
    public static $EAP_NONE = array("OUTER" => NONE, "INNER" => NONE);

    /**
     * This function takes the EAP method in array representation (OUTER/INNER) and returns it in a custom format for the
     * Linux installers (not numbers, but strings as values).
     * @param array EAP method in array representation (OUTER/INNER)
     * @return array EAP method in array representation (OUTER as string/INNER as string)
     */
    public static function eapDisplayName($eap) {
        $EAP_DISPLAY_NAME = array();
        $EAP_DISPLAY_NAME[serialize(EAP::$PEAP_MSCHAP2)] = array("OUTER" => 'PEAP', "INNER" => 'MSCHAPV2');
        $EAP_DISPLAY_NAME[serialize(EAP::$TLS)] = array("OUTER" => 'TLS', "INNER" => '');
        $EAP_DISPLAY_NAME[serialize(EAP::$TTLS_PAP)] = array("OUTER" => 'TTLS', "INNER" => 'PAP');
        $EAP_DISPLAY_NAME[serialize(EAP::$TTLS_MSCHAP2)] = array("OUTER" => 'TTLS', "INNER" => 'MSCHAPV2');
        $EAP_DISPLAY_NAME[serialize(EAP::$TTLS_GTC)] = array("OUTER" => 'TTLS', "INNER" => 'GTC');
        $EAP_DISPLAY_NAME[serialize(EAP::$FAST_GTC)] = array("OUTER" => 'FAST', "INNER" => 'GTC');
        $EAP_DISPLAY_NAME[serialize(EAP::$PWD)] = array("OUTER" => 'EAP-pwd', "INNER" => '');
        $EAP_DISPLAY_NAME[serialize(EAP::$EAP_NONE)] = array("OUTER" => '', "INNER" => '');
        return($EAP_DISPLAY_NAME[serialize($eap)]);
    }

    /**
     * This function retrieves all known EAP types from the database and returns them as an array
     * 
     * @return array of all EAP types the CAT knows about (as stored in the database)
     */
    public static function listKnownEAPTypes() {
        $returnarray = array();
        $methods = DBConnection::exec(EAP::$DB_TYPE, "SELECT php_serialized FROM eap_method ORDER BY display_name");
        while ($a = mysqli_fetch_object($methods))
            $returnarray[] = unserialize($a->php_serialized);
        return $returnarray;
    }

    /**
     * Returns the (integer) row number in the database for a given EAP type.
     * If not found in DB, returns FALSE
     * 
     * @param array $method_array
     * @return mixed
     */
    public static function EAPMethodIdFromArray($method_array) {
        // TODO the row index of the EAP type should never be needed. Rewrite the callers of this function to not need that
        $exec_q = DBConnection::exec(EAP::$DB_TYPE, "SELECT eap_method_id FROM eap_method WHERE php_serialized = '" . serialize($method_array) . "'");
        while ($a = mysqli_fetch_object($exec_q))
            return $a->eap_method_id;
        return FALSE;
    }

}

?>
