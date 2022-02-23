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
 * This file defines an abstract class used for generic XML
 * devices
 * actual modules only define available EAP types.
 *
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

namespace devices\eap_config;

use Exception;

/**
 * This class implements full functionality of the generic XML device
 * the only fuction of the extenstions of this class is to specify
 * supported EAP methods.
 * Instead of specifying supported EAPS an extension can set $all_eaps to true
 * this will cause the installer to configure all EAP methods supported by 
 * the current profile and declared by the given device.
 */
abstract class DeviceXML extends \core\DeviceConfig
{
    
    /**
     *  @var array $AuthMethodElements is used to limit
     *  XML elements present within ServerSideCredentials and
     *  ClientSideCredentials to ones which are relevant
     *  for a given EAP method.
     *  @var array of XLM element names which are allowed
     *  EAP method names are defined in core/EAP.php
     */
    private $authMethodElements = [
        'server' => [
            \core\common\EAP::TLS => ['CA', 'ServerID'],
            \core\common\EAP::FAST => ['CA', 'ServerID'],
            \core\common\EAP::PEAP => ['CA', 'ServerID'],
            \core\common\EAP::TTLS => ['CA', 'ServerID'],
            \core\common\EAP::PWD => ['ServerID'],
        ],
        'client' => [
            \core\common\EAP::TLS => ['UserName', 'Password', 'ClientCertificate'],
            \core\common\EAP::NE_MSCHAP2 => ['UserName', 'Password', 'OuterIdentity', 'InnerIdentitySuffix', 'InnerIdentityHint'],
            \core\common\EAP::MSCHAP2 => ['UserName', 'Password', 'OuterIdentity', 'InnerIdentitySuffix', 'InnerIdentityHint'],
            \core\common\EAP::GTC => ['UserName', 'OneTimeToken'],
            \core\common\EAP::NE_PAP => ['UserName', 'Password', 'OuterIdentity', 'InnerIdentitySuffix', 'InnerIdentityHint'],
            \core\common\EAP::NE_SILVERBULLET => ['UserName', 'ClientCertificate', 'OuterIdentity'],
        ]
    ];

    /**
     * construct the device
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * $langScope can be 'global' when all lang and all lang-specific information
     * is dumped or 'single' when only the selected lang (and defaults) are passed
     * NOTICE: 'global' is not yet supported
     * 
     * @var string
     */
    public $langScope;

    /**
     * whether all EAP types should be included in the file or only the 
     * preferred one
     * 
     * @var boolean
     */
    public $allEaps = false;

    /**
     * vendor-specific additional information, this is nit yest fully
     * implemented due to lack of use cases.
     * 
     * @var array
     */
    public $VendorSpecific;
    
    /**
     * A flag to preserve backwards compatibility for eduroamCAT application
     */
    public $eduroamCATcompatibility = false; 
    public $singleEAPProvider = false;

    private $eapId;
    private $namespace;
    private $providerInfo;
    private $authMethodsList;
    
    /**
     * create HTML code explaining the installer
     * 
     * @return string
     */
    public function writeDeviceInfo()
    {
        \core\common\Entity::intoThePotatoes();
        $out = "<p>";
        $out .= sprintf(_("This is a generic configuration file in the IETF <a href='%s'>EAP Metadata -00</a> XML format."), "https://tools.ietf.org/html/draft-winter-opsawg-eap-metadata-00");
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }

