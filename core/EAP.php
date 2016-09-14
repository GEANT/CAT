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

/**
 * PEAP-MSCHAPv2: Outer EAP Type = 25, Inner EAP Type = 26
 */
const PEAP_MSCHAP2 = ["OUTER" => PEAP, "INNER" => MSCHAP2];

/**
 * EAP-TLS: Outer EAP Type = 13, no inner EAP
 */
const TLS = ["OUTER" => TLS, "INNER" => NONE];

/**
 * EAP-TLS: Outer EAP Type = 13, no inner EAP
 */
const SILVERBULLET = ["OUTER" => TLS, "INNER" => NE_SILVERBULLET];

/**
 * TTLS-PAP: Outer EAP type = 21, no inner EAP, inner non-EAP = 1
 */
const TTLS_PAP = ["OUTER" => TTLS, "INNER" => NONE];

/**
 * TTLS-MSCHAP-v2: Outer EAP type = 21, no inner EAP, inner non-EAP = 3
 */
const TTLS_MSCHAP2 = ["OUTER" => TTLS, "INNER" => MSCHAP2];

/**
 * TTLS-GTC: Outer EAP type = 21, Inner EAP Type = 6
 */
const TTLS_GTC = ["OUTER" => TTLS, "INNER" => GTC];

/**
 * EAP-FAST (GTC): Outer EAP type = 43, Inner EAP Type = 6
 */
const FAST_GTC = ["OUTER" => FAST, "INNER" => GTC];

/**
 * PWD: Outer EAP type = 52, no inner EAP
 */
const PWD = ["OUTER" => PWD, "INNER" => NONE];

/**
 * NULL: no outer EAP, no inner EAP
 */
const EAP_NONE = ["OUTER" => NONE, "INNER" => NONE];

/**
 *  ANY: not really an EAP method, but the term to use when needing to express "any EAP method we know"
 */
const EAP_ANY = ["OUTER" => 255, "INNER" => 255];

// PHP7 allows to define constants with arrays as value. Hooray! This makes
// lots of public static members of the EAP class obsolete

class EAP {
    /* constants only work for simple types. So these arrays need to be variables. :-(
      Don't ever change them though. */

    /**
     * This function takes the EAP method in array representation (OUTER/INNER) and returns it in a custom format for the
     * Linux installers (not numbers, but strings as values).
     * @param array EAP method in array representation (OUTER/INNER)
     * @return array EAP method in array representation (OUTER as string/INNER as string)
     */
    public static function eapDisplayName($eap) {
        $eapDisplayName = [];
        $eapDisplayName[serialize(PEAP_MSCHAP2)] = ["OUTER" => 'PEAP', "INNER" => 'MSCHAPV2'];
        $eapDisplayName[serialize(TLS)] = ["OUTER" => 'TLS', "INNER" => ''];
        $eapDisplayName[serialize(TTLS_PAP)] = ["OUTER" => 'TTLS', "INNER" => 'PAP'];
        $eapDisplayName[serialize(TTLS_MSCHAP2)] = ["OUTER" => 'TTLS', "INNER" => 'MSCHAPV2'];
        $eapDisplayName[serialize(TTLS_GTC)] = ["OUTER" => 'TTLS', "INNER" => 'GTC'];
        $eapDisplayName[serialize(FAST_GTC)] = ["OUTER" => 'FAST', "INNER" => 'GTC'];
        $eapDisplayName[serialize(PWD)] = ["OUTER" => 'PWD', "INNER" => ''];
        $eapDisplayName[serialize(EAP_NONE)] = ["OUTER" => '', "INNER" => ''];
        $eapDisplayName[serialize(SILVERBULLET)] = ["OUTER" => 'TLS', "INNER" => 'SILVERBULLET'];
        $eapDisplayName[serialize(EAP_ANY)] = ["OUTER" => 'PEAP TTLS TLS', "INNER" => 'MSCHAPV2 PAP GTC'];
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
        if ($eap == TTLS_PAP)
            $out['METHOD'] = NE_PAP;
        if ($eap == TTLS_MSCHAP2)
            $out['METHOD'] = NE_MSCHAP2;

        return $out;
    }

    /**
     * This function enumerates all known EAP types and returns them as array
     * 
     * @return array of all EAP types the CAT knows about
     */
    public static function listKnownEAPTypes() {
        $returnarray = [];
        $returnarray[] = FAST_GTC;
        $returnarray[] = PEAP_MSCHAP2;
        $returnarray[] = PWD;
        $returnarray[] = TLS;
        $returnarray[] = TTLS_GTC;
        $returnarray[] = TTLS_MSCHAP2;
        $returnarray[] = TTLS_PAP;
        $returnarray[] = SILVERBULLET;
        return $returnarray;
    }

    public static function eAPMethodIdFromArray($methodArray) {
        switch ($methodArray) {
            case FAST_GTC:
                return INTEGER_FAST_GTC;
            case PEAP_MSCHAP2:
                return INTEGER_PEAP_MSCHAPv2;
            case PWD:
                return INTEGER_EAP_pwd;
            case TLS:
                return INTEGER_TLS;
            case TTLS_GTC:
                return INTEGER_TTLS_GTC;
            case TTLS_MSCHAP2:
                return INTEGER_TTLS_MSCHAPv2;
            case TTLS_PAP:
                return INTEGER_TTLS_PAP;
            case SILVERBULLET:
                return INTEGER_SILVERBULLET;
        }

        return FALSE;
    }

    public static function eAPMethodArrayFromId($identifier) {
        switch ($identifier) {
            case INTEGER_EAP_pwd:
                return PWD;
            case INTEGER_FAST_GTC:
                return FAST_GTC;
            case INTEGER_PEAP_MSCHAPv2:
                return PEAP_MSCHAP2;
            case INTEGER_TLS:
                return TLS;
            case INTEGER_TTLS_GTC:
                return TTLS_GTC;
            case INTEGER_TTLS_MSCHAPv2:
                return TTLS_MSCHAP2;
            case INTEGER_TTLS_PAP:
                return TTLS_PAP;
            case INTEGER_SILVERBULLET:
                return SILVERBULLET;
        }
        return NULL;
    }

}
