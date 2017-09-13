<?php
namespace web\lib\admin\domain;

interface PersistentInterface {
    /**
     * Stores attributes into persistent storage
     * 
     * @return mixed|boolean Returns result or FALSE
     */
    public function save();
    /**
     * Loads attributes from persistent storage
     * @param Attribute $searchAttribute
     * @return boolean
     */
    public function load($searchAttribute = null);
    /**
     * Removes attributes from persistent storage
     * 
     * @return boolean
     */
    public function delete();
    /**
     * Performs validation whether all required fields are defined properly before executing save() method.
     * 
     * @return
     */
    public function validate();
}
