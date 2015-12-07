<?php

trait ConvertM1M2_Code
{
    #use ConvertM1M2_Common;

    protected function _convertPhpClasses($folder, $callback = null)
    {
        $dir = $this->_expandSourcePath("@EXT/{$folder}");
        $files = $this->_findFilesRecursive($dir);
        sort($files);
        $targetDir = $this->_expandOutputPath($folder);
        $fromName = array_keys($this->_replace['files_regex']);
        $toName = array_values($this->_replace['files_regex']);
        foreach ($files as $filename) {
            $contents = $this->_readFile("{$dir}/{$filename}");
            $targetFile = "{$targetDir}/{$filename}";
            if ($callback) {
                $params = ['source_file' => $filename, 'target_file' => &$targetFile];
                $contents = call_user_func($callback, $contents, $params);
            } else {
                $contents = $this->_convertCodeContents($contents);
                $targetFile = preg_replace($fromName, $toName, $targetFile);
            }
            $this->_writeFile($targetFile, $contents);
        }
    }

    protected function _convertCodeContents($contents, $mode = 'php')
    {
        $this->_currentFile = [
            'filename' => $this->_currentFile['filename'],
        ];


        if (preg_match('#(\\r\\n|\\r|\\n)#', $contents, $m)) {
            $this->_currentFile['nl'] = $m[0];
        } else {
            $this->_currentFile['nl'] = "\r\n";
        }

        // Replace code snippets
        $codeTr = $this->_replace['code'];
        $contents = str_replace(array_keys($codeTr), array_values($codeTr), $contents);
        $codeTr = $this->_replace['code_regex'];
        $contents = preg_replace(array_keys($codeTr), array_values($codeTr), $contents);

        if ($mode === 'php') {
            $contents = $this->_convertCodeContentsPhpMode($contents);
        }
        if ($mode === 'phtml') {
            $contents = str_replace($this->_objMgr . '(\'Magento\Framework\View\LayoutFactory\')->create()',
                '$block->getLayout()', $contents);
        }

        // convert block name to block class
        $contents = preg_replace_callback('#(->createBlock\([\'"])([^\'"]+)([\'"]\))#', function($m) {
            return $m[1] . $this->_getClassName('blocks', $m[2]) . $m[3];
        }, $contents);

        // Replace getModel|getSingleton|helper calls with ObjectManager::get calls
        $re = '#(Mage::getModel|Mage::getResourceModel|Mage::getSingleton|Mage::helper|\$this->helper)\([\'"]([a-zA-Z0-9/_]+)[\'"]\)#';
        $contents = preg_replace_callback($re, function($m) {
            $classKey = $m[2];
            if (strpos($m[1], 'helper') !== false) {
                $class = $this->_getClassName('helpers', $classKey, false);
            } else {
                if (strpos($m[1], 'getResourceModel') !== false) {
                    list($modKey, $clsKey) = explode('/', $classKey);
                    if (!empty($this->_aliases['models']["{$modKey}_resource"])) {
                        $classKey = "{$modKey}_resource/{$clsKey}";
                    } elseif (!empty($this->_aliases['models']["{$modKey}_mysql4"])) {
                        $classKey = "{$modKey}_mysql4/{$clsKey}";
                    }
                }
                $class = $this->_getClassName('models', $classKey, false);
            }
            $result = $this->_objMgr . "('{$class}')";
            return $result;
        }, $contents);

        // Replace M1 classes with M2 classes
        $classTr = $this->_replace['classes'];
        $contents = str_replace(array_keys($classTr), array_values($classTr), $contents);
        $classRegexTr = $this->_replace['classes_regex'];
        $contents = preg_replace(array_keys($classRegexTr), array_values($classRegexTr), $contents);

        // Convert any left underscored class names to backslashed. If class name is in string value, don't prefix
        $contents = preg_replace_callback('#(.)([A-Z][A-Za-z0-9][a-z0-9]+_[A-Za-z0-9_]+)#', function($m) {
            return $m[1] . ($m[1] !== "'" && $m[1] !== '"' ? '\\' : '') . str_replace('_', '\\', $m[2]);
        }, $contents);

        // add template prefix for files existing in module
        $contents = preg_replace_callback('#(->setTemplate\([\'"])([A-Za-z0-9_/.]+)([\'"]\))#', function($m) {
            $pattern = "{$this->_env['ext_root_dir']}/app/design/*/*/*/template/{$m[2]}";
            $file = glob($pattern);
            if ($file) {
                return "{$m[1]}{$this->_env['ext_name']}::{$m[2]}{$m[3]}";
            } else{
                return $m[0];
            }
        }, $contents);

        // Add namespace to class declarations
        $classPattern = '#^((final|abstract)\s+)?class \\\\([A-Z][\\\\A-Za-z0-9]+)\\\\([A-Za-z0-9]+)((\s+)(extends|implements)\s|\s*$)?#ms';
        #$contents = preg_replace($classPattern, "namespace \$3;\n\n\$1\$2class \$4\$5", $contents);
        if (preg_match($classPattern, $contents, $m)) {
            $this->_currentFile['namespace'] = $m[3];
            $this->_currentFile['class'] = $m[3] . '\\' . $m[4];
            $contents  = str_replace($m[0], "namespace {$m[3]};\n\n{$m[1]}class {$m[4]}{$m[5]}", $contents);
        }

        return $contents;
    }

