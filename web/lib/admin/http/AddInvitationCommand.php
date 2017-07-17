<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AddInvitationCommand extends AbstractInvokerCommand{

    const COMMAND = 'newinvitation';

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
        $userIndex = $this->parseInt($_POST[self::COMMAND]);
        $userIds = $this->parseArray($_POST[SaveUsersCommand::PARAM_ID]);
        $invitationsQuantities = $this->parseArray($_POST[SaveUsersCommand::PARAM_QUANTITY]);
        
        $userId = $userIds[$userIndex];
        $invitationsQuantity = $invitationsQuantities[$userIndex];
        $user = SilverbulletUser::prepare($userId);
        $user->load();
        
        if($user->isExpired()){
            $this->storeErrorMessage(sprintf(_("User '%s' has expired. In order to generate credentials please extend the expiry date!"), $user->getUsername()));
        }else{
            $this->context->createInvitation($user, $this, (int) $invitationsQuantity);
            if(!is_numeric($invitationsQuantity)){
                $this->storeErrorMessage(sprintf(_("Invitations quantity '%' provided for user '%s' was not numeric. Assumed quantity as '1' !"), $invitationsQuantity, $user->getUsername()));
            }
            if($user->isDeactivated()){
                $user->setDeactivated(false, $this->context->getProfile());
                $user->save();
            }
        }
        $this->context->redirectAfterSubmit();
    }

}
