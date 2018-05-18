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

require_once("phpqrcode.php");
const QRCODE_PIXELS_PER_SYMBOL = 12;


class SilverbulletInvitation extends common\Entity {

    /**
     * row ID in the database pertaining to this invitation. 0 on invalid invitations.
     * 
     * @var int
     */
    public $identifier;

    /**
     * The profile this invitation belongs to. 0 on invalid invitations.
     * 
     * @var int
     */
    public $profile;

    /**
     * The user this invitation was created for (integer DB ID). 0 on invalid invitations.
     * 
     * @var int
     */
    public $userId;

    /**
     *
     * @var string
     */
    public $invitationTokenString;

    /**
     * 
     * @var int
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
     * @var int
     */
    public $activationsTotal;

    /**
     * How many devices have not yet been activated? 0 on invalid invitations.
     *
     * @var int
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

    public function __construct($invitationId) {
        parent::__construct();
        $this->invitationTokenString = $invitationId;
        $this->databaseHandle = DBConnection::handle("INST");
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
        $certificatesResult = $this->databaseHandle->exec("SELECT `serial_number` FROM `silverbullet_certificate` WHERE `silverbullet_invitation_id` = ? ORDER BY `revocation_status`, `expiry` DESC", "i", $this->identifier);
        $certificatesNumber = ($certificatesResult ? $certificatesResult->num_rows : 0);
        $this->loggerInstance->debug(5, "At token validation level, " . $certificatesNumber . " certificates exist.\n");
        // SELECT -> resource, no boolean
        while ($runner = mysqli_fetch_object(/** @scrutinizer ignore-type */ $certificatesResult)) {
            $this->associatedCertificates[] = new \core\SilverbulletCertificate($runner->serial_number);
        }
        $this->activationsRemaining = (int) $this->activationsTotal - (int) $certificatesNumber;
        switch ($certificatesNumber) {
            case 0:
                // find out if it has expired
                $now = new \DateTime();
                $expiryObject = new \DateTime($this->expiry);
                $delta = $now->diff($expiryObject);
                if ($delta->invert == 1) {
                    $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_EXPIRED;
                    $this->activationsRemaining = 0;
                    break;
                }
                $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_VALID;
                break;
            case $invitationRow->quantity:
                $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_REDEEMED;
                break;
            default:
                assert($certificatesNumber > 0); // no negatives allowed
                assert($certificatesNumber < $invitationRow->quantity || $invitationRow->quantity == 0); // not more than max quantity allowed (unless quantity is zero)
                $this->invitationTokenStatus = SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED;
        }

        $this->loggerInstance->debug(5, "Done creating invitation token state from DB.\n");
    }

    public function link() {
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
     * @return string
     */
    public function invitationMailSubject() {
        return sprintf(_("Your %s access is ready"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
    }

    /**
     * returns the body to use in an invitation mail
     * @return string
     */
    public function invitationMailBody() {
        $text = _("Hello!");
        $text .= "\n\n";
        $text .= sprintf(_("A new %s access credential has been created for you by your network administrator."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $text .= " ";
        $text .= sprintf(_("Please follow the following link with the device you want to enable for %s to get a custom %s installation program just for you. You can click on the link, copy and paste it into a browser or scan the attached QR code."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $text .= "\n\n" . $this->link() . "\n\n"; // gets replaced with the token value by getBody()
        $text .= sprintf(_("Please keep this email or bookmark this link for future use. After picking up your %s installation program, you can use the same link to get status information about your %s account."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $text .= "\n\n";
        $text .= _("Regards,");
        $text .= "\n\n";
        $text .= sprintf("%s", CONFIG['APPEARANCE']['productname_long']);

        return $text;
    }

    /**
     * generates a new hex string to be used as an activation token
     * 
     * @return string
     */
    private static function generateInvitation() {
        return hash("sha512", base_convert(rand(0, (int) 10e16), 10, 36));
    }

    /**
     * creates a new invitation in the database
     * @param int $profileId
     * @param int $userId
     * @param int $activationCount
     */
    public static function createInvitation($profileId, $userId, $activationCount) {
        $handle = DBConnection::handle("INST");
        $query = "INSERT INTO silverbullet_invitation (profile_id, silverbullet_user_id, token, quantity, expiry) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))";
        $newToken = SilverbulletInvitation::generateInvitation();
        $handle->exec($query, "iisi", $profileId, $userId, $newToken, $activationCount);
        return new SilverbulletInvitation($newToken);
    }

    /**
     * revokes an invitation
     */
    public function revokeInvitation() {
        $query = "UPDATE silverbullet_invitation SET expiry = NOW() WHERE id = ? AND profile_id = ?";
        $this->databaseHandle->exec($query, "ii", $this->invitationTokenString, $this->identifier);
    }

    /**
     * 
     * @param string $number the number to send to
     * @return int an OutsideComm constant indicating how the sending went
     */
    public function sendBySms($number) {
        return \core\common\OutsideComm::sendSMS($number, sprintf(_("Your %s access is ready! Click here: %s (on Android, first install the app '%s'!)"), CONFIG_CONFASSISTANT['CONSORTIUM']['name'], $this->link()), "eduroam CAT");
    }

    public function sendByMail($properEmail) {
        $mail = \core\common\OutsideComm::mailHandle();
        $uiElements = new \web\lib\admin\UIElements();
        $bytestream = $uiElements->pngInjectConsortiumLogo(\QRcode::png($this->link(), FALSE, QR_ECLEVEL_Q, QRCODE_PIXELS_PER_SYMBOL), QRCODE_PIXELS_PER_SYMBOL);
        $mail->FromName = sprintf(_("%s Invitation System"), CONFIG['APPEARANCE']['productname']);
        $mail->Subject = $this->invitationMailSubject();
        $mail->Body = $this->invitationMailBody();
        $mail->addStringAttachment($bytestream, "qr-code-invitation.png", "base64", "image/png");
        $mail->addAddress($properEmail);
        $domainStatus = \core\common\OutsideComm::mailAddressValidSecure($properEmail);
        return ["SENT" => $mail->send(), "TRANSPORT" => $domainStatus == common\OutsideComm::MAILDOMAIN_STARTTLS ? TRUE : FALSE];
    }

}
