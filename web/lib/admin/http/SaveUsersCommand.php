<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SaveUsersCommand extends AbstractInvokerCommand{

    const COMMAND = 'saveusers';

    const PARAM_EXPIRY = 'userexpiry';
    const PARAM_EXPIRY_MULTIPLE = 'userexpiry[]';
    const PARAM_QUANTITY = 'invitationsquantity';
    const PARAM_QUANTITY_MULTIPLE = 'invitationsquantity[]';
    const PARAM_ID = 'userid';
    const PARAM_ID_MULTIPLE = 'userid[]';
    const PARAM_ACKNOWLEDGE = 'acknowledge';
    
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
        if(isset($_POST[self::PARAM_ID]) && isset($_POST[self::PARAM_EXPIRY])){
            $userIds = $this->parseArray($_POST[self::PARAM_ID]);
            foreach ($userIds as $key => $userId) {
                $user = SilverbulletUser::prepare($userId);
                $user->load();
                if(isset($_POST[self::PARAM_ACKNOWLEDGE]) && $_POST[self::PARAM_ACKNOWLEDGE]=='true'){
                    $user->makeAcknowledged();
                }
                $user->save();
            }
        }
        $this->context->redirectAfterSubmit();
    }

}
