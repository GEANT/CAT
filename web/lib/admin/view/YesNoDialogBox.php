<?php
namespace web\lib\admin\view;

/**
 *
 * @author Zilvinas Vaira
 *
 */
class YesNoDialogBox extends AbstractTextDialogBox{

    const NO = 0;
    const YES = 1;
    
    private $id = '';
    
    /**
     * 
     * @var array
     */
    private $controls = array(array('name'=>'', 'value'=>''), array('name'=>'confirm', 'value'=>'yes'));

    /**
     * 
     * @param string $id
     * @param string $action
     * @param string $text
     */
    public function __construct($id, $action, $text){
        parent::__construct($action, $text);
        $this->id = $id;
    }
    
    /**
     * 
     * @param string $name
     * @param string $value
     */
    public function setYesControl($name, $value){
        $this->controls[self::YES]['name'] = $name;
        $this->controls[self::YES]['value'] = $value;
    }

    /**
     * 
     * @param string $name
     * @param string $value
     */
    public function setNoControl($name, $value){
        $this->controls[self::NO]['name'] = $name;
        $this->controls[self::NO]['value'] = $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractDialogBox::renderControls()
     */
    protected function renderControls(){
        ?>
            <button type="submit" name="<?php echo $this->controls[self::YES]['name']; ?>" value="<?php echo $this->controls[self::YES]['value']; ?>"><?php echo _('Yes'); ?></button>
        <?php if($this->controls[self::NO]['name'] == ''){ ?>
            <button class="<?php echo $this->id . '-close'; ?>" type="reset"><?php echo _('No'); ?></button>
        <?php }else{ ?>
            <button type="submit" name="<?php echo $this->controls[self::NO]['name']; ?>" value="<?php echo $this->controls[self::NO]['value']; ?>"><?php echo _('No'); ?></button>
        <?php }
    }
}
