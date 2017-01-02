<?php
namespace lib\storage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SessionStorage implements StorageInterface{
    
    const INDEX = 'silverbullet-storage';
    const GLOBAL = 'global';
    const SCOPED = 'scoped';
    
    /**
     * 
     * @var SessionStorage
     */
    private static $instance = null;

    /**
     * 
     * @var SessionStorage[]
     */
    private static $pool = array();

    /**
     * 
     * @var string
     */
    private $package = "";
    
    /**
     * 
     * @var array
     */
    private $session = null;
    
    
    /**
     * 
     * @param string $package
     */
    private function __construct($package = ""){
        if(!isset($_SESSION)){
            session_start();
        }
        if(!isset($_SESSION['silverbullet-storage'])){
            $_SESSION[self::INDEX] = array();
            $_SESSION[self::INDEX][self::GLOBAL] = array();
            $_SESSION[self::INDEX][self::SCOPED] = array();
        }
        if (!empty($package)){
            if(!isset($_SESSION[self::INDEX][self::SCOPED][$package])){
                $_SESSION[self::INDEX][self::SCOPED][$package] = array();
            }
            $this->session = &$_SESSION[self::INDEX][self::SCOPED][$package];
        }else{
            $this->session = &$_SESSION[self::INDEX][self::GLOBAL];
        }
        $this->package = $package;
    }
    
    /**
     * 
     * @param string $package
     * @return SessionStorage
     */
    public static function getInstance($package = ""){
        
        if(empty($package)){
            if(empty(self::$instance)){
                self::$instance = new self($package);
            }
            return self::$instance;
        }else{
            if(!isset(self::$pool[$package])){
                self::$pool[$package] = new self($package);
            } 
            return self::$pool[$package];
        }
    }
    
    public static function close(){
        session_unset();
        session_destroy();
    }

    /**
     *
     * {@inheritDoc}
     * @see \lib\domain\storage\StorageInterface::put()
     */
    public function put($identifier, $object){
        $this->session[$identifier] = $object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\storage\StorageInterface::add()
     */
    public function add($identifier, $object){
        $this->session[$identifier][] = $object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\storage\StorageInterface::get()
     */
    public function get($identifier){
        if(isset($this->session[$identifier])){
            return $this->session[$identifier];
        }else{
            return array();
        }
    }
    
    /**
     * 
     */
    public function getAll(){
        return $this->session;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\domain\storage\StorageInterface::delete()
     */
    public function delete($identifier){
        unset($this->session[$identifier]);
    }
    
}
