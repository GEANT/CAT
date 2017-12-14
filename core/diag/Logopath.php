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
     * @var type 
     */
    private $userEmail;
    
    private $mailStack;
    
    const EDUROAM_OT = 0;
    const NRO_IDP = 1;
    const NRO_SP = 2;
    const IDP = 3;
    const SP = 4;
    
    // we start all our mails with a common prefix, internationalised
    private $subjectPrefix;
    
    public function __construct($userEmail = FALSE) {
        parent::__construct();
        $validator = new \web\lib\common\InputValidation();
        // remains FALSE if it was not given or bogus, otherwise stores this as mail target
        $this->userEmail = $validator->email($userEmail);
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
            "IDP_EXISTS_BUT_NO_DATABASE" => [
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
}