<?php
namespace web\lib\admin\view\html;
/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Table extends Tag{
    
    const TITLED_CELL_CLASS = 'sb-titled-cell';
    
    /**
     * 
     * @var array
     */
    private $columns = array();
    /**
     * 
     * @var Row[]
     */
    private $rows = array();
    
    /**
     * 
     * @param array $rows
     */
    public function __construct($rows = array()){
        parent::__construct('table');
        $this->setRows($rows);
    }
    
    /**
     * 
     * @return number
     */
    public function size(){
        return count($this->rows);
    }

    /**
     * 
     * @param int $row
     * @param string $column
     */
    private function createRow($row, $column){
        if(!in_array($column, $this->columns)){
            $this->columns [] = $column;
        }
        if(!isset($this->rows[$row])){
            $this->rows [$row] = new Row();
        }
    }
    
    /**
     * 
     * @param int $row
     * @param string $column
     * @param UnaryTag $element
     */
    public function addToCell($row, $column, $element){
        $this->createRow($row, $column);
        $this->rows[$row]->addToCell($column, $element);
    }
    
    /**
     * 
     * @param int $row
     * @param string $column
     * @return \web\lib\admin\view\html\CompositeTag
     */
    public function getCell($row, $column){
        $this->createRow($row, $column);
        return $this->rows[$row]->getCell($column);
    }
    
    /**
     * 
     * @param array $row
     */
    public function addRowArray($cells){
        $this->addRow(new Row($cells));
    }
    
    /**
     *
     * @param Row $row
     */
    public function addRow($row){
        if(count($row->size())>0){
            $this->rows [] = $row;
            $this->setColumns($row);
        }
    }
    
    /**
     * 
     * @param array $rows
     */
    public function setRows($rows){
        if(count($rows)>0){
            foreach ($rows as $cells) {
                $this->addRowArray($cells);
            }
        }
    }
    
    /**
     * 
     * @param Row $row
     */
    private function setColumns($row){
        $cells = $row->getCells();
        foreach ($cells as $key => $value) {
            if(!in_array($key, $this->columns)){
                $this->columns [] = $key;
            }
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function composeInnerString(){
        $innerString = "";
        foreach ($this->rows as $row) {
            $row->setColumns($this->columns);
            $row->setTab("\t".$this->tab);
            $innerString .= $row;
        }
        return $innerString;
    }
    
}
