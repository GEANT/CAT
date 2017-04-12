<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;

/**
 * Updates user expiry date once update button is clicked.
 * 
 * @author Zilvinas Vaira
 *
 */
class UpdateUserCommand extends AbstractCommand{

    /**
     * Update command identifier.
     * 
     * @var string
     */
    const COMMAND = 'updateuser';
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
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
        
        $this->controller->redirectAfterSubmit();
    }

}
