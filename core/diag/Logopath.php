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

namespace core\diag;

/**
 * This class evaluates the evidence of previous Telepath and/or Sociopath runs
 * and figures out whom to send emails to, and with that content. It then sends
 * these emails.
 */
class Logopath extends AbstractTest {

    /**
     * storing the end user's email, if he has given it to us
     * @var string|boolean
     */
    private $userEmail;

    /**
     * maybe the user has some additional evidence directly on his device?
     * @var string|boolean
     */
    private $additionalScreenshot;

    /**
     * the list of mails to send
     * @var array
     */
    private $mailStack;

    /*
     * categories of people to contact
     */

    const TARGET_EDUROAM_OT = 0;
    const TARGET_NRO_IDP = 1;
    const TARGET_NRO_SP = 2;
    const TARGET_IDP = 3;
    const TARGET_SP = 4;
    const TARGET_ENDUSER = 5;

    /** we start all our mails with a common prefix, internationalised
     *
     * @var string
     */
    private $subjectPrefix;

    /** and we end with a greeting/disclaimer
     *
     * @var string
     */
    private $finalGreeting;

    /**
     * We need to vet user inputs.
     * @var \web\lib\common\InputValidation
     */
    private $validatorInstance;

    /**
     * will be filled with the exact emails to send, by determineMailsToSend()
     * @var array
     */
    private $mailQueue;

    /**
     *
     * @var array
     */
    private $concreteRecipients;

    /*
     *  cases to consider
     */

    const IDP_EXISTS_BUT_NO_DATABASE = 100;
    const IDP_SUSPECTED_PROBLEM_INTERACTIVE_FORCED = 101;
    const IDP_SUSPECTED_PROBLEM_INTERACTIVE_EVIDENCED = 102;

    /*
     * types of supplemental string data to send 
     */

