<?php
namespace web\lib\admin\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletController extends AbstractController implements MessageDistributor{
    
    
    /**
     * 
     * @var SilverbulletContext
     */
    private $context = null;

    
    /**
     * Creates Silverbullet front controller object and prepares commands and common rules how the commands are executed.
     * 
     * @param SilverbulletContext $context Requires silverbullet page context object
     */
    public function __construct($context){
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\ControllerInterface::parseRequest()
     */
    public function parseRequest(){
        $commandToken = '';
        if(isset($_POST[SilverbulletController::COMMAND])){
            $commandToken = $_POST[SilverbulletController::COMMAND];
            if($commandToken == SaveUsersCommand::COMMAND){
                if(isset($_POST[DeleteUserCommand::COMMAND])){
                    $commandToken = DeleteUserCommand::COMMAND;
                }elseif(isset($_POST[AddCertificateCommand::COMMAND])){
                    $commandToken = AddCertificateCommand::COMMAND;
                }elseif(isset($_POST[UpdateUserCommand::COMMAND])){
                    $commandToken = UpdateUserCommand::COMMAND;
                }elseif (isset($_POST[RevokeCertificateCommand::COMMAND])){
                    $commandToken = RevokeCertificateCommand::COMMAND;
                }elseif (isset($_POST[SaveUsersCommand::COMMAND])){
                    $commandToken = SaveUsersCommand::COMMAND;
                }
            }
        }
        $currentCommand = $this->createCommand($commandToken);
        $this->context->setCurrentCommand($currentCommand);
        $currentCommand->execute();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractController::doCreateCommand()
     */
    protected function doCreateCommand($commandToken){
        if($this->context->isAgreementSigned()){
            if($commandToken == AddUserCommand::COMMAND){
                return new AddUserCommand($commandToken, $this->context);
            }elseif ($commandToken == AddUsersCommand::COMMAND){
                return new AddUsersCommand($commandToken, $this->context);
            }elseif ($commandToken == DeleteUserCommand::COMMAND){
                return new DeleteUserCommand($commandToken, $this->context);
            }elseif ($commandToken == AddCertificateCommand::COMMAND){
                return new AddCertificateCommand($commandToken, $this->context);
            }elseif ($commandToken == UpdateUserCommand::COMMAND){
                return new UpdateUserCommand($commandToken, $this->context);
            }elseif ($commandToken == RevokeCertificateCommand::COMMAND){
                return new RevokeCertificateCommand($commandToken, $this->context);
            }elseif ($commandToken == SaveUsersCommand::COMMAND){
                return new SaveUsersCommand($commandToken, $this->context);
            }elseif ($commandToken == SendTokenByEmail::COMMAND){
                return new SendTokenByEmail($commandToken, $this->context);
            }else{
                return new DefaultCommand($commandToken);
            }
        }else{
            if($commandToken == TermsOfUseCommand::COMMAND){
                return new TermsOfUseCommand($commandToken, $this->context);
            }else{
                return new DefaultCommand($commandToken);
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\MessageDistributor::distributeMessages()
     */
    public function distributeMessages($commandToken, $receiver){
        $command = $this->createCommand($commandToken);
        $command->publishMessages($receiver);
    }
    
}
