<?php
namespace web\lib\admin\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletUser extends PersistentEntity{
    
    const LEVEL_GREEN = 0;
    const LEVEL_YELLOW = 1;
    const LEVEL_RED = 2;
    const MAX_ACKNOWLEDGE = 365;
    
    
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
     * List of invitations for user entity
     *
     * @var SilverbulletInvitation[]
     */
    private $invitations = array();
    
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
     * {@inheritDoc}
     * @see \web\lib\admin\domain\PersistentInterface::validate()
     */
    public function validate(){
        return (
                !empty($this->get(self::PROFILEID))
        );
    }
    
    /**
     * Accepts any string date value representation and prepares it to be stored.
     * 
     * @param string $date Takes any string value that can passed to strtotime() function.
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
     * Calculates last acknowledge warning level.
     * 
     * @return int One of the following constants LEVEL_GREEN, LEVEL_YELLOW, LEVEL_RED.
     */
    public function getAcknowledgeLevel(){
        $max = CONFIG_CONFASSISTANT['SILVERBULLET']['gracetime'] ?? SilverbulletUser::MAX_ACKNOWLEDGE;
        $days = $this->getAcknowledgeDays();
        if($days <= $max * 0.2 && $days > $max * 0.1){
            return self::LEVEL_YELLOW;
        }elseif ($days <= $max * 0.1){
            return self::LEVEL_RED;
        }else{
            return self::LEVEL_GREEN;
        }
    }
    
    /**
     * Retrieves number of days left until user needs to be acknowledged.
     * 
     * @return number Number of days from 0 to maximum period.
     */
    public function getAcknowledgeDays(){
        $max = CONFIG_CONFASSISTANT['SILVERBULLET']['gracetime'] ?? SilverbulletUser::MAX_ACKNOWLEDGE;
        $lastAcknowledge = strtotime($this->get(self::LAST_ACKNOWLEDGE));
        $now = strtotime('now');
        $days = $max - ceil(($now - $lastAcknowledge) / (24 * 3600));
        return $days > 0 ? $days : 0;
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
     * @return \web\lib\admin\domain\SilverbulletCertificate
     */
    public function getCertificates(){
        return $this->certificates;
    }

    /**
     *
     * @return \web\lib\admin\domain\SilverbulletInvitation
     */
    public function getInvitations(){
        return $this->invitations;
    }
    
    /**
     * 
     * @return boolean
     */
    public function hasCertificates(){
        return count($this->certificates) > 0;
    }
    
    /**
     *
     * @return boolean
     */
    public function hasActiveCertificates(){
        $count = 0;
        foreach ($this->certificates as $certificate) {
            if(!$certificate->isExpired() && !$certificate->isRevoked()){
                $count++;
            }
        }
        return $count > 0;
    }
    
    
    /**
     * 
     * @param boolean $isDeactivated
     */
    public function setDeactivated($isDeactivated, $profile){
        $this->set(self::DEACTIVATION_STATUS, $isDeactivated ? self::INACTIVE : self::ACTIVE);
        if($isDeactivated){
            $this->set(self::DEACTIVATION_TIME, date('Y-m-d H:i:s', strtotime("now")));
            foreach ($this->certificates as $certificate) {
                $certificate->revoke($profile);
            }
        }else{
            $this->set(self::DEACTIVATION_TIME, '0000-00-00 00:00:00');
        }
    }
    
    /**
     * 
     * @return boolean
     */
    public function isDeactivated(){
        return $this->get(self::DEACTIVATION_STATUS) == self::INACTIVE;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\domain\PersistentInterface::load()
     */
    public function load($searchAttribute = null){
        $state = parent::load();
        $this->certificates = SilverbulletCertificate::getList($this, $searchAttribute);
        $this->invitations = SilverbulletInvitation::getList($this);
        return $state;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\domain\PersistentInterface::delete()
     */
    public function delete(){
        $state = parent::delete();
        foreach ($this->certificates as $certificate) {
            $certificate->delete();
        }
        foreach ($this->invitations as $invitation) {
            $invitation->delete();
        }
        return $state;
    }
    
    /**
     * 
     * @param integer $userId
     * @return \web\lib\admin\domain\SilverbulletUser
     */
    public static function prepare($userId){
        $instance = new SilverbulletUser(null, '');
        $instance->set(self::ID, $userId);
        return $instance;
    }
    
    /**
     * 
     * @return \web\lib\admin\domain\SilverbulletUser []
     */
    public static function getList($profileId) {
        $databaseHandle = \core\DBConnection::handle(self::TYPE_INST);
        $query = sprintf("SELECT * FROM `%s` WHERE `%s`=?", self::TABLE, self::PROFILEID);
        $result = $databaseHandle->exec($query, 'i', $profileId);
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $user = new SilverbulletUser(null, '');
            $user->setRow($row);
            $user->certificates = SilverbulletCertificate::getList($user);
            $user->invitations = SilverbulletInvitation::getList($user);
            $list[] = $user;
        }
        return $list;
    }
    
}
