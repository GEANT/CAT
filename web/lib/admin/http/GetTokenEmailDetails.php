<?php
namespace web\lib\admin\http;

use web\lib\admin\view\html\Tag;

require_once(dirname(dirname(dirname(dirname(__DIR__)))) . "/config/_config.php");
require_once(dirname(dirname(dirname(dirname(__DIR__)))) . "/core/phpqrcode.php");

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class GetTokenEmailDetails extends AbstractAjaxCommand{
    
    const COMMAND = "gettokenemaildetails";
    const PARAM_TOKENLINK = "tokenlink";
    
    private $subject = '';

    private $body = '';
    
    /**
     *
     * @param string $commandToken
     * @param DefaultContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
        $this->subject = sprintf(_("Your %s access is ready"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->body =  _("Hello!");
        $this->body .= "\n\n";
        $this->body .= sprintf(_("A new %s access credential has been created for you by your network administrator."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->body .= " ";
        $this->body .= sprintf(_("Please follow the following link with the device you want to enable for %s to get a custom %s installation program just for you. You can click on the link, copy and paste it into a browser or scan the attached QR code."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->body .= "\n\n%s\n\n"; // gets replaced with the token value by getBody()
        $this->body .= sprintf(_("Please keep this email or bookmark this link for future use. After picking up your %s installation program, you can use the same link to get status information about your %s account."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->body .= "\n\n";
        $this->body .= _("Regards,");
        $this->body .= "\n\n";
        $this->body .= sprintf("%s", CONFIG['APPEARANCE']['productname_long']);
    }
    
    /**
     * 
     * @return string
     */
    public function getSubject(){
        return $this->subject;
    }
    
    /**
     * 
     * @param string $invitationToken
     * @return string
     */
    public function getBody($invitationToken){
        return sprintf($this->body, $invitationToken);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute() {
        if(isset($_POST[self::PARAM_TOKENLINK])){
            
            $invitationToken = $this->parseString($_POST[self::PARAM_TOKENLINK]);

            $uiELements = new \web\lib\admin\UIElements();
            
            $tokenTag = new Tag('email');
            $tokenTag->addAttribute('subject', $this->getSubject());
            $bytestream = $uiELements->pngInjectConsortiumLogo(\QRcode::png($invitationToken, FALSE, QR_ECLEVEL_Q, 12),12);
            $tokenTag->addAttribute('image', "data:image/png;base64," . base64_encode($bytestream));
            $tokenTag->addText($this->getBody($invitationToken));
            
            $this->publish($tokenTag);
        }
    }
}
