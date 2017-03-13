<?php
namespace lib\http;

use lib\storage\SessionStorage;
use lib\view\MessageReceiverInterface;
use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletController{
    
    const COMMAND = 'command';
    
    const STATS_TOTAL = 'total';
    const STATS_ACTIVE = 'active';
    const STATS_PASSIVE = 'passive';
    
    /**
     * 
     * @var AbstractCommand[]
     */
    protected $commands = null;

    /**
     *
     * @var AbstractCommand
     */
    private $currentCommand = null;

    /**
     *
     * @var \lib\view\InstitutionPageBuilder
     */
    private $builder;
    
    /**
     *
     * @var \core\ProfileSilverbullet
     */
    private $profile;
    
    /**
     * 
     * @var SilverbulletUser[]
     */
    private $users = array();
    
    /**
     * 
     * @var SessionStorage
     */
    protected $session;
    
    /**
     * Creates Silverbullet front controller object prepares builder, profile ans session objects
     * 
     * @param \lib\view\InstitutionPageBuilder $builder
     */
    public function __construct($builder){
        $this->builder = $builder;
        $this->profile = $builder->getProfile();
        $this->session = SessionStorage::getInstance('sb-messages');
    }
    
    /**
     * Provides access to session storage object
     * 
     * @return \lib\storage\SessionStorage
     */
    public function getSession(){
        return $this->session;
    }
    
    /**
     * Retrievies present user profile
     * 
     * @return \core\ProfileSilverbullet
     */
    public function getProfile(){
        return $this->profile;
    }

    /**
     * Retrieves present page builder object
     * 
     * @return \lib\view\InstitutionPageBuilder
     */
    public function getBuilder(){
        return $this->builder;
    }
    
    /**
     * Checks wether user signed the agreement or not
     * 
     * @return boolean
     */
    public function isAgreementSigned(){
        $agreement_attributes = $this->profile->getAttributes("hiddenprofile:tou_accepted");
        return count($agreement_attributes) > 0;
    }
    
    /**
     * Marks agreement as signed inside the database
     */
    public function signAgreement(){
        $this->profile->addAttribute("hiddenprofile:tou_accepted",NULL,TRUE);
    }
    
    /**
     * Finds and executes required command based on request data
     * 
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
                }elseif (isset($_POST[RevokeCertificateCommand::COMMAND])){
                    $commandToken = RevokeCertificateCommand::COMMAND;
                }elseif (isset($_POST[SaveUsersCommand::COMMAND])){
                    $commandToken = SaveUsersCommand::COMMAND;
                }
            }
        }
        $this->currentCommand = $this->createCommand($commandToken);
        $this->currentCommand->execute();
    }
    
    /**
     * Retrieves existing command from object pool based on string command token or creates a new one by usig factory method
     * 
     * @param string $commandToken
     * @return \lib\http\AbstractCommand
     */
    public function createCommand($commandToken){
        if(!isset($this->commands[$commandToken]) || $this->commands[$commandToken] == null){
            $this->commands[$commandToken] = $this->doCreateCommand($commandToken);
        }
        return $this->commands[$commandToken];
    }
    
    /**
     * Factory method creates command object based on strign command token
     * 
     * @param string $commandToken
     * @return \lib\http\AbstractCommand
     */
    private function doCreateCommand($commandToken){
        if($this->isAgreementSigned()){
            if($commandToken == AddUserCommand::COMMAND){
                return new AddUserCommand($commandToken, $this);
            }elseif ($commandToken == AddUsersCommand::COMMAND){
                return new AddUsersCommand($commandToken, $this);
            }elseif ($commandToken == DeleteUserCommand::COMMAND){
                return new DeleteUserCommand($commandToken, $this);
            }elseif ($commandToken == AddCertificateCommand::COMMAND){
                return new AddCertificateCommand($commandToken, $this);
            }elseif ($commandToken == RevokeCertificateCommand::COMMAND){
                return new RevokeCertificateCommand($commandToken, $this);
            }elseif ($commandToken == SaveUsersCommand::COMMAND){
                return new SaveUsersCommand($commandToken, $this);
            }else{
                return new DefaultCommand($commandToken, $this);
            }
        }else{
            if($commandToken == TermsOfUseCommand::COMMAND){
                return new TermsOfUseCommand($commandToken, $this);
            }else{
                return new DefaultCommand($commandToken, $this);
            }
        }
    }
    
    /**
     * Distributes messages from particular invoker to a requested receiver
     * 
     * @param string $commandToken
     * @param MessageReceiverInterface $receiver
     */
    public function distributeMessages($commandToken, $receiver){
        $command = $this->createCommand($commandToken);
        $command->publishMessages($receiver);
    }
    
    /**
     * Factory method that creates Silverbullet user object stores it to database
     * 
     * @param string $username
     * @param string $expiry
     * @return \lib\domain\SilverbulletUser
     */
    public function createUser($username, $expiry){
        $user = new SilverbulletUser($this->profile->identifier, $username);
        if(empty($username)){
            $this->currentCommand->storeErrorMessage(_('User name should not be empty!'));
        }elseif(empty($expiry)){
            $this->currentCommand->storeErrorMessage(_('No expiry date has been provided!'));
        }else{
            $user->setExpiry($expiry);
            $user->save();
            if(empty($user->get(SilverbulletUser::EXPIRY))){
                $this->currentCommand->storeErrorMessage(_('Expiry date was incorect for') .' "'. $username .'"!');
            }elseif(empty($user->getIdentifier())){
                $this->currentCommand->storeErrorMessage(_('Username') .' "'. $username .'"'. _('already exist!'));
            }
        }
        return $user;
    }
    
    /**
     * Factory method that creates Silverbullet certificate object and stores it to database
     * 
     * @param SilverbulletUser $user
     * @return \lib\domain\SilverbulletCertificate
     */
    public function createCertificate($user){
        $certificate = new SilverbulletCertificate($user);
        $certificate->save();
        if(empty($certificate->getIdentifier())){
            $this->currentCommand->storeErrorMessage(_('Could not create certificate!'));
        }
        return $certificate;
    }
    
    /**
     * Factory method that retrieves Silverbullet users from database and creates theyr objects
     * 
     * @return \lib\domain\SilverbulletUser
     */
    public function createUsers(){
        $this->users = SilverbulletUser::getList($this->profile->identifier);
        return $this->users;
    }
    
    /**
     * Calculates and retrieves user statistics array
     * 
     * @return array
     */
    public function getUserStats(){
        $count[self::STATS_TOTAL] = 0;
        $count[self::STATS_ACTIVE] = 0;
        $count[self::STATS_PASSIVE] = 0;
        foreach ($this->users as $user) {
            $count[self::STATS_TOTAL]++;
            if($user->hasCertificates()){
                $count[self::STATS_ACTIVE]++;
            }else{
                $count[self::STATS_PASSIVE]++;
            }
        }
        return $count;
    }
    
    /**
     * Redirects page to itself in order to prevent acidental form resubmition
     */
    public function redirectAfterSubmit(){
        if(isset($_SERVER['REQUEST_URI'])){
            $location = $this->addQuery($_SERVER['SCRIPT_NAME']);
            header('Location: ' . $location );
            exit;
        }
    }
    
    /**
	 * Appends present GET parameters to a clean url
	 * 
	 * @param string $url
	 * @return string
	 */
	public function addQuery($url){
		$query = '';
		if (is_array($_GET) && count($_GET)) {
			foreach($_GET as $key => $val) {
				if(strpos($key , '/') === false){
					if (empty($key) || empty($val)) { continue; }
					$query .= ($query == '') ? '?' : "&";
					$query .= urlencode($key) . '=' . urlencode($val);
				}
			}
		}
		return $url . $query;
	}
    
}
