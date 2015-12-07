<?php

trait ConvertM1M2_Layout
{
    #use ConvertM1M2_Common;

    protected function _convertAllLayouts()
    {
        $this->_convertLayoutAreaTheme('adminhtml', 'default/default');
        $this->_convertLayoutAreaTheme('frontend', 'default/default');
        $this->_convertLayoutAreaTheme('frontend', 'base/default');
    }

    protected function _convertLayoutAreaTheme($area, $theme)
    {
        $dir = "{$this->_env['ext_root_dir']}/app/design/{$area}/{$theme}/layout";
        $files = $this->_findFilesRecursive($dir);
        $outputDir = $this->_expandOutputPath("view/{$area}/layout");
        foreach ($files as $file) {
            $this->_convertLayoutFile($area, $dir . '/' . $file, $outputDir);
        }
    }

    protected function _convertLayoutFile($area, $file, $outputDir)
    {
        $xml = $this->_readFile($file);

        foreach ($xml->children() as $layoutName => $layoutNode) {
            $resultXml = $this->_createConfigXml('urn:magento:framework:View/Layout/etc/page_configuration.xsd', 'page');
            $headXml = $resultXml->addChild('head');
            $bodyXml = $resultXml->addChild('body');
            foreach ($layoutNode->children() as $nodeTag => $node) {
                switch ($nodeTag) {
                    case 'update':
                        $updateXml = $resultXml->addChild('update');
                        $updateXml->addAttribute('handle', (string)$node['handle']);
                        break;

                    case 'label':
                        $resultXml['label'] = (string)$node;
                        break;

                    case 'remove':
                        $removeXml = $bodyXml->addChild('remove');
                        $removeXml->addAttribute('name', (string)$node['name']);
                        break;

                    case 'reference':
                        if ((string)$node['name'] === 'head') {
                            foreach ($node->children() as $headNode) {
                                $this->_convertLayoutHeadNode($headNode, $headXml);
                            }
                            break;
                        }
                    //nobreak;

                    case 'block':
                        $this->_convertLayoutRecursive($area, $node, $bodyXml);
                        break;


                    default:
                        //...
                }
            }
            if (!$headXml->children()) {
                unset($headXml[0][0]);
            }
            if (!$bodyXml->children()) {
                unset($bodyXml[0][0]);
            }

            $this->_writeFile("{$outputDir}/{$layoutName}.xml", $resultXml);
        }
    }

    protected function _convertLayoutHeadNode(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        foreach ($sourceXml->children() as $child) {
            $path = $this->_env['ext_name'] . '::' . (string)$child;
            break;
        }
        switch ((string)$sourceXml['method']) {
            case 'addJs':
                $targetNode = $targetXml->addChild('js');
                $targetNode->addAttribute('class', $path);
                break;

            case 'addCss':
                $targetNode = $targetXml->addChild('css');
                $targetNode->addAttribute('src', $path);
                break;

            default:
                $targetNode = $targetXml->appendChild($sourceXml->cloneNode(true));
        }
    }

    protected function _convertLayoutRecursive($area, SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        $tagName = $sourceXml->getName();
        switch ($tagName) {
            case 'reference':
                $nodeName = (string)$sourceXml['name'];
                if (!empty($this->_layouts[$area]['blocks'][$nodeName])) {
                    $className = $this->_layouts[$area]['blocks'][$nodeName];
                    if (is_subclass_of($className, 'Mage_Core_Block_Text_List') || $nodeName === 'content') {
                        $targetChildXml = $targetXml->addChild('referenceContainer');
                    } else {
                        $targetChildXml = $targetXml->addChild('reference');
                    }
                } else {
                    $targetChildXml = $targetXml->addChild('reference');
                }
                $targetChildXml->addAttribute('name', $nodeName);
                break;

            case 'block':
                $nodeName = (string)$sourceXml['name'];
                $nodeType = (string)$sourceXml['type'];
                $blockClass = $this->_getClassName('blocks', $nodeType);
                if (is_subclass_of($blockClass, 'Mage_Core_Block_Text_List')) {
                    $targetChildXml = $targetXml->addChild('container');
                    $targetChildXml->addAttribute('label', $nodeName); //TODO: no source info for that...
                } else {
                    $targetChildXml = $targetXml->addChild('block');
                    $targetChildXml->addAttribute('class', $blockClass);
                    if (!empty($sourceXml['template'])) {
                        $template = $this->_env['ext_name'] . '::' . (string)$sourceXml['template'];
                        $targetChildXml->addAttribute('template', $template);
                    }
                    if (!empty($sourceXml['as'])) {
                        $targetChildXml->addAttribute('as', (string)$sourceXml['as']);
                    }
                }
                $targetChildXml->addAttribute('name', $nodeName);
                break;
        }
        if ('reference' === $tagName || 'block' === $tagName) {
            $translate = !empty($sourceXml['translate']) ? array_flip(explode(' ', $sourceXml['translate'])) : [];
            $argumentsXml = null;
            foreach ($sourceXml->children() as $childTag => $childXml) {
                switch ($childTag) {
                    case 'action':
                        $actionXml = $targetChildXml->addChild('action');
                        $actionXml->addAttribute('method', $childXml['method']);
                        if ($childXml->children()) {
                            $argumentsXml = $actionXml->addChild('arguments');
                            foreach ($childXml->children() as $argName => $argNode) {
                                if ($argNode->children()) {
                                    $argXml = $argumentsXml->addChild('argument');
                                    $this->_convertLayoutArgumentRecursive($argNode, $argXml);
                                } else {
                                    $argValue = $this->_getOpportunisticArgValue($argNode);
                                    $argXml   = $argumentsXml->addChild('argument', $argValue);
                                    $argXml->addAttribute('xsi:type', 'string', $this->_schemas['@XSI']);
                                    if (isset($translate[$argName])) {
                                        $argXml->addAttribute('translate', 'true');
                                    }
                                }
                                $argXml->addAttribute('name', $argName);
                            }
                        }
                        break;

                    case 'reference':
                    case 'block':
                        $this->_convertLayoutRecursive($area, $childXml, $targetChildXml);
                        break;
                }
            }
        }
    }

    protected function _convertLayoutArgumentRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        $targetXml->addAttribute('xsi:type', 'array', $this->_schemas['@XSI']);
        foreach ($sourceXml->children() as $childTag => $childNode) {
            if ($childNode->children()) {
                $childXml = $targetXml->addChild('item');
                $this->_convertLayoutArgumentRecursive($childNode, $childXml);
            } else {
                $argValue = $this->_getOpportunisticArgValue($childNode);
                $childXml = $targetXml->addChild('item', $argValue);
                $childXml->addAttribute('xsi:type', 'string', $this->_schemas['@XSI']);
            }
            $childXml->addAttribute('name', $childTag);
        }
    }

    protected function _getOpportunisticArgValue($value)
    {
        $value = (string)$value;

        if (preg_match('#\.phtml$#', $value)) {
            return $this->_env['ext_name'] . '::' . $value;
        }

        if (preg_match('#^[A-Za-z_]+/[A-Za-z0-9_]+$#', $value)) {
            $class = $this->_getClassName('models', $value);
            if ($class) {
                return $class;
            }
            $class = $this->_getClassName('helpers', $value);
            if ($class) {
                return $class;
            }
            $class = $this->_getClassName('blocks', $value);
            if ($class) {
                return $class;
            }
        }

        return $value;
    }
}