<?php
namespace web\lib\admin\view;

/**
 *
 * @author Zilvinas Vaira
 *
 */
class YesNoDialogBox extends AbstractDialogBox{

    const NO = 0;
    const YES = 1;
    
    private $text = '';
    
    private $controls = array(array('name'=>'', 'value'=>''), array('name'=>'confirm', 'value'=>'yes'));

    public function __construct($id, $action, $title, $text) {
        parent::__construct($id, $action, $title);
        $this->text = $text;
    }
    
    public function setYesControl($name, $value){
        $this->controls[self::YES]['name'] = $name;
        $this->controls[self::YES]['value'] = $value;
    }

    public function setNoControl($name, $value){
        $this->controls[self::NO]['name'] = $name;
        $this->controls[self::NO]['value'] = $value;
    }
    
    protected function renderContent(){
        ?><p><?php echo $this->text; ?></p><?php
    }
    
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
