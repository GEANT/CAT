<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/**
 * This file defines an abstract class used for generic XML
 * devices
 * actual modules only define available EAP types.
 *
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */
namespace devices\xml;
require_once(dirname(__FILE__).'/XML.inc.php');

/**
 * This class implements full functionality of the generic XML device
 * the only fuction of the extenstions of this class is to specify
 * supported EAP methods.
 * Instead of specifying supported EAPS an extension can set $all_eaps to true
 * this will cause the installer to configure all EAP methods supported by 
 * the current profile and declared by the given device.
 */
abstract class Device_XML extends \core\DeviceConfig {

    public function __construct() {
        parent::__construct();
    }

    /**
     * $lang_scope can be 'global' wheb all lang and all lang-specific information
     * is dumped or 'single' when only the selected lang (and defaults) are passed
     * NOTICE: 'global' is not yet supported
     */
    public $langScope;
    public $allEaps = FALSE;
    public $VendorSpecific;

    public function writeDeviceInfo() {
        $out = "<p>";
        $out .= sprintf(_("This is a generic configuration file in the IETF <a href='%s'>EAP Metadata -00</a> XML format."), "https://tools.ietf.org/html/draft-winter-opsawg-eap-metadata-00");
        return $out;
    }

    public function writeInstaller() {
        $attr = $this->attributes;
        $NAMESPACE = 'urn:RFC4282:realm';
//EAPIdentityProvider  begin
        $eapIdp = new EAPIdentityProvider();
//    $eap_idp->setProperty('ValidUntil',$this->getValidUntil());
// ProviderInfo->
        $eapIdp->setProperty('ProviderInfo', $this->getProviderInfo());
// TODO    $eap_idp->setProperty('VendorSpecific',$this->getVendorSpecific());
//AuthenticationMethods
// TODO
//ID attribute
//lang attribute
        $methodList = [];
        if ($this->allEaps) {
            $eapmethods = [];
            foreach ($attr['all_eaps'] as $eap) {
                if (in_array($eap, $this->supportedEapMethods)) {
                    $eapmethods[] = $eap;
                }
            }
        } else {
            $eapmethods = [$this->selectedEap];
        }

        foreach ($eapmethods as $eap) {
            $methodList[] = $this->getAuthMethod($eap);
        }
        $authMethods = new AuthenticationMethods();
        $authMethods->setProperty('AuthenticationMethods', $methodList);
        $eapIdp->setProperty('AuthenticationMethods', $authMethods);
        if (empty($attr['internal:realm'][0])) {
            $eapIdp->setAttribute('ID', 'undefined');
            $eapIdp->setAttribute('namespace', 'urn:undefined');
        } else {
            $eapIdp->setAttribute('ID', $attr['internal:realm'][0]);
            $eapIdp->setAttribute('namespace', $NAMESPACE);
        }
        if ($this->langScope === 'single') {
            $eapIdp->setAttribute('lang', $this->languageInstance->getLang());
        }
        $eapIdp->setAttribute('version', '1');


// EAPIdentityProvider end
// Generate XML

        $rootname = 'EAPIdentityProviderList';
        $root = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"utf-8\" ?><{$rootname} xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"eap-metadata.xsd\"></{$rootname}>");

