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
        $this->body = sprintf(_("Hello!\n\nA new %s access credential has been created for you by your network administrator.\n\nPlease follow the following link with the device you want to enable for eduroam to get a custom %s installation program just for you:"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->body .= "\n%s\n\n"; // gets replaced with the token value by getBody()
        $this->body .= sprintf(_("Regards,\n\n%s"), CONFIG['APPEARANCE']['productname_long']);
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

            $tokenTag = new Tag('email');
            $tokenTag->addAttribute('subject', $this->getSubject());
            $bytestream = \QRcode::png($invitationToken, FALSE, QR_ECLEVEL_Q, 12);
            $tokenTag->addAttribute('image', "data:image/png;base64," . base64_encode($bytestream));
            $tokenTag->addText($this->getBody($invitationToken));
            
            $this->publish($tokenTag);
        }
    }
}
