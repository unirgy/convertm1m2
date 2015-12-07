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

class ConvertM1M2
{
    use ConvertM1M2_Mapping;
    use ConvertM1M2_Common;
    use ConvertM1M2_Collect;
    use ConvertM1M2_Config;
    use ConvertM1M2_Layout;
    use ConvertM1M2_Code;
    use ConvertM1M2_Controller;
    use ConvertM1M2_Observer;
    use ConvertM1M2_Meta;
    use ConvertM1M2_Simple;
    use ConvertM1M2_Template;
    use ConvertM1M2_DI;

    public function __construct($rootDir, $mage1Dir, $mage2Dir)
    {
        $this->_env['source_dir']     = str_replace('\\', '/', $rootDir);
        $this->_env['mage1_dir']      = str_replace('\\', '/', $mage1Dir);
        $this->_env['mage2_dir']      = str_replace('\\', '/', $mage2Dir);
        $this->_env['mage2_code_dir'] = $this->_env['mage2_dir'] . '/app/code';

        if (!is_dir($this->_env['source_dir'])) {
            $this->log('[ERROR] Invalid modules code source directory: ' . $this->_env['source_dir']);
        }
        if (!is_dir($this->_env['mage1_dir'])) {
            $this->log('[ERROR] Invalid Magento 1.x directory: ' . $this->_env['mage1_dir']);
        }
        if (!is_dir($this->_env['mage2_dir'])) {
            $this->log('[ERROR] Invalid Magento 2.x directory: ' . $this->_env['mage2_dir']);
        }

        spl_autoload_register([$this, 'autoloadCallback']);

        $this->_replace = $this->_getReplaceMaps();
    }

    public function convertAllExtensions($stage)
    {
        if ($stage === 1) {
            $this->_collectCoreModulesConfigs();
            $this->_collectCoreModulesLayouts();
        }

        $this->log('')->log("[INFO] LOOKING FOR ALL EXTENSIONS IN {$this->_env['source_dir']}")->log('');

        $extDirs = glob($this->_env['source_dir'] . '/*', GLOB_ONLYDIR);
        foreach ($extDirs as $extDir) {
            if (!preg_match('#^(.*)/([A-Za-z0-9]+_[A-Za-z0-9]+)$#', $extDir, $m)) {
                continue;
            }
            switch ($stage) {
                case 1:
                    $this->_convertExtensionStage1($m[2], $m[1]);
                    break;

                case 2:
                    $this->_convertExtensionStage2($m[2]);
                    break;

                case 3:
                    #$this->_convertExtensionStage3($m[2]);
                    break;
            }
        }

        return $this;
    }


    protected function _convertExtensionStage1($extName, $rootDir)
    {
        $this->_autoloadMode = 'm1';

        $this->log("[INFO] EXTENSION: {$extName}");

        $this->_env['ext_name'] = $extName;
        $folders = glob($this->_env['mage1_dir'] . '/app/code/*/' . str_replace('_', '/', $extName));
        if ($folders && preg_match('#app/code/(core|community|local)/#', $folders[0], $m)) {
            $this->_env['ext_pool'] = $m[1];
        } else {
            $this->_env['ext_pool'] = 'community';
        }
        $this->_env['ext_root_dir'] = $rootDir . '/' . $extName;
        #$this->_env['ext_output_dir'] = $rootDir . '/output/' . $extName;
        $this->_env['ext_output_dir'] = $this->_env['mage2_code_dir'] . '/' . str_replace('_', '/', $extName);

        $this->_fileCache = [];

        $this->_convertGenerateMetaFiles();
        $this->_convertAllConfigs();
        $this->_convertAllControllers();
        $this->_convertAllObservers();
        $this->_convertAllLayouts();
        $this->_convertAllTemplates();
        $this->_convertAllWebAssets();
        $this->_convertAllI18n();
        $this->_convertAllOtherFiles();
        $this->_convertAllPhpFilesDI();

        $this->log("[SUCCESS] FINISHED: {$extName}")->log('');

        return $this;
    }


    protected function _convertExtensionStage2($extName)
    {
        $this->_autoloadMode = 'm2';

        $this->log("[INFO] EXTENSION (STAGE 2): {$extName}");

        $extDir = str_replace('_', '/', $extName);

        $files = $this->_findFilesRecursive("{$this->_env['mage2_code_dir']}/{$extDir}");
        foreach ($files as $file) {
            if ('php' !== pathinfo($file, PATHINFO_EXTENSION)) {
                continue;
            }
            if (strpos($file, 'registration.php') !== false) {
                continue;
            }
            $class = str_replace(['/', '.php'], ['\\', ''], "{$extDir}/{$file}");
            if (!class_exists($class)) {
                $this->log('[ERROR] Class not found: ' . $class);
            }
        }
    }
}
