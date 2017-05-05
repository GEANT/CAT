<?php
namespace web\lib\admin\http;



/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletAjaxController extends AbstractController{

    /**
     * 
     * @var DefaultContext
     */
    private $context = null;
    
    /**
     * Creates Silverbullet Ajax front controller object and prepares commands and common rules how the commands are executed.
     *
     * @param DefaultContext $context Requires default context object.
     */
    public function __construct($context){
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractController::doCreateCommand()
     */
    protected function doCreateCommand($commandToken) {
        if($commandToken == ValidateEmailAddress::COMMAND){
            return new ValidateEmailAddress($commandToken, $this->context);
        }elseif($commandToken == GetTokenEmailDetails::COMMAND) {
            return new GetTokenEmailDetails($commandToken, $this->context);
        }else {
            return new DefaultCommand($commandToken);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractController::parseRequest()
     */
    public function parseRequest(){
        $commandToken = '';
        if(isset($_REQUEST[SilverbulletAjaxController::COMMAND])){
            $commandToken = $_REQUEST[SilverbulletAjaxController::COMMAND];
        }
        $currentCommand = $this->createCommand($commandToken);
        $currentCommand->execute();
    }
    
}
