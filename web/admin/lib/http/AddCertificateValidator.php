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
        $user = SilverbulletUser::prepare($_POST[self::COMMAND]);
        $user->load();
        $this->factory->createCertificate($user);
        $this->factory->redirectAfterSubmit();
    }

}