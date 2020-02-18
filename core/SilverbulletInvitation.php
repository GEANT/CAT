<?php

/*
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Horizon 2020 
 * research and innovation programme under Grant Agreement No. 731122 (GN4-2).
 * 
 * On behalf of the GÉANT project, GEANT Association is the sole owner of the 
 * copyright in all material which was developed by a member of the GÉANT 
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
 * This file contains the SilverbulletInvitation class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

class SilverbulletInvitation extends common\Entity
{

    /**
     * row ID in the database pertaining to this invitation. 0 on invalid invitations.
     * 
     * @var integer
     */
    public $identifier;

    /**
     * The profile this invitation belongs to. 0 on invalid invitations.
     * 
     * @var integer
     */
    public $profile;

    /**
     * The user this invitation was created for (integer DB ID). 0 on invalid invitations.
     * 
     * @var integer
     */
    public $userId;

    /**
     *
     * @var string
     */
    public $invitationTokenString;

    /**
     * 
     * @var integer
     */
    public $invitationTokenStatus;

    /**
     * Expiry timestamp of invitation token. 2000-01-01 00:00:00 on invalid invitations.
     * 
     * @var string
     */
    public $expiry;

    /**
     * How many devices were allowed to be activated in total? 0 on invalid invitations.
     * 
     * @var integer
     */
    public $activationsTotal;

    /**
     * How many devices have not yet been activated? 0 on invalid invitations.
     *
     * @var integer
     */
    public $activationsRemaining;

    /**
     * 
     * @var array
     */
    public $associatedCertificates;

    /**
     * handle to the database
     * 
     * @var DBConnection
     */
    private $databaseHandle;

    const SB_TOKENSTATUS_VALID = 0;
    const SB_TOKENSTATUS_PARTIALLY_REDEEMED = 1;
    const SB_TOKENSTATUS_REDEEMED = 2;
    const SB_TOKENSTATUS_EXPIRED = 3;
    const SB_TOKENSTATUS_INVALID = 4;

    /**
     * instantiates an invitation identified by the token
     * 
     * @param string $invitationId token
     * @throws Exception
     */
    public function __construct($invitationId)
    {
        parent::__construct();
        $this->invitationTokenString = $invitationId;
        $handle = DBConnection::handle("INST");
        if ($handle instanceof DBConnection) {
            $this->databaseHandle = $handle;
        } else {
            throw new Exception("This database type is never an array!");
        }
        /*
         * Finds invitation by its token attribute and loads all certificates generated using the token.
         * Certificate details will always be empty, since code still needs to be adapted to return multiple certificates information.
         */
        $invColumnNames = "`id`, `profile_id`, `silverbullet_user_id`, `token`, `quantity`, `expiry`";
        $invitationsResult = $this->databaseHandle->exec("SELECT $invColumnNames FROM `silverbullet_invitation` WHERE `token`=? ORDER BY `expiry` DESC", "s", $this->invitationTokenString);
        $this->associatedCertificates = [];
        if ($invitationsResult->num_rows == 0) {
            $this->loggerInstance->debug(2, "Token $this->invitationTokenString not found in database or database query error!\n");
            $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_INVALID;
            $this->identifier = 0;
            $this->profile = 0;
            $this->userId = 0;
            $this->expiry = "2000-01-01 00:00:00";
            $this->activationsTotal = 0;
            $this->activationsRemaining = 0;
            return;
        }
        // if not returned, we found the token in the DB
        // -> instantiate the class
        // SELECT -> resource, no boolean
        $invitationRow = mysqli_fetch_object(/** @scrutinizer ignore-type */ $invitationsResult);
        $this->identifier = $invitationRow->id;
        $this->profile = $invitationRow->profile_id;
        $this->userId = $invitationRow->silverbullet_user_id;
        $this->expiry = $invitationRow->expiry;
        $this->activationsTotal = $invitationRow->quantity;
        $certificatesResult = $this->databaseHandle->exec("SELECT `serial_number`, `ca_type` FROM `silverbullet_certificate` WHERE `silverbullet_invitation_id` = ? ORDER BY `revocation_status`, `expiry` DESC", "i", $this->identifier);
        $certificatesNumber = ($certificatesResult ? $certificatesResult->num_rows : 0);
        $this->loggerInstance->debug(5, "At token validation level, " . $certificatesNumber . " certificates exist.\n");
        // SELECT -> resource, no boolean
        while ($runner = mysqli_fetch_object(/** @scrutinizer ignore-type */ $certificatesResult)) {
            $this->associatedCertificates[] = new \core\SilverbulletCertificate($runner->serial_number, $runner->ca_type);
        }
        $this->activationsRemaining = (int) ($this->activationsTotal - $certificatesNumber);
        // always check first if it is expired
        $now = new \DateTime();
        $expiryObject = new \DateTime($this->expiry);
        $delta = $now->diff($expiryObject);

        switch ($certificatesNumber) {
            case 0:
                // find out if it has expired
                if ($delta->invert == 1) {
                    $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_EXPIRED;
                    $this->activationsRemaining = 0;
                } else {
                    $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_VALID;
                }
                break;
            case $invitationRow->quantity:
                $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_REDEEMED;
                break;
            default:
                // find out if it has expired
                if ($delta->invert == 1) {
                    $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_EXPIRED;
                    $this->activationsRemaining = 0;
                } else {
                    assert($certificatesNumber > 0); // no negatives allowed
                    assert($certificatesNumber < $invitationRow->quantity || $invitationRow->quantity == 0); // not more than max quantity allowed (unless quantity is zero)
                    $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED;
                }
        }

        $this->loggerInstance->debug(5, "Done creating invitation token state from DB.\n");
    }

    /**
     * creates the full URL link, including the token as a GET parameter
     * 
     * @return string
     * @throws Exception
     */
    public function link()
    {
        if (isset($_SERVER['HTTPS'])) {
            $link = 'https://';
        } else {
            $link = 'http://';
        }
        $link .= $_SERVER['SERVER_NAME'];
        $relPath = dirname(dirname($_SERVER['SCRIPT_NAME']));
        if (substr($relPath, -1) == '/') {
            $relPath = substr($relPath, 0, -1);
            if ($relPath === FALSE) {
                throw new Exception("Uh. Something went seriously wrong with URL path mangling.");
            }
        }
        $link = $link . $relPath;

        if (preg_match('/admin$/', $link)) {
            $link = substr($link, 0, -6);
            if ($link === FALSE) {
                throw new Exception("Impossible: the string ends with '/admin' but it's not possible to cut six characters from the end?!");
            }
        }

        return $link . '/accountstatus/accountstatus.php?token=' . $this->invitationTokenString;
    }

    /**
     * returns the subject to use in an invitation mail
     * 
     * @return string
     */
    public function invitationMailSubject()
    {
        common\Entity::intoThePotatoes();
        $retval = sprintf(_("Your %s access is ready"), \config\ConfAssistant::CONSORTIUM['display_name']);
        common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * returns the body to use in an invitation mail
     * 
     * @return string
     */
    public function invitationMailBody()
    {
        common\Entity::intoThePotatoes();
        $text = _("Hello!");
        $text .= "\n\n";
        $text .= sprintf(_("A new %s access credential has been created for you by your network administrator."), \config\ConfAssistant::CONSORTIUM['display_name']);
        $text .= " ";
        $text .= sprintf(_("Please follow the following link with the device you want to enable for %s to get a custom %s installation program just for you. You can click on the link, copy and paste it into a browser or scan the attached QR code."), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['display_name']);
        $text .= "\n\n" . $this->link() . "\n\n"; // gets replaced with the token value by getBody()
        $text .= sprintf(_("Please keep this email or bookmark this link for future use. After picking up your %s installation program, you can use the same link to get status information about your %s account."), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['display_name']);
        $text .= "\n\n";
        $text .= _("Regards,");
        $text .= "\n\n";
        $text .= sprintf("%s", \config\Master::APPEARANCE['productname_long']);
        common\Entity::outOfThePotatoes();
        return $text;
    }

    /**
     * generates a new hex string to be used as an activation token
     * 
     * @return string
     */
    private static function generateInvitation()
    {
        return hash("sha512", openssl_random_pseudo_bytes(100));
    }

    /**
     * creates a new invitation in the database
     * @param int $profileId       the profile identifier
     * @param int $userId          the user identifier
     * @param int $activationCount number of activations for this invitation
     * @return SilverbulletInvitation the generated invitation
     */
    public static function createInvitation($profileId, $userId, $activationCount)
    {
        $handle = DBConnection::handle("INST");
        $query = "INSERT INTO silverbullet_invitation (profile_id, silverbullet_user_id, token, quantity, expiry) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))";
        $newToken = SilverbulletInvitation::generateInvitation();
        $handle->exec($query, "iisi", $profileId, $userId, $newToken, $activationCount);
        return new SilverbulletInvitation($newToken);
    }

    /**
     * revokes the invitation
     * 
     * @return void
     */
    public function revokeInvitation()
    {
        $query = "UPDATE silverbullet_invitation SET expiry = CURRENT_TIMESTAMP WHERE id = ? AND profile_id = ?";
        $this->databaseHandle->exec($query, "ii", $this->identifier, $this->profile);
    }

    /**
     * sends out the invitation by SMS
     * 
     * @param string $number the number to send to
     * @return int an OutsideComm constant indicating how the sending went
     */
    public function sendBySms($number)
    {
        common\Entity::intoThePotatoes();
        $text = sprintf(_("Your %s access is ready! Click here: %s (on Android, first install the app '%s'!)"), \config\ConfAssistant::CONSORTIUM['name'], $this->link(), "eduroam CAT");
        common\Entity::outOfThePotatoes();
        return \core\common\OutsideComm::sendSMS($number, $text);
    }

    /**
     * sends out the invitation by email
     * 
     * @param string $properEmail the email address
     * @return array status of the sending
     * @throws Exception
     */
    public function sendByMail($properEmail)
    {
        common\Entity::intoThePotatoes();
        $pixelsPerCode = 12;
        $mail = \core\common\OutsideComm::mailHandle();
        $uiElements = new \web\lib\admin\UIElements();
        // the following never returns NULL because $filename is FALSE; but
        // make sure it really is so for Scrutinizer
        $qrCode = new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
                    'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel' => \chillerlan\QRCode\QRCode::ECC_H,
                    'scale' => $pixelsPerCode,
                    'imageBase64' => FALSE,
        ]));

        $rawQr = $qrCode->render($this->link());
        if (empty($rawQr)) {
            throw new Exception("Something went seriously wrong with the QR code generation!");
        }
        $bytestream = $uiElements->pngInjectConsortiumLogo($rawQr, $pixelsPerCode);
        $mail->FromName = sprintf(_("%s Invitation System"), \config\Master::APPEARANCE['productname']);
        $mail->Subject = $this->invitationMailSubject();
        $mail->Body = $this->invitationMailBody();
        $mail->addStringAttachment($bytestream, "qr-code-invitation.png", "base64", "image/png");
        $mail->addAddress($properEmail);
        $domainStatus = \core\common\OutsideComm::mailAddressValidSecure($properEmail);
        common\Entity::outOfThePotatoes();
        return ["SENT" => $mail->send(), "TRANSPORT" => $domainStatus == common\OutsideComm::MAILDOMAIN_STARTTLS ? TRUE : FALSE];
    }
}