<?php
namespace web\lib\admin\http;

class AddUserCommand extends AbstractCommand{
    
    const COMMAND = 'newuser';
    const PARAM_NAME = 'username';
    const PARAM_EXPIRY = 'userexpiry';
    
    public function execute(){
        if(isset($_POST[self::PARAM_NAME]) && isset($_POST[self::PARAM_EXPIRY])){
            $name = $this->parseString($_POST[self::PARAM_NAME]);
            $expiry = $this->parseString($_POST[self::PARAM_EXPIRY]);
            $user = $this->controller->createUser($name, $expiry);
            if(!empty($user->getIdentifier())){
                $this->storeInfoMessage(_('User was added successfully!'));
            }
        }else{
            $this->storeErrorMessage(_('User name or expiry parameters are missing!'));
        }
    }
    
}
