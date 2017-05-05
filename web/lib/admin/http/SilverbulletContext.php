<?php
namespace web\lib\admin\http;

use core\ProfileSilverbullet;
use web\lib\admin\domain\SilverbulletCertificate;
use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\view\InstitutionPageBuilder;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletContext extends DefaultContext{
    
    const STATS_TOTAL = 'total';
    const STATS_ACTIVE = 'active';
    const STATS_PASSIVE = 'passive';
    
    /**
     *
     * @var SilverbulletUser[]
     */
    private $users = array();
    
    /**
     *
     * @var InstitutionPageBuilder
     */
    private $builder;
    
    /**
     *
     * @var ProfileSilverbullet
     */
    private $profile;
    
    /**
     *
     * @var AbstractCommand
     */
    private $currentCommand = null;
    
    /**
     * 
     * @param InstitutionPageBuilder $builder
     */
    public function __construct($builder) {
        parent::__construct($builder->getPage());
        $this->builder = $builder;
        $this->profile = $builder->getProfile();
    }
    
    /**
     * Retrievies present user profile
     *
     * @return ProfileSilverbullet
     */
    public function getProfile(){
        return $this->profile;
    }
    
    /**
     * Retrieves present page builder object
     *
     * @return InstitutionPageBuilder
     */
    public function getBuilder(){
        return $this->builder;
    }
    
    /**
     * 
     * @param AbstractCommand $currentCommand
     */
    public function setCurrentCommand($currentCommand){
        $this->currentCommand = $currentCommand;
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
     * Factory method that creates Silverbullet user object stores it to database
     *
     * @param string $username
     * @param string $expiry
     * @return SilverbulletUser
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
                $this->currentCommand->storeErrorMessage(sprintf(_("Expiry date was incorect for '%s'!"), $username));
            }elseif(empty($user->getIdentifier())){
                $this->currentCommand->storeErrorMessage(sprintf(_("Username '%s' already exist!"), $username));
            }
        }
        return $user;
    }
    
    /**
     * Factory method that creates Silverbullet certificate object and stores it to database
     *
     * @param SilverbulletUser $user
     * @return SilverbulletCertificate
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
     * @return SilverbulletUser
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
        $silverbulletMaxUsers = $this->profile->getAttributes("internal:silverbullet_maxusers");
        $count = array();
        $count[self::STATS_TOTAL] = isset($silverbulletMaxUsers[0]['value']) ? $silverbulletMaxUsers[0]['value'] : -1;
        $count[self::STATS_ACTIVE] = 0;
        $count[self::STATS_PASSIVE] = 0;
        foreach ($this->users as $user) {
            if($user->hasActiveCertificates()){
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
