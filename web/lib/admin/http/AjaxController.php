<?php
namespace web\lib\admin\http;

use web\lib\admin\view\DefaultAjaxPage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AjaxController extends AbstractController{
    
    /**
     * 
     * @var DefaultAjaxPage
     */
    private $page = null;
    
    /**
     * 
     * @param DefaultAjaxPage $page
     */
    public function __construct($page){
        $this->page = $page;
    }
    
    /**
     * 
     * @return \web\lib\admin\view\DefaultAjaxPage
     */
    public function getPage(){
        return $this->page;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractController::doCreateCommand()
     */
    protected function doCreateCommand($commandToken) {
        if($commandToken == ValidateEmailAddress::COMMAND){
            return new ValidateEmailAddress($commandToken, $this);
        }elseif($commandToken == SendTokenByEmail::COMMAND) {
            return new SendTokenByEmail($commandToken, $this);
        }else {
            return new DefaultCommand($commandToken);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractController::parseRequest()
     */
    public function parseRequest(){
        $commandToken = '';
        if(isset($_GET[AjaxController::COMMAND])){
            $commandToken = $_GET[AjaxController::COMMAND];
        }
        $this->currentCommand = $this->createCommand($commandToken);
        $this->currentCommand->execute();
    }
    
}