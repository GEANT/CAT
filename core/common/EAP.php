<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * This file contains the EAP class and some constants for EAP types.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 * 
 */

namespace core\common;

use \Exception;

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
     * some EAP-related constants.
     */
    const PEAP = 25;
    const MSCHAP2 = 26;
    const TTLS = 21;
    const TLS = 13;
    const NONE = 0;
    const GTC = 6;
    const FAST = 43;
    const PWD = 52;
    const NE_PAP = 1;
    const NE_MSCHAP = 2;
    const NE_MSCHAP2 = 3;
    const NE_SILVERBULLET = 999;
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
    const EAPTYPE_PEAP_MSCHAP2 = ["OUTER" => EAP::PEAP, "INNER" => EAP::MSCHAP2];

    /**
     * EAP-TLS: Outer EAP Type = 13, no inner EAP
     */
    const EAPTYPE_TLS = ["OUTER" => EAP::TLS, "INNER" => EAP::NONE];

    /**
     * EAP-TLS: Outer EAP Type = 13, no inner EAP
     */
    const EAPTYPE_SILVERBULLET = ["OUTER" => EAP::TLS, "INNER" => EAP::NE_SILVERBULLET];

    /**
     * TTLS-PAP: Outer EAP type = 21, no inner EAP, inner non-EAP = 1
     */
    const EAPTYPE_TTLS_PAP = ["OUTER" => EAP::TTLS, "INNER" => EAP::NONE];

    /**
     * TTLS-MSCHAP-v2: Outer EAP type = 21, no inner EAP, inner non-EAP = 3
     */
    const EAPTYPE_TTLS_MSCHAP2 = ["OUTER" => EAP::TTLS, "INNER" => EAP::MSCHAP2];

    /**
     * TTLS-GTC: Outer EAP type = 21, Inner EAP Type = 6
     */
    const EAPTYPE_TTLS_GTC = ["OUTER" => EAP::TTLS, "INNER" => EAP::GTC];

    /**
     * EAP-FAST (GTC): Outer EAP type = 43, Inner EAP Type = 6
     */
    const EAPTYPE_FAST_GTC = ["OUTER" => EAP::FAST, "INNER" => EAP::GTC];

    /**
     * PWD: Outer EAP type = 52, no inner EAP
     */
    const EAPTYPE_PWD = ["OUTER" => EAP::PWD, "INNER" => EAP::NONE];

    /**
     * NULL: no outer EAP, no inner EAP
     */
    const EAPTYPE_NONE = ["OUTER" => EAP::NONE, "INNER" => EAP::NONE];

    /**
     *  ANY: not really an EAP method, but the term to use when needing to express "any EAP method we know"
     */
    const EAPTYPE_ANY = ["OUTER" => 255, "INNER" => 255];

    /**
     * conversion table between array and integer representations
     */
    const EAPTYPES_CONVERSION = [
        EAP::INTEGER_FAST_GTC => EAP::EAPTYPE_FAST_GTC,
        EAP::INTEGER_PEAP_MSCHAPv2 => EAP::EAPTYPE_PEAP_MSCHAP2,
        EAP::INTEGER_EAP_pwd => EAP::EAPTYPE_PWD,
        EAP::INTEGER_TLS => EAP::EAPTYPE_TLS,
        EAP::INTEGER_TTLS_GTC => EAP::EAPTYPE_TTLS_GTC,
        EAP::INTEGER_TTLS_MSCHAPv2 => EAP::EAPTYPE_TTLS_MSCHAP2,
        EAP::INTEGER_TTLS_PAP => EAP::EAPTYPE_TTLS_PAP,
        EAP::INTEGER_SILVERBULLET => EAP::EAPTYPE_SILVERBULLET,
    ];

    /**
     * The array representation of the EAP type
     * @var array
     */
    private $arrayRep;

    /**
     * The integer representation of the EAP type
     * @var int
     */
    private $intRep;

    /**
     * Instantiates the EAP class for a concrete EAP type. Only call it to 
     * instantiate *real* EAP types, i.e. not EAPTYPE::ANY or EAPTYPE::NONE
     * 
     * @param mixed $eapType the EAP type, either in its integer or array representation
     */
    public function __construct($eapType) {
        if (is_numeric($eapType) && array_key_exists($eapType, EAP::EAPTYPES_CONVERSION)) {
            $key = array_keys(EAP::EAPTYPES_CONVERSION, EAP::EAPTYPES_CONVERSION[$eapType]);
            $this->intRep = $key[0];
            $this->arrayRep = EAP::EAPTYPES_CONVERSION[$this->intRep];
            return;
        }
        if (is_array($eapType)) {
            $key = array_search($eapType, EAP::EAPTYPES_CONVERSION);
            if ($key !== FALSE) {
                $this->intRep = $key;
                $this->arrayRep = EAP::EAPTYPES_CONVERSION[$key];
                return;
            }
        }
        throw new Exception("Unable to instantiate the EAP class - the EAP type is bogus.");
    }

    /**
     * Is this a password-based EAP method?
     * @return int
     * @throws Exception
     */
    public function isPasswordRequired() {
        switch ($this->intRep) {
            case EAP::INTEGER_EAP_pwd:
            case EAP::INTEGER_FAST_GTC:
            case EAP::INTEGER_PEAP_MSCHAPv2:
            case EAP::INTEGER_TTLS_GTC:
            case EAP::INTEGER_TTLS_MSCHAPv2:
            case EAP::INTEGER_TTLS_PAP:
                return TRUE;
            case EAP::INTEGER_TLS:
            case EAP::INTEGER_SILVERBULLET:
                return FALSE;
            default:
                throw new Exception("Unable to determine if the EAP type required a password or not!");
        }
    }

    /**
     * There could be EAP methods which have an optional need for a password.
     * Not aware of any, so this is a simple function :-)
     * @return boolean
     */
    public function isPasswordOptional() {
        return FALSE;
    }

    /**
     * Is this a certificate-based EAP method?
     * @return int
     * @throws Exception
     */
    public function isClientCertRequired() {
        switch ($this->intRep) {
            case EAP::INTEGER_EAP_pwd:
            case EAP::INTEGER_FAST_GTC:
            case EAP::INTEGER_PEAP_MSCHAPv2:
            case EAP::INTEGER_TTLS_GTC:
            case EAP::INTEGER_TTLS_MSCHAPv2:
            case EAP::INTEGER_TTLS_PAP:
                return FALSE;
            case EAP::INTEGER_TLS:
            case EAP::INTEGER_SILVERBULLET:
                return TRUE;
            default:
                throw new Exception("Unable to determine if the EAP type requires client-certificates or not!");
        }
    }

    /**
     * Does an EAP type optionally allow to send a client certificate?
     */
    public function isClientCertOptional() {
        switch ($this->intRep) {
            case EAP::INTEGER_EAP_pwd:
            case EAP::INTEGER_TLS:
            case EAP::INTEGER_SILVERBULLET:
                return FALSE;
            case EAP::INTEGER_FAST_GTC:
            case EAP::INTEGER_PEAP_MSCHAPv2:
            case EAP::INTEGER_TTLS_GTC:
            case EAP::INTEGER_TTLS_MSCHAPv2:
            case EAP::INTEGER_TTLS_PAP:
                return TRUE;
            default:
                throw new Exception("Unable to determine if the EAP type has optional client-certificates or not!");
        }
    }

    /**
     * Does the EAP type require the specification of trusted CAs to be secure?
     * @return bool
     * @throws Exception
     */
    public function needsServerCACert() {
        switch ($this->intRep) {
            case EAP::INTEGER_EAP_pwd:
                return FALSE;
            case EAP::INTEGER_FAST_GTC:
            case EAP::INTEGER_PEAP_MSCHAPv2:
            case EAP::INTEGER_TTLS_GTC:
            case EAP::INTEGER_TTLS_MSCHAPv2:
            case EAP::INTEGER_TTLS_PAP:
            case EAP::INTEGER_TLS:
            case EAP::INTEGER_SILVERBULLET:
                return TRUE;
            default:
                throw new Exception("Unable to determine if the EAP type requires a CA trust base for secure functioning or not!");
        }
    }

    /**
     * Does the EAP type require the specification of a server name to be secure?
     * EAP-pwd has one, but it is not really required.
     * @return bool
     * @throws Exception
     */
    public function needsServerName() {
        switch ($this->intRep) {
            case EAP::INTEGER_FAST_GTC:
            case EAP::INTEGER_PEAP_MSCHAPv2:
            case EAP::INTEGER_TTLS_GTC:
            case EAP::INTEGER_TTLS_MSCHAPv2:
            case EAP::INTEGER_TTLS_PAP:
            case EAP::INTEGER_TLS:
            case EAP::INTEGER_SILVERBULLET:
                return TRUE;
            case EAP::INTEGER_EAP_pwd:
                return FALSE;
            default:
                throw new Exception("Unable to determine if the EAP type requires a server name trust base for secure functioning or not!");
        }
    }

    /**
     * Returns the Array representation of the EAP type.
     * 
     * @return array
     */
    public function getArrayRep() {
        return $this->arrayRep;
    }

    /**
     * Returns the int representation of the EAP type.
     * 
     * @return int
     */
    public function getIntegerRep() {
        return $this->intRep;
    }

    /**
     * This function takes the EAP method in array representation (OUTER/INNER) and returns it in a custom format for the
     * Linux installers (not numbers, but strings as values).
     * @param array EAP method in array representation (OUTER/INNER)
     * @return array EAP method in array representation (OUTER as string/INNER as string)
     */
    public static function eapDisplayName($eap) {
        $eapDisplayName = [];
        $eapDisplayName[serialize(EAP::EAPTYPE_PEAP_MSCHAP2)] = ["OUTER" => 'PEAP', "INNER" => 'MSCHAPV2'];
        $eapDisplayName[serialize(EAP::EAPTYPE_TLS)] = ["OUTER" => 'TLS', "INNER" => ''];
        $eapDisplayName[serialize(EAP::EAPTYPE_TTLS_PAP)] = ["OUTER" => 'TTLS', "INNER" => 'PAP'];
        $eapDisplayName[serialize(EAP::EAPTYPE_TTLS_MSCHAP2)] = ["OUTER" => 'TTLS', "INNER" => 'MSCHAPV2'];
        $eapDisplayName[serialize(EAP::EAPTYPE_TTLS_GTC)] = ["OUTER" => 'TTLS', "INNER" => 'GTC'];
        $eapDisplayName[serialize(EAP::EAPTYPE_FAST_GTC)] = ["OUTER" => 'FAST', "INNER" => 'GTC'];
        $eapDisplayName[serialize(EAP::EAPTYPE_PWD)] = ["OUTER" => 'PWD', "INNER" => ''];
        $eapDisplayName[serialize(EAP::EAPTYPE_NONE)] = ["OUTER" => '', "INNER" => ''];
        $eapDisplayName[serialize(EAP::EAPTYPE_SILVERBULLET)] = ["OUTER" => 'TLS', "INNER" => 'SILVERBULLET'];
        $eapDisplayName[serialize(EAP::EAPTYPE_ANY)] = ["OUTER" => 'PEAP TTLS TLS', "INNER" => 'MSCHAPV2 PAP GTC'];
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
            case EAP::EAPTYPE_TTLS_PAP:
                $out['METHOD'] = EAP::NE_PAP;
                break;
            case EAP::EAPTYPE_TTLS_MSCHAP2:
                $out['METHOD'] = EAP::NE_MSCHAP2;
        }
        return $out;
    }

    /**
     * This function enumerates all known EAP types and returns them as array
     * 
     * @return array of all EAP types the CAT knows about, as objects
     */
    public static function listKnownEAPTypes() {
        $retval = [];

        foreach (array_values(EAP::EAPTYPES_CONVERSION) as $oneArrayRep) {
            $retval[] = new EAP($oneArrayRep);
        }
        return $retval;
    }

    public function getPrintableRep() {
        $nameMapping = [
            _("PEAP-MSCHAPv2") => \core\common\EAP::EAPTYPE_PEAP_MSCHAP2,
            _("TLS") => \core\common\EAP::EAPTYPE_TLS,
            _("TTLS-PAP") => \core\common\EAP::EAPTYPE_TTLS_PAP,
            _("TTLS-MSCHAPv2") => \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
            _("TTLS-GTC") => \core\common\EAP::EAPTYPE_TTLS_GTC,
            _("FAST-GTC") => \core\common\EAP::EAPTYPE_FAST_GTC,
            _("EAP-pwd") => \core\common\EAP::EAPTYPE_PWD,
            \core\ProfileSilverbullet::PRODUCTNAME => \core\common\EAP::EAPTYPE_SILVERBULLET,
        ];
        $find = array_keys($nameMapping, $this->arrayRep, TRUE);
        return $find[0];
    }

}
