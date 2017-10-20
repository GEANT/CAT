<?php
namespace web\lib\admin\view;

/**
 * Provides an overlay HTML template for popup message boxes.
 * 
 * @author Zilvinas Vaira
 *
 */
class PopupMessageContainer implements PageElementInterface{
    
    protected $id = '';
    
    protected $title = '';
    
    protected $closeButtonClass = '';
    
    protected $disabledStyle = '';
    
    
    /**
     * 
     * @var PageElementInterface
     */
    private $pageElement;
    
    /**
     * Any page element can be placed inside popup message container. Element id and title arguments are mandatory.
     * 
     * @param PageElementInterface $pageElement
     * @param string $id
     * @param string $title
     * @param boolean $isVisible
     */
    public function __construct($pageElement, $id, $title, $isVisible = true) {
        $this->pageElement = $pageElement;
        $this->id = $id;
        $this->title = $title;
        if(!$isVisible){
            $this->disabledStyle = 'style="display:none;"';
        }
        $this->setCloseButtonClass('close');
    }
    
    /**
     * Can be used to change class for close button. Default value is "someidvalue-close" generated using following template: <id>-<token>.
     * 
     * @param string $token
     */
    public function setCloseButtonClass($token){
        $this->closeButtonClass = $this->id . '-' . $token;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render() {
        ?>
        <div id="<?php echo $this->id; ?>" <?php echo $this->disabledStyle; ?>>
            <div id="overlay"></div>
            <div id="msgbox">
                <div style="top: 100px;">
                    <div class="graybox">
                        <img class="<?php echo $this->closeButtonClass; ?>" src="../resources/images/icons/button_cancel.png" alt="cancel">
                        <h1><?php echo $this->title; ?></h1>
                        <div class="containerbox" style="position: relative;">
                            <?php $this->pageElement->render(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
