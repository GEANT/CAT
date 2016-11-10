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
    const ONETIMETOKEN = 'one_time_token';
    
    /**
     *
     * @var string
     */
    const TOKENEXPIRY = 'token_expiry';
    
    private $defaultTokenExpiry;
    
    /**
     * List of certificates for user entity
     * 
     * @var SilverbulletCertificate []
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
        $this->set(self::PROFILEID, $profileId);
        $this->set(self::USERNAME, $username);
        $this->set(self::ONETIMETOKEN, $this->generateToken());
        //$this->set(self::TOKENEXPIRY, 'NOW() + INTERVAL 1 WEEK');
        $this->defaultTokenExpiry = date('Y-m-d H:i:s',strtotime("+1 week"));
        $this->set(self::TOKENEXPIRY, $this->defaultTokenExpiry);
    }
    
    public function setTokenExpiry($year, $month, $day){
        $tokenExpiry = date('Y-m-d H:i:s', strtotime($year."-".$month."-".$day));
        if($tokenExpiry > $this->defaultTokenExpiry){
            $this->set(self::TOKENEXPIRY, $tokenExpiry);
        }
    }
    
    /**
     *
     * @return string
     */
    private function generateToken(){
        return hash("sha512", base_convert(rand(0, (int) 10e16), 10, 36));
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
    public function isTokenExpired(){
        $tokenExpiryTime = strtotime($this->get(self::TOKENEXPIRY));
        $currentTime = time();
        return $currentTime > $tokenExpiryTime;
    }
    
    /**
     *
     * @return string
     */
    public function getOneTimeToken(){
        if($this->isTokenExpired()){
            return _('User did not consume the token and it expired!');
        }else{
            return $this->get(self::ONETIMETOKEN);
        }
    }
    
    public function getOneTimeTokenLink($host = ''){
        $link = "";
        if(empty($host)){
            if (isset($_SERVER['HTTPS'])) {
                $link = 'https://' . $_SERVER["HTTP_HOST"];
            } else {
                $link = 'http://' . $_SERVER["HTTP_HOST"];
            }
        }else{
            $link = $host;
        }
        if($this->isTokenExpired()){
            $link = _('User did not consume the token and it expired!');
        }else{
            $link .= "/accountstatus.php?token=".$this->get(self::ONETIMETOKEN);
        }
        return $link;
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
    public static function list($profileId) {
        $databaseHandle = \DBConnection::handle(self::TYPE_INST);
        $result = $databaseHandle->exec("SELECT * FROM `" . self::TABLE . "` WHERE `".self::PROFILEID."`='" . $profileId . "'");
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
