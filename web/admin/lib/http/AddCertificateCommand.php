<?php
namespace lib\http;

use lib\domain\SilverbulletUser;

class AddCertificateCommand extends AbstractCommand{

    const COMMAND = 'newcertificate';

    /**
     * 
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
     */
    public function execute(){
        $userId = $this->parseInt($_POST[self::COMMAND]);
        $user = SilverbulletUser::prepare($userId);
        $user->load();
        $this->controller->createCertificate($user);
        $this->controller->redirectAfterSubmit();
    }

}
