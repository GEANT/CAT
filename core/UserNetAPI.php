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
class UserNetAPI extends UserAPI
{

    /**
     * nothing special to be done here.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *  wrapper JSON function
     * 
     * @param array|bool|null $data      the core data to be converted to JSON
     * @param int             $status    extra status information, defaults to 1
     * @param array           $otherData additional data to include
     * @return string JSON encoded data
     * @throws \Exception
     */
    public function returnJSON($data, $status = 1, $otherData = [])
    {
        $validator = new \web\lib\common\InputValidation();
        $host = $validator->hostname($_SERVER['SERVER_NAME']);
        if ($host === FALSE) {
            throw new \Exception("We don't know our own hostname?!? Giving up.");
        }
        $returnArray = [];
        $returnArray['status'] = $status;
        $returnArray['data'] = $data;
        $returnArray['tou'] = "Please consult Terms of Use at: //".$host.\core\CAT::getRootUrlPath()."/tou.php";
        if (!empty($otherData)) {
            $returnArray['otherdata'] = $otherData;
        }
        return(json_encode($returnArray));
    }

    /**
     * outputs the list of supported languages.
     * 
     * @return void creates direct output
     */
    public function jsonListLanguages()
    {
        $returnArray = [];
        foreach (\config\Master::LANGUAGES as $id => $val) {
            $returnArray[] = ['lang' => $id, 'display' => $val['display'], 'locale' => $val['locale']];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs the list of countries with configured IdPs
     *
     * @return void creates direct output
     */
    public function jsonListCountries()
    {
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
     * @return void creates direct output
     */
    public function jsonListIdentityProviders($country)
    {
        $idps = $this->listAllIdentityProviders(1, $country);
        $returnArray = [];
        foreach ($idps as $idp) {
            $returnArray[] = ['idp' => $idp['entityID'], 'id' => $idp['entityID'], 'display' => $idp['title']];
        }
        echo $this->returnJSON($returnArray);
    }

    /**
     * outputs the list of all active IdPs
     * The IdP list is formatted for DiscoJuice consumption
     * 
     * @return void creates direct output
     */
    public function jsonListIdentityProvidersForDisco()
    {
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
     * @param string $country  the country in question
     * @param array  $location the coordinates of the approximate user location
     * @return void creates direct output
     */
    public function jsonOrderIdentityProviders($country, $location)
    {
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
     * @param int $sort          should the result set be sorted? 0 = no, 1 = yes
     * @return void creates direct output
     */
    public function jsonListProfiles($idpIdentifier, $sort = 0)
    {
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
            $returnArray[] = ['profile' => $profile->identifier, 'id' => $profile->identifier, 'display' => $profile->name, 'idp_name' => $profile->instName, 'logo' => $hasLogo];
        }
        echo $this->returnJSON($returnArray, 1, $otherData);
    }
    
    /**
     * outputs a full list of IdPs containing the fllowing data:
     * institution_is, institution name in all available languages,
     * list of production profiles.
     * For eache profile the profile identifier, profile name in all languages
     * and redirect values (empty rediret value means that no redirect has been
     * set).
     * @return array of identity providers with attributes
     */
    public function jsonListIdentityProvidersWithProfiles() {
        echo $this->returnJSON($this->listIdentityProvidersWithProfiles());
    }

    /**
     * outputs the list of devices available for the given profile
     *
     * @param int $profileId the Profile identifier
     * @return void creates direct output
     */
    public function jsonListDevices($profileId)
    {
        $returnArray = [];
        $profileAttributes = $this->profileAttributes($profileId);
        $thedevices = $profileAttributes['devices'];
        foreach ($thedevices as $D) {
            if (\core\common\Entity::getAttributeValue($D, 'options', 'hidden') === 1) {
                continue;
            }
            if ($D['id'] === '0') { // This is a global profile level redirect therefore no device name is available
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
     * @param string $device  identifier as in {@link devices.php}
     * @param int    $prof_id profile identifier
     * @return void creates direct output
     */
    public function jsonGenerateInstaller($device, $prof_id, $openRoaming = 0)
    {
        $this->loggerInstance->debug(4, "JSON::generateInstaller arguments: $device,$prof_id, $openRoaming\n");
        $output = $this->generateInstaller($device, $prof_id, 'user', $openRoaming);
        $this->loggerInstance->debug(4, "output from GUI::generateInstaller:");
        $this->loggerInstance->debug(4, print_r($output, true));
        $this->loggerInstance->debug(4, json_encode($output));
//    header('Content-type: application/json; utf-8');
        echo $this->returnJSON($output);
    }

    /**
     * outputs OS guess in JSON
     * 
     * @return void creates direct output
     */
    public function jsonDetectOS()
    {
        $status = 1;
        $returnArray = $this->detectOS();
        if ($returnArray === FALSE) {
            $status = 0;
        }
        echo $this->returnJSON($returnArray, $status);
    }

    /**
     * outputs user certificates pertaining to a given token in JSON
     * @param string $token the token for which we want certs
     * @return void creates direct output
     */
    public function jsonGetUserCerts($token)
    {
        $status = 1;
        $returnArrayE = $this->getUserCerts($token);
        $returnArray = [];
        if ($returnArrayE === FALSE) {
            $status = 0;
        }
        if ($status === 1) {
            foreach ($returnArrayE as $element) {
                $returnArray[] = $element->getBasicInfo();
            }
        }
        echo $this->returnJSON($returnArray, $status);
    }

    /**
     * outputs the user location as JSON
     * 
     * @return void creates direct output
     */
    public function jsonLocateUser()
    {
        header('Content-type: application/json; utf-8');
        echo json_encode($this->locateDevice());
    }

    /**
     * outputs support data prepared within {@link GUI::profileAttributes()}
     * 
     * @param integer $profileId the profile ID
     * @return void creates direct output
     */
    public function jsonProfileAttributes($profileId)
    {
//    header('Content-type: application/json; utf-8');
        echo $this->returnJSON($this->profileAttributes($profileId));
    }

    /**
     * outputs a logo
     * 
     * @param int|string $identifier identifier of the object whose logo is sought
     * @param string     $type       "federation" or "idp"
     * @param integer    $width      desired target width
     * @param integer    $height     desired target height
     * @return void creates direct output
     */
    public function sendLogo($identifier, $type, $width, $height)
    {
        $logo = $this->getLogo($identifier, $type, $width, $height);
        header("Content-type: ".$logo['filetype']);
        header("Cache-Control:max-age=36000, must-revalidate");
        header($logo['expires']);
        echo $logo['blob'];
    }
}