<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface TabbedElementInterface extends PageElementInterface{
    
    /**
     * @return boolean
     */
    public function isActive();

}
