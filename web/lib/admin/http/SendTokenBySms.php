<?php
namespace web\lib\admin\http;

use core\common\OutsideComm;

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
        $this->detailsCommand = new GetTokenEmailDetails(GetTokenEmailDetails::COMMAND, $context);
        $this->context = $context;
    }
    
    /**
     * 
     * @param string $phone
     * @param string $content
     */
    private function sendSMS($phone, $content){
        $error = "";
        $result = -1;
        try {
            $result = OutsideComm::sendSMS($phone, $content);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        switch ($result) {
            case OutsideComm::SMS_SENT:
                $this->storeInfoMessage(sprintf(_("SMS message has been sent successfuly to '%s'!"), $phone));
                break;
            case OutsideComm::SMS_FRAGEMENTSLOST:
                $this->storeErrorMessage(sprintf(_("Part of the SMS message to '%s' was lost. Sender error: '%s'."), $phone, $result));
                break;
            case OutsideComm::SMS_NOTSENT:
                $this->storeErrorMessage(sprintf(_("SMS message could not be sent to '%s'. Sender error: '%s'."), $phone, $result));
                break;
            default:
                $this->storeErrorMessage(sprintf(_("SMS message could not be sent to '%s'. Sender error: '%s'"), $phone, $error));
                break;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute() {
        if(isset($_POST[GetTokenEmailDetails::PARAM_TOKENLINK]) && isset($_POST[SendTokenBySms::PARAM_PHONE])){
            
            $invitationToken = $this->parseString($_POST[GetTokenEmailDetails::PARAM_TOKENLINK]);
            $phone = $this->parseString($_POST[SendTokenBySms::PARAM_PHONE]);
            $content = sprintf(
                _("Your %s access is ready! Please click on the link at the end to continue! (On Android, install the %s app before doing that.) %s"), 
                CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'],
                "eduroam CAT",
                $invitationToken
            );
            
            $this->sendSMS($phone, $content);
            
            $this->context->redirectAfterSubmit();
        }
    }
}
