<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\view\YesNoDialogBox;
use web\lib\admin\view\PageElementInterface;
use web\lib\admin\view\PopupMessageContainer;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class DeleteUserCommand extends AbstractInvokerCommand{

    const COMMAND = 'deleteuser';
    const PARAM_CONFIRMATION = 'confirmation';

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
        if(isset($_POST[self::PARAM_CONFIRMATION])){
            $confirmation = $this->parseString($_POST[self::PARAM_CONFIRMATION]);
            if($confirmation=='true'){
                $user->setDeactivated(true, $this->context->getProfile());
                $user->save();
            }else{
                $this->storeInfoMessage("User '".$user->getUsername()."' deactivation has been canceled!");
            }

            $this->context->redirectAfterSubmit();
        }else{
            //Append terms of use popup
            $builder = $this->context->getBuilder();
            $dialogTitle = _('Deactivate User');
            $dialogText = sprintf(_("Are you sure you want to deactivate user '%s' and revoke all user certificates?"), $user->getUsername());
            $dialogBox = new YesNoDialogBox(PageElementInterface::MESSAGEPOPUP_CLASS, $this->context->addQuery($_SERVER['SCRIPT_NAME']), $dialogText);
            $dialogBox->addParameter('command', SaveUsersCommand::COMMAND);
            $dialogBox->addParameter(self::COMMAND, $user->getIdentifier());
            $dialogBox->setYesControl(self::PARAM_CONFIRMATION, 'true');
            $dialogBox->setNoControl(self::PARAM_CONFIRMATION, 'false');
            $builder->addContentElement(new PopupMessageContainer($dialogBox, PageElementInterface::MESSAGEPOPUP_CLASS, $dialogTitle));
        }
        
    }

}
