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
 * This is the collection of methods dedicated for the user GUI
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserAPI
 *
 * Parts of this code are based on simpleSAMLPhp discojuice module.
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
 */

namespace core;
use \Exception;

/**
 * This class collect methods used for comminication via network UserAPI
 * The methods are generally wrappers around more general UserAPI ones
 */

class UserNetAPI extends UserAPI {

    /**
     * nothing special to be done here.
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     *  wrapper JSON function
     * 
     * @param array|bool|null $data the core data to be converted to JSON
     * @param int $status extra status information, defaults to 1
     * @return string JSON encoded data
     */
    public function returnJSON($data, $status = 1, $otherData = []) {
        $validator = new \web\lib\common\InputValidation();
        $host = $validator->hostname($_SERVER['SERVER_NAME']);
        if ($host === FALSE) {
            throw new \Exception("We don't know our own hostname?!? Giving up.");
        }
        $returnArray = [];
        $returnArray['status'] = $status;
        $returnArray['data'] = $data;
        $returnArray['tou'] = "Please consult Terms of Use at: //" . $host . \core\CAT::getRootUrlPath() . "/tou.php";
        if (!empty($otherData)) {
            $returnArray['otherdata'] = $otherData;
        }
        return(json_encode($returnArray));
    }

    /**
     * outputs the list of supported languages.
     */
    public function JSON_listLanguages() {
        $returnArray = [];
        foreach (CONFIG['LANGUAGES'] as $id => $val) {
            $returnArray[] = ['lang' => $id, 'display' => $val['display'], 'locale' => $val['locale']];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs the list of countries with configured IdPs
     *
     */
    public function JSON_listCountries() {
        $federations = $this->printCountryList(1);
        $returnArray = [];
        foreach ($federations as $id => $val) {
            $returnArray[] = ['federation' => $id, 'display' => $val];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs the list of IdPs in a given country
     *
     * @param string $country the country we are interested in
     */
    public function JSON_listIdentityProviders($country) {
        $idps = $this->listAllIdentityProviders(1, $country);
        $returnArray = [];
        foreach ($idps as $idp) {
            $returnArray[] = ['idp' => $idp['entityID'], 'id' => $idp['entityID'], 'display' => $idp['title']];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs the list of all active IdPs
     *
     * The IdP list is formatted for DiscoJuice consumption
     */
    public function JSON_listIdentityProvidersForDisco() {
        $idps = $this->listAllIdentityProviders(1);
        $returnArray = [];
        foreach ($idps as $idp) {
            $idp['idp'] = $idp['entityID'];
            $idp['id'] = $idp['entityID'];
            $returnArray[] = $idp;
        }
        echo json_encode($returnArray);
    }

    /**
     * outputs the list of IdPs in a given country ordered with respect to their distance to the user's location
     * 
     * @param string $country the country in question
     * @param array $location the coordinates of the approximate user location
     *
     */
    public function JSON_orderIdentityProviders($country, $location = NULL) {
        $idps = $this->orderIdentityProviders($country, $location);
        $returnArray = [];
        foreach ($idps as $idp) {
            $returnArray[] = ['idp' => $idp['id'], 'display' => $idp['title']];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs a list of profiles available for a given IdP
     *
     * @param int $idpIdentifier the IdP identifier
     * @param int $sort should the result set be sorted? 0 = no, 1 = yes
     */
    public function JSON_listProfiles($idpIdentifier, $sort = 0) {
        $this->languageInstance->setTextDomain("web_user");
        $returnArray = [];
        try {
            $idp = new IdP($idpIdentifier);
        } catch (\Exception $fail) {
            echo $this->returnJSON($returnArray, 0);
            return;
        }
        $hasLogo = 0;
        $logo = $idp->getAttributes('general:logo_file');
        if (count($logo) > 0) {
            $hasLogo = 1;
        }
        $fed = new Federation($idp->federation);
        $fedUrl = $idp->languageInstance->getLocalisedValue($fed->getAttributes('fed:url'));
        $fedName = $idp->languageInstance->getLocalisedValue($fed->getAttributes('fed:realname'));
        $otherData = [];
        if (!empty($fedUrl)) {
            $otherData['fedurl'] = $fedUrl;
        }
        if (!empty($fedName)) {
            $otherData['fedname'] = $fedName;
        }
        $profiles = $idp->listProfiles(TRUE);
        if ($sort == 1) {
            usort($profiles, ["UserAPI", "profileSort"]);
        }
        foreach ($profiles as $profile) {
            $returnArray[] = ['profile' => $profile->identifier, 'id'=>$profile->identifier, 'display' => $profile->name, 'idp_name' => $profile->instName, 'logo' => $hasLogo];
        }
        echo $this->returnJSON($returnArray, 1, $otherData);
    }

    /**
     * outputs the list of devices available for the given profile
     *
     * @param int $profileId the Profile identifier
     */
    public function JSON_listDevices($profileId) {
        $this->languageInstance->setTextDomain("web_user");
        $returnArray = [];
        $profileAttributes = $this->profileAttributes($profileId);
        $thedevices = $profileAttributes['devices'];
        foreach ($thedevices as $D) {
            if (\core\common\Entity::getAttributeValue($D, 'options', 'hidden') === 1) {
                continue;
            }
            if ($D['device'] === '0') {
                $disp = '';
            } else {
                $disp = $D['display'];
            }
            $returnArray[] = ['device' => $D['id'], 'display' => $disp, 'status' => $D['status'], 'redirect' => $D['redirect']];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs the link to the installers (additionally, actually generates it or takes it from cache)
     *
     * @param string $device identifier as in {@link devices.php}
     * @param int $prof_id profile identifier
     */
    public function JSON_generateInstaller($device, $prof_id) {
        $this->loggerInstance->debug(4, "JSON::generateInstaller arguments: $device,$prof_id\n");
        $output = $this->generateInstaller($device, $prof_id);
        $this->loggerInstance->debug(4, "output from GUI::generateInstaller:");
        $this->loggerInstance->debug(4, print_r($output, true));
        $this->loggerInstance->debug(4, json_encode($output));
//    header('Content-type: application/json; utf-8');
        echo $this->returnJSON($output);
    }

    /**
     * outputs OS guess in JSON
     */
    public function JSON_detectOS() {
        $returnArray = $this->detectOS();
        $status = is_array($returnArray) ? 1 : 0;
        echo $this->returnJSON($returnArray, $status);
    }
    
    /**
     * outputs user certificates pertaining to a given token in JSON
     * @param string $token
     */
    public function JSON_getUserCerts($token) {
        $returnArrayE = $this->getUserCerts($token);
        $returnArray = [];
        $status = is_array($returnArrayE) ? 1 : 0;
        if ($status === 1) {
            foreach ($returnArrayE as $element) {
                $returnArray[] = $element->getBasicInfo();
            }
        }
        echo $this->returnJSON($returnArray, $status);
    }
    
    /** outputs the user location as JSON
     * @throws Exception
     */
    public function JSON_locateUser() {
        header('Content-type: application/json; utf-8');
        echo json_encode($this->locateDevice());
    }

    /**
     * outputs support data prepared within {@link GUI::profileAttributes()}
     */
    public function JSON_profileAttributes($prof_id) {
//    header('Content-type: application/json; utf-8');
        echo $this->returnJSON($this->profileAttributes($prof_id));
    }

    /**
     * outputs a logo
     * 
     * @param int|string $identifier
     * @param string $type "federation" or "idp"
     * @param int $width
     * @param int $height
     */
    public function sendLogo($identifier, $type, $width = 0, $height = 0) {
        $logo = $this->getLogo($identifier, $type, $width, $height);
        $blob = $logo === NULL ? file_get_contents(ROOT . '/web/resources/images/empty.png') : $logo['blob'];
        header("Content-type: " . $logo['filetype']);
        header("Cache-Control:max-age=36000, must-revalidate");
        header($logo['expires']);
        echo $blob;
    }
    
}
