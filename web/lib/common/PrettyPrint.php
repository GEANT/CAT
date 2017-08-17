<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
namespace web\lib\common;

class PrettyPrint {
 
    public function eapNames($arrayRep) {
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
    $find = array_keys($nameMapping, $arrayRep, TRUE);
    return $find[0];
    }
}