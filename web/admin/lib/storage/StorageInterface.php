<?php
namespace lib\storage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface StorageInterface {

    /**
     *
     * @param string $identifier
     * @param object $object
     */
    public function put($identifier, $object);
    
    /**
     * 
     * @param string $identifier
     * @param object $object
     */
    public function add($identifier, $object);
    
    /**
     * 
     * @param string $identifier
     */
    public function get($identifier);
    
    /**
     * 
     * @param string $identifier
     */
    public function delete($identifier);
}
