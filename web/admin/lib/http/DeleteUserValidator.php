<?php
namespace lib\http;

use lib\domain\SilverbulletUser;

class DeleteUserValidator extends AbstractCommandValidator{

    const COMMAND = 'deleteuser';

    public function execute(){
        $user = SilverbulletUser::prepare($_POST[self::COMMAND]);
        $user->delete();
        $this->factory->redirectAfterSubmit();
    }

}