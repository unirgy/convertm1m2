<?php

trait ConvertM1M2_Collect
{
    #use ConvertM1M2_Common;

    protected function _collectCoreModulesConfigs()
    {
        $this->log("[INFO] COLLECTING M1 CONFIGURATION...")->log('');
        $configFiles = glob($this->_env['mage1_dir'] . '/app/code/*/*/*/etc/config.xml');
        foreach ($configFiles as $file) {
            $xml = simplexml_load_file($file, 'SimpleDOM');
            foreach (['models', 'helpers', 'blocks'] as $type) {
                if (!empty($xml->global->{$type})) {
                    foreach ($xml->global->{$type}->children() as $key => $node) {
                        if (!empty($node->class)) {
                            $this->_aliases[$type][$key] = (string)$node->class;
                            if ('models' === $type) {
                                $this->_aliases['modules'][$key] = str_replace('_Model', '', $node->class);
                            }
                        }
                    }
                }
            }
        }
        //var_dump($this->_aliases);
        return $this;
    }

    protected function _collectCoreModulesLayouts()
    {
        $this->log("[INFO] COLLECTING M1 LAYOUTS...")->log('');
        $layoutFiles = glob($this->_env['mage1_dir'] . '/app/design/*/*/*/layout/*.xml');
        foreach ($layoutFiles as $file) {
            preg_match('#/app/design/([^/]+)/([^/]+/[^/]+)#', $file, $m);
            $xml    = simplexml_load_file($file, 'SimpleDOM');
            $blocks = $xml->xpath('//block');
            foreach ($blocks as $blockNode) {
                if ($blockNode['type'] && $blockNode['name']) {
                    $className = $this->_getClassName('blocks', (string)$blockNode['type'], false);
                    $this->_layouts[$m[1]]['blocks'][(string)$blockNode['name']] = $className;
                }
            }
        }
    }

}
