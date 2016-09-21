<?php
/**
 * Class that can be used to layout any administrator page that manages things for particular IdP.
 * 
 * @author Zilvinas Vaira
 *
 */
class IdpPageBuilder implements PageBuilder{
    
   /**
    * Particular IdP instance. If set to null means that page is entered by a mistake.
    * 
    * @var IdP
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
     * Initiates contents of a page.
     * 
     * @param string $titleSlug Common title slug that identifies main feature of the page.
     * @param string $pageType Page type identifier.
     */
    public function initiate($titleSlug, $pageType){
        if(isset($_GET['inst_id'])){
            $this->institution = valid_IdP($_GET['inst_id'], $_SESSION['user']);
            $this->pageType = $pageType;
            $this->pageTitle = sprintf(_("%s: %s '%s'"), CONFIG['APPEARANCE']['productname'], $titleSlug, $this->institution->name);
            $this->headerTitle = sprintf(_("%s information for '%s'"), $titleSlug, $this->institution->name);
        }
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
     * @see PageBuilder::printPageHeader()
     */
    public function printPageHeader(){
        productheader($this->pageType, CAT::get_lang());
        ?>
        <h1>
            <?php echo $this->headerTitle; ?>
        </h1>
        <?php
    }
    
    /**
     * {@inheritDoc}
     * @see PageBuilder::printPageFooter()
     */
    public function printPageFooter(){
        footer();
    }

}