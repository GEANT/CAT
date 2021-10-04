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

namespace core\common;

/**
 * This class contains a number of functions for talking to the outside world
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
class OutsideComm extends Entity
{

    /**
     * downloads a file from the internet
     * @param string $url the URL to download
     * @return string|boolean the data we got back, or FALSE on failure
     */
    public static function downloadFile($url)
    {
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
    public static function mailHandle()
    {
// use PHPMailer to send the mail
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->Host = \config\Master::MAILSETTINGS['host'];
        if (\config\Master::MAILSETTINGS['user'] === NULL && \config\Master::MAILSETTINGS['pass'] === NULL) {
            $mail->SMTPAuth = false;
        } else {
            $mail->SMTPAuth = true;
            $mail->Username = \config\Master::MAILSETTINGS['user'];
            $mail->Password = \config\Master::MAILSETTINGS['pass'];
        }
        $mail->SMTPOptions = \config\Master::MAILSETTINGS['options'];
// formatting nitty-gritty
        $mail->WordWrap = 72;
        $mail->isHTML(FALSE);
        $mail->CharSet = 'UTF-8';
        $configuredFrom = \config\Master::APPEARANCE['from-mail'] . "";
        $mail->From = $configuredFrom;
// are we fancy? i.e. S/MIME signing?
        if (isset(\config\Master::MAILSETTINGS['certfilename'], \config\Master::MAILSETTINGS['keyfilename'], \config\Master::MAILSETTINGS['keypass'])) {
            $mail->sign(\config\Master::MAILSETTINGS['certfilename'], \config\Master::MAILSETTINGS['keyfilename'], \config\Master::MAILSETTINGS['keypass']);
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
     * 
     * @param string $address the mail address to check
     * @return int status of the mail domain
     */
    public static function mailAddressValidSecure($address)
    {
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
        $loggerInstance->debug(5, "Domain: $domain MX: " . /** @scrutinizer ignore-type */ print_r($mx, TRUE));
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
        $loggerInstance->debug(5, "Domain: $domain Addrs: " . /** @scrutinizer ignore-type */ print_r($ipAddrs, TRUE));
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
     * @param string $number  the number to send to: with country prefix, but without the + sign ("352123456" for a Luxembourg example)
     * @param string $content the text to send
     * @return integer status of the sending process
     * @throws \Exception
     */
    public static function sendSMS($number, $content)
    {
        $loggerInstance = new \core\common\Logging();
        switch (\config\ConfAssistant::SMSSETTINGS['provider']) {
            case 'Nexmo':
                // taken from https://docs.nexmo.com/messaging/sms-api
                $url = 'https://rest.nexmo.com/sms/json?' . http_build_query(
                                [
                                    'api_key' => \config\ConfAssistant::SMSSETTINGS['username'],
                                    'api_secret' => \config\ConfAssistant::SMSSETTINGS['password'],
                                    'to' => $number,
                                    'from' => \config\ConfAssistant::CONSORTIUM['name'],
                                    'text' => $content,
                                    'type' => 'unicode',
                                ]
                );

                $ch = curl_init($url);
                if ($ch === FALSE) {
                    $loggerInstance->debug(2, 'Problem with SMS invitation: unable to send API request with CURL!');
                    return OutsideComm::SMS_NOTSENT;
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
               
                // we have set RETURNTRANSFER so anything except string means something went wrong
                if (!is_string($response)) {
                    throw new \Exception("Error while sending API request with SMS: curl did not deliver a response string.");
                }
                $decoded_response = json_decode($response, true);
                $messageCount = $decoded_response['message-count'];

                curl_close($ch);
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
     * @param string           $targets       one or more mail addresses, comma-separated
     * @param string           $introtext     introductory sentence (varies by situation)
     * @param string           $newtoken      the token to send
     * @param string           $idpPrettyName the name of the IdP, in best-match language
     * @param \core\Federation $federation    if not NULL, indicates that invitation comes from authorised fed admin of that federation
     * @param string           $type          the type of participant we're invited to
     * @return array
     * @throws \Exception
     */
    public static function adminInvitationMail($targets, $introtext, $newtoken, $idpPrettyName, $federation, $type)
    {
        if (!in_array($introtext, OutsideComm::INVITE_CONTEXTS)) {
            throw new \Exception("Unknown invite mode!");
        }
        if ($introtext == OutsideComm::INVITE_CONTEXTS[1] && $federation === NULL) { // comes from fed admin, so federation must be set
            throw new \Exception("Invitation from a fed admin, but we do not know the corresponding federation!");
        }
        $prettyPrintType = "";
        switch ($type) {
            case \core\IdP::TYPE_IDP:
                $prettyPrintType = Entity::$nomenclature_idp;
                break;
            case \core\IdP::TYPE_SP:
                $prettyPrintType = Entity::$nomenclature_hotspot;
                break;
            case \core\IdP::TYPE_IDPSP:
                $prettyPrintType = sprintf(_("%s and %s"), Entity::$nomenclature_idp, Entity::$nomenclature_hotspot);
                break;
            default:
                throw new \Exception("This is controlled vocabulary, impossible.");
        }
        Entity::intoThePotatoes();
        $mail = OutsideComm::mailHandle();
        new \core\CAT(); // makes sure Entity is initialised
        // we have a few stock intro texts on file
        $introTexts = [
            OutsideComm::INVITE_CONTEXTS[0] => sprintf(_("a %s of the %s %s \"%s\" has invited you to manage the %s together with him."), Entity::$nomenclature_fed, \config\ConfAssistant::CONSORTIUM['display_name'], Entity::$nomenclature_participant, $idpPrettyName, Entity::$nomenclature_participant),
            OutsideComm::INVITE_CONTEXTS[1] => sprintf(_("a %s %s has invited you to manage the future %s  \"%s\" (%s). The organisation will be a %s."), \config\ConfAssistant::CONSORTIUM['display_name'], Entity::$nomenclature_fed, Entity::$nomenclature_participant, $idpPrettyName, strtoupper($federation->tld), $prettyPrintType),
            OutsideComm::INVITE_CONTEXTS[2] => sprintf(_("a %s %s has invited you to manage the %s  \"%s\". This is a %s."), \config\ConfAssistant::CONSORTIUM['display_name'], Entity::$nomenclature_fed, Entity::$nomenclature_participant, $idpPrettyName, $prettyPrintType),
        ];
        $validity = sprintf(_("This invitation is valid for 24 hours from now, i.e. until %s."), strftime("%x %X %Z", time() + 86400));
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
                $message .= wordwrap(sprintf(_("Additional message from your %s administrator:"), Entity::$nomenclature_fed), 72) . "\n---------------------------------" .
                        wordwrap($customtext[0]['value'], 72) . "\n---------------------------------\n\n";
            }
            // and add Reply-To already now
            foreach ($federation->listFederationAdmins() as $fedadmin_id) {
                $fedadmin = new \core\User($fedadmin_id);
                $mailaddrAttrib = $fedadmin->getAttributes("user:email");
                $nameAttrib = $fedadmin->getAttributes("user:realname");
                $name = $nameAttrib[0]['value'] ?? sprintf(_("%s administrator"), Entity::$nomenclature_fed);
                if (count($mailaddrAttrib) > 0) {
                    $mail->addReplyTo($mailaddrAttrib[0]['value'], $name);
                    $replyToMessage = wordwrap(sprintf(_("manually. If you reply to this mail, you will reach your %s administrators."), Entity::$nomenclature_fed), 72);
                }
            }
        }
        $productname = \config\Master::APPEARANCE['productname'];
        $consortium = \config\ConfAssistant::CONSORTIUM['display_name'];
        $message .= wordwrap(sprintf(_("To enlist as an administrator for that %s, please click on the following link:"), Entity::$nomenclature_participant), 72) . "\n\n" .
                $proto . $_SERVER['SERVER_NAME'] . \config\Master::PATHS['cat_base_url'] . "admin/action_enrollment.php?token=$newtoken\n\n" .
                wordwrap(sprintf(_("If clicking the link doesn't work, you can also go to the %s Administrator Interface at"), $productname), 72) . "\n\n" .
                $proto . $_SERVER['SERVER_NAME'] . \config\Master::PATHS['cat_base_url'] . "admin/\n\n" .
                _("and enter the invitation token") . "\n\n" .
                $newtoken . "\n\n$replyToMessage\n\n" .
                wordwrap(_("Do NOT forward the mail before the token has expired - or the recipients may be able to consume the token on your behalf!"), 72) . "\n\n" .
                wordwrap(sprintf(_("We wish you a lot of fun with the %s."), $productname), 72) . "\n\n" .
                sprintf(_("Sincerely,\n\nYour friendly folks from %s Operations"), $consortium);


// who to whom?
        $mail->FromName = \config\Master::APPEARANCE['productname'] . " Invitation System";

        if (isset(\config\Master::APPEARANCE['invitation-bcc-mail']) && \config\Master::APPEARANCE['invitation-bcc-mail'] !== NULL) {
            $mail->addBCC(\config\Master::APPEARANCE['invitation-bcc-mail']);
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
        Entity::outOfThePotatoes();
        if (!$domainStatus) {
            return ["SENT" => FALSE, "TRANSPORT" => FALSE];
        }

// what do we want to say?
        Entity::intoThePotatoes();
        $mail->Subject = sprintf(_("%s: you have been invited to manage an %s"), \config\Master::APPEARANCE['productname'], Entity::$nomenclature_participant);
        Entity::outOfThePotatoes();
        $mail->Body = $message;
        return ["SENT" => $mail->send(), "TRANSPORT" => $secStatus];
    }

    /**
     * sends a POST with some JSON inside
     * 
     * @param string $url       the URL to POST to
     * @param array  $dataArray the data to be sent in PHP array representation
     * @return array the JSON response, decoded into PHP associative array
     * @throws \Exception
     */
    public static function postJson($url, $dataArray)
    {
        $loggerInstance = new Logging();
        $ch = \curl_init($url);
        if ($ch === FALSE) {
            $loggerInstance->debug(2, "Unable to POST JSON request: CURL init failed!");
            return json_decode(json_encode(FALSE), TRUE);
        }
        \curl_setopt_array($ch, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POSTFIELDS => json_encode($dataArray),
            CURLOPT_FRESH_CONNECT => TRUE,
        ));
        $response = \curl_exec($ch);
        if (!is_string($response)) { // With RETURNTRANSFER, TRUE is not a valid return
            throw new \Exception("the POST didn't work!");
        }
        return json_decode($response, TRUE);
    }

    /**
     * aborts code execution if a required mail address is invalid
     * 
     * @param mixed $newmailaddress input string, possibly one or more mail addresses
     * @return array mail addresses that passed validation
     */
    public static function exfiltrateValidAddresses($newmailaddress)
    {
        $validator = new \web\lib\common\InputValidation();
        $addressSegments = explode(",", $newmailaddress);
        $confirmedMails = [];
        if ($addressSegments === FALSE) {
            return [];
        }
        foreach ($addressSegments as $oneAddressCandidate) {
            $candidate = trim($oneAddressCandidate);
            if ($validator->email($candidate) !== FALSE) {
                $confirmedMails[] = $candidate;
            }
        }
        if (count($confirmedMails) == 0) {
            return [];
        }
        return $confirmedMails;
    }
    /**
     * performs an HTTP request. Currently unused, will be for external CA API calls.
     * 
     * @param string $url the URL to send the request to
     * @param array $postValues POST values to send
     * @return string the returned HTTP content

      public static function PostHttp($url, $postValues) {
      $options = [
      'http' => ['header' => 'Content-type: application/x-www-form-urlencoded\r\n', "method" => 'POST', 'content' => http_build_query($postValues)]
      ];
      $context = stream_context_create($options);
      return file_get_contents($url, false, $context);
      }
     * 
     */
}