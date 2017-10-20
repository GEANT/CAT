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
class User extends EntityWithDBProperties {

    /**
     * Class constructor. The required argument is a user's persistent identifier as was returned by the authentication source.
     * 
     * @param string $userId User Identifier as per authentication source
     */
    public function __construct($userId) {
        $this->databaseType = "USER";
        parent::__construct(); // database handle is now available
        $this->attributes = [];
        $this->entityOptionTable = "user_options";
        $this->entityIdColumn = "user_id";
        $this->identifier = $userId;

        $optioninstance = Options::instance();

        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
// e d u r o a m DB doesn't follow the usual approach
// we could get multiple rows below (if administering multiple
// federations), so consolidate all into the usual options
            $info = $this->databaseHandle->exec("SELECT email, common_name, role, realm FROM view_admin WHERE eptid = ?", "s", $userId);
            $visited = FALSE;
            while ($userDetailQuery = mysqli_fetch_object($info)) {
                if (!$visited) {
                    $mailOptinfo = $optioninstance->optionType("user:email");
                    $this->attributes[] = ["name" => "user:email", "lang" => NULL, "value" => $userDetailQuery->email, "level" => "User", "row" => 0, "flag" => $mailOptinfo['flag']];
                    $realnameOptinfo = $optioninstance->optionType("user:realname");
                    $this->attributes[] = ["name" => "user:realname", "lang" => NULL, "value" => $userDetailQuery->common_name, "level" => "User", "row" => 0, "flag" => $realnameOptinfo['flag']];
                    $visited = TRUE;
                }
                if ($userDetailQuery->role == "fedadmin") {
                    $optinfo = $optioninstance->optionType("user:fedadmin");
                    $this->attributes[] = ["name" => "user:fedadmin", "lang" => NULL, "value" => strtoupper($userDetailQuery->realm), "level" => "User", "row" => 0, "flag" => $optinfo['flag']];
                }
            }
        } else {
            $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row
                                                FROM $this->entityOptionTable
                                                WHERE $this->entityIdColumn = ?", "User", "s", $userId);
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
    public function isFederationAdmin($federation = 0) {
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
    public function isSuperadmin() {
        return in_array($this->identifier, CONFIG['SUPERADMINS']);
    }

    /**
     *  This function tests if the current user is an ovner of a given IdP
     *
     * @return boolean TRUE if the user is an owner, FALSE if not 
     */
    public function isIdPOwner($idp) {
        $temp = new IdP($idp);
        foreach ($temp->owner() as $oneowner) {
            if ($oneowner['ID'] == $this->identifier) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function sendMailToUser($subject, $content) {
        $mailaddr = $this->getAttributes("user:email");
        if (count($mailaddr) == 0) { // we don't know user's mail address
            return FALSE;
        }
        $mail = \core\common\OutsideComm::mailHandle();
// who to whom?
        $mail->FromName = CONFIG['APPEARANCE']['productname'] . " Notification System";
        $mail->addReplyTo(CONFIG['APPEARANCE']['support-contact']['developer-mail'], CONFIG['APPEARANCE']['productname'] . " " . _("Feedback"));
        $mail->addAddress($mailaddr[0]["value"]);
// what do we want to say?
        $mail->Subject = $subject;
        $mail->Body = $content;

        $sent = $mail->send();

        return $sent;
    }

    public function updateFreshness() {
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
     * @param string $mail
     * @return false|array the list of auth source IdPs we found for the mail, or FALSE if none found or invalid input
     */
    public static function findLoginIdPByEmail($mail) {
        $listOfProviders = [];
        $realmail = filter_var($mail, FILTER_VALIDATE_EMAIL);
        if ($realmail === FALSE) {
            return FALSE;
        }
        $dbHandle = \core\DBConnection::handle("INST");
        $query = $dbHandle->exec("SELECT user_id FROM ownership WHERE orig_mail = ?", "s", $realmail);
        while ($oneRow = mysqli_fetch_object($query)) {
            $matches = [];
            $lookFor = "";
            foreach (User::PROVIDER_STRINGS as $name => $prettyname) {
                if ($lookFor != "") {
                    $lookFor .= "|";
                }
                $lookFor .= "$name";
            }
            $finding = preg_match("/^(".$lookFor."):(.*)/", $oneRow->user_id, $matches);
            if ($finding === 0 || $finding === FALSE) {
                return FALSE;
            }
            
            $providerStrings = array_keys(User::PROVIDER_STRINGS);
            switch ($matches[1]) {
                case $providerStrings[0]: // eduGAIN needs to find the exact IdP behind it
                    $moreMatches = [];
                    $exactIdP = preg_match("/.*!(.*)$/", $matches[2], $moreMatches);
                    if ($exactIdP === 0 || $exactIdP === FALSE) {
                        return FALSE;
                    }
                    if (!in_array(User::PROVIDER_STRINGS[$providerStrings[0]] . " - IdP " . $moreMatches[1], $listOfProviders)) {
                        $listOfProviders[] = User::PROVIDER_STRINGS[$providerStrings[0]] . " - IdP " . $moreMatches[1];
                    }
                    break;
                case $providerStrings[1]:
                case $providerStrings[2]:
                case $providerStrings[3]:
                case $providerStrings[4]:
                case $providerStrings[5]:
                    if (!in_array(User::PROVIDER_STRINGS[$matches[1]],$listOfProviders)) {
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
