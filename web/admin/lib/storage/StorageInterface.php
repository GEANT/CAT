<?php
namespace lib\domain\storage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface StorageInterface {
    
    public function add($identifier, $object);
    
    public function get($identifier);
    
    public function delete($identifier);
}
