<?php

class ConvertM1M2TestCase extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $argv = ['--testrun'];
        require_once __DIR__ . '/../ConvertM1M2.php';
    }
}

