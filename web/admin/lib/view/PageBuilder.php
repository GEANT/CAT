<?php
namespace lib\view;
/**
 * Provides means to layout page elements.
 * 
 * @author Zilvinas Vaira
 *
 */
interface PageBuilder {
    
    /**
     * Manage users base page type
     * 
     * @var string
     */
    const ADMIN_IDP_USERS = 'ADMIN-IDP-USERS';
    
    /**
     * Prints page header elements.
     */
    public function renderPageHeader();
    
    /**
     * Prints page footer elements.
     */
    public function renderPageFooter();
    
    /**
     * Prints page content elements.
     */
    public function renderPageContent();
}
