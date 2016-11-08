<?php
namespace lib\view;

use lib\view\html\Table;
use lib\view\html\Row;

class InfoBlockTable implements PageElement{
    
    /**
     * 
     * @var Table
     */
    private $table;
    
    /**
     * 
     * @var TitledBlockDecorator
     */
    private $decorator;
    
    public function __construct($title){
        $this->table = new Table();
        $this->table->addAttribute("cellpadding", 5);
        $this->decorator = new TitledBlockDecorator($this->table, $title,  PageElement::INFOBLOCK_CLASS);
    }
    
    /**
     * @param array $rowArray
     */
    public function addRow($rowArray){
        $row = new Row($rowArray);
        $row->addCellAttribute(0, 'class', Table::TITLED_CELL_CLASS);
        $this->table->addRow($row);
    }
    
    public function render(){
        $this->decorator->render();
    }
}
