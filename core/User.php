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
            $visited = FALSE;
            // SELECT -> resource, not boolean
            while ($userDetailQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $info)) {
                if (!$visited) {
                    $mailOptinfo = $optioninstance->optionType("user:email");
                    $this->attributes[] = ["name" => "user:email", "lang" => NULL, "value" => $userDetailQuery->email, "level" => Options::LEVEL_USER, "row" => 0, "flag" => $mailOptinfo['flag']];
                    $realnameOptinfo = $optioninstance->optionType("user:realname");
                    $this->attributes[] = ["name" => "user:realname", "lang" => NULL, "value" => $userDetailQuery->common_name, "level" => Options::LEVEL_USER, "row" => 0, "flag" => $realnameOptinfo['flag']];
                    $visited = TRUE;
                }
                if ($userDetailQuery->role == "fedadmin") {
                    $optinfo = $optioninstance->optionType("user:fedadmin");
                    $this->attributes[] = ["name" => "user:fedadmin", "lang" => NULL, "value" => strtoupper($userDetailQuery->realm), "level" => Options::LEVEL_USER, "row" => 0, "flag" => $optinfo['flag']];
                }
            }
        } else {
            $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row
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
     * @return boolean TRUE if the user is federation admin, FALSE if not 
     */
    public function isFederationAdmin($federation = 0)
    {
        $feds = $this->getAttributes("user:fedadmin");
        if (count($feds) == 0) { // not a fedadmin at all
            return FALSE;
        }
        if ($federation === 0) { // fedadmin for one; that's all we want to know
            return TRUE;
        }
        foreach ($feds as $fed) { // check if authz is for requested federation
            if (strtoupper($fed['value']) == strtoupper($federation)) {
                return TRUE;
            }
        }
        return FALSE; // no luck so far? Not the admin we are looking for.
    }

    /**
     * This function tests if the current user has been configured as the system superadmin, i.e. if the user is allowed
     * to execute the 112365365321.php script
     *
     * @return boolean TRUE if the user is a superadmin, FALSE if not 
     */
    public function isSuperadmin()
    {
        return in_array($this->userName, \config\Master::SUPERADMINS);
    }

    /**
     * This function tests if the current user is an ovner of a given IdP
     *
     * @param int $idp integer identifier of the IdP
     * @return boolean TRUE if the user is an owner, FALSE if not 
     */
    public function isIdPOwner($idp)
    {
        $temp = new IdP($idp);
        foreach ($temp->listOwners() as $oneowner) {
            if ($oneowner['ID'] == $this->userName) {
                return TRUE;
            }
        }
        return FALSE;
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
            return FALSE;
        }
        common\Entity::intoThePotatoes();
        $mail = \core\common\OutsideComm::mailHandle();
// who to whom?
        $mail->FromName = \config\Master::APPEARANCE['productname'] . " Notification System";
        $mail->addReplyTo(\config\Master::APPEARANCE['support-contact']['developer-mail'], \config\Master::APPEARANCE['productname'] . " " . _("Feedback"));
        $mail->addAddress($mailaddr[0]["value"]);
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
     * @return boolean|array the list of auth source IdPs we found for the mail, or FALSE if none found or invalid input
     */
    public static function findLoginIdPByEmail($mail, $lang)
    {
        $loggerInstance = new common\Logging();
        $listOfProviders = [];
        $matchedProviders = [];
        $skipCurl = 0;
        $realmail = filter_var($mail, FILTER_VALIDATE_EMAIL);
        if ($realmail === FALSE) {
            return FALSE;
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
            if ($finding === 0 || $finding === FALSE) {
                return FALSE;
            }

            $providerStrings = array_keys(User::PROVIDER_STRINGS);
            switch ($matches[1]) {
                case $providerStrings[0]: // eduGAIN needs to find the exact IdP behind it
                    $moreMatches = [];
                    $exactIdP = preg_match("/.*!(.*)$/", $matches[2], $moreMatches);
                    if ($exactIdP === 0 || $exactIdP === FALSE) {
                        break;
                    }
                    $idp = $moreMatches[1];
                    if (!in_array($idp, $matchedProviders)) {
                        $matchedProviders[] = $idp;
                        $name = $idp;
                        if ($skipCurl == 0) {
                            $url = \config\Diagnostics::EDUGAINRESOLVER['url'] . "?action=get_entity_name&type=idp&e_id=$idp&lang=$lang";
                            $ch = curl_init($url);
                            if ($ch === FALSE) {
                                $loggerInstance->debug(2, "Unable ask eduGAIN about IdP - CURL init failed!");
                                break;
                            }
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, \config\Diagnostics::EDUGAINRESOLVER['timeout']);
                            $response = curl_exec($ch);
                            if (is_bool($response)) { // catch both FALSE and TRUE because we use CURLOPT_RETURNTRANSFER
                                $skipCurl = 1;
                            } else {
                                $name = json_decode($response);
                            }
                            curl_close($ch);
                        }
                        $listOfProviders[] = User::PROVIDER_STRINGS[$providerStrings[0]] . " - IdP: " . $name;
                    }
                    break;
                case $providerStrings[1]:
                case $providerStrings[2]:
                case $providerStrings[3]:
                case $providerStrings[4]:
                case $providerStrings[5]:
                    if (!in_array(User::PROVIDER_STRINGS[$matches[1]], $listOfProviders)) {
                        $listOfProviders[] = User::PROVIDER_STRINGS[$matches[1]];
                    }
                    break;
                default:
                    return FALSE;
            }
        }
        return $listOfProviders;
    }
}