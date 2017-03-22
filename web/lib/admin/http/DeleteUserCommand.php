<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\view\YesNoDialogBox;

class DeleteUserCommand extends AbstractCommand{

    const COMMAND = 'deleteuser';
    const PARAM_CONFIRMATION = 'confirmation';

    public function execute(){
        $userId = $this->parseInt($_POST[self::COMMAND]);
        $user = SilverbulletUser::prepare($userId);
        $user->load();
        if(isset($_POST[self::PARAM_CONFIRMATION])){
            $confirmation = $this->parseString($_POST[self::PARAM_CONFIRMATION]);
            if($confirmation=='true'){
                $user->setDeactivated(true, $this->controller->getProfile());
                $user->save();
            }else{
                $this->storeInfoMessage("User '".$user->getUsername()."' deactivation has been canceled!");
            }

            $this->controller->redirectAfterSubmit();
        }else{
            //Append terms of use popup
            $builder = $this->controller->getBuilder();
            $dialogBox = new YesNoDialogBox('sb-popup-message', $this->controller->addQuery($_SERVER['SCRIPT_NAME']), _('Deactivate User'), "Are you sure you want to deactivate user '".$user->getUsername()."' and revoke all user certificates?");
            $dialogBox->addParameter('command', SaveUsersCommand::COMMAND);
            $dialogBox->addParameter(self::COMMAND, $user->getIdentifier());
            $dialogBox->setYesControl(self::PARAM_CONFIRMATION, 'true');
            $dialogBox->setNoControl(self::PARAM_CONFIRMATION, 'false');
            $builder->addContentElement($dialogBox);
        }
        
    }

}
