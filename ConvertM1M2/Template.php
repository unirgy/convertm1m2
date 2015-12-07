<?php

trait ConvertM1M2_Template
{
    #use ConvertM1M2_Common;
    #use ConvertM1M2_Code;

    protected function _convertAllTemplates()
    {
        $this->_convertTemplatesAreaTheme('adminhtml', 'default/default');
        $this->_convertTemplatesAreaTheme('frontend', 'default/default');
        $this->_convertTemplatesAreaTheme('frontend', 'base/default');
        $this->_convertTemplatesEmails();
    }

    protected function _convertTemplatesAreaTheme($area, $theme)
    {
        $dir = "{$this->_env['ext_root_dir']}/app/design/{$area}/{$theme}/template";
        $files = $this->_findFilesRecursive($dir);
        $outputDir = $this->_expandOutputPath("view/{$area}/templates");
        foreach ($files as $filename) {
            $contents = $this->_readFile("{$dir}/{$filename}");
            $contents = $this->_convertCodeContents($contents, 'phtml');
            $this->_writeFile("{$outputDir}/{$filename}", $contents);
        }
    }

    protected function _convertTemplatesEmails()
    {
        $area = 'frontend'; //TODO: any way to know from M1?
        $dir = "{$this->_env['ext_root_dir']}/app/locale/en_US/template/email";
        $outputDir = $this->_expandOutputPath("view/{$area}/email");
        #$this->_copyRecursive($dir, $outputDir);
        $files = $this->_findFilesRecursive($dir);
        foreach ($files as $filename) {
            $this->_copyFile("{$dir}/{$filename}", "{$outputDir}/{$filename}");
            #$contents = $this->_readFile("{$dir}/{$filename}");
            #$this->_writeFile("{$outputDir}/{$filename}", $contents);
        }
    }
}