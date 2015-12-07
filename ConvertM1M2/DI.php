<?php

trait ConvertM1M2_DI
{
    #use ConvertM1M2_Common;
    #use ConvertM1M2_Code;

    protected function _convertAllPhpFilesDI()
    {
        #spl_autoload_unregister([$this, 'autoloadCallback']);
        $this->_autoloadMode = 'm2';
        $files = $this->_findFilesRecursive($this->_env['ext_output_dir']);
        sort($files);
        foreach ($files as $file) {
            if ($file === 'registration.php' || 'php' !== pathinfo($file, PATHINFO_EXTENSION)) {
                continue;
            }
            $fullFilename = "{$this->_env['ext_output_dir']}/{$file}";
            $contents = file_get_contents($fullFilename);
            $class = str_replace('_', '\\', $this->_env['ext_name']) . '\\' . str_replace(['/', '.php'], ['\\', ''], $file);
            $this->_currentFile = [
                'filename' => $fullFilename,
                'class' => $class,
                'nl' => preg_match('#(\\r\\n|\\r|\\n)#', $contents, $m) ? $m[0] : "\r\n",
            ];
            $contents = $this->_convertCodeObjectManagerToDI($contents);
            $contents = $this->_convertNamespaceUse($contents);
            $this->_writeFile($fullFilename, $contents);
        }
        $this->_autoloadMode = 'm1';
        #spl_autoload_register([$this, 'autoloadCallback']);
    }

