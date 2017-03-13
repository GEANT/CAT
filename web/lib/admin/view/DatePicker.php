<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\HtmlElementInterface;
use web\lib\admin\view\html\UnaryTag;
use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\html\Tag;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class DatePicker implements HtmlElementInterface, PageElementInterface{
    
    const BLOCK_CLASS = 'sb-date-container';
    const INPUT_CLASS = 'sb-date-picker';
    const BUTTON_CLASS = 'sb-date-button';
    
    /**
     * Counts DatePicker objects to generate id's
     * @var int
     */
    private static $COUNT = 0;
    
    /**
     * Input element id
     * 
     * @var string
     */
    private $id = '';
    /**
     * Input element name
     * @var string
     */
    private $name = '';
    /**
     * Input element format template for the date value
     * @var string
     */
    private $format = '';
    
    /**
     * 
     * @param string $name Name of an input element
     * @param string $format Defines a format template for the date value
     */
    public function __construct($name, $format='yyyy-MM-dd'){
        self::$COUNT++;
        $this->id = self::INPUT_CLASS.'-'.self::$COUNT;
        $this->name = $name;
        $this->format = $format;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render(){
        echo $this;
        
        /*?>
        <div class=<?php echo self::BLOCK_CLASS; ?>>
            <input id="<?php echo $this->id; ?>" class="<?php echo self::INPUT_CLASS; ?>" type="date" name="<?php echo $this->name; ?>" value="<?php echo $this->format; ?>" maxlength="10">
            <button class=<?php echo self::BUTTON_CLASS; ?>>▼</button>
        </div>
        <?php
        */
    }
    
    public function __toString(){
        $div = new CompositeTag('div');
        $div->addAttribute('class', self::BLOCK_CLASS);
            $input = new UnaryTag('input');
            $input->addAttribute('type', 'text');
            $input->addAttribute('maxlength', 10);
            $input->addAttribute('id', $this->id);
            $input->addAttribute('class', self::INPUT_CLASS);
            $input->addAttribute('name', $this->name);
            $input->addAttribute('value', $this->format);
        $div->addTag($input);
            $button = new Tag('button');
            $button->addText('▼');
            $button->addAttribute('class', self::BUTTON_CLASS);
            $button->addAttribute('type', 'button');
        $div->addTag($button);
        return $div->__toString();
    }
    
}
