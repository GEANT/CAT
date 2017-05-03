<?php
namespace web\lib\admin\http;

use web\lib\admin\view\html\Tag;
use web\lib\admin\domain\SilverbulletUser;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class ValidateEmailAddress extends AbstractAjaxCommand{

    const COMMAND = 'getinvocationtoken';
    const PARAM_USERID = 'userid';
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute(){
        
        if(isset($_GET[self::PARAM_USERID])){
            $userId = $this->parseInt($_GET[self::PARAM_USERID]);
            $user = SilverbulletUser::prepare($userId);
            $user->load();
            $page = $this->controller->getPage();
            $tokenTag = new Tag('token');
            $tokenTag->addText($user->getUsername());
            $page->appendResponse($tokenTag);
        }
    }
}