<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

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
require_once('DBConnection.php');
require_once("Federation.php");
require_once("IdP.php");
require_once('EntityWithDBProperties.php');
require_once("core/PHPMailer/src/PHPMailer.php");
require_once("core/PHPMailer/src/SMTP.php");

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
        $this->attributes = [];
        $this->entityOptionTable = "user_options";
        $this->entityIdColumn = "user_id";
        $this->identifier = DBConnection::escape_value($this->databaseType, $userId);

        $optioninstance = Options::instance();

        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
// e d u r o a m DB doesn't follow the usual approach
// we could get multiple rows below (if administering multiple
// federations), so consolidate all into the usual options
            $info = DBConnection::exec($this->databaseType, "SELECT email, common_name, role, realm FROM view_admin WHERE eptid = '$userId'");
            $visited = FALSE;
            while ($userDetailQuery = mysqli_fetch_object($info)) {
                if (!$visited) {
                    $mailOptinfo = $optioninstance->optionType("user:email");
                    $this->attributes[] = ["name" => "user:email", "value" => $userDetailQuery->email, "level" => "User", "row" => 0, "flag" => $mailOptinfo['flag']];
                    $realnameOptinfo = $optioninstance->optionType("user:realname");
                    $this->attributes[] = ["name" => "user:realname", "value" => $userDetailQuery->common_name, "level" => "User", "row" => 0, "flag" => $realnameOptinfo['flag']];
                    $visited = TRUE;
                }
                if ($userDetailQuery->role == "fedadmin") {
                    $optinfo = $optioninstance->optionType("user:fedadmin");
                    $this->attributes[] = ["name" => "user:fedadmin", "value" => strtoupper($userDetailQuery->realm), "level" => "User", "row" => 0, "flag" => $optinfo['flag']];
                }
            }
        } else {
            $this->attributes = $this->retrieveOptionsFromDatabase("SELECT option_name, option_value, id AS row
                                                FROM $this->entityOptionTable
                                                WHERE $this->entityIdColumn = '$userId'", "User");
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
        return in_array($this->identifier, Config::$SUPERADMINS);
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
// use PHPMailer to send the mail
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->Host = Config::$MAILSETTINGS['host'];
        $mail->Username = Config::$MAILSETTINGS['user'];
        $mail->Password = Config::$MAILSETTINGS['pass'];
// formatting nitty-gritty
        $mail->WordWrap = 72;
        $mail->isHTML(FALSE);
        $mail->CharSet = 'UTF-8';
// who to whom?
        $mail->From = Config::$APPEARANCE['from-mail'];
        $mail->FromName = Config::$APPEARANCE['productname'] . " Notification System";
        $mail->addReplyTo(Config::$APPEARANCE['support-contact']['mail'], Config::$APPEARANCE['productname'] . " " . _("Feedback"));
        $mail->addAddress($mailaddr[0]["value"]);
// what do we want to say?
        $mail->Subject = $subject;
        $mail->Body = $content;
        if (isset(Config::$CONSORTIUM['certfilename'], Config::$CONSORTIUM['keyfilename'], Config::$CONSORTIUM['keypass'])) {
            $mail->sign(Config::$CONSORTIUM['certfilename'], Config::$CONSORTIUM['keyfilename'], Config::$CONSORTIUM['keypass']);
        }

        $sent = $mail->send();

        return $sent;
    }

}