    protected function _convertCodeObjectManagerToDI($contents)
    {
        $objMgrRe = preg_quote($this->_objMgr, '#');
        if (!preg_match_all("#{$objMgrRe}\(['\"]([\\\\A-Za-z0-9]+?)['\"]\)#", $contents, $matches, PREG_SET_ORDER)) {
            return $contents;
        }
        $propertyLines = [];
        $constructLines = [];
        $declared = [];
        $pad = '    ';

        $parentArgs = $this->_convertDIGetParentConstructArgs($contents);
        $constructArgs = $parentArgs['args'];
        $constructParentArgs = $parentArgs['parent_args'];
        $optionalArgs = $parentArgs['optional'];
        $hasParent = $parentArgs['has_parent'];
        $parentClasses = $parentArgs['classes'];
        //*/
        //var_dump($constructArgs, $constructParentArgs);
#echo '<hr>' . $this->_currentFile['class'].'<br>'; var_dump($declared);
        foreach ($matches as $m) {
            $class = '\\' . ltrim($m[1], '\\');
            if (!empty($declared[$class])) {
                continue;
            }
            $declared[$class] = 1;
            $cArr = array_reverse(explode('\\', $class));
            $var = (!empty($cArr[2]) ? $cArr[2] : '') . $cArr[1] . $cArr[0];
            $var[0] = strtolower($var[0]);

            if (empty($parentClasses[$class])) {
                $propertyLines[] = "{$pad}/**";
                $propertyLines[] = "{$pad} * @var {$class}";
                $propertyLines[] = "{$pad} */";
                $propertyLines[] = "{$pad}protected \$_{$var};";
                $propertyLines[] = "";

                $constructArgs[] = "{$class} \${$var}" . ($optionalArgs ? ' = null' : '');

                $constructLines[] = "{$pad}{$pad}\$this->_{$var} = \${$var};";
            }

            //$constructParentArgs[] = $var;

            $contents = str_replace($m[0], "\$this->_{$var}", $contents);
        }
//echo '<hr>' . $this->_currentFile['class'].'<br>'; var_dump($constructArgs, $constructParentArgs);

        $nl = $this->_currentFile['nl'];
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+[A-Za-z0-9_]+\s+[^{]*\{#m';
        $classStartWith = "\$0{$nl}" . join($nl, $propertyLines);
        $argsStr = join(", {$nl}{$pad}{$pad}", $constructArgs);
        $assignStr = join($nl, $constructLines);
        $constructParentArgsStr = join(', ', $constructParentArgs);
        if (preg_match('#^(\s*public\s+function\s+__construct\()(.*?)(\)\s+\{)#ms', $contents, $m)) {
            $comma = !empty($m[2]) ? ', ' : '';
            $contents = str_replace($m[0], "{$m[1]}{$m[2]}{$comma}{$argsStr}{$m[3]}{$nl}{$assignStr}{$nl}", $contents);
            $contents = preg_replace_callback('#(parent::__construct\()\s*(.)#', function($m) use ($constructParentArgsStr) {
                return $m[1] . $constructParentArgsStr . ($m[2] !== ')' ? ', ' : '') . $m[2];
            }, $contents);
        } else {
            $classStartWith .= "{$nl}{$pad}public function __construct({$argsStr}){$nl}{$pad}{{$nl}{$assignStr}{$nl}";
            if ($hasParent) {
                $classStartWith .= "{$nl}{$pad}{$pad}parent::__construct({$constructParentArgsStr});";
            }
            $classStartWith .= "{$nl}{$pad}}{$nl}";
        }
        $contents = preg_replace($classStartRe, $classStartWith, $contents);

        return $contents;
    }

    protected function _convertDIGetParentConstructArgs($contents)
    {
        static $cache = [];

        $result = [
            'args' => [],
            'parent_args' => [],
            'classes' => [],
            'optional' => false,
            'has_parent' => false,
        ];

        $parentResult = $this->_convertFindParentConstruct($contents);
        if (!$parentResult) {
            return $result;
        }
        $parentClass = $parentResult['parent_class'];
        $parentConstructClass = $parentResult['construct_class'];
        if (!empty($cache[$parentClass])) {
            return $cache[$parentClass];
        }
        $parentContents = $parentResult['contents'];

        $parentMethods = $this->_convertCodeParseMethods($parentContents, false, true);
        $parentConstruct = null;
        foreach ($parentMethods as $method) {
            if ($method['name'] === '__construct') {
                $parentConstruct = $method;
                break;
            }
        }
        if (!$parentConstruct) {
            return $result;
        }
        $result['has_parent'] = true;
        $mode = 1;
        $argLines = [];
        foreach ($parentConstruct['lines'] as $i => $line) {
            if ($mode === 1 && preg_match('#function\s+__construct\s*\(\s*(.*)$#', $line, $m)) {
                $argLines[] = $m[1];
                $mode = 2;
                continue;
            }
            if ($mode === 2) {
                $argLines[] = $line;
                if (preg_match('#\{\s*$#', $line)) {
                    break;
                }
            }
        }
        $argsStr = preg_replace('#\)\s*\{#', '', join(' ', $argLines));
        if (!preg_match_all('#([\\\\A-Za-z0-9]+)\s+(\$[A-Za-z0-9_]+)([^,]*)#m', $argsStr, $matches, PREG_SET_ORDER)) {
            return $result;
        }
        foreach ($matches as $m) {
            $argClass = $this->_convertGetFullClassName($parentContents, $parentConstructClass, $m[1]);
            $result['classes'][$argClass] = 1;
            $result['args'][] = rtrim($argClass . ' ' . $m[2] . $m[3]);
            $result['parent_args'][] = $m[2];
        }
        $result['optional'] = strpos($argsStr, '=') !== false;
        $cache[$parentClass] = $cache[$parentConstructClass] = $result;
        return $result;
    }

    protected function _convertFindParentConstruct($contents, $first = true)
    {
        static $cache = [];
        static $autoloaded = false;

        if (!preg_match('#^\s*namespace\s+(.*);$#m', $contents, $m)) {
            return false;
        }
        $parentNamespace = $m[1];

        if (!preg_match('#^\s*((abstract|final)\s+)?class\s+([^\s]+)\s+extends\s+([^\s]+)#m', $contents, $m)) {
            return false;
        }
        $parentClass = $this->_convertGetFullClassName($contents, $parentNamespace . '\\' . $m[3], $m[4]);

        if (!empty($cache[$parentClass])) {
            return $cache[$parentClass];
        }

        $parentFile = str_replace('\\', '/', $parentClass) . '.php';
        if (preg_match('#^\\\\Magento\\\\Framework\\\\#', $parentClass)) {
            $parentPath = $this->_env['mage2_dir'] . '/lib/internal' . $parentFile;
            if (!file_exists($parentPath)) {
                $parentFile1 = preg_replace('#^Magento/Framework/#', '', $parentFile);
                $parentPath = $this->_env['mage2_dir'] . '/vendor/magento/framework' . $parentFile1;
            }
        } else {
            $parentPath = $this->_env['mage2_code_dir'] . $parentFile;
        }
        if (!file_exists($parentPath)) {
            if (!$autoloaded) {
                $autoloadFile = realpath($this->_env['mage2_dir'] . '/vendor/autoload.php');
                if (file_exists($autoloadFile)) {
                    include_once($autoloadFile);
                    $autoloaded = true;
                }
            }
            if ($autoloaded) {

                try {
                    $refl = new ReflectionClass($parentClass);
                } catch (Exception $e) {
                    $this->log("[WARN] Reflection Exception: " . $e->getMessage());
                    $refl = false;
                }
                $parentPath = $refl ? $refl->getFileName() : false;
            }
        }
        if (!$parentPath || !file_exists($parentPath)) {
            $this->log("[WARN] Could not find a parent class file: {$parentPath} ({$parentClass} <- {$this->_currentFile['class']})");
            return false;
        }
        $parentContents = file_get_contents($parentPath);

        if (preg_match('#function\s+__construct\s*\(#', $parentContents)) {
            $result = [
                'construct_class' => $parentClass,
                'contents' => $parentContents,
            ];
        } else {
            $result = $this->_convertFindParentConstruct($parentContents, false);
        }

        if ($result && $first) {
            $result['parent_class'] = $parentClass;
            $cache[$parentClass] = $result;
        }

        return $result;
    }

    /**
     * @param $contents
     * @param $contentsClass
     * @param $shortClass
     * @return string full class with first backslash for consistency
     */
    protected function _convertGetFullClassName($contents, $contentsClass, $shortClass)
    {
        static $useLineRe = '#^\s*use\s+([\\\\A-Za-z0-9]+\\\\([A-Za-z0-9]+))(\s+as\s+([A-Za-z0-9]+))?\s*;$#m';
        static $useCache = [];
        static $nsCache = [];

        if ($shortClass === 'array' || $shortClass[0] === '\\') {
            return $shortClass;
        }

        $contentsClass = ltrim($contentsClass, '\\');

        if (empty($useCache[$contentsClass])) {
            $useCache[$contentsClass] = [];
            if (preg_match_all($useLineRe, $contents, $parentUseMatches, PREG_SET_ORDER)) {
                foreach ($parentUseMatches as $m) {
                    $alias = !empty($m[4]) ? $m[4] : $m[2];
                    $useCache[$contentsClass][$alias] = '\\' . ltrim($m[1], '\\');
                }
            }
        }

        if (!empty($useCache[$contentsClass][$shortClass])) {
            $fullClass = $useCache[$contentsClass][$shortClass];
        } else {
            if (empty($nsCache[$contentsClass])) {
                $parentClassArr = explode('\\', $contentsClass);
                array_pop($parentClassArr);
                $nsCache[$contentsClass] = join('\\', $parentClassArr);
            }
            $fullClass = '\\' . $nsCache[$contentsClass] . '\\' . $shortClass;
        }
        #echo '<hr>Class: ' . $contentsClass . ' Short: ' . $shortClass . ' Full: ' . $fullClass;
        return $fullClass;
    }

    protected function _convertNamespaceUse($contents)
    {
        #return $contents;

        if (!preg_match('#^\s*namespace\s+(.*);$#m', $contents, $m)) {
            return $contents;
        }
        $namespaceLine = $m[0];
        $namespace = '\\' . $m[1];
        if (!preg_match('#^\s*((abstract|final)\s+)?class\s+([^\s]+)#m', $contents, $m)) {
            return $contents;
        }
        $fileAlias = $m[3];
        $fileClass = $namespace . '\\' . $m[3];
        if (!preg_match_all('#[^\\\\A-Za-z0-9]((\\\\([A-Za-z0-9]+))+)(\s*\*/)?#m', $contents, $matches, PREG_SET_ORDER)) {
            return $contents;
        }
        $mapByClass = [];
        $mapByAlias = [$fileAlias => $fileClass];

        $useLines = [];
        foreach ($matches as $m) {
            if (!empty($m[4])) {
                continue;
            }
            $class = $m[1];
            if ($class === $namespace) {
                #continue; // not sure if always will result in correct code
            }
            if (!empty($mapByClass[$class])) {
                continue;
            }
            $parts = explode('\\', $class);
            array_shift($parts);
            $i = sizeof($parts) - 1;
            if ($i < 2) {
                continue;
            }
            $alias = $parts[$i];
            $useAs = false;
            while ($i > 0 && !empty($mapByAlias[$alias]) || preg_match($this->_reservedWordsRe, $alias)) {
                $i--;
                $alias = $parts[$i] . $alias;
                $useAs = true;
            }
            $mapByClass[$class] = $alias;
            $mapByAlias[$alias] = $class;
            array_pop($parts);
            if ('\\' . join('\\', $parts) !== $namespace || $useAs) {
                $useLines[] = 'use ' . $class . ($useAs ? ' as ' . $alias : '') . ";\n";
            }
        }

        $nl = $this->_currentFile['nl'];
        uksort($mapByClass, function($s1, $s2) {
            $l1 = strlen($s1);
            $l2 = strlen($s2);
            return $l1 < $l2 ? 1 : ($l1 > $l2 ? -1 : 0);
        });
        sort($useLines);
        $contents = str_replace(array_keys($mapByClass), array_values($mapByClass), $contents);
        $contents = str_replace($namespaceLine, $namespaceLine . $nl . $nl . join($nl, $useLines), $contents);
        return $contents;
    }
}