<?php
namespace lib\view\html;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class JsButton extends Button{
    
    const REVOKE_CREDENTIAL_ACTION = 'revokeCredential';
    const DELETE_USER_ACTION = 'deleteUser';
    const NEW_CREDENTIAL_ACTION = 'newCredential';
    const NEW_USER_ACTION = 'newUser';
    const SAVE_ACTION = 'save';
    
    private $action = "";
    
    public function __construct($type, $title, $name, $value, $action, $class = ''){
        parent::__construct($type, $title, $name, $value, $class);
        $this->action = $action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\html\RegularButton::composeNameValueString()
     */
    protected function composeNameValueString() {
        return ' onclick="'.$this->action.'(\''.$this->name.'\',\''.$this->value.'\')"';
    }
}