<?php
namespace lib\http;

use lib\domain\SilverbulletUser;

class AddCertificateValidator extends AbstractCommandValidator{

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
        $this->factory->createCertificate($user);
        $this->factory->redirectAfterSubmit();
    }

}
