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
     *
     * @param \core\ProfileSilverbullet $profile
     */
    public function __construct($profile){
        $this->profile = $profile;
        $this->session = SessionStorage::getInstance('sb-messages');
        
        $this->validators[AddUserValidator::COMMAND] = new AddUserValidator(AddUserValidator::COMMAND, $this, $this->session);
        $this->validators[AddUsersValidator::COMMAND] = new AddUsersValidator(AddUsersValidator::COMMAND, $this, $this->session);
        $this->validators[DeleteUserValidator::COMMAND] = new DeleteUserValidator(DeleteUserValidator::COMMAND, $this, $this->session);
        $this->validators[AddCertificateValidator::COMMAND] = new AddCertificateValidator(AddCertificateValidator::COMMAND, $this, $this->session);
        $this->validators[RevokeCertificateValidator::COMMAND] = new RevokeCertificateValidator(RevokeCertificateValidator::COMMAND, $this, $this->session);
        $this->validators[SaveUsersValidator::COMMAND] = new SaveUsersValidator(SaveUsersValidator::COMMAND, $this, $this->session);
        $this->validators[TermsOfUseValidator::COMMAND] = new TermsOfUseValidator(TermsOfUseValidator::COMMAND, $this, $this->session);
    }
    
    /**
     * 
     * @return \lib\storage\SessionStorage
     */
    public function getSession(){
        return $this->session;
    }
    
    /**
     * 
     * @return \core\ProfileSilverbullet
     */
    public function getProfile(){
        return $this->profile;
    }
    
    /**
     * 
     */
    public function parseRequest(){
        $agreement_attributes = $this->profile->getAttributes("hiddenprofile:tou_accepted");
        if(count($agreement_attributes) > 0){
            if(isset($_POST[AddUserValidator::COMMAND]) && isset($_POST[AddUserValidator::PARAM_EXPIRY])){
                $this->validator = $this->validators[AddUserValidator::COMMAND];
            }elseif (isset($_FILES[AddUsersValidator::COMMAND])){
                $this->validator = $this->validators[AddUsersValidator::COMMAND];
            }elseif (isset($_POST[DeleteUserValidator::COMMAND])){
                $this->validator = $this->validators[DeleteUserValidator::COMMAND];
            }elseif (isset($_POST[AddCertificateValidator::COMMAND])){
                $this->validator = $this->validators[AddCertificateValidator::COMMAND];
            }elseif (isset($_POST[RevokeCertificateValidator::COMMAND])){
                $this->validator = $this->validators[RevokeCertificateValidator::COMMAND];
            }elseif (isset($_POST[SaveUsersValidator::COMMAND])){
                $this->validator = $this->validators[SaveUsersValidator::COMMAND];
            }
        }else{
            if(isset($_POST[SilverbulletFactory::COMMAND])){
                if($_POST[SilverbulletFactory::COMMAND] == TermsOfUseValidator::COMMAND){
                    $this->validator = $this->validators[TermsOfUseValidator::COMMAND];
                }
            }
        }
        
        if($this->validator != null){
            $this->validator->execute();
        }
    }
    
    /**
     * 
     * @param string $command
     * @param MessageReceiverInterface $receiver
     */
    public function distributeMessages($command, $receiver){
        if(isset($this->validators[$command]) && $this->validators[$command] != null){
            $this->validators[$command]->publishMessages($receiver);
        }
    }
    
    /**
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
     *
     * @return \lib\domain\SilverbulletUser
     */
    public function createUsers(){
        $this->users = SilverbulletUser::getList($this->profile->identifier);
        return $this->users;
    }
    
    /**
     *
     * @return array
     */
    public function getUserStats(){
        $count[self::STATS_TOTAL] = 0;
        $count[self::STATS_ACTIVE] = 0;
        $count[self::STATS_PASSIVE] = 0;
        foreach ($this->users as $user) {
            $count[self::STATS_TOTAL]++;
            if($user->isActive()){
                $count[self::STATS_ACTIVE]++;
            }else{
                $count[self::STATS_PASSIVE]++;
            }
        }
        return $count;
    }
    
    /**
     * 
     */
    public function redirectAfterSubmit(){
        if(isset($_SERVER['REQUEST_URI'])){
            $location = $this->addQuery($_SERVER['SCRIPT_NAME']);
            header('Location: ' . $location );
            exit;
        }
    }
    
    /**
	 * Appends GET parameters to a clean url.
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
