<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletCertificate;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class RevokeCertificateCommand extends AbstractInvokerCommand{

    const COMMAND = 'revokecertificate';

    /**
     *
     * @var SilverbulletContext
     */
    private $context;
    
    /**
     *
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute(){
        $profile = $this->context->getProfile();
        $certificateId = $this->parseInt($_POST[self::COMMAND]);
        
        $certificate = SilverbulletCertificate::prepare($certificateId);
        $certificate->load();
        
        $certificate->revoke($profile);
        
        $this->context->redirectAfterSubmit();
    }

}
