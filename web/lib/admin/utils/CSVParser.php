<?php
namespace web\lib\admin\utils;

/**
 * Parses comma separated CSV file values
 * 
 * @author Zilvinas Vaira
 *
 */
class CSVParser {
    
    /**
     * Current row index
     * 
     * @var integer
     */
    private $index = 0;
    
    /**
     * Stores read row strings
     * 
     * @var string[]
     */
    protected $rows = array();
    
    /**
     * New line symbol used in a CSV file
     * 
     * @var string
     */
    protected $rowDelimiter;

    /**
     * Symbol that separates cells in a CSV file
     *
     * @var string
     */
    protected $tokenDelimiter;
    
    /**
     * Is file data valid
     * 
     * @var boolean
     */
    private $state = true;
    
    /**
     * Validates data and reads file contents
     * 
     * @param string[] $fileData Data array from $_FILES['yourfile']
     * @param string $rowDelimiter New line symbol
     * @param string $tokenDelimiter New cell symbol
     */
    public function __construct($fileData, $rowDelimiter, $tokenDelimiter) {
        $this->rowDelimiter = $rowDelimiter;
        $this->tokenDelimiter = $tokenDelimiter;
        if($this->validate($fileData)){
            $handle = fopen($fileData['tmp_name'], "r");
            $contents = fread($handle, filesize($fileData['tmp_name']));
            fclose($handle);
            $this->rows = str_getcsv($contents, $this->rowDelimiter);
        }
    }
    
    /**
     * Validates file data
     * 
     * @param array $fileData Data array from $_FILES['yourfile']
     * @return boolean Is file data valid
     */
    protected function validate($fileData){
        if(empty($fileData) || empty($fileData['name']) || empty($fileData['tmp_name']) || $fileData['error']>0){
            $this->state = false;
        }
        
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        if($extension!='csv' && $extension!='CSV'){
            $this->state = false;
        }
        return $this->state;
    }
    
    /**
     * Is file data valid
     * 
     * @return boolean
     */
    public function isValid() {
        return $this->state;
    }
    
    /**
     * Cheks if there are more rows based on currennt index
     * 
     * @return boolean
     */
    public function hasMoreRows(){
        return $this->state && isset($this->rows[$this->index]);
    }
    
    /**
     * Retrieves next row if there is one, otherwise empty array is passed
     * 
     * @return array
     */
    public function nextRow(){
        if($this->state && $this->hasMoreRows()){
            $row = str_getcsv($this->rows[$this->index], $this->tokenDelimiter);
            $this->index++;
            return $row;
        }else{
            return array();
        }
    }
    
}
