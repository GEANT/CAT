<?php
namespace lib\http;

use lib\domain\SilverbulletUser;

class DeleteUserValidator extends AbstractCommandValidator{

    const COMMAND = 'deleteuser';

    public function execute(){
        $user = SilverbulletUser::prepare($_POST[self::COMMAND]);
        $user->load();
        
        $user->setDeactivated(true, $this->factory->getProfile());
        $user->save();
        
        $this->factory->redirectAfterSubmit();
    }

}