    /**
     * create the actual XML file
     * 
     * @return string filename of the generated installer
     * @throws Exception
     *
     */
    public function writeInstaller()
    {
        \core\common\Entity::intoThePotatoes();
        $rootname = 'EAPIdentityProviderList';
        $dom = new \DOMDocument('1.0', 'utf-8');
        $root = $dom->createElement($rootname);
        $dom->appendChild($root);
        $ns = $dom->createAttributeNS( 'http://www.w3.org/2001/XMLSchema-instance', 'xsi:noNamespaceSchemaLocation' );
        $ns->value = "eap-metadata.xsd";
        $root->appendChild($ns);
        $this->openRoamingToU = sprintf(_("I have read and agree to OpenRoaming Terms of Use at %s."), "https://wballiance.com/openroaming/toc-2020/");
        foreach ($this->languageInstance->getAllTranslations("I have read and agree to OpenRoaming Terms of Use at %s", "device") as $lang => $message) {
            $this->openRoamingToUArray[$lang] = sprintf($message, "https://wballiance.com/openroaming/toc-2020/");
        }
        
        if (empty($this->attributes['internal:realm'][0])) {
            $this->eapId = 'undefined';
            $this->namespace = 'urn:undefined';
        } else {
            $this->eapId = $this->attributes['internal:realm'][0];
            $this->namespace = 'urn:RFC4282:realm';
        }
        
        $this->authMethodsList = $this->getAuthMethodsList();
        $this->loggerInstance->debug(5, $this->attributes['internal:networks'], "NETWORKS:", "\n");
        /*
         * This approach is forced by geteduroam compatibility. We pack all networks into a single Provider
         * with the exception of the openroaming one which we pack separately.
         */
        
        if ($this->singleEAPProvider === true) {
            /*
             * if "condition" is set to openroaming, OpenRoaming terms of use must be mentioned
             * unless the preagreed is set for openroaming
             * if "ask" is set then we generate a separate OR profile which needs to contain the OR ToU
             * the ToU is not needed in the eduroam-only profile
             */
            $ssids = [];
            $ois = [];
            $orNetwork = [];
            foreach ($this->attributes['internal:networks'] as $netName => $netDefinition) {
                if ($netDefinition['condition'] === 'internal:openroaming' &&
                        $this->attributes['internal:openroaming']) {
                    $this->setORtou();
                    if (preg_match("/^ask/",$this->attributes['media:openroaming'][0])) {
                        $orNetwork = $netDefinition;
                        continue;                        
                    }
                }
                foreach ($netDefinition['ssid'] as $ssid) {
                    $ssids[] = $ssid;
                }
                foreach ($netDefinition['oi'] as $oi) {
                    $ois[] = $oi;
                }
            }

            if (!empty($orNetwork)) {
                $this->addORtou = false;
            }
            $this->providerInfo = $this->getProviderInfo();
            
            if (!empty($ssids) || !empty($ois)) {
                \core\DeviceXMLmain::marshalObject($dom, $root, 'EAPIdentityProvider', $this->eapIdp($ssids, $ois));
            }
            
            if (!empty($orNetwork)) {
                // here we need to add the Tou unless preagreed is set
                $this->setORtou();
                $this->providerInfo = $this->getProviderInfo();
                \core\DeviceXMLmain::marshalObject($dom, $root, 'EAPIdentityProvider', $this->eapIdp($orNetwork['ssid'], $orNetwork['oi']));
            }
        } else {

            foreach ($this->attributes['internal:networks'] as $netName => $netDefinition) {
                if ($netDefinition['condition'] === 'internal:openroaming' &&
                        $this->attributes['internal:openroaming']) {
                    $this->setORtou();
                } else {
                    $this->addORtou = false;
                }
                $this->providerInfo = $this->getProviderInfo();
                $ssids = $netDefinition['ssid'];
                $ois = $netDefinition['oi'];
                if (!empty($ssids) || !empty($ois)) {
                    \core\DeviceXMLmain::marshalObject($dom, $root, 'EAPIdentityProvider', $this->eapIdp($ssids, $ois));
                }
            }
        }
        
        if ($dom->schemaValidate(ROOT.'/devices/eap_config/eap-metadata.xsd') === FALSE) {
            throw new Exception("Schema validation failed for eap-metadata");
        }

        $dom->formatOutput = true;
        file_put_contents($this->installerBasename.'.eap-config', $dom->saveXML($dom));
        \core\common\Entity::outOfThePotatoes();
        return($this->installerBasename.'.eap-config');
    }
    
    private function setORtou() {
        if (preg_match("/preagreed/",$this->attributes['media:openroaming'][0])) {
            $this->addORtou = false;
        } else {
            $this->addORtou = true;
        }
    }
    
