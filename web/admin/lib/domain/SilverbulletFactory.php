<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletFactory {
    
    const COMMAND_ADD_USER = 'newuser';
    const COMMAND_DELETE_USER = 'deleteuser';
    const COMMAND_ADD_CERTIFICATE = 'newcertificate';
    const COMMAND_REVOKE_CERTIFICATE = 'revokecertificate';
    
    const STATS_TOTAL = 'total';
    const STATS_ACTIVE = 'active';
    const STATS_PASSIVE = 'passive';
    
    /**
     *
     * @var \IdP
     */
    private $institution;
    
    /**
     * 
     * @var SilverbulletUser []
     */
    private $users = array();
    
    /**
     *
     * @param \IdP $institution
     */
    public function __construct($institution){
        $this->institution = $institution;
    }
    
    public function parseRequest(){
        if(isset($_POST[self::COMMAND_ADD_USER])){
            $this->addUser($_POST[self::COMMAND_ADD_USER]);
        }elseif (isset($_POST[self::COMMAND_DELETE_USER])){
            $this->deleteUser($_POST[self::COMMAND_DELETE_USER]);
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_ADD_CERTIFICATE])){
            $certificate = new SilverbulletCertificate();
            $certificate->setFields($this->institution->identifier, $_POST[self::COMMAND_ADD_CERTIFICATE]);
            $certificate->save();
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_REVOKE_CERTIFICATE])){
            $certificate = new SilverbulletCertificate($_POST[self::COMMAND_REVOKE_CERTIFICATE]);
            $certificate->delete();
            $this->redirectAfterSubmit();
        }
    }
    
    private function redirectAfterSubmit(){
        if(isset($_SERVER['REQUEST_URI'])){
            header("Location: " . $_SERVER['REQUEST_URI'] );
            die();
        }
    }
    
    /**
     *
     * @param string $userId
     * @return SilverbulletUser
     */
    private function addUser($userId){
        return new SilverbulletUser($userId, $this->institution->identifier);
    }
    
    /**
     *
     * @param string $userId
     * @return SilverbulletUser
     */
    private function deleteUser($userId){
        $user = new SilverbulletUser($userId, $this->institution->identifier);
        $user->delete();
        return $user;
    }
    
    /**
     * 
     * @return \lib\domain\SilverbulletUser
     */
    public function createUsers(){
        $this->users = SilverbulletUser::list($this->institution->identifier);
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