<?php
namespace lib\http;

use lib\domain\SilverbulletCertificate;

class RevokeCertificateCommand extends AbstractCommand{

    const COMMAND = 'revokecertificate';

    /**
     *
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
     */
    public function execute(){
        $profile = $this->controller->getProfile();
        $certificateId = $this->parseInt($_POST[self::COMMAND]);
        
        $certificate = SilverbulletCertificate::prepare($certificateId);
        $certificate->load();
        
        $certificate->revoke($profile);
        
        $this->controller->redirectAfterSubmit();
    }

}
