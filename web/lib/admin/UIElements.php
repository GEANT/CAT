<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\admin;

class UIElements {

    public function displayName($input) {

        $ssidText = _("SSID");
        $ssidLegacyText = _("SSID (with WPA/TKIP)");
        $passpointOiText = _("HS20 Consortium OI");

        if (count(CONFIG['CONSORTIUM']['ssid']) > 0) {
            $ssidText = _("Additional SSID");
            $ssidLegacyText = _("Additional SSID (with WPA/TKIP)");
        }
        if (!empty(CONFIG['CONSORTIUM']['interworking-consortium-oi']) && count(CONFIG['CONSORTIUM']['interworking-consortium-oi']) > 0) {
            $passpointOiText = _("Additional HS20 Consortium OI");
        }

        $displayNames = [_("Support: Web") => "support:url",
            _("Support: EAP Types") => "support:eap_types",
            _("Support: Phone") => "support:phone",
            _("Support: E-Mail") => "support:email",
            _("Institution Name") => "general:instname",
            _("Location") => "general:geo_coordinates",
            _("Logo URL") => "general:logo_url",
            _("Logo image") => "general:logo_file",
            _("Configure Wired Ethernet") => "media:wired",
            _("Name (CN) of Authentication Server") => "eap:server_name",
            _("Enable device assessment") => "eap:enable_nea",
            _("Terms of Use") => "support:info_file",
            _("CA Certificate URL") => "eap:ca_url",
            _("CA Certificate File") => "eap:ca_file",
            _("Profile Display Name") => "profile:name",
            _("Production-Ready") => "profile:production",
            _("Extra text on downloadpage for device") => "device-specific:customtext",
            _("Redirection Target") => "device-specific:redirect",
            _("Extra text on downloadpage for EAP method") => "eap-specific:customtext",
            _("Turn on selection of EAP-TLS User-Name") => "eap-specific:tls_use_other_id",
            _("Profile Description") => "profile:description",
            _("Federation Administrator") => "user:fedadmin",
            _("Real Name") => "user:realname",
            _("E-Mail Address") => "user:email",
            _("PEAP-MSCHAPv2") => \core\EAP::EAPTYPE_PEAP_MSCHAP2,
            _("TLS") => \core\EAP::EAPTYPE_TLS,
            _("TTLS-PAP") => \core\EAP::EAPTYPE_TTLS_PAP,
            _("TTLS-MSCHAPv2") => \core\EAP::EAPTYPE_TTLS_MSCHAP2,
            _("TTLS-GTC") => \core\EAP::EAPTYPE_TTLS_GTC,
            _("FAST-GTC") => \core\EAP::EAPTYPE_FAST_GTC,
            _("EAP-pwd") => \core\EAP::EAPTYPE_PWD,
            \core\ProfileSilverbullet::PRODUCTNAME => \core\EAP::EAPTYPE_SILVERBULLET,
            _("Remove/Disable SSID") => "media:remove_SSID",
            _("Custom CSS file for User Area") => "fed:css_file",
            _("Federation Logo") => "fed:logo_file",
            _("Preferred Skin for User Area") => "fed:desired_skin",
            _("Federation Operator Name") => "fed:realname",
            _("Custom text in IdP Invitations") => "fed:custominvite",
            sprintf(_("Enable %s"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet",
            sprintf(_("%s: Do not terminate EAP"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet-noterm",
            sprintf(_("%s: max users per profile"), \core\ProfileSilverbullet::PRODUCTNAME) => "fed:silverbullet-maxusers",
            $ssidText => "media:SSID",
            $ssidLegacyText => "media:SSID_with_legacy",
            $passpointOiText => "media:consortium_OI",
        ];

        $find = array_search($input, $displayNames);

        if ($find === FALSE) { // sending back the original if we didn't find a better name
            $find = $input;
        }
        return $find;
    }

}
