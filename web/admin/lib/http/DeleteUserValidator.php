<?php
namespace lib\http;

use lib\domain\SilverbulletUser;
use lib\view\YesNoDialogBox;

class DeleteUserValidator extends AbstractCommandValidator{

    const COMMAND = 'deleteuser';
    const PARAM_CONFIRMATION = 'confirmation';

    public function execute(){
        $user = SilverbulletUser::prepare($_POST[self::COMMAND]);
        $user->load();
        if(isset($_POST[self::PARAM_CONFIRMATION])){
            if($_POST[self::PARAM_CONFIRMATION]=='true'){
                $user->setDeactivated(true, $this->factory->getProfile());
                $user->save();
            }else{
                $this->storeInfoMessage("User '".$user->getUsername()."' deletion has been canceled!");
            }

            $this->factory->redirectAfterSubmit();
        }else{
            //Append terms of use popup
            $builder = $this->factory->getBuilder();
            $dialogBox = new YesNoDialogBox('sb-popup-message', $this->factory->addQuery($_SERVER['SCRIPT_NAME']), _('Delete User'), "Are you sure you want to delete user '".$user->getUsername()."' and revoke all user certificates?", SaveUsersValidator::COMMAND);
            $dialogBox->addParameter('command', SaveUsersValidator::COMMAND);
            $dialogBox->addParameter(self::COMMAND, $_POST[self::COMMAND]);
            $dialogBox->setYesControl(self::PARAM_CONFIRMATION, 'true');
            $dialogBox->setNoControl(self::PARAM_CONFIRMATION, 'false');
            $builder->addContentElement($dialogBox);
        }
        
    }

}