    /**
     * determines the inner authentication. Is it EAP, and which mechanism is used to convey actual auth data
     * @param array $eap the EAP type for which we want to get the inner auth
     * @return array
     */    
    private function eapIdp($ssids, $oids)
    {
        $eapIdp = new \core\DeviceXMLmain();
        $eapIdp->setAttribute('version', '1');
        if ($this->langScope === 'single') {
            $eapIdp->setAttribute('lang', $this->languageInstance->getLang());
        }
        $eapIdp->setAttribute('ID', $this->eapId);
        $eapIdp->setAttribute('namespace', $this->namespace);
        $authMethods = new \core\DeviceXMLmain();
        $authMethods->setChild('AuthenticationMethod', $this->authMethodsList);
        $eapIdp->setChild('AuthenticationMethods', $authMethods);
        $eapIdp->setChild('CredentialApplicability', $this->getCredentialApplicability($ssids,$oids));
// TODO   $eap_idp->setChild('ValidUntil',$this->getValidUntil());
        $eapIdp->setChild('ProviderInfo', $this->providerInfo);
// TODO   $eap_idp->setChild('VendorSpecific',$this->getVendorSpecific());
        return($eapIdp);
    }

    /**
     * determines the inner authentication. Is it EAP, and which mechanism is used to convey actual auth data
     * @param array $eap the EAP type for which we want to get the inner auth
     * @return array
     */  
    private function innerAuth($eap)
    {
        $out = [];
        $out['EAP'] = 0;
        switch ($eap["INNER"]) {
            case \core\common\EAP::NE_MSCHAP2:
                if ($this->eduroamCATcompatibility === TRUE) {
                    $out['METHOD'] = \core\common\EAP::MSCHAP2;
                    $out['EAP'] = 1;
                } else {
                    $out['METHOD'] = $eap["INNER"];
                }
                break;
            case \core\common\EAP::NE_SILVERBULLET:
                $out['METHOD'] = \core\common\EAP::NONE;
                break;
            default:
                $out['METHOD'] = $eap["INNER"];
                break;
        }
        // override if there is an inner EAP
        if ($eap["INNER"] > 0) { // there is an inner EAP method
            $out['EAP'] = 1;
        }
        return $out;
    }
    
    /**
     * 
     * @param string $attrName the attribute name
     * @return array of values for this attribute
     */
    private function getSimpleMLAttribute($attrName)
    {
        if (empty($this->attributes[$attrName][0])) {
            return([]);
        }
        $attributeList = $this->attributes[$attrName];
        $objs = [];
        if ($this->langScope === 'global') {
            foreach ($attributeList['langs'] as $language => $value) {
                $language = ($language === 'C' ? 'any' : $language);
                $obj = new \core\DeviceXMLmain();
                $obj->setValue($value);
                $obj->setAttributes(['lang' => $language]);
                $objs[] = $obj;
            }
        } else {
            $objs[] = $attributeList[0];
        }
        return($objs);
    }
    
    /**
     * constructs the name of the institution and puts it into the XML.
     * consists of the best-language-match inst name, and if the inst has more 
     * than one profile also the best-language-match profile name
     * 
     * @return \core\DeviceXMLmain[]
     */
    private function getDisplayName()
    {
        $attr = $this->attributes;
        $objs = [];
        if ($this->langScope === 'global') {
            $instNameLangs = $attr['general:instname']['langs'];
            if ($attr['internal:profile_count'][0] > 1) {
                $profileNameLangs = $attr['profile:name']['langs'];
            }
            foreach ($instNameLangs as $language => $value) {
                $language = ($language === 'C' ? 'any' : $language);
                $displayname = new \core\DeviceXMLmain();
                if (isset($profileNameLangs)) {
                    $langOrC = isset($profileNameLangs[$language]) ? $profileNameLangs[$language] : $profileNameLangs['C'];
                    $value .= ' - '.$langOrC;
                }
                $displayname->setValue($value);
                $displayname->setAttributes(['lang' => $language]);
                $objs[] = $displayname;
            }
        } else {
            $displayname = new \core\DeviceXMLmain();
            $value = $attr['general:instname'][0];
            if ($attr['internal:profile_count'][0] > 1) {
                $value .= ' - '.$attr['profile:name'][0];
            }
            $displayname->setValue($value);
            $objs[] = $displayname;
        }
        return $objs;
    }

