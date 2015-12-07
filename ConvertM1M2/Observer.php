<?php

trait ConvertM1M2_Observer
{
    #use ConvertM1M2_Code;

    protected function _convertAllObservers()
    {
        //TODO: scan config.xml for observer callbacks?
        $path = $this->_expandSourcePath('@EXT/Model/Observer.php');
        if (file_exists($path)) {
            $targetDir = $this->_expandOutputPath('Observer');
            $this->_convertObserver($path, $targetDir);
        }
    }

    protected function _convertObserver($sourceFile, $targetDir)
    {
        $contents = $this->_readFile($sourceFile);
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+([A-Za-z0-9_]+)(\s+extends\s+([A-Za-z0-9_]+))?#m';
        if (!preg_match($classStartRe, $contents, $m)) {
            $this->log('[WARN] Invalid observer class: ' . $sourceFile);
            return;
        }

        $origClass = $m[3];
        $abstractClass = str_replace('_Model_Observer', '_Observer_AbstractObserver', $origClass);

        $contents = str_replace($origClass, $abstractClass, $contents);

        #$this->log('CONTROLLER: ' . $origClass);
        $contents = $this->_convertCodeContents($contents);
        $contents = $this->_convertCodeParseMethods($contents, 'observer');

        $this->_writeFile($targetDir . '/AbstractObserver.php', $contents);

        $nl = $this->_currentFile['nl'];
        $funcRe = '#(public\s+function\s+)([a-zA-Z0-9_]+)(\([^)]+\))#';
        $funcExecute = '$1execute(\Magento\Framework\Event\Observer \$observer)';
        foreach ($this->_currentFile['methods'] as $method) {
            if (!preg_match('#^[A-Za-z]+_#', $method['name'], $m)) {
                continue;
            }
            $obsName = str_replace(' ', '', ucwords(str_replace('_', ' ', $method['name'])));
            $obsClass = "{$origClass}_{$obsName}";
            $methodContents = join($nl, $method['lines']);
            $txt = preg_replace($funcRe, $funcExecute, $methodContents);
            $classContents = "<?php{$nl}{$nl}class {$obsClass} extends {$abstractClass} implements "
                             ."\\Magento\\Framework\\Event\\ObserverInterface{$nl}{{$nl}{$txt}{$nl}}{$nl}";

            $classContents = $this->_convertCodeContents($classContents);

            $targetObsFile = "{$targetDir}/{$obsName}.php";

            $this->_writeFile($targetObsFile, $classContents, false);
        }
    }

}