    protected function _convertCodeContentsPhpMode($contents)
    {
        // Replace $this->_init() in models and resources with class names and table names
        $contents = preg_replace_callback('#(\$this->_init\([\'"])([A-Za-z0-9_/]+)([\'"])#', function ($m) {
            $filename = $this->_currentFile['filename'];
            $cls = explode('/', $m[2]);
            if (!empty($this->_aliases['models']["{$cls[0]}_resource"]) && !empty($cls[1])) {
                $resKey = "{$cls[0]}_resource/{$cls[1]}";
            } elseif (!empty($this->_aliases['models']["{$cls[0]}_mysql4"]) && !empty($cls[1])) {
                $resKey = "{$cls[0]}_mysql4/{$cls[1]}";
            } else {
                $resKey = false;
            }
            if (preg_match('#/Model/(Mysql4|Resource)/.*/Collection\.php$#', $filename)) {
                if ($resKey) {
                    $model    = $this->_getClassName('models', $m[2], true);
                    $resModel = $this->_getClassName('models', $resKey, true);
                    return $m[1] . $model . $m[3] . ', ' . $m[3] . $resModel . $m[3];
                } else {
                    $this->log("[WARN] No resource model for {$m[2]}");
                    return $m[0];
                }
            } elseif (preg_match('#/Model/(Mysql4|Resource)/#', $filename)) {
                return $m[1] . str_replace('/', '_', $m[2]) . $m[3]; //TODO: try to figure out original table name
            } elseif (preg_match('#/Model/#', $filename)) {
                if ($resKey) {
                    $resModel = $this->_getClassName('models', $resKey, true);
                    return $m[1] . $resModel . $m[3];
                } else {
                    $this->log("[WARN] No resource model for {$m[2]}");
                    return $m[0];
                }
            } else {
                return $m[0];
            }
        }, $contents);

        return $contents;
    }

