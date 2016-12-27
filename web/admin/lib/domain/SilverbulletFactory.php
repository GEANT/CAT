<?php
namespace lib\domain;

use lib\view\MessageContainerInterface;
use lib\http\ValidatorInterface;

use lib\utils\CSVParser;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletFactory implements ValidatorInterface{
    
    const COMMAND_ADD_USER = 'newuser';
    const COMMAND_ADD_USERS = 'newusers';
    const COMMAND_SAVE = 'saveusers';
    const COMMAND_DELETE_USER = 'deleteuser';
    const COMMAND_ADD_CERTIFICATE = 'newcertificate';
    const COMMAND_REVOKE_CERTIFICATE = 'revokecertificate';
    
    const PARAM_EXPIRY = 'userexpiry';
    const PARAM_EXPIRY_MULTIPLE = 'userexpiry[]';
    const PARAM_ID = 'userid';
    const PARAM_ID_MULTIPLE = 'userid[]';
    const PARAM_ACKNOWLEDGE = 'acknowledge';
    
    const STATS_TOTAL = 'total';
    const STATS_ACTIVE = 'active';
    const STATS_PASSIVE = 'passive';
    
    /**
     *
     * @var \ProfileSilverbullet
     */
    private $profile;
    
    /**
     * 
     * @var SilverbulletUser []
     */
    private $users = array();
    
    /**
     *
     * @param \ProfileSilverbullet $profile
     */
    public function __construct($profile){
        $this->profile = $profile;
    }
    
    /**
     * 
     */
    public function parseRequest(){
        if(isset($_POST[self::COMMAND_ADD_USER]) && !empty($_POST[self::COMMAND_ADD_USER]) && isset($_POST[self::PARAM_EXPIRY])){
            $this->createUser($this->profile->identifier, $_POST[self::COMMAND_ADD_USER], $_POST[self::PARAM_EXPIRY]);
        }elseif (isset($_FILES[self::COMMAND_ADD_USERS])){
            $this->createUsersFromFile();
        }elseif (isset($_POST[self::COMMAND_DELETE_USER])){
            $user = SilverbulletUser::prepare($_POST[self::COMMAND_DELETE_USER]);
            $user->delete();
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_ADD_CERTIFICATE])){
            $user = SilverbulletUser::prepare($_POST[self::COMMAND_ADD_CERTIFICATE]);
            $user->load();
            $this->createCertificate($user);
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_REVOKE_CERTIFICATE])){
            $certificate = SilverbulletCertificate::prepare($_POST[self::COMMAND_REVOKE_CERTIFICATE]);
            $certificate->delete();
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_SAVE])){
            $userIds = $_POST[self::PARAM_ID];
            $userExpiries = $_POST[self::PARAM_EXPIRY];
            foreach ($userIds as $key => $userId) {
                $user = SilverbulletUser::prepare($userId);
                $user->load();
                $user->setExpiry($userExpiries[$key]);
                if(isset($_POST[self::PARAM_ACKNOWLEDGE]) && $_POST[self::PARAM_ACKNOWLEDGE]=='true'){
                    $user->makeAcknowledged();
                }
                $user->save();
            }
            $this->redirectAfterSubmit();
        }
    }
    
    /**
     * 
     * @param string $command
     * @param unknown $message
     */
    private function storeMessage($command, $message){
        $_SESSION['sb-messages'][$command][] = $message; 
    }
    
    /**
     * 
     * @param MessageContainerInterface $messageContainer
     * @param string $command
     * {@inheritDoc}
     * @see \lib\domain\http\ValidatorInterface::provideMessages()
     */
    public function provideMessages($messageContainer, $command){
        $messageContainer;
    }
    
    /**
     * 
     * @param int $profileId
     * @param string $username
     * @param string $expiry
     * @return \lib\domain\SilverbulletUser
     */
    public function createUser($profileId, $username, $expiry){
        $user = new SilverbulletUser($profileId, $username);
        if(isset($expiry) && !empty($expiry)){
            $user->setExpiry($expiry);
            $user->save();
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
        return $certificate;
    }
    
    /**
     * 
     */
    public function createUsersFromFile(){
        $parser = new CSVParser($_FILES[self::COMMAND_ADD_USERS], "\n", ',');
        while($parser->hasMoreRows()){
            $row = $parser->nextRow();
            if(isset($row[0]) && isset($row[1])){
                $user = $this->createUser($this->profile->identifier, $row[0], $row[1]);
                $max = empty($row[2]) ? 1 : $row[2];
                for($i=0; $i<$max; $i++){
                    $this->createCertificate($user);
                }
            }
        }
    }
    
    /**
     * 
     */
    private function redirectAfterSubmit(){
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
}
