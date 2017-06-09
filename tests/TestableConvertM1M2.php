<?php
/**
 * Overrides constructor to avoid needing directory
 * May not handle all cases -- i.e. things that require
 * some sort of state enabled by the constructor, or by 
 * previous methods being called.  
 */
class TestableConvertM1M2 extends ConvertM1M2
{
    public function __construct()
    {
        $this->_replace = $this->getReplaceMaps();
    }
    
    public function getCurrentFile()
    {
        return $this->_currentFile;
    }
}