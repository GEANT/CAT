<?php
namespace lib\domain;

interface Persistent {
    /**
     * Stores attributes into persistent storage
     * 
     * @return mixed|boolean Returns result or FALSE
     */
    public function save();
    /**
     * Loads attributes from persistent storage
     * 
     * @return boolean
     */
    public function load();
    /**
     * Removes attributes from persistent storage
     * 
     * @return boolean
     */
    public function delete();
}