    /**
     * retrieves the provider logo and puts it into the XML structure
     * 
     * @return \core\DeviceXMLmain
     */
    private function getProviderLogo()
    {
        $attr = $this->attributes;
        if (isset($attr['general:logo_file'][0])) {
            $logoString = base64_encode($attr['general:logo_file'][0]);
            $logoMime = 'image/'.$attr['internal:logo_file'][0]['mime'];
            $providerlogo = new \core\DeviceXMLmain();
            $providerlogo->setAttributes(['mime' => $logoMime, 'encoding' => 'base64']);
            $providerlogo->setValue($logoString);
            return $providerlogo;
        }
        return NULL;
    }

    /**
     * retrieves provider information and puts it into the XML structure.
     * contains the profile description and the ToU file, if any
     * 
     * @return \core\DeviceXMLmain
     */
    private function getProviderInfo()
    {
        $providerinfo = new \core\DeviceXMLmain();
        $providerinfo->setChild('DisplayName', $this->getDisplayName());
        $providerinfo->setChild('Description', $this->getSimpleMLAttribute('profile:description'));
        $providerinfo->setChild('ProviderLocation', $this->getProviderLocation());
        $providerinfo->setChild('ProviderLogo', $this->getProviderLogo());
        $providerinfo->setChild('TermsOfUse', $this->getProviderTou(), null, 'cdata');
        $providerinfo->setChild('Helpdesk', $this->getHelpdesk());
        return $providerinfo; 
    }
    
    private function getProviderTou() {
        $standardTou = $this->getSimpleMLAttribute('support:info_file');
        if ($this->addORtou === false) {
            return $standardTou;
        }
        $out = [];
        if ($this->langScope === 'global') {
            foreach ($standardTou as $touObj) {
                $tou = $touObj->getValue();
                $lngAttr = $touObj->getAttributes();
                $lng = $lngAttr['lang'] === 'any' ? \config\Master::APPEARANCE['defaultlocale'] : $lngAttr['lang'];
                $tou .= "\n".$this->openRoamingToUArray[$lng];
                $touObj->setValue($tou);
                $out[] =  $touObj;
            } 
        } else {
            $tou = $standardTou[0];
            $tou .= "\n".$this->openRoamingToU;
            $out = [$tou];
        }
        return $out;
    }

    /**
     * retrieves the location information and puts it into the XML structure
     * 
     * @return \core\DeviceXMLmain[]
     */
    private function getProviderLocation()
    {
        $attr = $this->attributes;
        if (isset($attr['general:geo_coordinates'])) {
            $attrCoordinates = $attr['general:geo_coordinates'];
            $location = [];
            foreach ($attrCoordinates as $a) {
                $providerlocation = new \core\DeviceXMLmain();
                $b = json_decode($a, true);
                $providerlocation->setChild('Longitude', $b['lon']);
                $providerlocation->setChild('Latitude', $b['lat']);
                $location[] = $providerlocation;
            }           
            return $location;
        }
        return NULL;
    }

    /**
     * retrieves helpdesk contact information and puts it into the XML structure
     * 
     * @return \core\DeviceXMLmain
     */
    private function getHelpdesk()
    {
        $helpdesk = new \core\DeviceXMLmain();
        $helpdesk->setChild('EmailAddress', $this->getSimpleMLAttribute('support:email'));
        $helpdesk->setChild('WebAddress', $this->getSimpleMLAttribute('support:url'));
        $helpdesk->setChild('Phone', $this->getSimpleMLAttribute('support:phone'));
        return $helpdesk;
    }

