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
define("INTEGER_TTLS_PAP", 1);
define("INTEGER_PEAP_MSCHAPv2", 2);
define("INTEGER_TLS", 3);
define("INTEGER_FAST_GTC", 4);
define("INTEGER_TTLS_GTC", 5);
define("INTEGER_TTLS_MSCHAPv2", 6);
define("INTEGER_EAP_pwd", 7);
define("INTEGER_SILVERBULLET", 8);

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
    public static $PEAP_MSCHAP2 = ["OUTER" => PEAP, "INNER" => MSCHAP2];

    /**
     * EAP-TLS: Outer EAP Type = 13, no inner EAP
     *
     * @var array of EAP type IDs that describe EAP-TLS
     */
    public static $TLS = ["OUTER" => TLS, "INNER" => NONE];

   /**
     * EAP-TLS: Outer EAP Type = 13, no inner EAP
     *
     * @var array of EAP type IDs that describe EAP-TLS
     */
    public static $SILVERBULLET = ["OUTER" => TLS, "INNER" => NE_SILVERBULLET];

    /**
     * TTLS-PAP: Outer EAP type = 21, no inner EAP, inner non-EAP = 1
     * 
     * @var array of EAP type IDs that describe TTLS-PAP
     */
    public static $TTLS_PAP = ["OUTER" => TTLS, "INNER" => NONE];

    /**
     * TTLS-MSCHAP-v2: Outer EAP type = 21, no inner EAP, inner non-EAP = 3
     * 
     * @var array of EAP type IDs that describe TTLS-MSCHAPv2
     */
    public static $TTLS_MSCHAP2 = ["OUTER" => TTLS, "INNER" => MSCHAP2];

    /**
     * TTLS-GTC: Outer EAP type = 21, Inner EAP Type = 6
     * 
     * @var array of EAP type IDs that describe TTLS-GTC
     */
    public static $TTLS_GTC = ["OUTER" => TTLS, "INNER" => GTC];

    /**
     * EAP-FAST (GTC): Outer EAP type = 43, Inner EAP Type = 6
     * 
     * @var array of EAP type IDs that describe EAP-FAST (GTC)
     */
    public static $FAST_GTC = ["OUTER" => FAST, "INNER" => GTC];

    /**
     * PWD: Outer EAP type = 52, no inner EAP
     * 
     * @var array of EAP type IDs that describe EAP-PWD
     */
    public static $PWD = ["OUTER" => PWD, "INNER" => NONE];

    /**
     * NULL: no outer EAP, no inner EAP
     * 
     * @var array of EAP type IDs that describes the NULL EAP Method
     */
    public static $EAP_NONE = ["OUTER" => NONE, "INNER" => NONE];

    /**
     *  ANY: not really an EAP method, but the term to use when needing to express "any EAP method we know"
     */
    public static $EAP_ANY = ["OUTER" => 255, "INNER" => 255];
    
    /**
     * This function takes the EAP method in array representation (OUTER/INNER) and returns it in a custom format for the
     * Linux installers (not numbers, but strings as values).
     * @param array EAP method in array representation (OUTER/INNER)
     * @return array EAP method in array representation (OUTER as string/INNER as string)
     */
    public static function eapDisplayName($eap) {
        $EAP_DISPLAY_NAME = [];
        $EAP_DISPLAY_NAME[serialize(EAP::$PEAP_MSCHAP2)] = ["OUTER" => 'PEAP', "INNER" => 'MSCHAPV2'];
        $EAP_DISPLAY_NAME[serialize(EAP::$TLS)] = ["OUTER" => 'TLS', "INNER" => ''];
        $EAP_DISPLAY_NAME[serialize(EAP::$TTLS_PAP)] = ["OUTER" => 'TTLS', "INNER" => 'PAP'];
        $EAP_DISPLAY_NAME[serialize(EAP::$TTLS_MSCHAP2)] = ["OUTER" => 'TTLS', "INNER" => 'MSCHAPV2'];
        $EAP_DISPLAY_NAME[serialize(EAP::$TTLS_GTC)] = ["OUTER" => 'TTLS', "INNER" => 'GTC'];
        $EAP_DISPLAY_NAME[serialize(EAP::$FAST_GTC)] = ["OUTER" => 'FAST', "INNER" => 'GTC'];
        $EAP_DISPLAY_NAME[serialize(EAP::$PWD)] = ["OUTER" => 'PWD', "INNER" => ''];
        $EAP_DISPLAY_NAME[serialize(EAP::$EAP_NONE)] = ["OUTER" => '', "INNER" => ''];
        $EAP_DISPLAY_NAME[serialize(EAP::$SILVERBULLET)] = ["OUTER" => 'TLS', "INNER" => 'SILVERBULLET'];
        $EAP_DISPLAY_NAME[serialize(EAP::$EAP_ANY)] = ["OUTER" => 'PEAP TTLS TLS', "INNER" => 'MSCHAPV2 PAP GTC'];
        return($EAP_DISPLAY_NAME[serialize($eap)]);
    }

    public static function innerAuth($eap) {
        $out = [];
        if ($eap["INNER"]) {
            $out['EAP'] = 1;
            $out['METHOD'] = $eap["INNER"];
        } else {
            $out['EAP'] = 0;
            if ($eap == EAP::$TTLS_PAP)
                $out['METHOD'] = NE_PAP;
            if ($eap == EAP::$TTLS_MSCHAP2)
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
        $returnarray = [];
        $returnarray[] = EAP::$FAST_GTC;
        $returnarray[] = EAP::$PEAP_MSCHAP2;
        $returnarray[] = EAP::$PWD;
        $returnarray[] = EAP::$TLS;
        $returnarray[] = EAP::$TTLS_GTC;
        $returnarray[] = EAP::$TTLS_MSCHAP2;
        $returnarray[] = EAP::$TTLS_PAP;
        $returnarray[] = EAP::$SILVERBULLET;
        return $returnarray;
    }

    public static function EAPMethodIdFromArray($method_array) {
        switch ($method_array) {
            case EAP::$FAST_GTC:
                return INTEGER_FAST_GTC;
            case EAP::$PEAP_MSCHAP2:
                return INTEGER_PEAP_MSCHAPv2;
            case EAP::$PWD:
                return INTEGER_EAP_pwd;
            case EAP::$TLS:
                return INTEGER_TLS;
            case EAP::$TTLS_GTC:
                return INTEGER_TTLS_GTC;
            case EAP::$TTLS_MSCHAP2:
                return INTEGER_TTLS_MSCHAPv2;
            case EAP::$TTLS_PAP:
                return INTEGER_TTLS_PAP;
            case EAP::$SILVERBULLET:
                return INTEGER_SILVERBULLET;
        }

        return FALSE;
    }

    public static function EAPMethodArrayFromId($id) {
        switch ($id) {
            case INTEGER_EAP_pwd:
                return EAP::$PWD;
            case INTEGER_FAST_GTC:
                return EAP::$FAST_GTC;
            case INTEGER_PEAP_MSCHAPv2:
                return EAP::$PEAP_MSCHAP2;
            case INTEGER_TLS:
                return EAP::$TLS;
            case INTEGER_TTLS_GTC:
                return EAP::$TTLS_GTC;
            case INTEGER_TTLS_MSCHAPv2:
                return EAP::$TTLS_MSCHAP2;
            case INTEGER_TTLS_PAP:
                return EAP::$TTLS_PAP;
            case INTEGER_SILVERBULLET:
                return EAP::$SILVERBULLET;
        }
        return NULL;
    }
}