    protected function _convertCodeParseMethods($contents, $fileType = false, $returnResult = false)
    {
        $nl = $this->_currentFile['nl'];

        $lines = preg_split('#\r?\n#', $contents);
        $linesCnt = sizeof($lines);

        // Find start of the class
        $classStart = null;
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+[A-Za-z0-9_]+(\s+(extends|implements)\s+|\s*$)#';
        for ($i = 0; $i < $linesCnt; $i++) {
            if (preg_match($classStartRe, $lines[$i])) {
                $classStart = $i;
                break;
            }
        }
        if (null === $classStart) { // not a class
            if ($returnResult) {
                return [];
            } else {
                $this->_currentFile['methods'] = [];
                $this->_currentFile['lines']   = $lines;
                return $contents;
            }
        }

        // Find starts of all methods
        $methods = [];
        $methodStartRe = '#(public|protected|private)\s+(static\s+)?function\s+([a-zA-Z0-9_]+?)?\(#';
        for ($i = 0; $i < $linesCnt; $i++) {
            if (preg_match($methodStartRe, $lines[$i], $m)) {
                $method = ['name' => $m[3], 'start' => $i, 'code_start' => $i];
                if (preg_match('#\}\s*$#', $lines[$i])) {
                    $method['end'] = $i;
                }
                $methods[] = $method;
            }
        }
        if (!$methods) {
            if ($returnResult) {
                return [];
            } else {
                $this->_currentFile['methods'] = [];
                $this->_currentFile['lines']   = $lines;
                return $contents;
            }
        }

        $lastMethodIdx = sizeof($methods) - 1;

        // Find end of the last method
        $pastEndOfClass = null;
        for ($i = $linesCnt - 1; $i > 0; $i--) {
            if (preg_match('#^\s*\}\s*$#', $lines[$i])) {
                if (!$pastEndOfClass) {
                    $pastEndOfClass = true;
                } else {
                    $methods[$lastMethodIdx]['end'] = $i;
                    break;
                }
            }
        }

        // Find phpdocs and ends of rest of the methods
        for ($i = $lastMethodIdx; $i >= 0; $i--) {
            $method =& $methods[$i];
            if (empty($method['end'])) {
                if (preg_match('#\s+abstract\s+#', $lines[$method['start']])) {
                    $method['end'] = $method['start'];
                } else {
                    for ($j = $methods[$i + 1]['start'] - 1; $j > $method['start']; $j--) {
                        if (preg_match('#^\s*(\{\s*)?\}\s*$#', $lines[$j])) {
                            $method['end'] = $j;
                            break;
                        }
                    }
                }
            }
        }
        for ($i = $lastMethodIdx; $i >= 0; $i--) {
            $method =& $methods[$i];
            for ($j = $method['start'] - 1; $j > ($i ? $methods[$i - 1]['end'] : $classStart); $j--) {
                if (empty($method['phpdoc_end'])) {
                    if (preg_match('#^\s*\*+/\s*$#', $lines[$j])) {
                        $method['phpdoc_end'] = $j;
                    }
                } else {
                    if (preg_match('#^\s*/\*+\s*$#', $lines[$j])) {
                        $method['phpdoc_start'] = $j;
                        $method['start'] = $j;
                        break;
                    }
                }
            }
        }
        unset($method);

        // Find each method contents and remove controller actions from $lines if requested
        for ($i = $lastMethodIdx; $i >= 0; $i--) {
            $method =& $methods[$i];
            $length = $method['end'] - $method['start'] + 1;
            if ($fileType === 'controller' && preg_match('#Action$#', $method['name'])) {
                $method['lines'] = array_splice($lines, $method['start'], $length);
            } elseif ($fileType === 'observer' && preg_match('#^[A-Za-z]+_#', $method['name'])) {
                $method['lines'] = array_splice($lines, $method['start'], $length);
            } else {
                $method['lines'] = array_slice($lines, $method['start'], $length);
            }
        }
        unset($method);

        if ($fileType === 'controller' || $fileType === 'observer') {
            $contents = join($nl, $lines);
            $contents = preg_replace('#^(\s*)(class\s+.*)$#m', '$1abstract $2', $contents);
        }
        if ($returnResult) {
            return $methods;
        }

        $this->_currentFile['methods'] = $methods;
        $this->_currentFile['lines'] = $lines;
        return $contents;
    }

    protected function _convertAllOtherFiles()
    {
        $dir = $this->_expandSourcePath("@EXT/");
        $files = glob("{$dir}*");
        $targetDir = $this->_env['ext_output_dir'];
        foreach ($files as $file) {
            $basename = basename($file);
            if ('etc' === $basename || 'controllers' === $basename || 'sql' === $basename || 'Observer.php' === $basename) {
                continue;
            }
            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $targetFile = "{$targetDir}/{$basename}";
            if (is_dir($file)) {
                if ($basename[0] >= 'A' && $basename[0] <= 'Z') {
                    $this->_convertPhpClasses($basename);
                } else {
                    $this->_copyRecursive($file, $targetFile);
                }
            } else {
                if ('php' === $fileExt) {
                    $contents = $this->_readFile($file);
                    $contents = $this->_convertCodeContents($contents);
                    $this->_writeFile($targetFile, $contents);
                } else {
                    copy($file, $targetFile);
                }
            }
        }
    }
}