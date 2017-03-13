<?php

use web\lib\admin\utils\CSVParser;

class MockCSVParser extends CSVParser{
    
    public function __construct($fileData, $rowDelimiter, $tokenDelimiter) {
        $this->rowDelimiter = $rowDelimiter;
        $this->tokenDelimiter = $tokenDelimiter;
        if($this->validate($fileData)){
            $this->rows = str_getcsv($fileData['contents'], $this->rowDelimiter);
        }
    }
    
}

class CSVParserTest extends PHPUnit_Framework_TestCase{
    
    private $rowDelimiter = "\n", $tokenDelimiter = ",";
    
    private $faultyFileData = array(
            'name' => 'import.csv',
            'type' => 'application/vnd.ms-excel',
            'tmp_name' => '',
            'error' => 0,
            'size' => 108
    );
    
    private $wrongFileData = array(
            'name' => 'import.pdf',
            'type' => 'application/pdf',
            'tmp_name' => 'C:\xampp\tmp\phpA37A.tmp',
            'error' => 0,
            'size' => 108
    );
    
    private $emptyFileData = array(
            'name' => 'import.csv',
            'type' => 'application/vnd.ms-excel',
            'tmp_name' => 'C:\xampp\tmp\phpA37A.tmp',
            'error' => 0,
            'size' => 108,
            'contents' => ""
    );
    
    private $successFileData = array(
            'name' => 'import.csv',
            'type' => 'application/vnd.ms-excel',
            'tmp_name' => 'C:\xampp\tmp\phpA37A.tmp',
            'error' => 0,
            'size' => 108,
            'contents' => ""
    );
    
    protected function setUp(){
        $this->successFileData['contents'] = "testuser1,".date('Y-m-d',strtotime("+1 month")).",2
                           testuser2,".date('Y-m-d',strtotime("+2 month")).",
                           testuser3,".date('Y-m-d',strtotime("+1 month")).",1
                           testuser4,".date('Y-m-d',strtotime("+3 month")).",
                           testuser5,".date('Y-m-d',strtotime("+1 month")).",3";
    }
    
    public function testIsValid(){
        $parser = new MockCSVParser($this->faultyFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertFalse($parser->isValid());
        
        $this->faultyFileData['tmp_name'] = 'some_name.csv';
        $this->faultyFileData['error'] = '1';
        $parser = new MockCSVParser($this->faultyFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertFalse($parser->isValid());
        
        $parser = new MockCSVParser($this->wrongFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertFalse($parser->isValid());
        
        $parser = new MockCSVParser($this->successFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertTrue($parser->isValid());
    }
    
    public function testHasMoreRows(){
        $parser = new MockCSVParser($this->faultyFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertFalse($parser->hasMoreRows());

        $parser = new MockCSVParser($this->emptyFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertFalse($parser->hasMoreRows());
        
        $parser = new MockCSVParser($this->successFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertTrue($parser->hasMoreRows());
    }
    
    public function testNextRow(){
        $parser = new MockCSVParser($this->faultyFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertCount(0, $parser->nextRow());
        
        $parser = new MockCSVParser($this->emptyFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertCount(0, $parser->nextRow());
        
        $parser = new MockCSVParser($this->successFileData, $this->rowDelimiter, $this->tokenDelimiter);
        $this->assertCount(3, $parser->nextRow());
    }
}
