<?php
namespace web\lib\admin\http;

/**
 * Controllers should implement decisions performed responding to user actions.
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractController {

    const COMMAND = 'command';

    /**
     * 
     * @var AbstractCommand[]
     */
    protected $commands = null;
    
    /**
     * Retrieves existing command from object pool based on string command token or creates a new one by usig factory method.
     *
     * @param string $commandToken
     * @return AbstractCommand
     */
    public function createCommand($commandToken){
        if(!isset($this->commands[$commandToken]) || $this->commands[$commandToken] == null){
            $this->commands[$commandToken] = $this->doCreateCommand($commandToken);
        }
        return $this->commands[$commandToken];
    }
    
    /**
     * Factory method creates command object based on strign command token
     *
     * @param string $commandToken
     * @return AbstractCommand
     */
    protected abstract function doCreateCommand($commandToken);
    
    /**
     * Finds and executes required command based on request data.
     */
    public abstract function parseRequest();
    
}