        marshalObject($root, $eapIdp);
        $dom = dom_import_simplexml($root)->ownerDocument;
        //TODO schema validation makes sense so probably should be used
        $res = $dom->schemaValidate(ROOT . '/devices/xml/eap-metadata.xsd');
        $file = fopen($this->installerBasename . '.eap-config', "w");
        fwrite($file, $dom->saveXML());
        fclose($file);
        return($this->installerBasename . '.eap-config');
    }

    private $AttributeNames = [
        'support:email' => 'EmailAddress',
        'support:url' => 'WebAddress',
        'support:phone' => 'Phone',
        'profile:description' => 'Description',
        'support:info_file' => 'TermsOfUse',
        'general:logo_file' => 'ProviderLogo',
    ];

    private function getSimpleAttribute($attrName) {
        if ((!isset($this->attributes[$attrName][0]) || !$this->attributes[$attrName][0])) {
            return '';
        }

        $attributeList = $this->attributes[$attrName];
        if (!isset($this->AttributeNames[$attrName])) {
            $this->loggerInstance->debug(4, "Missing class definition for $attrName\n");
            return;
        }
        $className = $this->AttributeNames[$attrName];
        $obj = new $className();
        $obj->setValue($attributeList[0]);
        return($obj);
    }

    /**
     * 
     * @param string $attrName
     * @return array of values for this attribute
     */
    private function getSimpleMLAttribute($attrName) {
        if ((!isset($this->attributes[$attrName][0]) || !$this->attributes[$attrName][0])) {
            return '';
        }

        $attributeList = $this->attributes[$attrName];
        if (!isset($this->AttributeNames[$attrName])) {
            $this->loggerInstance->debug(4, "Missing class definition for $attrName\n");
            return;
        }
        $className = "\devices\xml\\" . $this->AttributeNames[$attrName];
        $objs = [];
        if ($this->langScope === 'global') {
            foreach ($attributeList['langs'] as $language => $value) {
                $language = ( $language === 'C' ? 'any' : $language );
                $obj = new $className();
                $obj->setValue($value);
                $obj->setAttributes(['lang' => $language]);
                $objs[] = $obj;
            }
        } else {
            $obj = new $className();
            $obj->setValue($attributeList[0]);
            $objs[] = $obj;
        }

        return($objs);
    }

    private function getDisplayName() {
        $attr = $this->attributes;
        $objs = [];
        if ($this->langScope === 'global') {
            $instNameLangs = $attr['general:instname']['langs'];
            if ($attr['internal:profile_count'][0] > 1) {
                $profileNameLangs = $attr['profile:name']['langs'];
            }
            foreach ($instNameLangs as $language => $value) {
                $language = ( $language === 'C' ? 'any' : $language );
                $displayname = new DisplayName();
                if (isset($profileNameLangs)) {
                    $langOrC = isset($profileNameLangs[$language]) ? $profileNameLangs[$language] : $profileNameLangs['C'];
                    $value .= ' - ' . $langOrC;
                }
                $displayname->setValue($value);
                $displayname->setAttributes(['lang' => $language]);
                $objs[] = $displayname;
            }
        } else {
            $displayname = new DisplayName();
            $value = $attr['general:instname'][0];
            if ($attr['internal:profile_count'][0] > 1) {
                $value .= ' - ' . $attr['profile:name'][0];
            }
            $displayname->setValue($value);
            $objs[] = $displayname;
        }
        return $objs;
    }

    private function getProviderLogo() {
        $attr = $this->attributes;
        if (isset($attr['general:logo_file'][0])) {
            $logoString = base64_encode($attr['general:logo_file'][0]);
            $logoMime = 'image/' . $attr['internal:logo_file'][0]['mime'];
            $providerlogo = new ProviderLogo();
            $providerlogo->setAttributes(['mime' => $logoMime, 'encoding' => 'base64']);
            $providerlogo->setValue($logoString);
            return $providerlogo;
        }
    }

    private function getProviderInfo() {
        $providerinfo = new ProviderInfo();
        $providerinfo->setProperty('DisplayName', $this->getDisplayName());
        $providerinfo->setProperty('Description', $this->getSimpleMLAttribute('profile:description'));
        $providerinfo->setProperty('ProviderLocation', $this->getProvideLocation());
        $providerinfo->setProperty('ProviderLogo', $this->getProviderLogo());
        $providerinfo->setProperty('TermsOfUse', $this->getSimpleMLAttribute('support:info_file'));
        $providerinfo->setProperty('Helpdesk', $this->getHelpdesk());
        return $providerinfo;
    }

    private function getProvideLocation() {
        $attr = $this->attributes;
        if (isset($attr['general:geo_coordinates'])) {
            $attrCoordinates = $attr['general:geo_coordinates'];
            if (count($attrCoordinates) > 1) {
                $location = [];
                foreach ($attrCoordinates as $a) {
                    $providerlocation = new ProviderLocation();
                    $b = json_decode($a, true);
                    $providerlocation->setProperty('Longitude', $b['lon']);
                    $providerlocation->setProperty('Latitude', $b['lat']);
                    $location[] = $providerlocation;
                }
            } else {
                $providerlocation = new ProviderLocation();
                $b = json_decode($attrCoordinates[0], true);
                $providerlocation->setProperty('Longitude', $b['lon']);
                $providerlocation->setProperty('Latitude', $b['lat']);
                $location = $providerlocation;
            }
            return $location;
        }
    }

    private function getHelpdesk() {
        $helpdesk = new Helpdesk();
        $helpdesk->setProperty('EmailAddress', $this->getSimpleMLAttribute('support:email'));
        $helpdesk->setProperty('WebAddress', $this->getSimpleMLAttribute('support:url'));
        $helpdesk->setProperty('Phone', $this->getSimpleMLAttribute('support:phone'));
        return $helpdesk;
    }

    private function getCompatibleUses() {
        $ssids = $this->attributes['internal:SSID'];
        $compatibleuses = new CompatibleUses();
        $ieee80211s = [];
        foreach ($ssids as $ssid => $ciph) {
            $ieee80211 = new IEEE80211();
            $ieee80211->setProperty('SSID', $ssid);
            $ieee80211->setProperty('MinRSNProto', $ciph == 'AES' ? 'CCMP' : 'TKIP');
            $ieee80211s[] = $ieee80211;
        }
        $compatibleuses->setProperty('IEEE80211', $ieee80211s);
        return($compatibleuses);
    }

    private function getAuthenticationMethodParams($eap) {
        $inner = \core\common\EAP::innerAuth($eap);
        $outerMethod = $eap["OUTER"];

        if (isset($inner["METHOD"]) && $inner["METHOD"]) {
            $innerauthmethod = new InnerAuthenticationMethod();
            $typeOfInner = "\devices\xml\\" . ($inner["EAP"] ? 'EAPMethod' : 'NonEAPAuthMethod');
            $eapmethod = new $typeOfInner();
            $eaptype = new Type();
            $eaptype->setValue($inner['METHOD']);
            $eapmethod->setProperty('Type', $eaptype);
            $innerauthmethod->setProperty($typeOfInner, $eapmethod);
            return ['inner_method' => $innerauthmethod, 'methodID' => $outerMethod, 'inner_methodID' => $inner['METHOD']];
        } else {
            return ['inner_method' => 0, 'methodID' => $outerMethod, 'inner_methodID' => 0];
        }
    }

    private function getAuthMethod($eap) {
        $attr = $this->attributes;
        $eapParams = $this->getAuthenticationMethodParams($eap);
        $authmethod = new AuthenticationMethod();
        $eapmethod = new EAPMethod();
        $eaptype = new Type();
        $eaptype->setValue($eapParams['methodID']);
        $eapmethod->setProperty('Type', $eaptype);
        if (isset($this->VendorSpecific)) {
            $vendorspecifics = [];
            foreach ($this->VendorSpecific as $vs) {
                $vendorspecific = new VendorSpecific();
                $vs['value']->addAttribute('xsi:noNamespaceSchemaLocation', "xxx.xsd");
                $vendorspecific->setValue($vs['value']);
                $vendorspecific->setAttributes(['vendor' => $vs['vendor']]);
                $vendorspecifics[] = $vendorspecific;
            }
            $eapmethod->setProperty('VendorSpecific', $vendorspecifics);
        }
        $authmethod->setProperty('EAPMethod', $eapmethod);

// ServerSideCredentials
        $serversidecredential = new ServerSideCredential();

// Certificates and server names

        $cAlist = [];
        $attrCaList = $attr['internal:CAs'][0];
        foreach ($attrCaList as $ca) {
            $caObject = new CA();
            $caObject->setValue(base64_encode($ca['der']));
            $caObject->setAttributes(['format' => 'X.509', 'encoding' => 'base64']);
            $cAlist[] = $caObject;
        }

        $serverids = [];
        $servers = $attr['eap:server_name'];
        foreach ($servers as $server) {
            $serverid = new ServerID();
            $serverid->setValue($server);
            $serverids[] = $serverid;
        }

        $serversidecredential->setProperty('EAPType', $eaptype->getValue());
        $serversidecredential->setProperty('CA', $cAlist);
        $serversidecredential->setProperty('ServerID', $serverids);
        $authmethod->setProperty('ServerSideCredential', $serversidecredential);

// ClientSideCredentials

        $clientsidecredential = new ClientSideCredential();

// OuterIdentity 
        if ($attr['internal:use_anon_outer'] [0]) {
            $clientsidecredential->setProperty('OuterIdentity', $attr['internal:anon_local_value'][0] . '@' . $attr['internal:realm'][0]);
        }
        $clientsidecredential->setProperty('EAPType', $eapParams['inner_methodID'] ? $eapParams['inner_methodID'] : $eapParams['methodID']);
        
        // Client Certificate
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $clientCertificateObject = new ClientCertificate();
            $clientCertificateObject->setValue(base64_encode($this->clientCert["certdata"]));
            $clientCertificateObject->setAttributes(['format' => 'PKCS12', 'encoding' => 'base64']);
            
            $clientsidecredential->setProperty('ClientCertificate',$clientCertificateObject);
        }
        
        $authmethod->setProperty('ClientSideCredential', $clientsidecredential);
        if ($eapParams['inner_method']) {
            $authmethod->setProperty('InnerAuthenticationMethod', $eapParams['inner_method']);
        }
        return $authmethod;
    }
    


}
