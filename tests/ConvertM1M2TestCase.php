<?php

class ConvertM1M2TestCase extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../ConvertM1M2.php';
        require_once __DIR__ . '/TestableConvertM1M2.php';
    }
    
    public function testAvoidNoTestsFound()
    {
        $this->assertTrue(true);
    }
}