    /**
     * determine where this credential should be applicable
     * 
     * @return \core\DeviceXMLmain
     */
    private function getCredentialApplicability($ssids, $oids)
    {
        $setWired = isset($this->attributes['media:wired'][0]) && 
                $this->attributes['media:wired'][0] == 'on' ? 1 : 0;        
        $credentialapplicability = new \core\DeviceXMLmain();
        $ieee80211s = [];
        foreach ($ssids as $ssid) {
            $ieee80211 = new \core\DeviceXMLmain();
            $ieee80211->setChild('SSID', $ssid);
            $ieee80211->setChild('MinRSNProto', 'CCMP');
            $ieee80211s[] = $ieee80211;
        }
        foreach ($oids as $oid) {
            $ieee80211 = new \core\DeviceXMLmain();
            $ieee80211->setChild('ConsortiumOID', $oid);
            $ieee80211s[] = $ieee80211;
        }
        $credentialapplicability->setChild('IEEE80211', $ieee80211s);
        if ($setWired) {
            $credentialapplicability->setChild('IEEE8023', '');
        }
        return $credentialapplicability;
    }

    /**
     * retrieves the parameters needed for the given EAP method and creates
     * appropriate nodes in the XML structure for them
     * 
     * @param array $eap the EAP type in question
     * @return array a recap of the findings
     */
    private function getAuthenticationMethodParams($eap)
    {
        $inner = $this->innerAuth($eap);
        $outerMethod = $eap["OUTER"];

        if (isset($inner["METHOD"]) && $inner["METHOD"]) {
            $innerauthmethod = new \core\DeviceXMLmain();
            $typeOfInner = ($inner["EAP"] ? 'EAPMethod' : 'NonEAPAuthMethod');
            $eapmethod = new \core\DeviceXMLmain();
            $eapmethod->setChild('Type', abs($inner['METHOD']));
            $innerauthmethod->setChild($typeOfInner, $eapmethod);
            return ['inner_method' => $innerauthmethod, 'methodID' => $outerMethod, 'inner_methodID' => $inner['METHOD']];
        } else {
            return ['inner_method' => 0, 'methodID' => $outerMethod, 'inner_methodID' => 0];
        }
    }

    /**
     * sets the server-side credentials for a given EAP type
     * 
     * @param \devices\XML\Type $eaptype the EAP type
     * @return \core\DeviceXMLmain
     */
    private function getServerSideCredentials($eap)
    {
        $attr = $this->attributes;
        $children = $this->authMethodElements['server'][$eap];
        $serversidecredential = new \core\DeviceXMLmain();
// Certificates and server names
        $cAlist = [];
        $attrCaList = $attr['internal:CAs'][0];
        foreach ($attrCaList as $ca) {
            $caObject = new \core\DeviceXMLmain();
            $caObject->setValue(base64_encode($ca['der']));
            $caObject->setAttributes(['format' => 'X.509', 'encoding' => 'base64']);
            $cAlist[] = $caObject;
        }
        $serverids = [];
        $servers = $attr['eap:server_name'];
        foreach ($servers as $server) {
            $serverid = new \core\DeviceXMLmain();
            $serverid->setValue($server);
            $serverids[] = $serverid;
        }
        if (in_array('CA', $children)) {
            $serversidecredential->setChild('CA', $cAlist);
        }
        if (in_array('ServerID', $children)) {
            $serversidecredential->setChild('ServerID', $serverids);
        }
        return $serversidecredential;
    }

    /**
     * sets the realm information for the client-side credential
     * 
     * @param \core\DeviceXMLmain $clientsidecredential the ClientSideCredential to which the realm info is to be added
     * @return void
     */
    private function setClientSideRealm($clientsidecredential)
    {
        $attr = $this->attributes;
        $realm = \core\common\Entity::getAttributeValue($attr, 'internal:realm', 0);
        if ($realm === NULL) {
            return;
        }
        if (\core\common\Entity::getAttributeValue($attr, 'internal:verify_userinput_suffix', 0) !== 1) {
            return;
        }
        $clientsidecredential->setChild('InnerIdentitySuffix', $realm);
        if (\core\common\Entity::getAttributeValue($attr, 'internal:hint_userinput_suffix', 0) === 1) {
            $clientsidecredential->setChild('InnerIdentityHint', 'true');
        }
    }

