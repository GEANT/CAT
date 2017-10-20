<?php
namespace web\lib\admin\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AddUserCommand extends AbstractInvokerCommand {
    
    const COMMAND = 'newuser';
    const PARAM_NAME = 'username';
    const PARAM_EXPIRY = 'userexpiry';
    
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
        if(isset($_POST[self::PARAM_NAME]) && isset($_POST[self::PARAM_EXPIRY])){
            $name = $this->parseString(filter_input(INPUT_POST, self::PARAM_NAME, FILTER_SANITIZE_STRING));
            $expiry = $this->parseString($_POST[self::PARAM_EXPIRY]);
            $user = $this->context->createUser($name, $expiry, $this);
            if(!empty($user->getIdentifier())){
                $this->storeInfoMessage(_('User was added successfully!'));
            }
        }else{
            $this->storeErrorMessage(_('User name or expiry parameters are missing!'));
        }
    }
    
}