    /**
     * initialise the class: maintain state of existing evidence, and get translated versions of email texts etc.
     */
    public function __construct() {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        $this->userEmail = FALSE;
        $this->additionalScreenshot = FALSE;

        $this->mailQueue = [];
        $this->concreteRecipients = [];

        $this->validatorInstance = new \web\lib\common\InputValidation();

        $this->possibleFailureReasons = $_SESSION["SUSPECTS"] ?? []; // if we know nothing, don't talk to anyone
        $this->additionalFindings = $_SESSION["EVIDENCE"] ?? [];

        $this->subjectPrefix = _("[eduroam Diagnostics]") . " ";
        $this->finalGreeting = "\n"
                . _("(This service is in an early stage. We apologise if this is a false alert. If this is the case, please send an email report to cat-devel@lists.geant.org, forwarding the entire message (including the 'SUSPECTS' and 'EVIDENCE' data at the end), and explain why this is a false positive.)")
                . "\n"
                . _("Yours sincerely,") . "\n"
                . "\n"
                . _("Ed U. Roam, the eduroam diagnostics algorithm");

        $this->mailStack = [
            Logopath::IDP_EXISTS_BUT_NO_DATABASE => [
                "to" => [Logopath::TARGET_NRO_IDP],
                "cc" => [Logopath::TARGET_EDUROAM_OT],
                "bcc" => [],
                "reply-to" => [Logopath::TARGET_EDUROAM_OT],
                "subject" => _("[POLICYVIOLATION NATIONAL] IdP with no entry in eduroam database"),
                "body" => _("Dear NRO administrator,") . "\n"
                . "\n"
                . wordwrap(sprintf(_("an end-user requested diagnostics for realm %s. Real-time connectivity checks determined that the realm exists, but we were unable to find an IdP with that realm in the eduroam database."), $this->additionalFindings['REALM'])) . "\n"
                . "\n"
                . _("By not listing IdPs in the eduroam database, you are violating the eduroam policy.") . "\n"
                . "\n"
                . _("Additionally, this creates operational issues. In particular, we are unable to direct end users to their IdP for further diagnosis/instructions because there are no contact points for that IdP in the database.") . "\n"
                . "\n"
                . _("Please stop the policy violation ASAP by listing the IdP which is associated to this realm.")
                . "\n",
            ],
            Logopath::IDP_SUSPECTED_PROBLEM_INTERACTIVE_FORCED => [
                "to" => [Logopath::TARGET_IDP],
                "cc" => [],
                "bcc" => [],
                "reply-to" => [Logopath::TARGET_ENDUSER],
                "subject" => _("[TECHNICAL PROBLEM] Administrator suspects technical problem with your IdP"),
                "body" => _("Dear IdP administrator,") . "\n"
                . "\n"
                . sprintf(_("an organisation administrator requested diagnostics for realm %s. "), $this->additionalFindings['REALM'])
                . "\n"
                . _("Real-time connectivity checks determined that the realm appears to be working in acceptable parameters, but the administrator insisted to contact you with the supplemental information below.") . "\n"
                . "\n",
            ],
            Logopath::IDP_SUSPECTED_PROBLEM_INTERACTIVE_EVIDENCED => [
                "to" => [Logopath::TARGET_IDP],
                "cc" => [],
                "bcc" => [],
                "reply-to" => [Logopath::TARGET_ENDUSER],
                "subject" => _("[TECHNICAL PROBLEM] Administrator suspects technical problem with your IdP"),
                "body" => _("Dear IdP administrator,") . "\n"
                . "\n"
                . sprintf(_("an organisation administrator requested diagnostics for realm %s. "), $this->additionalFindings['REALM'])
                . "\n"
                . _("Real-time connectivity checks determined that the realm indeed has an operational problem at this point in time. Please see the supplemental information below.") . "\n"
                . "\n",
            ],
        ];

        // add exalted human-readable information to main mail body
        foreach ($this->mailStack as $oneEntry) {
            if (isset($this->additionalFindings['INTERACTIVE_ENDUSER_AUTH_TIMESTAMP'])) {
                $oneEntry["body"] .= _("Authentication/Attempt Timestamp of user session:") . " " . $this->additionalFindings['INTERACTIVE_ENDUSER_AUTH_TIMESTAMP'] . "\n";
            }
            if (isset($this->additionalFindings['INTERACTIVE_ENDUSER_MAC'])) {
                $oneEntry["body"] .= _("MAC address of end user in question:") . " " . $this->additionalFindings['INTERACTIVE_ENDUSER_MAC'] . "\n";
            }
            if (isset($this->additionalFindings['INTERACTIVE_ADDITIONAL_COMMENTS'])) {
                $oneEntry["body"] .= _("Additional Comments:") . " " . $this->additionalFindings['INTERACTIVE_ADDITIONAL_COMMENTS'] . "\n";
            }
        }

        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * if the system asked the user for his email and he's willing to give it to
     * us, store it with this function
     * 
     * @param string $userEmail the end-users email to store
     * @return void
     */
    public function addUserEmail($userEmail) {
// returns FALSE if it was not given or bogus, otherwise stores this as mail target
        $this->userEmail = $this->validatorInstance->email($userEmail);
    }

    /**
     * if the system asked the user for a screenshot and he's willing to give one
     * to us, store it with this function
     * 
     * @param string $binaryData the submitted binary data, to be vetted
     * @return void
     */
    public function addScreenshot($binaryData) {
        if ($this->validatorInstance->image($binaryData) === TRUE) {
            if (class_exists('\\Gmagick')) { 
                $magick = new \Gmagick(); 
            } else {
                $magick = new \Imagick();
            }
            $magick->readimageblob($binaryData);
            $magick->setimageformat("png");
            $this->additionalScreenshot = $magick->getimageblob();
        } else {
            // whatever we got, it didn't parse as an image
            $this->additionalScreenshot = FALSE;
        }
    }

    /**
     * looks at probabilities and evidence, and decides which mail scenario(s) to send
     * 
     * @return void
     */
    private function determineMailsToSend() {
        $this->mailQueue = [];
// check for IDP_EXISTS_BUT_NO_DATABASE
        if (!in_array(AbstractTest::INFRA_NONEXISTENTREALM, $this->possibleFailureReasons) && $this->additionalFindings[AbstractTest::INFRA_NONEXISTENTREALM]['DATABASE_STATUS']['ID2'] < 0) {
            $this->mailQueue[] = Logopath::IDP_EXISTS_BUT_NO_DATABASE;
        }

        if (in_array(AbstractTest::INFRA_IDP_ADMIN_DETERMINED_EVIDENCED, $this->possibleFailureReasons)) {
            $this->mailQueue[] = Logopath::IDP_SUSPECTED_PROBLEM_INTERACTIVE_EVIDENCED;
        }
        if (in_array(AbstractTest::INFRA_IDP_ADMIN_DETERMINED_FORCED, $this->possibleFailureReasons)) {
            $this->mailQueue[] = Logopath::IDP_SUSPECTED_PROBLEM_INTERACTIVE_FORCED;
        }

// after collecting all the conditions, find the target entities in all
// the mails, and check if they resolve to a known mail address. If they
// do not, this triggers more mails about missing contact info.

        $abstractRecipients = [];
        foreach ($this->mailQueue as $mail) {
            $abstractRecipients = array_unique(array_merge($this->mailStack[$mail]['to'], $this->mailStack[$mail]['cc'], $this->mailStack[$mail]['bcc'], $this->mailStack[$mail]['reply-to']));
        }
// who are those guys? Here is significant legwork in terms of DB lookup
        $this->concreteRecipients = [];
        foreach ($abstractRecipients as $oneRecipient) {
            switch ($oneRecipient) {
                case Logopath::TARGET_EDUROAM_OT:
                    $this->concreteRecipients[Logopath::TARGET_EDUROAM_OT] = ["eduroam-ot@lists.geant.org"];
                    break;
                case Logopath::TARGET_ENDUSER:
// will be filled when sending, from $this->userEmail
// hence the +1 below
                    break;
                case Logopath::TARGET_IDP:
                    // CAT contacts, if existing
                    if ($this->additionalFindings['INFRA_NONEXISTENT_REALM']['DATABASE_STATUS']['ID1'] > 0) {
                        $profile = \core\ProfileFactory::instantiate($this->additionalFindings['INFRA_NONEXISTENT_REALM']['DATABASE_STATUS']['ID1']);

                        foreach ($profile->getAttributes("support:email") as $oneMailAddress) {
                            // CAT contacts are always public
                            $this->concreteRecipients[Logopath::TARGET_IDP][] = $oneMailAddress;
                        }
                    }
                    // DB contacts, if existing
                    if ($this->additionalFindings['INFRA_NONEXISTENT_REALM']['DATABASE_STATUS']['ID2'] > 0) {
                        $cat = new \core\CAT();
                        $info = $cat->getExternalDBEntityDetails($this->additionalFindings['INFRA_NONEXISTENT_REALM']['DATABASE_STATUS']['ID2']);
                        foreach ($info['admins'] as $infoElement) {
                            if (isset($infoElement['email'])) {
                                // until DB Spec 2.0 is out and used, consider all DB contacts as private
                                $this->concreteRecipients[Logopath::TARGET_IDP][] = $infoElement['email'];
                            }
                        }
                    }
                    break;
                case Logopath::TARGET_NRO_IDP: // same code for both, fall through
                case Logopath::TARGET_NRO_SP:
                    $target = ($oneRecipient == Logopath::TARGET_NRO_IDP ? $this->additionalFindings['INFRA_NRO_IdP'] : $this->additionalFindings['INFRA_NRO_SP']);
                    $fed = new \core\Federation($target);
                    $adminList = $fed->listFederationAdmins();
                    // TODO: we only have those who are signed up for CAT currently, and by their ePTID.
                    // in touch with OT to get all, so that we can get a list of emails
                    break;
                case Logopath::TARGET_SP:
                    // TODO: needs a DB view on SPs in eduroam DB, in touch with OT
                    break;
            }
        }
// now see if we lack pertinent recipient info, and add corresponding
// mails to the list
        if (count($abstractRecipients) != count($this->concreteRecipients) + 1) {
            // there is a discrepancy, do something ...
            // we need to add a mail to the next higher hierarchy level as escalation
            // but may also have to remove the lower one because we don't know the guy.
        }
    }

    /**
     * sees if it is useful to ask the user for his contact details or screenshots
     * @return boolean
     */
    public function isEndUserContactUseful() {
        $contactUseful = FALSE;
        $this->determineMailsToSend();
        foreach ($this->mailQueue as $oneMail) {
            if (in_array(Logopath::TARGET_ENDUSER, $this->mailStack[$oneMail]['to']) ||
                    in_array(Logopath::TARGET_ENDUSER, $this->mailStack[$oneMail]['cc']) ||
                    in_array(Logopath::TARGET_ENDUSER, $this->mailStack[$oneMail]['bcc']) ||
                    in_array(Logopath::TARGET_ENDUSER, $this->mailStack[$oneMail]['reply-to'])) {
                $contactUseful = TRUE;
            }
        }
        return $contactUseful;
    }

    const CATEGORYBINDING = ['to' => 'addAddress', 'cc' => 'addCC', 'bcc' => 'addBCC', 'reply-to' => 'addReplyTo'];

    /**
     * sends the mails. Only call this after either determineMailsToSend() or
     * isEndUserContactUseful(), otherwise it will do nothing.
     * 
     * @return void
     */
    public function weNeedToTalk() {
        $this->determineMailsToSend();
        foreach ($this->mailQueue as $oneMail) {
            $theMail = $this->mailStack[$oneMail];
            // if user interaction would have been good, but the user didn't 
            // leave his mail address, remove him/her from the list of recipients
            foreach (Logopath::CATEGORYBINDING as $index => $functionName) {
                if (in_array(Logopath::TARGET_ENDUSER, $theMail[$index]) && $this->userEmail === FALSE) {
                    $theMail[$index] = array_diff($theMail[$index], [Logopath::TARGET_ENDUSER]);
                }
            }

            $handle = \core\common\OutsideComm::mailHandle();
            // let's identify outselves
            $handle->FromName = \config\Master::APPEARANCE['productname'] . " Real-Time Diagnostics System";
            // add recipients
            foreach (Logopath::CATEGORYBINDING as $arrayName => $functionName) {
                foreach ($theMail[$arrayName] as $onePrincipal) {
                    foreach ($this->concreteRecipients[$onePrincipal] as $oneConcrete) {
                        $handle->{$functionName}($oneConcrete);
                    }
                }
            }
            // and add what to say
            $handle->Subject = $theMail['subject'];
            $handle->Body = $theMail['body'];
            if (is_string($this->additionalScreenshot)) {
                $handle->addStringAttachment($this->additionalScreenshot, "screenshot.png", "base64", "image/png", "attachment");
            }
            $handle->send();
        }
    }

}
