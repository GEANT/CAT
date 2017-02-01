<?php
namespace lib\domain;

use lib\view\html\UnaryTag;

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
    const ONETIMETOKEN = 'one_time_token';
    
    
    /**
     *
     * @var string
     */
    const EXPIRY = 'expiry';
    
    /**
     *
     * @var string
     */
    const REVOCATION_STATUS = 'revocation_status';

    /**
     *
     * @var string
     */
    const REVOCATION_TIME = 'revocation_time';
    
    /**
     * 
     * @var string
     */
    const NOT_REVOKED = 'NOT_REVOKED';
    
    /**
     * 
     * @var string
     */
    const REVOKED = 'REVOKED';
    
    private $defaultTokenExpiry;
    
    /**
     * 
     * @param $silverbulletUser SilverbulletUser
     */
    public function __construct($silverbulletUser) {
        parent::__construct(self::TABLE, self::TYPE_INST);
        $this->setAttributeType(self::PROFILEID, Attribute::TYPE_INTEGER);
        $this->setAttributeType(self::SILVERBULLETUSERID, Attribute::TYPE_INTEGER);
        if(!empty($silverbulletUser)){
            $this->set(self::PROFILEID, $silverbulletUser->getProfileId());
            $this->set(self::SILVERBULLETUSERID, $silverbulletUser->getIdentifier());
            $this->set(self::ONETIMETOKEN, $this->generateToken());
            $this->defaultTokenExpiry = date('Y-m-d H:i:s',strtotime("+1 week"));
            $this->set(self::EXPIRY, $this->defaultTokenExpiry);
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
    public function getOneTimeToken(){
        if($this->isExpired()){
            return _('User did not consume the token and it expired!');
        }else{
            return $this->get(self::ONETIMETOKEN);
        }
    }
    
    public function getOneTimeTokenLink($host = ''){
        $link = '';
        if(empty($host)){
            if (isset($_SERVER['HTTPS'])) {
                $link = 'https://' . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
            } else {
                $link = 'http://' . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
            }
        }else{
            $link = $host;
        }
        if($this->isExpired()){
            $link = _('User did not consume the token and it expired!');
        }else{
            $link .= '/accountstatus.php?token='.$this->get(self::ONETIMETOKEN);
            $input = new UnaryTag('input');
            $input->addAttribute('type', 'text');
            $input->addAttribute('readonly','readonly');
            $input->addAttribute('value', $link);
            $input->addAttribute('size', strlen($link)+3);
            $input->addAttribute('style', 'color: grey;');
            $input->addAttribute('name', 'certificate-link[]');
            
            $link = $input->__toString();
        }
        return $link;
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
    public function getExpiry(){
        if(empty($this->get(self::EXPIRY))){
            return "n/a";
        }else{
            return date('Y-m-d', strtotime($this->get(self::EXPIRY)));
        }
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
    
    
    public function getCertificateDetails(){
        if($this->isGenerated()){
            return _('Serial Number:').$this->getSerialNumber().' '._('CN:').$this->getCommonName();
        }else{
            return $this->getOneTimeTokenLink();
        }
    }
    
    protected function validate(){
        //TODO Implement type handling for SilverbulletCertificate
    }
    
    /**
     * 
     * @return boolean
     */
    public function isGenerated(){
        return !empty($this->get(self::SERIALNUMBER))&&!empty($this->get(self::CN));
    }
    
    /**
     *
     * @param boolean $isRevoked
     */
    public function setRevoked($isRevoked){
        $this->set(self::REVOCATION_STATUS, $isRevoked ? self::REVOKED : self::NOT_REVOKED);
        $this->set(self::REVOCATION_TIME, date('Y-m-d H:i:s', strtotime("now")));
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
    public static function getList($silverbulletUser){
        $databaseHandle = \core\DBConnection::handle(self::TYPE_INST);
        $userId = $silverbulletUser->getAttribute(self::ID);
        $revocationStatus = new Attribute(self::REVOCATION_STATUS, self::NOT_REVOKED);
        $query = sprintf("SELECT * FROM `%s` WHERE `%s`=? AND `%s`=?", self::TABLE, self::SILVERBULLETUSERID, self::REVOCATION_STATUS);
        $result = $databaseHandle->exec($query, $userId->getType() . $revocationStatus->getType(), $userId->value, $revocationStatus->value);
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $certificate = new SilverbulletCertificate(null);
            $certificate->setRow($row);
            $list[] = $certificate;
        }
        return $list;
    }
    
}
