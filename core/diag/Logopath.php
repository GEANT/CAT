<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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
     * @var string
     */
    private $userEmail;
    
    /**
     * maybe the user has some additional evidence directly on his device?
     * @var string
     */
    private $additionalScreenshot;
    
    private $mailStack;
    
    const EDUROAM_OT = 0;
    const NRO_IDP = 1;
    const NRO_SP = 2;
    const IDP = 3;
    const SP = 4;
    const ENDUSER = 5;
    
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
    
    // cases to consider
    const IDP_EXISTS_BUT_NO_DATABASE = 100;
    
    public function __construct() {
        parent::__construct();
        $this->userEmail = FALSE;
        $this->additionalScreenshot = FALSE;
        $this->validatorInstance = new \web\lib\common\InputValidation();
        
        $this->possibleFailureReasons = $_SESSION["SUSPECTS"] ?? []; // if we know nothing, don't talk to anyone
        $this->additionalFindings = $_SESSION["EVIDENCE"] ?? [];
        
        $this->subjectPrefix = _("[eduroam Diagnostics]")." ";
        $this->finalGreeting = "\n"
                . _("(This service is in an early stage. We apologise if this is a false alert. If this is the case, please send an email report to cat-devel@lists.geant.org, forwarding the entire message (including the 'SUSPECTS' and 'EVIDENCE' data at the end), and explain why this is a false positive.)")
                . "\n"
                . _("Yours sincerely,"). "\n"
                . "\n"
                . _("The eduroam diagnostics algorithms");
        
        $this->mailStack = [
            Logopath::IDP_EXISTS_BUT_NO_DATABASE => [
                "to" => [Logopath::NRO_IDP], 
                "cc" => [Logopath::EDUROAM_OT], 
                "bcc" => [], 
                "reply-to" => [Logopath::EDUROAM_OT],
                "subject" => _("[POLICYVIOLATION NATIONAL] IdP with no entry in eduroam database"), 
                "body" => _("Dear NRO administrator,")."\n"
                        . "\n"
                        . wordwrap(sprintf(_("an end-user requested diagnostics for realm %s. Real-time connectivity checks determined that the realm exists, but we were unable to find an IdP with that realm in the eduroam database."), "foo.bar")) ."\n"
                        . "\n"
                        . _("By not listing IdPs in the eduroam database, you are violating the eduroam policy.")."\n"
                        . "\n"
                        . _("Additionally, this creates operational issues. In particular, we are unable to direct end users to their IdP for further diagnosis/instructions because there are no contact points for that IdP in the database."). "\n"
                        . "\n"
                        . "Please stop the policy violation ASAP by listing the IdP which is associated to this realm.",
                ],
        ];
        
    }
    
    /**
     * 
     * @param string $userEmail
     */
    public function addUserEmail($userEmail) {
        // returns FALSE if it was not given or bogus, otherwise stores this as mail target
        $this->userEmail = $this->validatorInstance->email($userEmail);
    }
    
    public function addScreenshot($binaryData) {
        if ($this->validatorInstance->image($binaryData) === TRUE) {
            $this->additionalScreenshot = $binaryData;
        }
    }
    
    /**
     * looks at probabilities and evidence, and decides which mail scenario(s) to send
     */
    public function determineMailsToSend() {
        $mailQueue = [];
        // check for IDP_EXISTS_BUT_NO_DATABASE
        if (!in_array(AbstractTest::INFRA_NONEXISTENTREALM, $this->possibleFailureReasons) && $this->additionalFindings[AbstractTest::INFRA_NONEXISTENTREALM]['DATABASE_STATUS']['ID2'] < 0) {
            $mailQueue[] = Logopath::IDP_EXISTS_BUT_NO_DATABASE;
        }
        
        // after collecting all the conditions, find the target entities in all
        // the mails, and check if they resolve to a known mail address. If they
        // do not, this triggers more mails about missing contact info.
        
        $abstractRecipients = [];
        foreach ($mailQueue as $mail) {
            $abstractRecipients = array_unique(array_merge($this->mailStack[$mail]['to'], $this->mailStack[$mail]['cc'], $this->mailStack[$mail]['bcc'], $this->mailStack[$mail]['reply-to']));
        }
        // who are those guys? Here is significant legwork in terms of DB lookup
        $concreteRecipients = [];
        foreach ($abstractRecipients as $oneRecipient) {
            switch ($oneRecipient) {
                case Logopath::EDUROAM_OT:
                    $concreteRecipients[$oneRecipient] = "eduroam-ot@lists.geant.org";
                    break;
                case Logopath::ENDUSER:
                    // will be filled when sending, from $this->userEmail
                    // hence the +1 below
                    break;
                case Logopath::IDP:
                    // TODO
                    break;
                case Logopath::NRO_IDP:
                    // TODO
                    break;
                case Logopath::SP:
                    // TODO
                    break;
                case Logopath::NRO_SP:
                    // TODO
                    break;
            }
        }
        // now see if we lack pertinent recipient info, and add corresponding
        // mails to the list
        if (count($abstractRecipients) != count($concreteRecipients) + 1) {
            // there is a discrepancy, do something ...
        }
        return $mailQueue;
        
    }
    
    /**
     * sees if it is useful to ask the user for his contact details or screenshots
     */
    public function isEndUserContactUseful() {
        $contactUseful = FALSE;
        $mailList = $this->determineMailsToSend();
        foreach ($mailList as $oneMail) {
            if (in_array(Logopath::ENDUSER, $this->mailStack[$oneMail]['to']) ||
                in_array(Logopath::ENDUSER, $this->mailStack[$oneMail]['cc']) ||
                in_array(Logopath::ENDUSER, $this->mailStack[$oneMail]['bcc'])) {
                $contactUseful = TRUE;
            }
        }
        return $contactUseful;
    }
    
    /**
     * sends the mails
     */
    public function weNeedToTalk() {
        // just send the mails out, TODO
    }
}