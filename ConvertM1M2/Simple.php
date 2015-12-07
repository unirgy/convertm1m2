<?php

trait ConvertM1M2_Simple
{
    #use ConvertM1M2_Common;

    protected function _convertAllI18n()
    {
        $dir = "{$this->_env['ext_root_dir']}/app/locale";
        $files = glob("{$dir}/*/*.csv");
        $outputDir = $this->_expandOutputPath("i18n");
        foreach ($files as $file) {
            if (!preg_match('#([a-z][a-z]_[A-Z][A-Z])[\\\\/].*(\.csv)#', $file, $m)) {
                continue;
            }
            $this->_copyFile($file, "{$outputDir}/{$m[1]}{$m[2]}");
            #$contents = $this->_readFile($file);
            #$this->_writeFile("{$outputDir}/{$m[1]}{$m[2]}", $contents);
        }
    }

    protected function _convertAllWebAssets()
    {
        $this->_copyRecursive('js', 'view/frontend/web/js', true);
        $this->_copyRecursive('media', 'view/frontend/web/media', true);
        $this->_copyRecursive('skin/adminhtml/default/default', 'view/adminhtml/web', true);
        $this->_copyRecursive('skin/frontend/base/default', 'view/frontend/web', true);
    }
}