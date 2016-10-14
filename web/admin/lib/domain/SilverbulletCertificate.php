<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletCertificate extends PersistentEntity{
    
    const TABLE = 'silverbullet_certificate';
    
    /**
     * Required institution identifier
     * 
     * @var string
     */
    const INSTID = 'inst_id';
    
    /**
     * Required user identifier
     * 
     * @var string
     */
    const SILVERBULLETUSERID = 'silverbullet_user_id';
    
    /**
     * 
     * @var string
     */
    const ONETIMETOKEN = 'one_time_token';
    
    /**
     * 
     * @var string
     */
    const TOKENEXPIRY = 'token_expiry';
    
    /**
     * 
     * @var string
     */
    const EXPIRY = 'expiry';
    
    /**
     * 
     * @var string
     */
    const DOCUMENT = 'document';
    
    /**
     * 
     * @param $silverbulletUser SilverbulletUser
     */
    public function __construct($silverbulletUser) {
        parent::__construct(self::TABLE, self::TYPE_INST);
        if(!empty($silverbulletUser)){
            $this->set(self::INSTID, $silverbulletUser->getInstitutionId());
            $this->set(self::SILVERBULLETUSERID, $silverbulletUser->getIdentifier());
            $this->set(self::ONETIMETOKEN, '92jd998d02u3d0dj02j3d2');
            //$this->set(self::TOKENEXPIRY, 'NOW() + INTERVAL 1 WEEK');
            $this->set(self::TOKENEXPIRY, date('Y-m-d H:i:s',strtotime("+1 week")));
        }
    }
    
    /**
     *
     * @return string
     */
    public function getOneTimeToken(){
        return $this->get(self::ONETIMETOKEN);
    }
    
    /**
     *
     * @return string
     */
    public function getTokenExpiry(){
        return $this->get(self::TOKENEXPIRY);
    }
    
    
    /**
     * 
     * @return string
     */
    public function getExpiry(){
        return $this->get(self::EXPIRY);
    }
    
    protected function validate(){
        //TODO Implement type handling for SilverbulletCertificate
    }
    
    /**
     * 
     * @param int $certificateId
     * @return \lib\domain\SilverbulletCertificate
     */
    public static function prepare($certificateId){
        $instance = new SilverbulletCertificate(null);
        $instance->set(self::ID, $certificateId);
        return $instance;
    }
    
    /**
     * 
     * @param SilverbulletUser $silverbulletUser
     * @return \lib\domain\SilverbulletCertificate[]
     */
    public static function list($silverbulletUser){
        $databaseHandle = \DBConnection::handle(self::TYPE_INST);
        $result = $databaseHandle->exec("SELECT * FROM `" . self::TABLE . "` WHERE `" . self::SILVERBULLETUSERID . "`='" . $silverbulletUser->getIdentifier() . "'");
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $certificate = new SilverbulletCertificate(null);
            $certificate->row = $row;
            $list[] = $certificate;
        }
        return $list;
    }
    
}