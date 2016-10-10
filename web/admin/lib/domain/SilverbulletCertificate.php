<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletCertificate extends \EntityWithDBProperties{
    
    private $institutionId;
    
    private $userId;
    
    /**
     * 
     * @var string
     */
    private $expiry;
    
    private $document;
    
    public function __construct($id = null) {
        $this->databaseType = "INST";
        parent::__construct();
        $this->entityIdColumn = "id";
        $this->identifier = $id;
    }
    
    /**
     * 
     * @param int $institutionId
     * @param string $userId
     * @param string $document
     */
    public function setFields($institutionId, $userId, $document = "Some certificate file contents to be stored.."){
        $this->institutionId = $institutionId;
        $this->userId = $userId;
        $this->document = $document;
    }
    
    /**
     * 
     * @return string
     */
    public function getExpiry(){
        return $this->expiry;
    }
    
    /**
     * 
     * @param array $row
     * @return boolean
     */
    private function parseFields($row){
        $state = false;
        if(count($row)>0){
            $this->setFields($row['inst_id'], $row['user_id'], $row['document']);
            $this->expiry = $row['expiry'];
            $state = true;
        }else{
            $this->identifier = null;
        }
        return $state;
    }
    
    /**
     * 
     * @return mixed|boolean|unknown
     */
    public function save(){
        $result = $this->databaseHandle->exec("INSERT INTO `certificate` (`inst_id`,`user_id`, `expiry`, `document`) VALUES ('" . $this->institutionId . "', '" . $this->userId . "', NOW() + INTERVAL 1 YEAR,'" .$this->document. "')");
        if($result){
            $this->identifier = $this->databaseHandle->lastID();
        }
        return $result;
    }
    
    /**
     * 
     * @return boolean
     */
    public function load(){
        $result = $this->databaseHandle->exec("SELECT * FROM `certificate` WHERE `id` = '" .$this->identifier. "'");
        $row = mysqli_fetch_assoc($result);
        return $this->parseFields($row);
    }
    
    /**
     * 
     * @return mixed|boolean|unknown
     */
    public function delete(){
        $result = $this->databaseHandle->exec("DELETE FROM `certificate` WHERE `id` = '" .$this->identifier. "'");
        return $result;
    }
    
    /**
     * 
     * @param string $userId
     * @return \lib\domain\SilverbulletCertificate[]
     */
    public static function list($userId){
        $userHandle = \DBConnection::handle("INST"); // we need something from the USER database for a change
        $result = $userHandle->exec("SELECT * FROM certificate WHERE user_id='" . $userId . "'");
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $certificate = new SilverbulletCertificate($row['id']);
            $certificate->parseFields($row);
            $list[] = $certificate;
        }
        return $list;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see EntityWithDBProperties::updateFreshness()
     */
    public function updateFreshness(){
        //No need to update
    }
}