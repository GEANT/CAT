<?php
namespace web\lib\admin\domain;

abstract class PersistentEntity extends \core\common\Entity implements PersistentInterface {
    
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
     * @var \core\DBConnection
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
     * @var Attribute[]
     */
    private $row = array();
    
    /**
     * 
     * @var string[]
     */
    private $types = array();
    
    /**
     * Defines table and creates database handle
     * 
     * @param string $table
     * @param string $databaseType
     */
    public function __construct($table, $databaseType = 'INST'){
        $this->table = $table;
        $this->setAttributeType(self::ID, Attribute::TYPE_INTEGER);
        $this->databaseHandle = \core\DBConnection::handle($databaseType);
    }
    
    /**
     * 
     * @param string $key
     * @param string $type
     */
    protected function setAttributeType($key, $type){
        $this->types[$key] = $type; 
    }
    
    /**
     * 
     * @param string $key
     * @return string
     */
    protected function getAttributeType($key){
        return isset($this->types[$key]) ? $this->types[$key] : Attribute::TYPE_STRING;
    }
    
    /**
     * Retrieves attribute value from record
     * 
     * @param string $key
     * @return string
     */
    public function get($key){
        return isset($this->row[$key]) ? $this->row[$key]->value : "";
    }
    
    /**
     * 
     * @param string $key
     * @return NULL|\web\lib\admin\domain\Attribute
     */
    public function getAttribute($key){
        return isset($this->row[$key]) ? $this->row[$key] : new Attribute('', '');
    }
    
    /**
     * Sets attribute value
     * 
     * @param string $key
     * @param mixed $value
     */
    protected function set($key, $value){
        $attribute = new Attribute($key, $value, $this->getAttributeType($key));
        $this->row[$key] = $attribute;
    }
    
    /**
     * 
     * @param array $row
     */
    public function setRow($row){
        $this->clear();
        foreach ($row as $key => $value){
            $this->set($key, $value);
        }
    }
    
    /**
     * 
     */
    public function clear(){
        $this->row = array();
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
     * @see \web\lib\admin\domain\PersistentInterface::save()
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
        $query = sprintf("INSERT INTO `%s`", $this->table);
        $keyString = "(";
        $valueString = "(";
        $types = '';
        $arguments = array();
        foreach ($this->row as $key => $attribute) {
            if($keyString != "(") $keyString .= " ,";
            if($valueString != "(") $valueString .= " ,";
            $keyString .= "`" . $key . "`";
            $valueString .= "?";
            $types .= $attribute->getType();
            $arguments [] = $attribute->value;
        }
        $keyString .= ")";
        $valueString .= ")";
        $isValid = $this->validate();
        if($isValid && $keyString != "()"){
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
        $query = sprintf("UPDATE `%s`", $this->table);
        $updateString = "";
        $types = '';
        $arguments = array();
        foreach ($this->row as $key => $attribute) {
            if(!empty($updateString)) $updateString .= " ,";
            else $updateString .=" SET ";
            $updateString .= "`" . $key . "`=?";
            $types .= $attribute->getType();
            $arguments [] = $attribute->value;
        }
        if(!empty($updateString)){
            $query .= " " .$updateString . " WHERE `" .self::ID. "`=?";
            $id = $this->getAttribute(self::ID);
            $types .= $id->getType();
            $arguments [] = $id->value;
            $result = $this->databaseHandle->exec($query, $types, ...$arguments);
        }
        return $result;
    }
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\domain\PersistentInterface::load()
     */
    public function load($searchAttribute = null){
        $state = false;
        $id = $this->getAttribute(self::ID);
        $query = sprintf("SELECT * FROM `%s` WHERE `%s` =?", $this->table, self::ID);
        $value = $id->value;
        $result = $this->databaseHandle->exec($query, $id->getType(), $value);
        if(mysqli_num_rows($result)>0){
            $this->setRow(mysqli_fetch_assoc($result));
            $state = true;
        }
        return $state;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\domain\PersistentInterface::delete()
     */
    public function delete(){
        $id = $this->getAttribute(self::ID);
        $query = sprintf("DELETE FROM `%s` WHERE `%s`=?", $this->table, self::ID);
        $type = $id->getType();
        $value = $id->value;
        return $this->databaseHandle->exec($query, $type, $value);
    }
    
}
