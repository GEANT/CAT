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
 * This class manages user privileges and bindings to institutions
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package Developer
 */
/**
 * necessary includes
 */

namespace core;

/**
 * This class represents a known CAT User (i.e. an institution and/or federation adiministrator).
 * @author Stefan Winter <stefan.winter@restena.lu>
 * 
 * @package Developer
 */
class User extends EntityWithDBProperties
{

    /**
     *
     * @var string
     */
    public $userName;
    
    public $edugain = false;

    /**
     * Class constructor. The required argument is a user's persistent identifier as was returned by the authentication source.
     * 
     * @param string $userId User Identifier as per authentication source
     */
    public function __construct($userId)
    {
        $this->databaseType = "USER";
        parent::__construct(); // database handle is now available
        $this->attributes = [];
        $this->entityOptionTable = "user_options";
        $this->entityIdColumn = "user_id";
        $this->identifier = 0; // not used
        $this->userName = $userId;
        $optioninstance = Options::instance();

        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
// e d u r o a m DB doesn't follow the usual approach
// we could get multiple rows below (if administering multiple
// federations), so consolidate all into the usual options
            $info = $this->databaseHandle->exec("SELECT email, common_name, role, realm FROM view_admin WHERE eptid = ?", "s", $this->userName);
            $visited = false;
            // SELECT -> resource, not boolean
            while ($userDetailQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $info)) {
                if (!$visited) {
                    $mailOptinfo = $optioninstance->optionType("user:email");
                    $this->attributes[] = ["name" => "user:email", "lang" => NULL, "value" => $userDetailQuery->email, "level" => Options::LEVEL_USER, "row_id" => 0, "flag" => $mailOptinfo['flag']];
                    $realnameOptinfo = $optioninstance->optionType("user:realname");
                    $this->attributes[] = ["name" => "user:realname", "lang" => NULL, "value" => $userDetailQuery->common_name, "level" => Options::LEVEL_USER, "row_id" => 0, "flag" => $realnameOptinfo['flag']];
                    $visited = TRUE;
                }
                if ($userDetailQuery->role == "fedadmin") {
                    $optinfo = $optioninstance->optionType("user:fedadmin");
                    $this->attributes[] = ["name" => "user:fedadmin", "lang" => NULL, "value" => strtoupper($userDetailQuery->realm), "level" => Options::LEVEL_USER, "row_id" => 0, "flag" => $optinfo['flag']];
                }
            }
        } else {
            $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row_id
                                                FROM $this->entityOptionTable
                                                WHERE $this->entityIdColumn = ?", "User");
        }
    }

    /**
     * This function checks whether a user is a federation administrator. When called without argument, it only checks if the
     * user is a federation administrator of *any* federation. When given a parameter (ISO shortname of federation), it checks
     * if the user administers this particular federation.
     * 
     * @param string $federation optional: federation to be checked
     * @return boolean TRUE if the user is federation admin, false if not 
     */
    public function isFederationAdmin($federation = 0)
    {
        $feds = $this->getAttributes("user:fedadmin");
        if (count($feds) == 0) { // not a fedadmin at all
            return false;
        }
        if ($federation === 0) { // fedadmin for one; that's all we want to know
            return TRUE;
        }
        foreach ($feds as $fed) { // check if authz is for requested federation
            if (strtoupper($fed['value']) == strtoupper($federation)) {
                return TRUE;
            }
        }
        return false; // no luck so far? Not the admin we are looking for.
    }

    /**
     * This function tests if the current user has been configured as the system superadmin, i.e. if the user is allowed
     * to execute the 112365365321.php script and obtain read-only access to admin areas.
     *
     * @return boolean TRUE if the user is a superadmin, false if not 
     */
    public function isSuperadmin()
    {
        return in_array($this->userName, \config\Master::SUPERADMINS);
    }
    
    
    /**
     * This function tests if the current user has been configured as the system superadmin, i.e. if the user is allowed
     *  obtain read-only access to admin areas.
     *
     * @return boolean TRUE if the user is a support member, false if not 
     */
    public function isSupport()
    {
        return in_array($this->userName, \config\Master::SUPPORT);
    }

    /**
     * This function tests if the current user is an ovner of a given IdP
     *
     * @param int $idp integer identifier of the IdP
     * @return boolean TRUE if the user is an owner, false if not 
     */
    public function isIdPOwner($idp)
    {
        $temp = new IdP($idp);
        foreach ($temp->listOwners() as $oneowner) {
            if ($oneowner['ID'] == $this->userName) {
                return TRUE;
            }
        }
        return false;
    }
    
    /** This function tests if user's IdP is listed in eduGAIN - it uses an external 
     *  call to technical eduGAIN API
     * 
     * @return boolean true if the IdP is listed, false otherwise
     * 
     */
    public function isFromEduGAIN()
    {
        $_SESSION['eduGAIN'] = false;
        preg_match('/!([^!]+)$/', $_SESSION['user'], $matches);
        if (!isset($matches[1])) {
            return false;
        }
        $entityId = $matches[1];
        $url = \config\Diagnostics::EDUGAINRESOLVER['url'] . "?action=get_entity_name&type=idp&opt=2&e_id=$entityId";
        \core\common\Logging::debug_s(4, $url, "URL: ","\n");
        $ch = curl_init($url);
        if ($ch === false) {
            $loggerInstance->debug(2, "Unable ask eduGAIN about IdP - CURL init failed!");
            return false;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, \config\Diagnostics::EDUGAINRESOLVER['timeout']);
        $response = curl_exec($ch);
        \core\common\Logging::debug_s(4, $response, "RESP\n", "\n");
        if (function_exists('json_validate')) {
            if (json_validate($response) === false) {
                \core\common\Logging::debug_s(2, "eduGAIN resolver did not return valid json\n");
                return false;
            }
        }
        $responseDetails = json_decode($response, true);
        if ($responseDetails == null || !isset($responseDetails['status'])) {
            \core\common\Logging::debug_s(2, $response, "EDUGAINRESOLVER returned incorrect response:\n", "\n");
            return false;
        }
        if ($responseDetails['status'] !== 1) {
            \core\common\Logging::debug_s(4, "EDUGAINRESOLVER returned status 0\n");
            return false;            
        }
        if ($responseDetails['name'] === null) {
            \core\common\Logging::debug_s(4,"User not in eduGAIN\n");
            return false;            
        }
        \core\common\Logging::debug_s(4,"User in eduGAIN\n");
        $_SESSION['eduGAIN'] = $responseDetails['regauth'];
        return true;
    }
    
    /**
     * This function lists all institution ids for which the user appears as admin
     * 
     * @return array if institution ids.
     */
    public function listOwnerships() {
        $dbHandle = \core\DBConnection::handle("INST");
        $query = $dbHandle->exec("SELECT institution_id FROM ownership WHERE user_id='".$this->userName."'");
        return array_column($query->fetch_all(), 0);
    }

    /**
     * shorthand function for email sending to the user
     * 
     * @param string $subject addressee of the mail
     * @param string $content content of the mail
     * @return boolean did it work?
     */
    public function sendMailToUser($subject, $content)
    {
        $mailaddr = $this->getAttributes("user:email");
        if (count($mailaddr) == 0) { // we don't know user's mail address
            return false;
        }
        return $this::doMailing($mailaddr[0]["value"], $subject, $content);
    }

    /**
     * sending mail to CAT admins (if defined in Master), mainly for debugging purposes
     * 
     * @param string $subject
     * @param string $content
     * @return boolean did it work?
     */
    public static function sendMailToCATadmins($subject, $content) {
        if (!isset(\config\Master::APPEARANCE['cat-admin-mail']) ||  \config\Master::APPEARANCE['cat-admin-mail'] === []) {
            return;
        }
        foreach (\config\Master::APPEARANCE['cat-admin-mail'] as $mailaddr) {
            $sent = User::doMailing($mailaddr, $subject, $content);
            if (!$sent) {
                \core\common\Logging::debug_s(2, $mailaddr, "Mailing to: ", " failed\n");
            }
        }
    }

    /**
     * shorthand function for actual email sending to the user
     * 
     * @param array $mailaddr the mail address ro mail to
     * @param string $subject addressee of the mail
     * @param string $content content of the mail
     * @return boolean did it work?
     */    
    private static function doMailing($mailaddr, $subject, $content) {
        common\Entity::intoThePotatoes();
        $mail = \core\common\OutsideComm::mailHandle();
// who to whom?
        $mail->FromName = \config\Master::APPEARANCE['productname'] . " Notification System";
        $mail->addReplyTo(\config\Master::APPEARANCE['support-contact']['developer-mail'], \config\Master::APPEARANCE['productname'] . " " . _("Feedback"));
        $mail->addAddress($mailaddr);
// what do we want to say?
        $mail->Subject = $subject;
        $mail->Body = $content;

        $sent = $mail->send();
        common\Entity::outOfThePotatoes();
        return $sent;        
    }

    /**
     * NOOP in this class, only need to override abstract base class
     * 
     * @return void
     */
    public function updateFreshness()
    {
        // User is always fresh
    }

    const PROVIDER_STRINGS = [
        "eduPersonTargetedID" => "eduGAIN",
        "pairwise-id" => "eduGAIN",
        "facebook_targetedID" => "Facebook",
        "google_eppn" => "Google",
        "linkedin_targetedID" => "LinkedIn",
        "twitter_targetedID" => "Twitter",
        "openid" => "Google (defunct)",
    ];

    /**
     * Some users apparently forget which eduGAIN/social ID they originally used
     * to log into CAT. We can try to help them: if they tell us the email
     * address by which they received the invitation token, then we can see if
     * any CAT IdPs are associated to an account which originally came in via
     * that email address. We then see which pretty-print auth provider name
     * was used
     * 
     * @param string $mail mail address to search with
     * @param string $lang language for the eduGAIN request
     * @return boolean|array the list of auth source IdPs we found for the mail, or false if none found or invalid input
     */
    public static function findLoginIdPByEmail($mail, $lang)
    {
        $loggerInstance = new common\Logging();
        $listOfProviders = [];
        $matchedProviders = [];
        $skipCurl = 0;
        $realmail = filter_var($mail, FILTER_VALIDATE_EMAIL);
        if ($realmail === false) {
            return false;
        }
        $dbHandle = \core\DBConnection::handle("INST");
        $query = $dbHandle->exec("SELECT user_id FROM ownership WHERE orig_mail = ?", "s", $realmail);

        // SELECT -> resource, not boolean
        while ($oneRow = mysqli_fetch_object(/** @scrutinizer ignore-type */ $query)) {
            $matches = [];
            $lookFor = "";
            foreach (User::PROVIDER_STRINGS as $name => $prettyname) {
                if ($lookFor != "") {
                    $lookFor .= "|";
                }
                $lookFor .= "$name";
            }
            $finding = preg_match("/^(" . $lookFor . "):(.*)/", $oneRow->user_id, $matches);
            if ($finding === 0 || $finding === false) {
                return false;
            }

            $providerStrings = array_keys(User::PROVIDER_STRINGS);
            switch ($matches[1]) {
                case $providerStrings[0]: // eduGAIN needs to find the exact IdP behind it
                case $providerStrings[1]:
                    $moreMatches = [];
                    $exactIdP = preg_match("/.*!([^!]*)$/", $matches[2], $moreMatches);
                    if ($exactIdP === 0 || $exactIdP === false) {
                        break;
                    }
                    $idp = $moreMatches[1];
                    if (!in_array($idp, $matchedProviders)) {
                        $matchedProviders[] = $idp;
                        $name = $idp;
                        if ($skipCurl == 0) {
                            $url = \config\Diagnostics::EDUGAINRESOLVER['url'] . "?action=get_entity_name&opt=2&type=idp&e_id=$idp&lang=$lang";
                            $ch = curl_init($url);
                            if ($ch === false) {
                                $loggerInstance->debug(2, "Unable ask eduGAIN about IdP - CURL init failed!");
                                break;
                            }
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, \config\Diagnostics::EDUGAINRESOLVER['timeout']);
                            $response = curl_exec($ch);
                            if (function_exists('json_validate')) {
                                if (json_validate($response) === false) {
                                    \core\common\Logging::debug_s(2, "eduGAIN resolver did not return valid json\n");
                                    return false;
                                }
                            }
                            if (is_bool($response)) { // catch both false and TRUE because we use CURLOPT_RETURNTRANSFER
                                $skipCurl = 1;
                            } else {
                                $responseDetails = json_decode($response, true);
                                if (isset($responseDetails['status']) && $responseDetails['status'] === 1 && $responseDetails['name'] !== null) {
                                    $name = $responseDetails['name'];
                                }
                            }
                            curl_close($ch);
                        }
                        $listOfProviders[] = User::PROVIDER_STRINGS[$providerStrings[0]] . " - IdP: " . $name;
                    }
                    break;
                case $providerStrings[2]:
                case $providerStrings[2]:
                case $providerStrings[3]:
                case $providerStrings[4]:
                case $providerStrings[6]:
                    if (!in_array(User::PROVIDER_STRINGS[$matches[1]], $listOfProviders)) {
                        $listOfProviders[] = User::PROVIDER_STRINGS[$matches[1]];
                    }
                    break;
                default:
                    return false;
            }
        }
        \core\common\Logging::debug_s(4,$listOfProviders, "PROVIDERS:\n", "\n");
        return $listOfProviders;
    }
}