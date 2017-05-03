<?php
namespace web\lib\admin\view;

/**
 * Provides common interface for HTML page object.
 * 
 * @author Zilvinas Vaira
 *
 */
interface HtmlPageInterface {
    
    /**
     * Manage users base page type.
     *
     * @var string
     */
    const ADMIN_IDP_USERS = 'ADMIN-IDP-USERS';
    
    /**
     * Sets page type token.
     * 
     * @param string $type 
     */
    public function setType($type);
    
    /**
     * Retrieves page type token.
     * 
     * @return string
     */
    public function getType();
    
    /**
     * Allows to change page title.
     * 
     * @param string $title Page title.
     */
    public function setTitle($title);
    
    /**
     * Every HTML page has title.
     * 
     * @return string
     */
    public function getTitle();

    /**
     * Appends page element to 'prelude' page elements section.
     *
     * @param PageElementInterface $element Any page element.
     */
    public function appendPrelude($element);
    
    /**
     * Retrieves 'prelude' section page elements.
     *
     * @return PageElementInterface
     */
    public function fetchPrelude();
    
    /**
     * Appends page element to 'content' page elements section.
     * 
     * @param PageElementInterface $element Any page element.
     */
    public function appendContent($element);
    
    /**
     * Retrieves 'content' section page elements.
     * 
     * @return PageElementInterface
     */
    public function fetchContent();
    
    /**
     * Appends JavaScript source file element to a 'script' page section.
     * 
     * @param string $url Path to a JavaScript file.
     */
    public function appendScript($url);
    /**
     * Retrieves 'script' section page element.
     * 
     * @return PageElementInterface
     */
    public function fetchScript();
    /**
     * Appends CSS source file element to a 'css' page section.
     * 
     * @param string $url Path to a CSS file.
     */
    public function appendCss($url);
    /**
     * Retrieves 'css' section page element.
     * 
     * @return PageElementInterface
     */
    public function fetchCss();
    /**
     * Appends meta element to a 'meta' page section.
     * 
     * @param string[] $attributes Associative list of attributes for meta element.
     */
    public function appendMeta($attributes);
    /**
     * Retrieves 'meta' section page element.
     *
     * @return PageElementInterface
     */
    public function fetchMeta();
}