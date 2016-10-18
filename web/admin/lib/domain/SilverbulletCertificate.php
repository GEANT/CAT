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
            $this->set(self::PROFILEID, $silverbulletUser->getProfileId());
            $this->set(self::SILVERBULLETUSERID, $silverbulletUser->getIdentifier());
            $this->set(self::ONETIMETOKEN, $this->generateToken());
            //$this->set(self::TOKENEXPIRY, 'NOW() + INTERVAL 1 WEEK');
            $this->set(self::TOKENEXPIRY, date('Y-m-d H:i:s',strtotime("+1 week")));
        }
    }
    
    /**
     * 
     * @return string
     */
    private function generateToken(){
        return hash("sha512", base_convert(rand(0, (int) 10e16), 10, 36));
    }
    
    /**
     *
     * @return string
     */
    public function getOneTimeToken(){
        $tokenExpiryTime = strtotime($this->get(self::TOKENEXPIRY));
        $currentTime = time();
        if(!empty($this->get(self::TOKENEXPIRY)) && empty($this->get(self::DOCUMENT))){
            if($currentTime > $tokenExpiryTime){
                return _('User did not consume the token and it expired!');
            }else{
                return $this->get(self::ONETIMETOKEN);
            }
        }else{
            return "";
        }
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
    
    public function getCertificateTitle($count = ''){
        if(empty($this->get(self::DOCUMENT))){
            return '';
        }else{
            return 'cert'.$count;
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