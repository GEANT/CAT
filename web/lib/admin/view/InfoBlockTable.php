<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\Table;
use web\lib\admin\view\html\Row;

class InfoBlockTable implements PageElementInterface{
    
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
        $this->decorator = new TitledBlockDecorator($this->table, $title,  PageElementInterface::INFOBLOCK_CLASS);
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
