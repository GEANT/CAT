<?php
namespace web\lib\admin\view;
use core\IdP;
use web\lib\admin\PageDecoration;
use web\lib\admin\view\html\Tag;
use web\lib\common\InputValidation;

/**
 * Class that can be used to layout any administrator page that manages things for particular IdP.
 * 
 * @author Zilvinas Vaira
 * @package web\lib\admin\view
 */
class InstitutionPageBuilder {
    
   /**
    * Particular IdP instance. If set to null means that page is entered by a mistake.
    * 
    * @var \core\IdP
    */
    protected $institution = null;
    
    /**
     * 
     * @var DefaultHtmlPage
     */
    protected $page = null;
    
    /**
     * Complete header title text.
     * 
     * @var string
     */
    private $headerTitle = "Unknown Page";
    
    /**
     * Provides a set of global page elements such as prelude, header and footer.
     * 
     * @var PageDecoration
     */
    private $decoration;
    
    /**
     * Provides global validation services.
     * 
     * @var InputValidation
     */
    private $validation;
    
    /**
     * Initiates basic building blocks for a page and validates Idp.
     * 
     * @param DefaultHtmlPage $page
     */
    public function __construct($page){
        $this->page = $page;
        $this->decoration = new PageDecoration();
        $this->validation = new InputValidation();
        if(isset($_GET['inst_id'])){
            try {
                $this->validateInstitution();
            } catch (\Exception $e) {
                $this->headerTitle = $e->getMessage();
            }
            
            if($this->isReady()){
                $pageTitle = sprintf(_("%s: %s '%s'"), CONFIG['APPEARANCE']['productname'], $page->getTitle(), $this->institution->name);
                $this->page->setTitle($pageTitle);
                $this->headerTitle = sprintf(_("%s information for '%s'"), $page->getTitle(), $this->institution->name);
            }
        }
    }
    
    /**
     * 
     * @return \web\lib\admin\view\DefaultHtmlPage
     */
    public function getPage(){
        return $this->page;
    }
    
    /**
     * Validates and retrieves institution.
     */
    protected function validateInstitution(){
        $this->institution = $this->validation->IdP($_GET['inst_id'], $_SESSION['user']);
    }
    
    /**
     * Returns true if institution is setup.
     * 
     * @return boolean
     */
    public function isReady(){
        return isset($this->institution);
    }
    
    /**
     * Retrieves institution istance.
     * 
     * @return IdP
     */
    public function getInstitution(){
        return $this->institution;
    }
    
    /**
     * Retrieves silverbullet profile instance.
     * 
     * @return \core\ProfileSilverbullet|mixed
     */
    public function getProfile(){
        $profile = null;
        if($this->isReady()){
            $profiles = $this->institution->listProfiles();
            if (count($profiles) == 1) {
                if ($profiles[0] instanceof \core\ProfileSilverbullet) {
                    $profile = $profiles[0];
                }
            }
        }
        return $profile;
    }
    
    /**
     * Retrieves realm name.
     * 
     * @return string
     */
    public function getRealmName(){
        $realmName = 'unknown';
        $profile = $this->getProfile();
        if(!empty($profile)){
            $realmName = $profile->realm;
        }
        return $realmName;
    }

    /**
     * Adds content element to page.
     * 
     * @param PageElementInterface $element
     */
    public function addContentElement($element){
        $this->page->appendContent($element);
    }
    
    /**
     * Builds page beginning elements. 
     * 
     */
    public function buildPagePrelude(){
        $pagePrelude = new PageElementAdapter();
        $pagePrelude->addText($this->decoration->defaultPagePrelude($this->page->getTitle()));
        $this->page->appendPrelude($pagePrelude);
    }
    
    /**
     * Builds page content header elements.
     */
    public function buildPageHeader(){
        $productHeader = new PageElementAdapter();
        $productHeader->addText($this->decoration->productheader($this->page->getType()));
        $this->page->appendContent($productHeader) ;
    
        $pageHeading = new Tag('h1');
        $pageHeading->addText($this->headerTitle);
        $this->page->appendContent(new PageElementAdapter($pageHeading));
    }
    
    /**
     * Builds page content footer elements.
     */
    public function buildPageFooter(){
        $pageFooter = new PageElementAdapter();
        $pageFooter->addText( $this->decoration->footer());
        $this->page->appendContent($pageFooter);
    }
    
}
