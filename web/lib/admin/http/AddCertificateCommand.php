<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AddCertificateCommand extends AbstractInvokerCommand{

    const COMMAND = 'newcertificate';

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
        $userId = $this->parseInt($_POST[self::COMMAND]);
        $user = SilverbulletUser::prepare($userId);
        $user->load();
        if($user->isExpired()){
            $this->storeErrorMessage(sprintf(_("User '%s' has expired. In order to generate credentials please extend the expiry date!"), $user->getUsername()));
        }else{
            $this->context->createCertificate($user);
            if($user->isDeactivated()){
                $user->setDeactivated(false, $this->context->getProfile());
                $user->save();
            }
        }
        $this->context->redirectAfterSubmit();
    }

}
