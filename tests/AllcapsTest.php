<?php
require_once 'ConvertM1M2TestCase.php';

class AllCaps extends ConvertM1M2TestCase
{
    public function testAllcaps()
    {
        $object = new TestableConvertM1M2();
        $contents = $object->convertCodeContents('<' . '?' . 'php' . "\n" . 
            'class FOO_Module_TestController{}');
     
        $this->assertContains('Module\\', $contents);   
        $this->assertNotContains('FOO_\\', $contents);   
    }
    
    public function testMethodParsingNoCaps()
    {
        $object = new TestableConvertM1M2();
        $contents = $object->convertCodeContents('<' . '?' . 'php' . "\n" . 
            'class Foo_Module_TestController{ public function indexAction(){}}');
        $contents = $object->convertCodeParseMethods($contents, 'php');
        $currentFile = $object->getCurrentFile();
        $this->assertNotEmpty(count($currentFile['methods']));
    }

    public function testMethodParsingAllCaps()
    {
        $object = new TestableConvertM1M2();
        $contents = $object->convertCodeContents('<' . '?' . 'php' . "\n" . 
            'class FOO_Module_TestController{ public function indexAction(){}}');
        $contents = $object->convertCodeParseMethods($contents, 'php');
        $currentFile = $object->getCurrentFile();     
        $this->assertNotEmpty(count($currentFile['methods']));
    }    
}