<?php
namespace web\lib\admin\http;

use web\lib\admin\view\html\Tag;

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
        $this->subject = _("New certificate at CAT!");
        $this->body = _("Hi!\n\nYou have new certificate issued at CAT please follow the link to download the certificate file '%s'.\n\nRegards,\nCAT Team");
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
            $tokenTag->addText($this->getBody($invitationToken));
            
            $this->publish($tokenTag);
        }
    }
}
