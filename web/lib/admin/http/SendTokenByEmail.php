<?php
namespace web\lib\admin\http;

use core\common\OutsideComm;
use web\lib\admin\view\html\Tag;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SendTokenByEmail extends AbstractAjaxCommand{
    
    const COMMAND = "sendtokenbyemail";
    const PARAM_TOKENLINK = "tokenlink";
    
    /**
     * 
     * @var PHPMailer
     */
    private $mail = null;
    
    public function __construct($command, $controller){
        parent::__construct($command, $controller);
        $this->mail = OutsideComm::mailHandle();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute() {
        if(isset($_GET[self::PARAM_TOKENLINK]) && isset($_GET[ValidateEmailAddress::PARAM_ADDRESS])){
            $page = $this->controller->getPage();
            $invitationToken = $this->parseString($_GET[self::PARAM_TOKENLINK]);
            $address = $this->parseString($_GET[ValidateEmailAddress::PARAM_ADDRESS]);
            
            $this->mail->addAddress($address);
            $this->mail->Subject  = _("New certificate at CAT");
            $this->mail->Body     = sprintf(_("Hi!\n\nYou have new certificate issued at CAT please follow the link to download the certificate file '%s'.\n\nRegards,\n CAT Team"), $invitationToken);
            
            $tokenTag = new Tag('email');
            $tokenTag->addAttribute('address', $address);
            
            if($this->mail->send()) {
                $tokenTag->addAttribute('status', 'true');
                $tokenTag->addText(_("Message has been sent."));
            } else {
                $tokenTag->addAttribute('status', 'false');
                $tokenTag->addText(sprintf(_("Message was not sent. Mailer error: '%s'."), $this->mail->ErrorInfo));
            }
            $page->appendResponse($tokenTag);
        }
    }
}