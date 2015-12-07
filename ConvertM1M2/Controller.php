<?php

trait ConvertM1M2_Controller
{
    #use ConvertM1M2_Common;
    #use ConvertM1M2_Code;

    protected function _convertAllControllers()
    {
        $dir = $this->_expandSourcePath('@EXT/controllers');
        $files = $this->_findFilesRecursive($dir);
        $targetDir = $this->_expandOutputPath('Controller');
        foreach ($files as $file) {
            $this->_convertController($file, $dir, $targetDir);
        }
    }

    protected function _convertController($file, $sourceDir, $targetDir)
    {
        $targetFile = preg_replace(['#Controller\.php$#', '#/[^/]+admin/#'], ['.php', '/Adminhtml/'],
            "{$targetDir}/{$file}");

        $fileClass = preg_replace(['#/#', '#\.php$#'], ['_', ''], $file);
        $origClass = "{$this->_env['ext_name']}_{$fileClass}";

        $fileClass = preg_replace(['#Controller$#', '#[^_]+admin_#'], ['', 'Adminhtml_'], $fileClass);
        $ctrlClass = "{$this->_env['ext_name']}_Controller_{$fileClass}";

        $contents = $this->_readFile("{$sourceDir}/{$file}");

        if (strpos($file, 'Controller.php') === false) {
            $contents = str_replace($origClass, $ctrlClass, $contents);
            $contents = $this->_convertCodeContents($contents);
            $this->_writeFile($targetFile, $contents, false);
            return;
        }

        $targetFile = preg_replace('#([^/]+)\.php$#', '\1/Abstract\1.php', $targetFile);
        $abstractClass = preg_replace('#([^_]+)$#', '\1_Abstract\1', $ctrlClass);

        #$this->log('CONTROLLER: ' . $origClass);
        $contents = str_replace($origClass, $abstractClass, $contents);
        $contents = $this->_convertCodeContents($contents);
        $contents = $this->_convertCodeParseMethods($contents, 'controller');

        $this->_writeFile($targetFile, $contents);

        $nl = $this->_currentFile['nl'];
        foreach ($this->_currentFile['methods'] as $method) {
            if (!preg_match('#^(.*)Action$#', $method['name'], $m)) {
                continue;
            }
            $method['name'] = $m[1];
            if ('new' === $method['name']) {
                $method['name'] = 'newAction';
            }
            $actionName = ucwords($method['name']);
            $actionClass = "{$ctrlClass}_{$actionName}";
            $methodContents = join($nl, $method['lines']);
            $txt = preg_replace('#(public\s+function\s+)([a-zA-Z0-9_]+)(\()#', '$1execute$3', $methodContents);
            $classContents = "<?php{$nl}{$nl}class {$actionClass} extends {$abstractClass}{$nl}{{$nl}{$txt}{$nl}}{$nl}";

            $classContents = $this->_convertCodeContents($classContents);

            $actionFile = str_replace([$this->_env['ext_name'] . '_', '_'], ['', '/'], $actionClass) . '.php';
            $targetActionFile = "{$this->_env['ext_output_dir']}/{$actionFile}";

            $this->_writeFile($targetActionFile, $classContents, false);
        }
    }
}