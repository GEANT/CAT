<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletInvitation;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class RevokeInvitationCommand extends AbstractInvokerCommand{

    const COMMAND = 'revokeinvitation';

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
        $invitationId = $this->parseInt($_POST[self::COMMAND]);
        
        $invitation = SilverbulletInvitation::prepare($invitationId);
        $invitation->setQuantity(0);
        $invitation->save();
        
        $this->context->redirectAfterSubmit();
    }

}
