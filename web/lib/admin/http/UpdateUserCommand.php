<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;

/**
 * Updates user expiry date once update button is clicked.
 * 
 * @author Zilvinas Vaira
 *
 */
class UpdateUserCommand extends AbstractInvokerCommand{

    /**
     * Update command identifier.
     * 
     * @var string
     */
    const COMMAND = 'updateuser';
    
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
        $userExpiries = $this->parseArray($_POST[SaveUsersCommand::PARAM_EXPIRY]);
        
        $userId = $userIds[$userIndex];
        $userExpiry = $userExpiries[$userIndex];
        $user = SilverbulletUser::prepare($userId);
        $user->load();

        $user->setExpiry($userExpiry);
        $username = $user->getUsername();
        if(empty($user->get(SilverbulletUser::EXPIRY))){
            $this->storeErrorMessage(sprintf(_("Expiry date was incorrect for '%s'!"), $username));
        }
        $user->save();
        
        $this->context->redirectAfterSubmit();
    }

}
