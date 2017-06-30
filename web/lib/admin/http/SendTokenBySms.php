<?php
namespace web\lib\admin\http;

use core\common\OutsideComm;
use PHPMailer\PHPMailer\PHPMailer;

require_once(dirname(dirname(dirname(dirname(__DIR__)))) . "/config/_config.php");

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SendTokenBySms extends AbstractInvokerCommand{
    
    const COMMAND = "sendtokenbysms";
    const PARAM_PHONE = "phone";
    
    /**
     * 
     * @var PHPMailer
     */
    //protected $phone = null;
    
    /**
     * 
     * @var GetTokenEmailDetails
     */
    protected $detailsCommand = null;
    
    /**
     *
     * @var SilverbulletContext
     */
    protected $context;
    
    /**
     * 
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
        //$this->mail = OutsideComm::mailHandle();
        $this->detailsCommand = new GetTokenEmailDetails(GetTokenEmailDetails::COMMAND, $context);
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute() {
        if(isset($_POST[GetTokenEmailDetails::PARAM_TOKENLINK]) && isset($_POST[SendTokenBySms::PARAM_PHONE])){
            
            $invitationToken = $this->parseString($_POST[GetTokenEmailDetails::PARAM_TOKENLINK]);
            $phone = $this->parseString($_POST[ValidateEmailAddress::PARAM_PHONE]);
            
            $this->mail->FromName = sprintf(_("%s Invitation System"), CONFIG['APPEARANCE']['productname']);
            $this->mail->Subject  = $this->detailsCommand->getSubject();
            $this->mail->Body = $this->detailsCommand->getBody($invitationToken);
            
            $this->mail->addAddress($address);
            if($this->mail->send()) {
                $this->storeInfoMessage(sprintf(_("Email message has been sent successfuly to '%s'!"), $address));
            } else {
                $this->storeErrorMessage(sprintf(_("Email message could not be sent to '%s'. Mailer error: '%s'."), $address, $this->mail->ErrorInfo));
            }
            $this->context->redirectAfterSubmit();
        }
    }
}
