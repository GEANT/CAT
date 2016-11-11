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
        $result = false;
        if(count($this->row) > 0){
            if(isset($this->row[self::ID])){
                $result = $this->executeUpdateQuery();
            }else{
                $result = $this->executeInsertQuery();
            }
        }
        return $result;
    }
    
    /**
     * 
     * @return boolean|mixed
     */
    private function executeInsertQuery(){
        $result = false;
        $query = "INSERT INTO `".$this->table."`";
        $keyString = "(";
        $valueString = "(";
        $types = '';
        $arguments = array();
        foreach ($this->row as $key => $value) {
            if($keyString != "(") $keyString .= " ,";
            if($valueString != "(") $valueString .= " ,";
            $keyString .= "`" . $key . "`";
            $valueString .= "?";
            $types .= 's';
            $arguments [] = $value;
        }
        $keyString .= ")";
        $valueString .= ")";
        if($keyString != "()"){
            $query .= " " .$keyString . " VALUES " . $valueString;
            $result = $this->databaseHandle->exec($query, $types, ...$arguments);
            if($result){
                $this->set(self::ID, $this->databaseHandle->lastID());
            }
        }
        return $result;
    }
    
    /**
     * 
     * @return boolean|mixed
     */
    private function executeUpdateQuery(){
        $result = false;
        $query = "UPDATE `".$this->table."`";
        $updateString = "";
        $types = '';
        $arguments = array();
        foreach ($this->row as $key => $value) {
            if(!empty($updateString)) $updateString .= " ,";
            else $updateString .=" SET ";
            $updateString .= "`" . $key . "`=?";
            $types .= 's';
            $arguments [] = $value;
        }
        if(!empty($updateString)){
            $query .= " " .$updateString . " WHERE `" .self::ID. "`=?";
            $types .= 's';
            $arguments [] = $this->get(self::ID);
            $result = $this->databaseHandle->exec($query, $types, ...$arguments);
        }
        return $result;
    }
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\Persistent::load()
     */
    public function load(){
        $state = false;
        $id = $this->get(self::ID);
        $result = $this->databaseHandle->exec("SELECT * FROM `".$this->table."` WHERE `".self::ID."` =?", 's', $id);
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
        $id = $this->get(self::ID);
        return $this->databaseHandle->exec("DELETE FROM `" . $this->table . "` WHERE `".self::ID."`=?", 's', $id);
    }
    
}
