<?php

if (PHP_SAPI === 'cli') {
    $cwd = getcwd();
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    $sourceDir = !empty($_GET['s']) ? $_GET['s'] : "{$cwd}/source";
    $mage1Dir = !empty($_GET['m']) ? $_GET['m'] : "{$cwd}/../magento";
    $mage2Dir = !empty($_GET['o']) ? $_GET['o'] : "{$cwd}/../magento2";
    $stage = !empty($_GET['a']) ? (int)$_GET['a'] : 1;
} else {
    $sourceDir = 'source';
    $mage1Dir = '../magento';
    $mage2Dir = '../magento2';
    $stage = !empty($_GET['a']) ? (int)$_GET['a'] : 1;
    echo "<pre>";
}

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

$time = microtime(true);
include_once __DIR__ . '/SimpleDOM.php';
include_once __DIR__ . '/ConvertM1M2.php';
$converter = new ConvertM1M2($sourceDir, $mage1Dir, $mage2Dir);
$converter->convertAllExtensions($stage);
$converter->log('[SUCCESS] ALL DONE (' . (microtime(true) - $time) . ' sec)')->log('');