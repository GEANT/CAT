<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletUser extends \User{
    
    const OPTION_SILVERBULLET_USER = 'user:silverbullet';
    
    /**
     * 
     * @var SilverbulletCertificate []
     */
    private $certificates = array();
    
    public function __construct($userId, $institutionId){
        parent::__construct($userId);
        $this->certificates = SilverbulletCertificate::list($userId);
        if(!$this->hasSillverbulletOption() && !empty($userId)){
            $this->addAttribute(self::OPTION_SILVERBULLET_USER, $institutionId);
        }
    }
    
    /**
     * 
     * @return boolean
     */
    private function hasSillverbulletOption(){
        $found = false;
        foreach ($this->attributes as $attribute) {
            if($attribute['name'] == self::OPTION_SILVERBULLET_USER){
                $found = true;
                break;
            }
        }
        return $found;
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
    
    /**
     * 
     */
    public function delete(){
        //$this->flushAttributes();
        $this->databaseHandle->exec("DELETE FROM `user_options` WHERE `user_id`='".$this->identifier."';");
        foreach ($this->certificates as $certificate) {
            $certificate->delete();
        }
    }
    
    /**
     * 
     * @return SilverbulletUser []
     */
    public static function list($institutionId) {
        $returnarray = array();
        $query = "SELECT user_id FROM user_options WHERE option_name = '" . self::OPTION_SILVERBULLET_USER . "' AND option_value = '" . $institutionId . "'";
        
        //TODO Not sure if this is required for Sillverbullet
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $query = "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = '" . strtolower($this->name) . "'";
        }
        
        $userHandle = \DBConnection::handle("USER"); // we need something from the USER database for a change
        $users = $userHandle->exec($query);
        
        while ($silverbulletUser = mysqli_fetch_object($users)) {
            $returnarray[] = new SilverbulletUser($silverbulletUser->user_id, $institutionId);
        }
        return $returnarray;
    }
    
    
}