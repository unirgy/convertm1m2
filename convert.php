<?php
/**

Copyright (c) 2015 Boris Gurvich

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

 */

spl_autoload_register(function($class) {
    $classFile = __DIR__ . '/' . str_replace(['_', '\\'], ['/', '/'], $class) . '.php';
    if (file_exists($classFile)) {
        include_once $classFile;
    }
});

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

$converter = new ConvertM1M2($sourceDir, $mage1Dir, $mage2Dir);
$converter->convertAllExtensions($stage);
$converter->log('[SUCCESS] ALL DONE (' . (microtime(true) - $time) . ' sec)')->log('');