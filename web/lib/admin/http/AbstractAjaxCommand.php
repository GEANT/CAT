<?php
namespace web\lib\admin\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractAjaxCommand extends AbstractCommand {
    
    /**
     *
     * @var AjaxController
     */
    protected $controller;
    
    /**
     * 
     * @param string $command
     * @param AjaxController $controller
     */
    public function __construct($command, $controller) {
        parent::__construct($command);
        $this->controller = $controller;
    }
}
