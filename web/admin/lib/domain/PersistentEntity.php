<?php
namespace lib\domain;

abstract  class PersistentEntity extends \Entity implements Persistent {
    
    /**
     * Identifier attribute
     * 
     * @var string
     */
    const ID = 'id';
    
    /**
     * Local institution database type
     * 
     * @var string
     */
    const TYPE_INST = 'INST';
    
    /**
     * External user database type
     * 
     * @var string
     */
    const TYPE_USER = 'USER';
    
    /**
     * 
     * @var \DBConnection
     */
    protected $databaseHandle;
    
    /**
     * Database table name
     * 
     * @var string
     */
    protected $table = "";
    
    /**
     * Record row from database
     * 
     * @var array
     */
    protected $row = array();
    
    /**
     * Defines table and creates database handle
     * 
     * @param string $table
     * @param string $databaseType
     */
    public function __construct($table, $databaseType = 'INST'){
        $this->table = $table;
        $this->databaseHandle = \DBConnection::handle($databaseType);
    }
    
    /**
     * @todo Need to implement attribute type handling for database
     */
    protected abstract function validate();
    
    /**
     * Retrieves attribute value from record
     * 
     * @param string $key
     * @return string
     */
    public function get($key){
        return isset($this->row[$key]) ? $this->row[$key] : "";
    }
    
    /**
     * Sets attribute value
     * 
     * @param string $key
     * @param mixed $value
     */
    protected function set($key, $value){
        $this->row[$key] = $value;
    }
    
    /**
     * 
     * @return string
     */
    public function getIdentifier(){
        return $this->get(self::ID);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\Persistent::save()
     */
    public function save(){
        if(count($this->row) > 0){
            $query = "";
            if(isset($this->row[self::ID])){
                $query = $this->updateQuery();
            }else{
                $query = $this->insertQuery();
            }
            if(!empty($query)){
                $result = $this->databaseHandle->exec($query);
            }
            if($result){
                $this->set(self::ID, $this->databaseHandle->lastID());
            }
            return $result;
        }else{
            return false;
        }
    }
    
    /**
     * 
     * @return string
     */
    private function insertQuery(){
        $query = "INSERT INTO `".$this->table."`";
        $keyString = "(";
        $valueString = "(";
        foreach ($this->row as $key => $value) {
            if($keyString != "(") $keyString .= " ,";
            if($valueString != "(") $valueString .= " ,";
            $keyString .= "`" . $key . "`";
            $valueString .= "'" . $value . "'";
        }
        $keyString .= ")";
        $valueString .= ")";
        if($keyString != "()"){
            $query .= " " .$keyString . " VALUES " . $valueString;
            return $query;
        }else{
            return "";
        }
    }
    
    /**
     * 
     * @return string
     */
    private function updateQuery(){
        $query = "UPDATE `".$this->table."`";
        $updateString = "";
        foreach ($this->row as $key => $value) {
            if(!empty($updateString)) $updateString .= " ,";
            $updateString .= "`" . $key . "`='" . $value . "'";
        }
        if(!empty($updateString)){
            $query .= " " .$updateString . " WHERE `" .self::ID. "`='".$this->get(self::ID)."'";
            return $query;
        }else{
            return "";
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\Persistent::load()
     */
    public function load(){
        $state = false;
        $result = $this->databaseHandle->exec("SELECT * FROM `".$this->table."` WHERE `".self::ID."` = '" .$this->get(self::ID). "'");
        if(mysqli_num_rows($result)>0){
            $this->row = mysqli_fetch_assoc($result);
            $state = true;
        }
        return $state;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\Persistent::delete()
     */
    public function delete(){
        return $this->databaseHandle->exec("DELETE FROM `" . $this->table . "` WHERE `".self::ID."`='".$this->get(self::ID)."'");
    }
    
}
