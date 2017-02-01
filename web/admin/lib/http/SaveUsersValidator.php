<?php
namespace lib\http;

use lib\domain\SilverbulletUser;

class SaveUsersValidator extends AbstractCommandValidator{

    const COMMAND = 'saveusers';

    const PARAM_EXPIRY = 'userexpiry';
    const PARAM_EXPIRY_MULTIPLE = 'userexpiry[]';
    const PARAM_ID = 'userid';
    const PARAM_ID_MULTIPLE = 'userid[]';
    const PARAM_ACKNOWLEDGE = 'acknowledge';
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
     */
    public function execute(){
        if(isset($_POST[self::PARAM_ID]) && isset($_POST[self::PARAM_EXPIRY])){
            $userIds = $this->parseArray($_POST[self::PARAM_ID]);
            $userExpiries = $this->parseArray($_POST[self::PARAM_EXPIRY]);
            foreach ($userIds as $key => $userId) {
                $user = SilverbulletUser::prepare($userId);
                $user->load();
                $username = $user->getUsername();
                $user->setExpiry($userExpiries[$key]);
                if(empty($user->get(SilverbulletUser::EXPIRY))){
                    $this->storeErrorMessage(_('Expiry date was incorrect for') .' "'. $username .'"!');
                }
                if(isset($_POST[self::PARAM_ACKNOWLEDGE]) && $_POST[self::PARAM_ACKNOWLEDGE]=='true'){
                    $user->makeAcknowledged();
                }
                $user->save();
            }
        }
        $this->factory->redirectAfterSubmit();
    }

}
