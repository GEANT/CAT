<?php
namespace web\lib\admin\domain;

use web\lib\admin\view\html\UnaryTag;
use core\ProfileSilverbullet;

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
    const DEVICE = 'device';
    
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
    
    /**
     * 
     * @var string
     */
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
            return _('Device:').$this->get(self::DEVICE).'<br> '
                  ._('Serial Number:').dechex($this->getSerialNumber()).'<br> '
                  ._('CN:').$this->getCommonName().'<br> '
                  ._('Expiry:').$this->getExpiry();
        }else{
            return $this->getOneTimeTokenLink();
        }
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
     * @return boolean
     */
    public function isRevoked(){
        return $this->get(self::REVOCATION_STATUS) == self::REVOKED;
    }
    
    /**
     * Revokes invitation or revokes certificate and its actual instance 
     * 
     * @param ProfileSilverbullet $profile
     */
    public function revoke($profile){
        if($this->isGenerated()){
            $profile->revokeCertificate($this->getSerialNumber());
        }else{
            $this->setRevoked(true);
            $this->save();
        }
    }
    
    /**
     * 
     * @param int $certificateId
     * @return \web\lib\admin\domain\SilverbulletCertificate
     */
    public static function prepare($certificateId){
        $instance = new SilverbulletCertificate(null);
        $instance->set(self::ID, $certificateId);
        return $instance;
    }
    
    /**
     * 
     * @param SilverbulletUser $silverbulletUser
     * @param Attribute $searchAttribute
     * @return \web\lib\admin\domain\SilverbulletCertificate[]
     */
    public static function getList($silverbulletUser, $searchAttribute = null){
        $databaseHandle = \core\DBConnection::handle(self::TYPE_INST);
        $userId = $silverbulletUser->getAttribute(self::ID);
        if($searchAttribute != null){
            $query = sprintf("SELECT * FROM `%s` WHERE `%s`=? AND `%s`=? ORDER BY `%s`, `%s` DESC", self::TABLE, self::SILVERBULLETUSERID, self::REVOCATION_STATUS, self::EXPIRY, $searchAttribute->key);
            $result = $databaseHandle->exec($query, $userId->getType().$searchAttribute->getType(), $userId->value, $searchAttribute->value);
        }else{
            $query = sprintf("SELECT * FROM `%s` WHERE `%s`=? ORDER BY `%s`, `%s` DESC", self::TABLE, self::SILVERBULLETUSERID, self::REVOCATION_STATUS, self::EXPIRY);
            $result = $databaseHandle->exec($query, $userId->getType(), $userId->value);
        }
        
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $certificate = new SilverbulletCertificate(null);
            $certificate->setRow($row);
            $list[] = $certificate;
        }
        return $list;
    }
    
}
