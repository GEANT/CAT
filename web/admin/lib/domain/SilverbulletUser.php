<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletUser extends PersistentEntity{
    
    const TABLE = 'silverbullet_user';
    
    /**
     * Required institution identifier
     * 
     * @var string
     */
    const INSTID = 'inst_id';
    /**
     * Required user name attribute
     * 
     * @var string
     */
    const USERNAME = 'username';
    
    /**
     * List of certificates for user entity
     * 
     * @var SilverbulletCertificate []
     */
    private $certificates = array();
    
    /**
     * Constructor that should be used when creating a new record. Refer to Silverbullet:: create and Silverbullet::list to load existing records.
     * 
     * @param int $institutionId
     * @param string $username
     */
    public function __construct($institutionId, $username){
        parent::__construct(self::TABLE, self::TYPE_INST);
        $this->set(self::INSTID, $institutionId);
        $this->set(self::USERNAME, $username);
    }
    
    public function getInstitutionId(){
        return $this->get(self::INSTID);
    }
    
    public function getUsername(){
        return $this->get(self::USERNAME);
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
    public function isActive(){
        return count($this->certificates) > 0;
    }
    
    protected function validate(){
        //TODO Implement type handling for SilverbulletUser
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\Persistent::load()
     */
    public function load(){
        $state = parent::load();
        $this->certificates = SilverbulletCertificate::list($this);
        return $state;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\Persistent::delete()
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
     * @param ins $userId
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
    public static function list($institutionId) {
        $databaseHandle = \DBConnection::handle(self::TYPE_INST);
        $result = $databaseHandle->exec("SELECT * FROM `" . self::TABLE . "` WHERE `".self::INSTID."`='" . $institutionId . "'");
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $user = new SilverbulletUser(null, '');
            $user->row = $row;
            $user->certificates = SilverbulletCertificate::list($user);
            $list[] = $user;
        }
        return $list;
    }
    
}