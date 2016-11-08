<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletFactory {
    
    const COMMAND_ADD_USER = 'newuser';
    const PARAM_YEAR = 'newuseryear';
    const PARAM_MONTH = 'newusermonth';
    const PARAM_DAY = 'newuserday';
    
    const COMMAND_DELETE_USER = 'deleteuser';
    const COMMAND_ADD_CERTIFICATE = 'newcertificate';
    const COMMAND_REVOKE_CERTIFICATE = 'revokecertificate';
    
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
    
    public function parseRequest(){
        if(isset($_POST[self::COMMAND_ADD_USER]) && !empty($_POST[self::COMMAND_ADD_USER])){
            $user = new SilverbulletUser($this->profile->identifier, $_POST[self::COMMAND_ADD_USER]);
            if(isset($_POST[self::PARAM_YEAR]) && isset($_POST[self::PARAM_MONTH]) && isset($_POST[self::PARAM_DAY])){
                $user->setTokenExpiry($_POST[self::PARAM_YEAR], $_POST[self::PARAM_MONTH], $_POST[self::PARAM_DAY]);
            }
            $user->save();
        }elseif (isset($_POST[self::COMMAND_DELETE_USER])){
            $user = SilverbulletUser::prepare($_POST[self::COMMAND_DELETE_USER]);
            $user->delete();
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_ADD_CERTIFICATE])){
            $user = SilverbulletUser::prepare($_POST[self::COMMAND_ADD_CERTIFICATE]);
            $user->load();
            $certificate = new SilverbulletCertificate($user);
            //$certificate->setCertificateDetails(rand(1000, 1000000), 'cert'.count($user->getCertificates()), $user->getTokenExpiry());
            $certificate->save();
            $this->redirectAfterSubmit();
        }elseif (isset($_POST[self::COMMAND_REVOKE_CERTIFICATE])){
            $certificate = SilverbulletCertificate::prepare($_POST[self::COMMAND_REVOKE_CERTIFICATE]);
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
     * @return \lib\domain\SilverbulletUser
     */
    public function createUsers(){
        $this->users = SilverbulletUser::list($this->profile->identifier);
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
