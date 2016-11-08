<?php
namespace lib\view;
/**
 * Class that can be used to layout any administrator page that manages things for particular IdP.
 * 
 * @author Zilvinas Vaira
 * @package web\admin\lib
 */
class InstitutionPageBuilder implements PageBuilder{
    
   /**
    * Particular IdP instance. If set to null means that page is entered by a mistake.
    * 
    * @var \IdP
    */
    private $institution = null;
    
    /**
     * Complete page title text.
     * 
     * @var string
     */
    private $pageTitle = "Unknown Page";
    /**
     * Complete header title text.
     * 
     * @var string
     */
    private $headerTitle = "Unknown Page";
    
    /**
     * Page type identifier.
     * 
     * @todo Page type behaviour could be handled by particular page object instead.
     * @see PageBuilder for constants with type values.
     * @var string
     */
    private $pageType = "";
    
    /**
     * 
     * @var PageElement []
     */
    private $contentElements = array();
    
    /**
     * 
     * @var integer
     */
    private $contentIndex = 0;
    
    /**
     * Initiates contents of a page.
     * 
     * @param Page $page Common title slug that identifies main feature of the page.
     * @param string $pageType Page type identifier.
     */
    public function __construct($page, $pageType){
        if(isset($_GET['inst_id'])){
            try {
                $this->institution = valid_IdP($_GET['inst_id'], $_SESSION['user']);
            } catch (\Exception $e) {
                $this->headerTitle = $e->getMessage();
            }
            if($this->isReady()){
                $this->pageType = $pageType;
                $this->pageTitle = sprintf(_("%s: %s '%s'"), CONFIG['APPEARANCE']['productname'], $page->getTitle(), $this->institution->name);
                $this->headerTitle = sprintf(_("%s information for '%s'"), $page->getTitle(), $this->institution->name);
            }
        }
    }
    
    /**
     * @return boolean
     */
    public function isReady(){
        return isset($this->institution);
    }
    
    /**
     * 
     * @return IdP
     */
    public function getInstitution(){
        return $this->institution;
    }
    
    /**
     * 
     * @return \ProfileSilverbullet|mixed
     */
    public function getProfile(){
        $profile = null;
        if($this->isReady()){
            $profiles = $this->institution->listProfiles();
            if (count($profiles) == 1) {
                if ($profiles[0] instanceof \ProfileSilverbullet) {
                    $profile = $profiles[0];
                }
            }
        }
        return $profile;
    }
    
    /**
     * 
     * @return \IdP
     */
    public function getRealmName(){
        $realmName = 'unknown';
        $profile = $this->getProfile();
        if(!empty($profile)){
            $realmName = $profile->realm;
        }
        return $realmName;
    }
    
    public function addContentElement($element){
        $this->contentElements [$this->contentIndex] [] = $element;
    }
    
    public function addContentSeparator(){
        $this->contentIndex++; 
    }
    
    /**
     * Factory method that creates CAT instance and prints page beginning elements. 
     * 
     * @return CAT 
     */
    public function createPagePrelude(){
        return defaultPagePrelude($this->pageTitle);
    }
    
    /**
     * {@inheritDoc}
     * @see \lib\view\PageBuilder::renderPageHeader()
     */
    public function renderPageHeader(){
        productheader($this->pageType, \CAT::get_lang());
        ?>
        <h1>
            <?php echo $this->headerTitle; ?>
        </h1>
        <?php
    }
    
    /**
     * {@inheritDoc}
     * @see \lib\view\PageBuilder::renderPageFooter()
     */
    public function renderPageFooter(){
        footer();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\view\PageBuilder::renderContent()
     */
    public function renderPageContent(){
        foreach ($this->contentElements as $inlineElements) {
            foreach ($inlineElements as $element) {
                $element->render();
            }
        }
    }

}