    /**
     * sets the client certificate
     * 
     * @return \core\DeviceXMLmain
     */
    private function getClientCertificate()
    {
        $clientCertificateObject = new \core\DeviceXMLmain();
        $clientCertificateObject->setValue(base64_encode($this->clientCert["certdata"]));
        $clientCertificateObject->setAttributes(['format' => 'PKCS12', 'encoding' => 'base64']);
        return $clientCertificateObject;
    }

    /**
     * sets the client-side credentials for the given EAP type
     * 
     * @param array $eapParams the EAP parameters
     * @return \core\DeviceXMLmain
     */
    private function getClientSideCredentials($eap)
    {
        $children = $this->authMethodElements['client'][$eap];
        $clientsidecredential = new \core\DeviceXMLmain();
        $outerId = $this->determineOuterIdString();
        $this->loggerInstance->debug(5, $eap, "XMLOI:", "\n");
        if (in_array('OuterIdentity', $children)) {
            if ($outerId !== NULL) {
                $clientsidecredential->setChild('OuterIdentity', $outerId);
            }
        }
        $this->setClientSideRealm($clientsidecredential);
//        $clientsidecredential->setChild('EAPType', $eapParams['inner_methodID'] ? $eapParams['inner_methodID'] : $eapParams['methodID']);

        // Client Certificate
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $attr = $this->attributes;
            $outerId = \core\common\Entity::getAttributeValue($attr, 'internal:username', 0);
            $clientsidecredential->setChild('OuterIdentity', $outerId);
            $clientsidecredential->setChild('ClientCertificate', $this->getClientCertificate());
        }
        return $clientsidecredential;
    }

    /**
     * sets the EAP method
     * 
     * @param \devices\XML\Type $eaptype the EAP type XMLObject
     * @return \core\DeviceXMLmain
     */
    private function getEapMethod($eaptype)
    {
        $eapmethod = new \core\DeviceXMLmain();
        $eapmethod->setChild('Type', $eaptype);
        if (isset($this->VendorSpecific)) {
            $vendorspecifics = [];
            foreach ($this->VendorSpecific as $vs) {
                $vendorspecific = new \core\DeviceXMLmain();
                $vs['value']->addAttribute('xsi:noNamespaceSchemaLocation', "xxx.xsd");
                $vendorspecific->setValue($vs['value']);
                $vendorspecific->setAttributes(['vendor' => $vs['vendor']]);
                $vendorspecifics[] = $vendorspecific;
            }
            $eapmethod->setChild('VendorSpecific', $vendorspecifics);
        }
        return($eapmethod);
    }

    /**
     * determines the authentication method to use
     * 
     * @param array $eap the EAP methods, in array representation
     * @return \core\DeviceXMLmain
     */
    private function getAuthMethod($eap)
    {
        $authmethod = new \core\DeviceXMLmain();
        $eapParams = $this->getAuthenticationMethodParams($eap);
        $eaptype = new \core\DeviceXMLmain();
        $eaptype->setValue($eapParams['methodID']);
// Type
        $authmethod->setChild('EAPMethod', $this->getEapMethod($eaptype));

// ServerSideCredentials
        $authmethod->setChild('ServerSideCredential', $this->getServerSideCredentials($eap['OUTER']));

// ClientSideCredentials
        $authmethod->setChild('ClientSideCredential', $this->getClientSideCredentials($eap['INNER']));

        if ($eapParams['inner_method']) {
            $authmethod->setChild('InnerAuthenticationMethod', $eapParams['inner_method']);
        }
        return $authmethod;
    }
    
    private function getAuthMethodsList() {
        $methodList = [];
        if ($this->allEaps) {
            $eapmethods = [];
            foreach ($this->attributes['all_eaps'] as $eap) {
                $eapRep = $eap->getArrayRep();
                if (in_array($eapRep, $this->supportedEapMethods)) {
                    $eapmethods[] = $eapRep;
                }
            }
        } else {
            $eapmethods = [$this->selectedEap];
        }
        foreach ($eapmethods as $eap) {
            $methodList[] = $this->getAuthMethod($eap);
        }
        return $methodList;
    }
    
    private $openRoamingToUArray;
    private $openRoamingToU;
    private $addORtou = false;

}
