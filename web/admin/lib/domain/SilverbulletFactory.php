<?php
namespace lib\domain;

use lib\http\AbstractCommandValidator;
use lib\http\AddCertificateValidator;
use lib\http\AddUsersValidator;
use lib\http\AddUserValidator;
use lib\http\DeleteUserValidator;
use lib\http\RevokeCertificateValidator;
use lib\http\SaveUsersValidator;
use lib\storage\SessionStorage;
use lib\view\MessageReceiverInterface;
use lib\http\TermsOfUseValidator;
use lib\http\DefaultValidator;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletFactory{
    
    const COMMAND = 'command';
    
    const STATS_TOTAL = 'total';
    const STATS_ACTIVE = 'active';
    const STATS_PASSIVE = 'passive';
    
    /**
     * 
     * @var AbstractCommandValidator[]
     */
    protected $validators = null;

    /**
     *
     * @var AbstractCommandValidator
     */
    private $validator = null;

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
     * Creates Silverbullet factory object prepares builder, profile ans session objects
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
     * Finds and executes required validator based on request data
     * 
     */
    public function parseRequest(){
        $commandToken = '';
        if(isset($_POST[SilverbulletFactory::COMMAND])){
            $commandToken = $_POST[SilverbulletFactory::COMMAND];
            if($commandToken == SaveUsersValidator::COMMAND){
                if(isset($_POST[DeleteUserValidator::COMMAND])){
                    $commandToken = DeleteUserValidator::COMMAND;
                }elseif(isset($_POST[AddCertificateValidator::COMMAND])){
                    $commandToken = AddCertificateValidator::COMMAND;
                }elseif (isset($_POST[RevokeCertificateValidator::COMMAND])){
                    $commandToken = RevokeCertificateValidator::COMMAND;
                }elseif (isset($_POST[SaveUsersValidator::COMMAND])){
                    $commandToken = SaveUsersValidator::COMMAND;
                }
            }
        }
        $this->validator = $this->createValidator($commandToken);
        $this->validator->execute();
    }
    
    /**
     * Retrieves existing validator from object pool based on string command token or creates a new one by usig factory method
     * 
     * @param string $commandToken
     * @return \lib\http\AbstractCommandValidator
     */
    public function createValidator($commandToken){
        if(!isset($this->validators[$commandToken]) || $this->validators[$commandToken] == null){
            $this->validators[$commandToken] = $this->doCreateValidator($commandToken);
        }
        return $this->validators[$commandToken];
    }
    
    /**
     * Factory method creates validator object based on strign command token
     * 
     * @param unknown $commandToken
     * @return \lib\http\AbstractCommandValidator
     */
    private function doCreateValidator($commandToken){
        if($this->isAgreementSigned()){
            if($commandToken == AddUserValidator::COMMAND){
                return new AddUserValidator($commandToken, $this);
            }elseif ($commandToken == AddUsersValidator::COMMAND){
                return new AddUsersValidator($commandToken, $this);
            }elseif ($commandToken == DeleteUserValidator::COMMAND){
                return new DeleteUserValidator($commandToken, $this);
            }elseif ($commandToken == AddCertificateValidator::COMMAND){
                return new AddCertificateValidator($commandToken, $this);
            }elseif ($commandToken == RevokeCertificateValidator::COMMAND){
                return new RevokeCertificateValidator($commandToken, $this);
            }elseif ($commandToken == SaveUsersValidator::COMMAND){
                return new SaveUsersValidator($commandToken, $this);
            }else{
                return new DefaultValidator($commandToken, $this);
            }
        }else{
            if($commandToken == TermsOfUseValidator::COMMAND){
                return new TermsOfUseValidator($commandToken, $this);
            }else{
                return new DefaultValidator($commandToken, $this);
            }
        }
    }
    
    /**
     * Distributes messages from particular validator to a requested receiver
     * 
     * @param string $command
     * @param MessageReceiverInterface $receiver
     */
    public function distributeMessages($command, $receiver){
        $validator = $this->createValidator($command);
        $validator->publishMessages($receiver);
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
            $this->validator->storeErrorMessage(_('User name should not be empty!'));
        }elseif(empty($expiry)){
            $this->validator->storeErrorMessage(_('No expiry date has been provided!'));
        }else{
            $user->setExpiry($expiry);
            $user->save();
            if(empty($user->get(SilverbulletUser::EXPIRY))){
                $this->validator->storeErrorMessage(_('Expiry date was incorect for') .' "'. $username .'"!');
            }elseif(empty($user->getIdentifier())){
                $this->validator->storeErrorMessage(_('Username') .' "'. $username .'"'. _('already exist!'));
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
            $this->validator->storeErrorMessage(_('Could not create certificate!'));
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
