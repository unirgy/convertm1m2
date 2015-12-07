<?php

trait ConvertM1M2_Common
{
    protected $_env = [];

    protected $_fileCache = [];

    protected $_classFileCache = [];

    protected $_aliases = [];

    protected $_layouts = [];

    protected $_schemas = [
        '@XSI' => 'http://www.w3.org/2001/XMLSchema-instance',
        '@Framework/' => '../../../../../lib/internal/Magento/Framework/', //deprecated
        '@Magento/' => '../../../Magento/', //deprecated
    ];

    protected $_objMgr = '\Magento\Framework\App\ObjectManager::getInstance()->get';

    protected $_replace;

    protected $_currentFile;

    protected $_autoloadMode = 'm1';

    protected $_reservedWordsRe = '#^(
(a(bstract|nd|rray|s))|
(c(a(llable|se|tch)|l(ass|one)|on(st|tinue)))|
(d(e(clare|fault)|ie|o))|
(e(cho|lse(if)?|mpty|nd(declare|for(each)?|if|switch|while)|val|x(it|tends)))|
(f(inal|or(each)?|unction))|
(g(lobal|oto))|
(i(f|mplements|n(clude(_once)?|st(anceof|eadof)|terface)|sset))|
(n(amespace|ew))|
(p(r(i(nt|vate)|otected)|ublic))|
(re(quire(_once)?|turn))|
(s(tatic|witch))|
(t(hrow|r(ait|y)))|
(u(nset|se))|
(__halt_compiler|break|list|(x)?or|var|while)
|map|data
)$#ix';

    public function autoloadCallback($class)
    {
        if (!empty($this->_classFileCache[$class])) {
            return;
        }
        $m1Pools = [
            "{$this->_env['mage1_dir']}/lib",
            "{$this->_env['mage1_dir']}/app/code/core",
            "{$this->_env['mage1_dir']}/app/code/community",
            "{$this->_env['mage1_dir']}/app/code/local",
        ];
        $m2Pools = [
            "{$this->_env['mage2_dir']}/lib/internal",
            "{$this->_env['mage2_code_dir']}",
        ];
        switch ($this->_autoloadMode) {
            case 'm1':
                $pools = $m1Pools;
                break;

            case 'm2':
                $pools = $m2Pools;#array_merge($m2Pools, $m1Pools);
                break;

            default:
                $pools = [];
        }
        foreach ($pools as $pool) {
            $classFile = str_replace(['_', '\\'], ['/', '/'], $class);
            $filename  = "{$pool}/{$classFile}.php";
            if (file_exists($filename)) {
                include_once $filename;
                $this->_classFileCache[$class] = $filename;
                return;
            }
        }
    }

    public function log($msg, $continue = false)
    {
        static $htmlColors = [
            'ERROR' => 'red',
            'WARN' => 'orange',
            'INFO' => 'black',
            'DEBUG' => 'gray',
            'SUCCESS' => 'green',
        ];
        if (!is_scalar($msg)) {
            $msg = print_r($msg, 1);
        }
        if (!$continue) {
            echo "\n";
        }
        if (empty($msg)) {
            return $this;
        }
        if ('cli' === PHP_SAPI) {
            echo '[' . date("Y-m-d H:i:s") . ']' . $msg;
        } else {
            preg_match('#\[([A-Z]+)\]#', $msg, $type);
            echo '<span style="color:' . $htmlColors[$type[1]] . '">';
            echo '[' . date("Y-m-d H:i:s") . ']' . $msg;
            echo '</span>';
        }

        return $this;
    }

    protected function _expandSourcePath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_root_dir'] . '/' . $path;
        }
        $target = 'app/code/' . $this->_env['ext_pool'] . '/' . str_replace('_', '/', $this->_env['ext_name']) . '/';
        $path   = str_replace('@EXT/', $target, $path);
        return $path;
    }

    protected function _expandOutputPath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_output_dir'] . '/' . $path;
        }
        return $path;
    }

    protected function _readFile($filename, $expand = false)
    {
        $this->_currentFile = [
            'filename' => $filename,
        ];

        if ($expand) {
            $filename = $this->_expandSourcePath($filename);
        }

        if (isset($this->_fileCache[$filename])) {
            return $this->_fileCache[$filename];
        }

        if (!file_exists($filename)) {
            $this->_fileCache[$filename] = false;
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'xml':
                $content = simplexml_load_file($filename, 'SimpleDOM');
                break;

            default:
                $content = file_get_contents($filename);
        }
        $this->_fileCache[$filename] = $content;
        return $content;
    }

    protected function _writeFile($filename, $content, $expand = false)
    {
        if ($expand) {
            $filename = $this->_expandOutputPath($filename);
        }

        $dir = dirname($filename);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if ($content instanceof SimpleXMLElement) {
            $content->asPrettyXml($filename);
        } else {
            file_put_contents($filename, $content);
        }
    }

    protected function _copyFile($src, $dst, $expand = false)
    {
        if ($expand) {
            $src = $this->_expandSourcePath($src);
            $dst = $this->_expandOutputPath($dst);
        }

        $dir = dirname($dst);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        copy($src, $dst);
    }

    protected function _copyRecursive($src, $dst, $expand = false)
    {
        if ($expand) {
            $src = $this->_expandSourcePath($src);
            if (!file_exists($src)) {
                return;
            }
            $dst = $this->_expandOutputPath($dst);
        }

        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->_copyRecursive($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    protected function _deleteFile($filename, $expand = false)
    {
        if ($expand) {
            $filename = $this->_expandOutputPath($filename);
        }

        if (file_exists($filename)) {
            unlink($filename);
        }
        /*
                $dir = dirname($filename);
                if (file_exists($dir)) {
                    $empty = true;
                    $dirFiles = glob($dir . '/*');
                    foreach ($dirFiles as $file) {
                        if (!preg_match('#(^|/)\.+$#', $file)) {
                            $empty = false;
                            break;
                        }
                    }
                    if ($empty) {
                        unlink($dir);
                    }
                }
        */
    }

    protected function _findFilesRecursive($dir, $expand = false)
    {
        if ($expand) {
            $dir = $this->_expandSourcePath($dir);
        }
        if (!file_exists($dir)) {
            return [];
        }
        $dirIter = new RecursiveDirectoryIterator($dir);
        $iter    = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);
        $files   = [];
        foreach ($iter as $file) {
            if (!is_dir((string)$file)) {
                $files[] = str_replace($dir . '/', '', str_replace('\\', '/', (string)$file));
            }
        }
        return $files;
    }

    protected function _getClassName($type, $moduleClassKey, $m2 = true)
    {
        $mk = explode('/', strtolower($moduleClassKey), 2);
        if (empty($mk[1])) {
            if ($type === 'helpers') {
                $mk[1] = 'data';
            } else {
                return false;
            }
        }
        list($moduleKey, $classKey) = $mk;

        if (empty($this->_aliases[$type][$moduleKey])) {
            $substTypes = ['models' => '_Model', 'blocks' => '_Block', 'helpers' => '_Helper'];
            foreach ($substTypes as $substType => $substPart) {
                if (!empty($this->_aliases[$substType][$moduleKey])) {
                    $this->_aliases[$type][$moduleKey] =
                        str_replace($substPart, $substTypes[$type], $this->_aliases[$substType][$moduleKey]);
                    break;
                }
            }
            if (empty($this->_aliases[$type][$moduleKey])) {
                $this->log('[WARN] Unknown module key: ' . $type . ' :: ' . $moduleKey);
                return 'UNKNOWN\\' . $moduleKey . '\\' . $classKey;
            }
        }

        $classKeyCapped = strtr(ucwords(strtr($classKey, ['_' => ' '])), [' ' => '_']);
        $className = $this->_aliases[$type][$moduleKey] . '_' . $classKeyCapped;

        if ($m2) {
            $classTr      = $this->_replace['classes'];
            $className    = str_replace(array_keys($classTr), array_values($classTr), $className);
            $classRegexTr = $this->_replace['classes_regex'];
            $className    = preg_replace(array_keys($classRegexTr), array_values($classRegexTr), $className);
            $className    = str_replace('_', '\\', $className);
        }

        return $className;
    }

}