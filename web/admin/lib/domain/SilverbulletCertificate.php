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
     * Required profile identifier
     * 
     * @var string
     */
    const PROFILEID = 'profile_id';
    
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
    const SERIALNUMBER = 'serial_number';
    
    /**
     *
     * @var string
     */
    const CN = 'cn';
    
    /**
     *
     * @var string
     */
    const EXPIRY = 'expiry';
    
    /**
     * 
     * @param $silverbulletUser SilverbulletUser
     */
    public function __construct($silverbulletUser) {
        parent::__construct(self::TABLE, self::TYPE_INST);
        if(!empty($silverbulletUser)){
            $this->set(self::PROFILEID, $silverbulletUser->getProfileId());
            $this->set(self::SILVERBULLETUSERID, $silverbulletUser->getIdentifier());
        }
    }
    
    /**
     * 
     * @param string $serialNumber
     * @param string $cn
     * @param string $expiry
     */
    public function setCertificateDetails($serialNumber, $cn, $expiry){
        $this->set(self::SERIALNUMBER, $serialNumber);
        $this->set(self::CN, $cn);
        $expiry = date('Y-m-d H:i:s', strtotime($expiry));
        $this->set(self::EXPIRY, $expiry);
    }

    /**
     *
     * @return string
     */
    public function getSerialNumber(){
        if(empty($this->get(self::SERIALNUMBER))){
            return "n/a";
        }else{
            return $this->get(self::SERIALNUMBER);
        }
    }

    /**
     *
     * @return string
     */
    public function getCommonName(){
        if(empty($this->get(self::CN))){
            return "n/a";
        }else{
            return $this->get(self::CN);
        }
    }
    
    /**
     * 
     * @return string
     */
    public function getExpiry(){
        if(empty($this->get(self::EXPIRY))){
            return "n/a";
        }else{
            return $this->get(self::EXPIRY);
        }
    }
    
    public function getCertificateTitle($count = ''){
        if(empty($this->get(self::SERIALNUMBER))||empty($this->get(self::CN))){
            return 'cert'.$count;
        }else{
            return $this->getCommonName();
        }
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
