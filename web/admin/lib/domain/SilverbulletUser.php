<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletUser extends PersistentEntity{
    
    const LEVEL_GREEN = 0;
    const LEVEL_YELLOW = 1;
    const LEVEL_RED = 2;
    
    const TABLE = 'silverbullet_user';
    
    /**
     * Required profile identifier
     * 
     * @var string
     */
    const PROFILEID = 'profile_id';
    
    /**
     * Required user name attribute
     * 
     * @var string
     */
    const USERNAME = 'username';
    
    /**
     *
     * @var string
     */
    const EXPIRY = 'expiry';
    
    /**
     * 
     * @var string
     */
    const LAST_ACKNOWLEDGE = 'last_ack';
    
    /**
     *
     * @var string
     */
    const DEACTIVATION_STATUS = 'deactivation_status';
    
    /**
     *
     * @var string
     */
    const DEACTIVATION_TIME = 'deactivation_time';
    
    /**
     *
     * @var string
     */
    const INACTIVE = 'INACTIVE';
    
    /**
     *
     * @var string
     */
    const ACTIVE = 'ACTIVE';
    
    
    private $defaultUserExpiry;
    
    /**
     * List of certificates for user entity
     * 
     * @var SilverbulletCertificate[]
     */
    private $certificates = array();
    
    /**
     * Constructor that should be used when creating a new record. Refer to Silverbullet:: create and Silverbullet::list to load existing records.
     * 
     * @param int $profileId
     * @param string $username
     */
    public function __construct($profileId, $username){
        parent::__construct(self::TABLE, self::TYPE_INST);
        $this->setAttributeType(self::PROFILEID, Attribute::TYPE_INTEGER);
        
        $this->set(self::PROFILEID, $profileId);
        $this->set(self::USERNAME, $username);
        //$this->set(self::EXPIRY, 'NOW() + INTERVAL 1 WEEK');
        $this->defaultUserExpiry = date('Y-m-d H:i:s',strtotime("today"));
        //$this->set(self::EXPIRY, $this->defaultUserExpiry);
    }
    
    /**
     * 
     * @param string $date
     */
    public function setExpiry($date){
        $tokenExpiry = date('Y-m-d H:i:s', strtotime($date));
        if($tokenExpiry > $this->defaultUserExpiry){
            $this->set(self::EXPIRY, $tokenExpiry);
        }else{
            $this->clear();
        }
    }
    
    /**
     * 
     */
    public function makeAcknowledged(){
        $this->set(self::LAST_ACKNOWLEDGE, date('Y-m-d H:i:s',strtotime("now")));
    }
    
    /**
     * 
     * @return int
     */
    public function getAcknowledgeLevel(){
        $lastAcknowledge = strtotime($this->get(self::LAST_ACKNOWLEDGE));
        $now = strtotime('now');
        if($now - $lastAcknowledge > 47 * 7 * 24 * 3600 && $now - $lastAcknowledge < 50 * 7 * 24 * 3600){
            return self::LEVEL_YELLOW;
        }elseif ($now - $lastAcknowledge >= 50 * 7 * 24 * 3600){
            return self::LEVEL_RED;
        }else{
            return self::LEVEL_GREEN;
        }
    }
    
    public function getProfileId(){
        return $this->get(self::PROFILEID);
    }
    
    public function getUsername(){
        return $this->get(self::USERNAME);
    }
    
    /**
     * 
     * @return boolean
     */
    public function isExpired(){
        $expiryTime = strtotime($this->get(self::EXPIRY));
        $currentTime = time();
        return $currentTime > $expiryTime;
    }
    
    /**
     *
     * @return string
     */
    public function getExpiry(){
        return date('Y-m-d', strtotime($this->get(self::EXPIRY)));
    }
    
    /**
     * 
     * @return \lib\domain\SilverbulletCertificate
     */
    public function getCertificates(){
        return $this->certificates;
    }
    
    /**
     * 
     * @return boolean
     */
    public function hasCertificates(){
        return count($this->certificates) > 0;
    }
    
    protected function validate(){
        //TODO Implement type handling for SilverbulletUser
    }
    
    /**
     * 
     * @param boolean $isDeactivated
     */
    public function setDeactivated($isDeactivated, $profile){
        $this->set(self::DEACTIVATION_STATUS, $isDeactivated ? self::INACTIVE : self::ACTIVE);
        $this->set(self::DEACTIVATION_TIME, date('Y-m-d H:i:s', strtotime("now")));
        foreach ($this->certificates as $certificate) {
            $certificate->setRevoked(true);
            $certificate->save();
            if($isDeactivated && $certificate->isGenerated()){
                $profile->revokeCertificate($certificate->getSerialNumber());
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\PersistentInterface::load()
     */
    public function load(){
        $state = parent::load();
        $this->certificates = SilverbulletCertificate::getList($this);
        return $state;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\PersistentInterface::delete()
     */
    public function delete(){
        $state = parent::delete();
        foreach ($this->certificates as $certificate) {
            $certificate->delete();
        }
        return $state;
    }
    
    /**
     * 
     * @param integer $userId
     * @return \lib\domain\SilverbulletUser
     */
    public static function prepare($userId){
        $instance = new SilverbulletUser(null, '');
        $instance->set(self::ID, $userId);
        return $instance;
    }
    
    /**
     * 
     * @return \lib\domain\SilverbulletUser []
     */
    public static function getList($profileId) {
        $databaseHandle = \core\DBConnection::handle(self::TYPE_INST);
        $deactivationStatus = new Attribute(self::DEACTIVATION_STATUS, self::ACTIVE);
        $result = $databaseHandle->exec("SELECT * FROM `" . self::TABLE . "` WHERE `".self::PROFILEID."`=? AND `".self::DEACTIVATION_STATUS."`=?", 'i'.$deactivationStatus->getType(), $profileId, $deactivationStatus->value);
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $user = new SilverbulletUser(null, '');
            $user->setRow($row);
            $user->certificates = SilverbulletCertificate::getList($user);
            $list[] = $user;
        }
        return $list;
    }
    
}
