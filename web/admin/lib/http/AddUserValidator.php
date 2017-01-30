<?php
namespace lib\http;

class AddUserValidator extends AbstractCommandValidator{
    
    const COMMAND = 'newuser';
    const PARAM_NAME = 'username';
    const PARAM_EXPIRY = 'userexpiry';
    
    public function execute(){
        if(isset($_POST[self::PARAM_NAME]) && isset($_POST[self::PARAM_EXPIRY])){
            $user = $this->factory->createUser($_POST[self::PARAM_NAME], $_POST[self::PARAM_EXPIRY]);
            if(!empty($user->getIdentifier())){
                $this->storeInfoMessage(_('User was added successfully!'));
            }
        }else{
            $this->storeErrorMessage(_('User name or expiry parameters are missing!'));
        }
    }
    
}
