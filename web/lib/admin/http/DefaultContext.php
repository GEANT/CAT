<?php
namespace web\lib\admin\http;

use web\lib\admin\storage\SessionStorage;
use web\lib\admin\view\AbstractPage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultContext {
    
    /**
     *
     * @var AbstractPage
     */
    protected $page = null;
    
    /**
     *
     * @var SessionStorage
     */
    protected $session;
    
    /**
     *
     * @param AbstractPage $page
     */
    public function __construct($page){
        $this->page = $page;
        $this->session = SessionStorage::getInstance('sb-messages');
    }
    
    /**
     *
     * @return AbstractPage
     */
    public function getPage(){
        return $this->page;
    }
    
    /**
     * Provides access to session storage object
     *
     * @return \web\lib\admin\storage\SessionStorage
     */
    public function getSession(){
        return $this->session;
    }
    
}
