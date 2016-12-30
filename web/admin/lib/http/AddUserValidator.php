<?php
namespace lib\http;

class AddUserValidator extends AbstractCommandValidator{
    
    const COMMAND = 'newuser';
    const PARAM_EXPIRY = 'userexpiry';
    
    public function execute(){
        $user = $this->factory->createUser($_POST[self::COMMAND], $_POST[self::PARAM_EXPIRY]);
        if(!empty($user->getIdentifier())){
            $this->storeInfoMessage(_('User was added successfully!'));
        }
    }
    
}