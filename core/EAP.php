<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
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
define("NE_PAP", 1);
define("NE_MSCHAP", 2);
define("NE_MSCHAP2", 3);
define("NE_SILVERBULLET", 999);

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
const INTEGER_TTLS_PAP = 1;
const INTEGER_PEAP_MSCHAPv2 = 2;
const INTEGER_TLS = 3;
const INTEGER_FAST_GTC = 4;
const INTEGER_TTLS_GTC = 5;
const INTEGER_TTLS_MSCHAPv2 = 6;
const INTEGER_EAP_pwd = 7;
const INTEGER_SILVERBULLET = 8;

// PHP7 allows to define constants with arrays as value. Hooray! This makes
// lots of public static members of the EAP class obsolete

/**
 * PEAP-MSCHAPv2: Outer EAP Type = 25, Inner EAP Type = 26
 */
const EAPTYPE_PEAP_MSCHAP2 = ["OUTER" => PEAP, "INNER" => MSCHAP2];

/**
 * EAP-TLS: Outer EAP Type = 13, no inner EAP
 */
const EAPTYPE_TLS = ["OUTER" => TLS, "INNER" => NONE];

/**
 * EAP-TLS: Outer EAP Type = 13, no inner EAP
 */
const EAPTYPE_SILVERBULLET = ["OUTER" => TLS, "INNER" => NE_SILVERBULLET];

/**
 * TTLS-PAP: Outer EAP type = 21, no inner EAP, inner non-EAP = 1
 */
const EAPTYPE_TTLS_PAP = ["OUTER" => TTLS, "INNER" => NONE];

/**
 * TTLS-MSCHAP-v2: Outer EAP type = 21, no inner EAP, inner non-EAP = 3
 */
const EAPTYPE_TTLS_MSCHAP2 = ["OUTER" => TTLS, "INNER" => MSCHAP2];

/**
 * TTLS-GTC: Outer EAP type = 21, Inner EAP Type = 6
 */
const EAPTYPE_TTLS_GTC = ["OUTER" => TTLS, "INNER" => GTC];

/**
 * EAP-FAST (GTC): Outer EAP type = 43, Inner EAP Type = 6
 */
const EAPTYPE_FAST_GTC = ["OUTER" => FAST, "INNER" => GTC];

/**
 * PWD: Outer EAP type = 52, no inner EAP
 */
const EAPTYPE_PWD = ["OUTER" => PWD, "INNER" => NONE];

/**
 * NULL: no outer EAP, no inner EAP
 */
const EAPTYPE_NONE = ["OUTER" => NONE, "INNER" => NONE];

/**
 *  ANY: not really an EAP method, but the term to use when needing to express "any EAP method we know"
 */
const EAPTYPE_ANY = ["OUTER" => 255, "INNER" => 255];

class EAP {

    /**
     * conversion table between array and integer representations
     */
    const EAPTYPES_CONVERSION = [
        EAPTYPE_FAST_GTC => INTEGER_FAST_GTC,
        EAPTYPE_PEAP_MSCHAP2 => INTEGER_PEAP_MSCHAPv2,
        EAPTYPE_PWD => INTEGER_EAP_pwd,
        EAPTYPE_TLS => INTEGER_TLS,
        EAPTYPE_TTLS_GTC => INTEGER_TTLS_GTC,
        EAPTYPE_TTLS_MSCHAP2 => INTEGER_TTLS_MSCHAPv2,
        EAPTYPE_TTLS_PAP => INTEGER_TTLS_PAP,
        EAPTYPE_SILVERBULLET => INTEGER_SILVERBULLET,
    ];

    /**
     * This function takes the EAP method in array representation (OUTER/INNER) and returns it in a custom format for the
     * Linux installers (not numbers, but strings as values).
     * @param array EAP method in array representation (OUTER/INNER)
     * @return array EAP method in array representation (OUTER as string/INNER as string)
     */
    public static function eapDisplayName($eap) {
        $eapDisplayName = [];
        $eapDisplayName[serialize(EAPTYPE_PEAP_MSCHAP2)] = ["OUTER" => 'PEAP', "INNER" => 'MSCHAPV2'];
        $eapDisplayName[serialize(EAPTYPE_TLS)] = ["OUTER" => 'TLS', "INNER" => ''];
        $eapDisplayName[serialize(EAPTYPE_TTLS_PAP)] = ["OUTER" => 'TTLS', "INNER" => 'PAP'];
        $eapDisplayName[serialize(EAPTYPE_TTLS_MSCHAP2)] = ["OUTER" => 'TTLS', "INNER" => 'MSCHAPV2'];
        $eapDisplayName[serialize(EAPTYPE_TTLS_GTC)] = ["OUTER" => 'TTLS', "INNER" => 'GTC'];
        $eapDisplayName[serialize(EAPTYPE_FAST_GTC)] = ["OUTER" => 'FAST', "INNER" => 'GTC'];
        $eapDisplayName[serialize(EAPTYPE_PWD)] = ["OUTER" => 'PWD', "INNER" => ''];
        $eapDisplayName[serialize(EAPTYPE_NONE)] = ["OUTER" => '', "INNER" => ''];
        $eapDisplayName[serialize(EAPTYPE_SILVERBULLET)] = ["OUTER" => 'TLS', "INNER" => 'SILVERBULLET'];
        $eapDisplayName[serialize(EAPTYPE_ANY)] = ["OUTER" => 'PEAP TTLS TLS', "INNER" => 'MSCHAPV2 PAP GTC'];
        return($eapDisplayName[serialize($eap)]);
    }

    public static function innerAuth($eap) {
        $out = [];
        if ($eap["INNER"]) { // there is an inner EAP method
            $out['EAP'] = 1;
            $out['METHOD'] = $eap["INNER"];
            return $out;
        }
        // there is none
        $out['EAP'] = 0;
        switch ($eap) {
            case EAPTYPE_TTLS_PAP:
                $out['METHOD'] = NE_PAP;
                break;
            case EAPTYPE_TTLS_MSCHAP2:
                $out['METHOD'] = NE_MSCHAP2;
        }
        return $out;
    }

    /**
     * This function enumerates all known EAP types and returns them as array
     * 
     * @return array of all EAP types the CAT knows about
     */
    public static function listKnownEAPTypes() {
        return array_keys(EAP::EAPTYPES_CONVERSION);
    }

    /**
     * EAP methods have two representations: an integer enumeration and an array with keys OUTER and INNER
     * This function converts between the two.
     * @param int|array $input either the integer ID of an EAP type (returns array representation) or the array representation (returns integer)
     * @return array|int
     */
    public static function eAPMethodArrayIdConversion($input) {
        if (is_array($input) && isset(EAP::EAPTYPES_CONVERSION[$input])) {
            return EAP::EAPTYPES_CONVERSION[$input];
        }
        if (is_numeric($input)) {
            $keys = array_keys(EAP::EAPTYPES_CONVERSION, $input);
            if (count($keys) == 1) {
                return $keys[0];
            }
        }
        throw new Exception("Unable to map EAP method array to EAP method int or vice versa: $input!");
    }
}