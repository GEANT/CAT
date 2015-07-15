<?php

/* *********************************************************************************
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
require_once("core/PHPMailer/PHPMailerAutoload.php");

/**
 * This class represents a known CAT User (i.e. an institution and/or federation adiministrator).
 * @author Stefan Winter <stefan.winter@restena.lu>
 * 
 * @package Developer
 */
class User {

    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $DB_TYPE = "USER";

    /**
     * This variable gets initialised with the known user attributes in the constructor. It never gets updated until the object
     * is destroyed. So if attributes change in the database, and user attributes are to be queried afterwards, the object
     * needs to be re-instantiated to have current values in this variable.
     * 
     * @var array of user attributes
     */
    private $priv_attributes;

    /**
     * This variable holds the user's persistent identifier. This is not a real name; it is just an opaque handle as was returned by
     * the authentication source. It is comparable to an eduPersonTargetedId value (and may even be one).
     * 
     * @var string User's persistent identifier
     */
    public $identifier;

    /**
     * Class constructor. The required argument is a user's persistent identifier as was returned by the authentication source.
     * 
     * @param string $user_id User Identifier as per authentication source
     */
    public function __construct($user_id) {
        $user_id = DBConnection::escape_value(User::$DB_TYPE, $user_id);
        $optioninstance = Options::instance();
        $this->identifier = $user_id;
        $this->priv_attributes = array();

        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            // e d u r o a m DB doesn't follow the usual approach
            // we could get multiple rows below (if administering multiple
            // federations), so consolidate all into the usual options
            $info = DBConnection::exec(User::$DB_TYPE, "SELECT email, common_name, role, realm FROM view_admin WHERE eptid = '$user_id'");
            $visited = FALSE;
            while ($a = mysqli_fetch_object($info)) {
                if (!$visited) {
                    $optinfo = $optioninstance->optionType("user:email");
                    $flag = $optinfo['flag'];
                    $this->priv_attributes[] = array("name" => "user:email", "value" => $a->email, "level" => "User", "row" => 0, "flag" => $flag);
                    $optinfo = $optioninstance->optionType("user:realname");
                    $flag = $optinfo['flag'];
                    $this->priv_attributes[] = array("name" => "user:realname", "value" => $a->common_name, "level" => "User", "row" => 0, "flag" => $flag);
                    $visited = TRUE;
                }
                if ($a->role == "fedadmin") {
                    $optinfo = $optioninstance->optionType("user:fedadmin");
                    $flag = $optinfo['flag'];
                    $this->priv_attributes[] = array("name" => "user:fedadmin", "value" => strtoupper($a->realm), "level" => "User", "row" => 0, "flag" => $flag);
                }
            }

        } else {
            $user_options = DBConnection::exec(User::$DB_TYPE, "SELECT option_name, option_value, id AS row FROM user_options WHERE user_id = '$user_id'");
            while ($a = mysqli_fetch_object($user_options)) {
                $lang = "";
                // decode base64 for files (respecting multi-lang)
                $optinfo = $optioninstance->optionType($a->option_name);
                $flag = $optinfo['flag'];

                if ($optinfo['type'] != "file") {
                    $this->priv_attributes[] = array("name" => $a->option_name, "value" => $a->option_value, "level" => "User", "row" => $a->row, "flag" => $flag);
                } else {
                    if (unserialize($a->option_value) != FALSE) { // multi-lang
                        $content = unserialize($a->option_value);
                        $lang = $content['lang'];
                        $content = $content['content'];
                    } else { // single lang, direct content
                        $content = $a->option_value;
                    }

                    $content = base64_decode($content);

                    $this->priv_attributes[] = array("name" => $a->option_name, "value" => ($lang == "" ? $content : serialize(Array('lang' => $lang, 'content' => $content))), "level" => "User", "row" => $a->row, "flag" => $flag);
                }
            }
            // print_r($this->priv_attributes);
        }
    }

    /**
     * This function retrieves the known attributes of the user from the private member priv_attributes. 
     * The attributes are not taken "fresh" from the database; this is a performance optimisation.
     * The function's single parameter $option_name is optional - if specified, it only returns the attributes of the given type.
     * Otherwise, all known attributes are returned.
     * 
     * @param string $option_name name of the option whose values are to be returned
     * @return array of attributes
     */
    public function getAttributes($option_name = 0) {
        if ($option_name) {
            $returnarray = Array();
            foreach ($this->priv_attributes as $the_attr)
                if ($the_attr['name'] == $option_name)
                    $returnarray[] = $the_attr;
            return $returnarray;
        }
        else {
            return $this->priv_attributes;
        }
    }

    /**
     * This function adds a new attribute to the user. The attribute is stored persistently in the database immediately; however
     * the priv_attributes array is not updated. To see the new attributes via getAttributes(), re-instantiate the object.
     * 
     * @param type $attr_name name of the attribute to add
     * @param type $attr_value value of the attribute to add
     */
    public function addAttribute($attr_name, $attr_value) {
        $escaped_name = DBConnection::escape_value(User::$DB_TYPE, $this->identifier);
        $attr_name = DBConnection::escape_value(User::$DB_TYPE, $attr_name);
        $attr_value = DBConnection::escape_value(User::$DB_TYPE, $attr_value);
        if (!Config::$DB['userdb-readonly'])
            DBConnection::exec(User::$DB_TYPE, "INSERT INTO user_options (user_id, option_name, option_value) VALUES('"
                    . $escaped_name . "', '"
                    . $attr_name . "', '"
                    . $attr_value
                    . "')");
    }

    /**
     * This function deletes most attributes in this profile immediately, and marks the rest (file-based attributes) for later deletion.
     * The typical usage is to call this function, then determine which of the file-based attributes were not selected for deletion by the
     * user, and then delete those that were by calling commitFlushAttributes. Read-only attributes, like "user:fedadmin" are left
     * untouched.
     * 
     * @return array list of row id's of file-based attributes which weren't deleted (to be consumed by commitFlushAttributes)
     */
    public function beginFlushAttributes() {
        DBConnection::exec(User::$DB_TYPE, "DELETE FROM user_options WHERE user_id = '$this->identifier' AND option_name NOT LIKE '%_file' AND option_name NOT LIKE 'user:fedadmin'");
        $exec_q = DBConnection::exec(User::$DB_TYPE, "SELECT id FROM user_options WHERE user_id = '$this->identifier' AND option_name NOT LIKE 'user:fedadmin'");
        $return_array = array();
        while ($a = mysqli_fetch_object($exec_q))
            $return_array[$a->row] = "KILLME";
        return $return_array;
    }

    /**
     * This function deletes attributes from the database by their database row ID. Its typical (only) use is to take the return
     * of beginFlushAttribute() and delete the attributes in there. It only deletes rows which actually belong to the instantiated
     * user.
     *
     * @param array $tobedeleted array of database rows which are to be deleted
     */
    public function commitFlushAttributes($tobedeleted) {
        foreach (array_keys($tobedeleted) as $row) {
            DBConnection::exec(User::$DB_TYPE, "DELETE FROM user_options WHERE user_id = $this->identifier AND id = $row");
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
        if ($federation === 0) {
            if (count($feds) == 0)
                return FALSE;
            else
                return TRUE;
        } else {
            foreach ($feds as $fed) {
                if (strtoupper($fed['value']) == strtoupper($federation))
                    return TRUE;
            }
            return FALSE;
        }
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

    public function sendMailToUser($subject, $content) {
        // use PHPMailer to send the mail
        $mail = new PHPMailer();
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
        $mail->addReplyTo(Config::$APPEARANCE['admin-mail'], Config::$APPEARANCE['productname'] . " " ._("Feedback"));
        
        $mailaddr = $this->getAttributes("user:email");
        if (count($mailaddr) == 0) // we don't know his mail address
            return FALSE;
        
        $mail->addAddress($mailaddr);
        
        /* echo "<pre>";
        print_r($mailaddr);
        echo "</pre>";*/

        // what do we want to say?
        $mail->Subject = $subject;
        $mail->Body = $content;
        

        $sent = $mail->send();
        
        return $sent;
    }

}

?>
