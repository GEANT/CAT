<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace core\common;

/**
 * This class contains a number of functions for talking to the outside world
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
class OutsideComm {

    /**
     * downloads a file from the internet
     * @param string $url
     * @return string|boolean the data we got back, or FALSE on failure
     */
    public static function downloadFile($url) {
        $loggerInstance = new \core\common\Logging();
        if (!preg_match("/:\/\//", $url)) {
            $loggerInstance->debug(3, "The specified string does not seem to be a URL!");
            return FALSE;
        }
        # we got a URL, download it
        $download = fopen($url, "rb");
        if ($download === FALSE) {
            $loggerInstance->debug(2, "Failed to open handle for $url");
            return FALSE;
        }
        $data = stream_get_contents($download);
        if ($data === FALSE) {
            $loggerInstance->debug(2, "Failed to download the file from $url");
            return FALSE;
        }
        return $data;
    }

    /**
     * create an email handle from PHPMailer for later customisation and sending
     * @return \PHPMailer\PHPMailer\PHPMailer
     */
    public static function mailHandle() {
// use PHPMailer to send the mail
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->Host = CONFIG['MAILSETTINGS']['host'];
        $mail->Username = CONFIG['MAILSETTINGS']['user'];
        $mail->Password = CONFIG['MAILSETTINGS']['pass'];
// formatting nitty-gritty
        $mail->WordWrap = 72;
        $mail->isHTML(FALSE);
        $mail->CharSet = 'UTF-8';
        $mail->From = CONFIG['APPEARANCE']['from-mail'];
// are we fancy? i.e. S/MIME signing?
        if (isset(CONFIG['MAILSETTINGS']['certfilename'], CONFIG['MAILSETTINGS']['keyfilename'], CONFIG['MAILSETTINGS']['keypass'])) {
            $mail->sign(CONFIG['MAILSETTINGS']['certfilename'], CONFIG['MAILSETTINGS']['keyfilename'], CONFIG['MAILSETTINGS']['keypass']);
        }
        return $mail;
    }

    const MAILDOMAIN_INVALID = -1000;
    const MAILDOMAIN_NO_MX = -1001;
    const MAILDOMAIN_NO_HOST = -1002;
    const MAILDOMAIN_NO_CONNECT = -1003;
    const MAILDOMAIN_NO_STARTTLS = 1;
    const MAILDOMAIN_STARTTLS = 2;

    /**
     * verifies whether a mail address is in an existing and STARTTLS enabled mail domain
     * @param string $address
     * @return int status of the mail domain
     */
    public static function mailAddressValidSecure($address) {
        $loggerInstance = new \core\common\Logging();
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $loggerInstance->debug(4, "OutsideComm::mailAddressValidSecure: invalid mail address.");
            return OutsideComm::MAILDOMAIN_INVALID;
        }
        $domain = substr($address, strpos($address, '@') + 1); // everything after the @ sign
        // we can be sure that the @ was found (FILTER_VALIDATE_EMAIL succeeded)
        // but let's be explicit
        if ($domain === FALSE) {
            return OutsideComm::MAILDOMAIN_INVALID;
        }
        // does the domain have MX records?
        $mx = dns_get_record($domain, DNS_MX);
        if ($mx === FALSE) {
            $loggerInstance->debug(4, "OutsideComm::mailAddressValidSecure: no MX.");
            return OutsideComm::MAILDOMAIN_NO_MX;
        }
        $loggerInstance->debug(5, "Domain: $domain MX: " . print_r($mx, TRUE));
        // create a pool of A and AAAA records for all the MXes
        $ipAddrs = [];
        foreach ($mx as $onemx) {
            $v4list = dns_get_record($onemx['target'], DNS_A);
            $v6list = dns_get_record($onemx['target'], DNS_AAAA);
            foreach ($v4list as $oneipv4) {
                $ipAddrs[] = $oneipv4['ip'];
            }
            foreach ($v6list as $oneipv6) {
                $ipAddrs[] = "[" . $oneipv6['ipv6'] . "]";
            }
        }
        if (count($ipAddrs) == 0) {
            $loggerInstance->debug(4, "OutsideComm::mailAddressValidSecure: no mailserver hosts.");
            return OutsideComm::MAILDOMAIN_NO_HOST;
        }
        $loggerInstance->debug(5, "Domain: $domain Addrs: " . print_r($ipAddrs, TRUE));
        // connect to all hosts. If all can't connect, return MAILDOMAIN_NO_CONNECT. 
        // If at least one does not support STARTTLS or one of the hosts doesn't connect
        // , return MAILDOMAIN_NO_STARTTLS (one which we can't connect to we also
        // can't verify if it's doing STARTTLS, so better safe than sorry.
        $retval = OutsideComm::MAILDOMAIN_NO_CONNECT;
        $allWithStarttls = TRUE;
        foreach ($ipAddrs as $oneip) {
            $loggerInstance->debug(5, "OutsideComm::mailAddressValidSecure: connecting to $oneip.");
            $smtp = new \PHPMailer\PHPMailer\SMTP;
            if ($smtp->connect($oneip, 25)) {
                // host reached! so at least it's not a NO_CONNECT
                $loggerInstance->debug(4, "OutsideComm::mailAddressValidSecure: connected to $oneip.");
                $retval = OutsideComm::MAILDOMAIN_NO_STARTTLS;
                if ($smtp->hello('eduroam.org')) {
                    $extensions = $smtp->getServerExtList(); // Scrutinzer is wrong; is not always null - contains server capabilities
                    if (!is_array($extensions) || !array_key_exists('STARTTLS', $extensions)) {
                        $loggerInstance->debug(4, "OutsideComm::mailAddressValidSecure: no indication for STARTTLS.");
                        $allWithStarttls = FALSE;
                    }
                }
            } else {
                // no connect: then we can't claim all targets have STARTTLS
                $allWithStarttls = FALSE;
                $loggerInstance->debug(5, "OutsideComm::mailAddressValidSecure: failed $oneip.");
            }
        }
        // did the state $allWithStarttls survive? Then up the response to
        // appropriate level.
        if ($retval == OutsideComm::MAILDOMAIN_NO_STARTTLS && $allWithStarttls) {
            $retval = OutsideComm::MAILDOMAIN_STARTTLS;
        }
        return $retval;
    }

    const SMS_SENT = 100;
    const SMS_NOTSENT = 101;
    const SMS_FRAGEMENTSLOST = 102;

    /**
     * Send SMS invitations to end users
     * 
     * @param string $number the number to send to: with country prefix, but without the + sign ("352123456" for a Luxembourg example)
     * @param string $content the text to send
     * @return integer status of the sending process
     * @throws Exception
     */
    public static function sendSMS($number, $content) {
        $loggerInstance = new \core\common\Logging();
        switch (CONFIG_CONFASSISTANT['SMSSETTINGS']['provider']) {
            case 'Nexmo':
                // taken from https://docs.nexmo.com/messaging/sms-api
                $url = 'https://rest.nexmo.com/sms/json?' . http_build_query(
                                [
                                    'api_key' => CONFIG_CONFASSISTANT['SMSSETTINGS']['username'],
                                    'api_secret' => CONFIG_CONFASSISTANT['SMSSETTINGS']['password'],
                                    'to' => $number,
                                    'from' => CONFIG['APPEARANCE']['productname'],
                                    'text' => $content,
                                ]
                );

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);

                $decoded_response = json_decode($response, true);
                $messageCount = $decoded_response['message-count'];

                if ($messageCount == 0) {
                    $loggerInstance->debug(2, 'Problem with SMS invitation: no message was sent!');
                    return OutsideComm::SMS_NOTSENT;
                }
                $loggerInstance->debug(2, 'Total of ' . $messageCount . ' messages were attempted to send.');

                $totalFailures = 0;
                foreach ($decoded_response['messages'] as $message) {
                    if ($message['status'] == 0) {
                        $loggerInstance->debug(2, $message['message-id'] . ": Success");
                    } else {
                        $loggerInstance->debug(2, $message['message-id'] . ": Failed (failure code = " . $message['status'] . ")");
                        $totalFailures++;
                    }
                }
                if ($messageCount == count($decoded_response['messages']) && $totalFailures == 0) {
                    return OutsideComm::SMS_SENT;
                }
                return OutsideComm::SMS_FRAGEMENTSLOST;
            default:
                throw new \Exception("Unknown SMS Gateway provider!");
        }
    }

    const INVITE_CONTEXTS = [
        0 => "CO-ADMIN",
        1 => "NEW-FED",
        2 => "EXISTING-FED",
    ];

    /**
     * 
     * @param string $targets one or more mail addresses, comma-separated
     * @param string $introtext introductory sentence (varies by situation)
     * @param string $newtoken the token to send
     * @param \core\Federation $federation if not NULL, indicates that invitation comes from authorised fed admin of that federation
     * @return array
     */
    public static function adminInvitationMail($targets, $introtext, $newtoken, $idpPrettyName, $federation) {
        if (!in_array($introtext, OutsideComm::INVITE_CONTEXTS)) {
            throw new \Exception("Unknown invite mode!");
        }
        if ($introtext == OutsideComm::INVITE_CONTEXTS[1] && $federation === NULL) { // comes from fed admin, so federation must be set
            throw new \Exception("Invitation from a fed admin, but we do not know the corresponding federation!");
        }
        $mail = OutsideComm::mailHandle();
        $cat = new \core\CAT();
        // we have a few stock intro texts on file
        $introTexts = [
            OutsideComm::INVITE_CONTEXTS[0] => sprintf(_("a %s of the %s %s \"%s\" has invited you to manage the %s together with him."), $cat->nomenclature_fed, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $cat->nomenclature_inst, $idpPrettyName, $cat->nomenclature_inst),
            OutsideComm::INVITE_CONTEXTS[1] => sprintf(_("a %s %s has invited you to manage the future %s  \"%s\" (%s)."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $cat->nomenclature_fed, $cat->nomenclature_inst, $idpPrettyName, strtoupper($federation->tld)),
            OutsideComm::INVITE_CONTEXTS[2] => sprintf(_("a %s %s has invited you to manage the %s  \"%s\"."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $cat->nomenclature_fed, $cat->nomenclature_inst, $idpPrettyName),
        ];
        $validity = sprintf(_("This invitation is valid for 24 hours from now, i.e. until %s."), strftime("%x %X", time() + 86400));
        // need some nomenclature
        // are we on https?
        $proto = "http://";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
            $proto = "https://";
        }
        // then, send out the mail
        $message = _("Hello,") . "\n\n" . wordwrap($introTexts[$introtext] . " " . $validity, 72) . "\n\n";
        // default means we don't have a Reply-To.
        $replyToMessage = wordwrap(_("manually. Please do not reply to this mail; this is a send-only address."));

        if ($federation !== NULL) {
            // see if we are supposed to add a custom message
            $customtext = $federation->getAttributes('fed:custominvite');
            if (count($customtext) > 0) {
                $message .= wordwrap(sprintf(_("Additional message from your %s administrator:"), $cat->nomenclature_fed), 72) . "\n---------------------------------" .
                        wordwrap($customtext[0]['value'], 72) . "\n---------------------------------\n\n";
            }
            // and add Reply-To already now
            foreach ($federation->listFederationAdmins() as $fedadmin_id) {
                $fedadmin = new \core\User($fedadmin_id);
                $mailaddrAttrib = $fedadmin->getAttributes("user:email");
                $nameAttrib = $fedadmin->getAttributes("user:realname");
                $name = $nameAttrib[0]['value'] ?? sprintf(_("%s administrator"), $cat->nomenclature_fed);
                if (count($mailaddrAttrib) > 0) {
                    $mail->addReplyTo($mailaddrAttrib[0]['value'], $name);
                    $replyToMessage = wordwrap(sprintf(_("manually. If you reply to this mail, you will reach your %s administrators."), $cat->nomenclature_fed), 72);
                }
            }
        }

        $message .= wordwrap(sprintf(_("To enlist as an administrator for that %s, please click on the following link:"), $cat->nomenclature_inst), 72) . "\n\n" .
                $proto . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/admin/action_enrollment.php?token=$newtoken\n\n" .
                wordwrap(sprintf(_("If clicking the link doesn't work, you can also go to the %s Administrator Interface at"), CONFIG['APPEARANCE']['productname']), 72) . "\n\n" .
                $proto . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/admin/\n\n" .
                _("and enter the invitation token") . "\n\n" .
                $newtoken . "\n\n$replyToMessage\n\n" .
                wordwrap(_("Do NOT forward the mail before the token has expired - or the recipients may be able to consume the token on your behalf!"), 72) . "\n\n" .
                wordwrap(sprintf(_("We wish you a lot of fun with the %s."), CONFIG['APPEARANCE']['productname']), 72) . "\n\n" .
                sprintf(_("Sincerely,\n\nYour friendly folks from %s Operations"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);


// who to whom?
        $mail->FromName = CONFIG['APPEARANCE']['productname'] . " Invitation System";

        if (isset(CONFIG['APPEARANCE']['invitation-bcc-mail']) && CONFIG['APPEARANCE']['invitation-bcc-mail'] !== NULL) {
            $mail->addBCC(CONFIG['APPEARANCE']['invitation-bcc-mail']);
        }

// all addresses are wrapped in a string, but PHPMailer needs a structured list of addressees
// sigh... so convert as needed
// first split multiple into one if needed
        $recipients = explode(", ", $targets);

        $secStatus = TRUE;
        $domainStatus = TRUE;

// fill the destinations in PHPMailer API
        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
            $status = OutsideComm::mailAddressValidSecure($recipient);
            if ($status < OutsideComm::MAILDOMAIN_STARTTLS) {
                $secStatus = FALSE;
            }
            if ($status < 0) {
                $domainStatus = FALSE;
            }
        }

        if (!$domainStatus) {
            return ["SENT" => FALSE, "TRANSPORT" => FALSE];
        }

// what do we want to say?
        $mail->Subject = sprintf(_("%s: you have been invited to manage an %s"), CONFIG['APPEARANCE']['productname'], $cat->nomenclature_inst);
        $mail->Body = $message;

        return ["SENT" => $mail->send(), "TRANSPORT" => $secStatus];
    }

    public static function postJson($url, $dataArray) {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POSTFIELDS => json_encode($dataArray),
            CURLOPT_FRESH_CONNECT => TRUE,
        ));
        $response = \curl_exec($ch);
        if ($response === FALSE || $response === NULL) {
            throw new \Exception("the POST didn't work!");
        }
        return json_decode($response, TRUE);
    }

}
