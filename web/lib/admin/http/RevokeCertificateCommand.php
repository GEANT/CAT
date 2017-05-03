<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletCertificate;

class RevokeCertificateCommand extends AbstractSilverbulletCommand{

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
