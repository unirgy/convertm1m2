<?php

trait ConvertM1M2_Config
{
    #use ConvertM1M2_Common;

    protected function _convertAllConfigs()
    {
        $this->_convertConfigModule();
        $this->_convertConfigDefaults();
        $this->_convertConfigAcl();
        $this->_convertConfigResources();
        $this->_convertConfigDI();
        $this->_convertConfigFrontendDI();
        $this->_convertConfigAdminhtmlDI();
        $this->_convertConfigEvents();
        $this->_convertConfigRoutesFrontend();
        $this->_convertConfigRoutesAdmin();
        $this->_convertConfigMenu();
        $this->_convertConfigSystem();
        $this->_convertConfigCrontab();
        $this->_convertConfigEmailTemplates();
        $this->_convertConfigCatalogAttributes();
        $this->_convertConfigFieldset();
        $this->_convertConfigSales();
        $this->_convertConfigPdf();
        $this->_convertConfigWidget();
    }

    /**
     * @param string $schemaPath
     * @param string $rootTagName
     * @return SimpleDOM
     */
    protected function _createConfigXml($schemaPath, $rootTagName = 'config')
    {
        $schemaPath = str_replace(array_keys($this->_schemas), array_values($this->_schemas), $schemaPath);
        return simpledom_load_string('<?xml version="1.0" encoding="UTF-8"?>
<' . $rootTagName . ' xmlns:xsi="' . $this->_schemas['@XSI'] . '" xsi:noNamespaceSchemaLocation="' . $schemaPath . '">
</' . $rootTagName . '>');
    }

    protected function _convertConfigModule()
    {
        $extName = $this->_env['ext_name'];
        $xml1    = $this->_readFile("@EXT/etc/config.xml", true);
        $xml2    = $this->_readFile("app/etc/modules/{$this->_env['ext_name']}.xml", true);

        $resultXml = $this->_createConfigXml('urn:magento:framework:Module/etc/module.xsd');
        $targetXml = $resultXml->addChild('module');
        $targetXml->addAttribute('name', $extName);
        if (!empty($xml1->modules->{$extName}->version)) {
            $targetXml->addAttribute('setup_version', (string)$xml1->modules->{$extName}->version);
        }
        if (!empty($xml2->modules->{$extName}->depends)) {
            $sequenceXml = $targetXml->addChild('sequence');
            $from        = array_keys($this->_replace['modules']);
            $to          = array_values($this->_replace['modules']);
            foreach ($xml2->modules->{$extName}->depends->children() as $depName => $_) {
                $depName = str_replace($from, $to, $depName);
                $sequenceXml->addChild('module')->addAttribute('name', $depName);
            }
        }

        $this->_writeFile('etc/module.xml', $resultXml, true);
    }

    protected function _convertConfigDefaults()
    {
        $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Store:etc/config.xsd');

        $xml1 = $this->_readFile("@EXT/etc/config.xml", true);
        if (!empty($xml1->default)) {
            $resultXml->appendChild($xml1->default->cloneNode(true));
        }

        $xml2 = $this->_readFile("@EXT/etc/magento2.xml");
        if ($xml2) {
            if (!empty($xml2->convert->custom_config)) {
                foreach ($xml2->convert->custom_config->children() as $metaNode) {
                    if (empty($metaNode['xpath'])) {
                        continue;
                    }
                    $origNode = $xml1->xpath($metaNode['xpath']);
                    if ($origNode) {
                        $resultXml->appendChild($origNode->cloneNode(true));
                    }
                }
            }
        }

        if ($resultXml->children()) {
            $this->_writeFile('etc/config.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/config.xml', true);
        }
    }

    protected function _convertConfigAcl()
    {
        $resultXml = $this->_createConfigXml('urn:magento:framework:Acl/etc/acl.xsd');
        $targetXml = $resultXml->addChild('acl')->addChild('resources');

        $xml1 = $this->_readFile("@EXT/etc/config.xml", true);
        if (!empty($xml1->adminhtml->acl)) {
            $this->_convertConfigAclRecursive($xml1->adminhtml->acl->resources, $targetXml);
        }

        $xml2 = $this->_readFile("@EXT/etc/adminhtml.xml", true);
        if ($xml2 && !empty($xml2->acl)) {
            $this->_convertConfigAclRecursive($xml2->acl->resources, $targetXml);
        }

        if ($targetXml->children()) {
            $this->_writeFile('etc/acl.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/acl.xml', true);
        }
    }

    protected function _convertConfigAclRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml, $path = '')
    {
        foreach ($sourceXml->children() as $key => $sourceNode) {
            $attr = [];
            if (!empty($this->_replace['acl_keys'][$path . $key])) {
                $aclId = $this->_replace['acl_keys'][$path . $key];
                if (is_array($aclId)) {
                    for ($i = 0, $l = sizeof($aclId) - 1; $i < $l; $i++) {
                        $targetXml = $targetXml->addChild('resource');
                        $targetXml->addAttribute('id', $aclId[$i]);
                    }
                    $attr['id'] = $aclId[$i];
                } else {
                    $attr['id'] = $aclId;
                }
            } else {
                $aclIdArr = explode('::', $targetXml['id']);
                if (sizeof($aclIdArr) === 2) {
                    $parentId = $aclIdArr[1];
                    $attr['id'] = "{$this->_env['ext_name']}::{$parentId}_{$key}";
                } else {
                    $this->log('[WARN] Invalid parent ACL id: ' . $targetXml['id']);
                    $attr['id'] = "{$this->_env['ext_name']}::UNKNOWN_{$key}";
                }
            }
            if (!empty($sourceNode->title)) {
                $attr['title'] = $sourceNode->title;
            }
            if (!empty($sourceNode->sort_order)) {
                $attr['sortOrder'] = $sourceNode->sort_order;
            }

            $targetNode = $targetXml->addChild('resource');
            foreach ($attr as $k => $v) {
                $targetNode->addAttribute($k, $v);
            }

            if (!empty($sourceNode->children)) {
                $this->_convertConfigAclRecursive($sourceNode->children, $targetNode, $path . $key . '/');
            }
        }
    }

    protected function _convertConfigResources()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->resources)) {

            $resultXml = $this->_createConfigXml('urn:magento:framework:App/etc/resources.xsd');

            foreach ($xml->global->resources->children() as $resKey => $resNode) {
                if (empty($resNode->connection->use)) {
                    continue;
                }
                $targetNode = $resultXml->addChild('resource');
                $targetNode->addAttribute('name', $resKey);
                $targetNode->addAttribute('extends', (string)$resNode->connection->use);
            }

            $this->_writeFile('etc/resources.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/resources.xml', true);
        }
    }

    protected function _convertConfigDI()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->_createConfigXml('urn:magento:framework:ObjectManager/etc/config.xsd');

        foreach (['models', 'helpers', 'blocks'] as $type) {
            if (empty($xml->global->{$type})) {
                continue;
            }
            foreach ($xml->global->{$type}->children() as $moduleKey => $mNode) {
                if (empty($mNode->rewrite)) {
                    continue;
                }
                foreach ($mNode->rewrite->children() as $classKey => $cNode) {
                    $origClass   = $this->_getClassName($type, $moduleKey . '/' . $classKey);
                    $targetClass = str_replace('_', '\\', (string)$cNode);
                    $prefNode    = $resultXml->addChild('preference');
                    $prefNode->addAttribute('for', $origClass);
                    $prefNode->addAttribute('type', $targetClass);
                }
            }
        }

        if ($resultXml->children()) {
            $this->_writeFile('etc/di.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/di.xml', true);
        }
    }

    protected function _convertConfigFrontendDI()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->_createConfigXml('urn:magento:framework:ObjectManager/etc/config.xsd');

        if (!empty($xml->frontend->secure_url)) {
            $n1 = $resultXml->addChild('type');
            $n1->addAttribute('name', 'Magento\Framework\Url\SecurityInfo');
            $n2 = $n1->addChild('arguments');
            $n3 = $n2->addChild('argument');
            $n3->addAttribute('name', 'secureUrlList');
            $n3->addAttribute('xsi:type', 'array', $this->_schemas['@XSI']);
            foreach ($xml->frontend->secure_url->children() as $itemName => $itemNode) {
                $n4 = $n3->addChild('item', (string)$itemNode);
                $n4->addAttribute('name', $itemName);
                $n4->addAttribute('xsi:type', 'string', $this->_schemas['@XSI']);
            }
        }

        if ($resultXml->children()) {
            $this->_writeFile('etc/frontend/di.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/frontend/di.xml', true);
        }
    }

    protected function _convertConfigAdminhtmlDI()
    {

    }

    protected function _convertConfigEvents()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        foreach (['global' => '', 'frontend' => 'frontend/', 'adminhtml' => 'adminhtml/'] as $area => $areaDir) {
            $xmlFilename = 'etc/' . $areaDir . 'events.xml';

            if (!empty($xml->{$area}->events)) {
                $resultXml = $this->_createConfigXml('urn:magento:framework:Event/etc/events.xsd');

                foreach ($xml->{$area}->events->children() as $eventName => $eventNode) {
                    $targetEventNode = $resultXml->addChild('event');
                    $targetEventNode->addAttribute('name', $eventName);
                    foreach ($eventNode->observers->children() as $obsName => $obsNode) {
                        $targetObsNode = $targetEventNode->addChild('observer');
                        $targetObsNode->addAttribute('name', $obsName);
                        $instance = $this->_getClassName('models', (string)$obsNode->class) . '\\'
                                    . str_replace(' ', '', ucwords(str_replace('_', ' ', (string)$obsNode->method)));
                        $targetObsNode->addAttribute('instance', $instance);
                        #$targetObsNode->addAttribute('method', (string)$obsNode->method);
                        if ($obsNode->type == 'model') {
                            $targetObsNode->addAttribute('shared', 'false');
                        }
                    }
                }
                $this->_writeFile($xmlFilename, $resultXml, true);
            } else {
                $this->_deleteFile($xmlFilename, true);
            }
        }
    }

    protected function _convertConfigRoutesFrontend()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        $xmlFilename = 'etc/frontend/routes.xml';

        if (!empty($xml->frontend->routers)) {
            $resultXml = $this->_createConfigXml('urn:magento:framework:App/etc/routes.xsd');

            $targetRouters = [];
            foreach ($xml->frontend->routers->children() as $routeName => $routeNode) {
                $routerName = (string)$routeNode->use;
                if (empty($targetRouters[$routerName])) {
                    $targetRouters[$routerName] = $resultXml->addChild('router');
                    $targetRouters[$routerName]->addAttribute('id', $routerName);
                }
                $targetRouteNode = $targetRouters[$routerName]->addChild('route');
                $targetRouteNode->addAttribute('id', $routeName);
                $targetRouteNode->addAttribute('frontName', (string)$routeNode->args->frontName);
                $targetRouteNode->addChild('module')->addAttribute('name', (string)$routeNode->args->module);
            }
            $this->_writeFile($xmlFilename, $resultXml, true);
        } else {
            $this->_deleteFile($xmlFilename, true);
        }
    }

    protected function _convertConfigRoutesAdmin()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        $xmlFilename = 'etc/adminhtml/routes.xml';

        if (!empty($xml->admin->routers->adminhtml->args->modules)) {
            $resultXml = $this->_createConfigXml('urn:magento:framework:App/etc/routes.xsd');

            $routerName    = 'admin';
            $targetRouters = [];
            $moduleFrom    = array_keys($this->_replace['modules']);
            $moduleTo      = array_values($this->_replace['modules']);
            foreach ($xml->admin->routers->adminhtml->args->modules->children() as $routeName => $routeNode) {
                if (empty($targetRouters[$routerName])) {
                    $targetRouters[$routerName] = $resultXml->addChild('router');
                    $targetRouters[$routerName]->addAttribute('id', $routerName);
                }
                $routeId    = preg_replace('#admin$#', '', $routeName);
                $moduleName = str_replace($moduleFrom, $moduleTo, (string)$routeNode);

                $targetRouteNode = $targetRouters[$routerName]->addChild('route');
                $targetRouteNode->addAttribute('id', $routeId);
                $targetRouteNode->addAttribute('frontName', $routeId);
                $module = $targetRouteNode->addChild('module');
                $module->addAttribute('name', $moduleName);
                if (!empty($routeNode['before'])) {
                    $moduleName = str_replace($moduleFrom, $moduleTo, (string)$routeNode['before']);
                    $module->addAttribute('before', $moduleName);
                }
                if (!empty($routeNode['after'])) {
                    $moduleName = str_replace($moduleFrom, $moduleTo, (string)$routeNode['after']);
                    $module->addAttribute('after', $moduleName);
                }
            }

            $this->_writeFile($xmlFilename, $resultXml, true);
        } else {
            $this->_deleteFile($xmlFilename, true);
        }
    }

    protected function _convertConfigMenu()
    {
        $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Backend:etc/menu.xsd');
        $targetXml = $resultXml->addChild('menu');

        $xml1 = $this->_readFile("@EXT/etc/config.xml", true);
        if (!empty($xml1->adminhtml->menu)) {
            $this->_convertConfigMenuRecursive($xml1->adminhtml->menu, $targetXml);
        }

        $xml2 = $this->_readFile("@EXT/etc/adminhtml.xml", true);
        if ($xml2 && !empty($xml2->acl)) {
            $this->_convertConfigMenuRecursive($xml2->menu, $targetXml);
        }

        if ($targetXml->children()) {
            $this->_writeFile('etc/adminhtml/menu.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/adminhtml/menu.xml', true);
        }
    }

    protected function _convertConfigMenuRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml, $parent = null)
    {
        foreach ($sourceXml->children() as $key => $srcNode) {
            if (!empty($this->_replace['menu'][$key])) {
                $attr['id'] = $this->_replace['menu'][$key];
            } else {
                $attr['id'] = $this->_env['ext_name'] . '::' . $key;
            }
            $attr['resource'] = $attr['id'];
            $attr['module']   = $this->_env['ext_name'];

            foreach (['title' => 'title', 'sort_order' => 'sortOrder', 'action' => 'action'] as $src => $tgt) {
                if (!empty($srcNode->{$src})) {
                    $attr[$tgt] = (string)$srcNode->{$src};
                }
            }
            if (!empty($srcNode->depends->module)) {
                $attr['dependsOnModule'] = (string)$srcNode->depends->module;
            }
            if (!empty($srcNode['module'])) {
                $moduleName = (string)$srcNode['module'];
                if (!empty($this->_aliases['modules'][$moduleName])) {
                    $attr['module'] = $this->_aliases['modules'][$moduleName];
                } else {
                    $this->log('[ERROR] Unknown module alias: ' . $moduleName);
                }
            }
            if ($parent) {
                $attr['parent'] = $parent;# ?: ''; #'Magento_Backend::content';
            }
            if (!empty($attr['action'])) {
                $attr['action'] = preg_replace(['#^adminhtml/#', '#_#', '#admin/#'], ['', '/', '/'], $attr['action']);
            }

            if (!empty($attr['title'])) {
                $targetNode = $targetXml->addChild('add');
                foreach ($attr as $k => $v) {
                    $targetNode->addAttribute($k, $v);
                }
            }
            if (!empty($srcNode->children)) {
                $nextParent = !empty($attr['title']) ? $attr['id'] : null;
                $this->_convertConfigMenuRecursive($srcNode->children, $targetXml, $nextParent);
            }
        }
    }

    protected function _convertConfigSystem()
    {
        $xml = $this->_readFile("@EXT/etc/system.xml", true);
        if ($xml) {
            $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Config:etc/system_file.xsd');
            $targetXml = $resultXml->addChild('system');

            if (!empty($xml->tabs)) {
                foreach ($xml->tabs->children() as $tabId => $tabNode) {
                    $this->_convertConfigSystemNode('tab', $tabNode, $targetXml);
                }
            }
            if (!empty($xml->sections)) {
                foreach ($xml->sections->children() as $sectionId => $sectionNode) {
                    $targetSectionNode = $this->_convertConfigSystemNode('section', $sectionNode, $targetXml);
                    $targetSectionNode->addChild('resource', $this->_env['ext_name'] . '::system_config');
                    if (empty($sectionNode->groups)) {
                        continue;
                    }
                    foreach ($sectionNode->groups->children() as $groupId => $groupNode) {
                        $targetGroupNode = $this->_convertConfigSystemNode('group', $groupNode, $targetSectionNode);
                        if (empty($groupNode->fields)) {
                            continue;
                        }
                        foreach ($groupNode->fields->children() as $fieldId => $fieldNode) {
                            $this->_convertConfigSystemNode('field', $fieldNode, $targetGroupNode);
                        }
                    }
                }
            }

            $this->_writeFile('etc/adminhtml/system.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/adminhtml/system.xml', true);
        }
    }

    protected function _convertConfigSystemNode($type, SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        $targetNode = $targetXml->addChild($type);
        $attr       = ['id' => $sourceXml->getName()];
        if (!empty($sourceXml['translate'])) {
            $attr['translate'] = (string)$sourceXml['translate'];
        }
        foreach (['sort_order' => 'sortOrder', 'frontend_type' => 'type', 'show_in_default' => 'showInDefault',
                     'show_in_website' => 'showInWebsite', 'show_in_store' => 'showInStore',
                 ] as $src => $tgt) {
            if (!empty($sourceXml->{$src})) {
                $attr[$tgt] = (string)$sourceXml->{$src};
            }
        }
        foreach ($attr as $k => $v) {
            $targetNode->addAttribute($k, $v);
        }
        $children = ['class', 'label', 'tab'];
        if ('field' === $type) {
            $children = array_merge($children, ['source_model', 'backend_model', 'upload_dir', 'base_url', 'comment']);
        }
        foreach ($children as $childKey) {
            if (!empty($sourceXml->{$childKey})) {
                $value = (string)$sourceXml->{$childKey};
                if ('source_model' === $childKey || 'backend_model' === $childKey) {
                    $value = $this->_getClassName('models', $value);
                }
                $targetNode->{$childKey} = $value;
                foreach ($sourceXml->{$childKey}->attributes() as $k => $v) {
                    $targetNode->{$childKey}->addAttribute($k, $v);
                }
            }
        }
        return $targetNode;
    }

    protected function _convertConfigCrontab()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);
        if (!empty($xml->crontab)) {
            $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Cron:etc/crontab.xsd');
            $targetXml = $resultXml->addChild('group');
            $targetXml->addAttribute('id', 'default');

            foreach ($xml->crontab->jobs->children() as $jobName => $jobNode) {
                $targetJobNode = $targetXml->addChild('job');
                $targetJobNode->addAttribute('name', $jobName);
                if (!empty($jobNode->run->model)) {
                    list($classAlias, $method) = explode('::', (string)$jobNode->run->model);
                    $targetJobNode->addAttribute('instance', $this->_getClassName('models', $classAlias));
                    $targetJobNode->addAttribute('method', $method);
                }
                $targetJobNode->addChild('schedule', (string)$jobNode->schedule->cron_expr);
            }

            $this->_writeFile('etc/crontab.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/crontab.xml', true);
        }
    }

    protected function _convertConfigEmailTemplates()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->template->email)) {
            $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Email:etc/email_templates.xsd');

            foreach ($xml->global->template->email->children() as $tplName => $tplNode) {
                $targetNode = $resultXml->addChild('template');
                $targetNode->addAttribute('id', $tplName);
                $targetNode->addAttribute('label', (string)$tplNode->label);
                $targetNode->addAttribute('file', (string)$tplNode->file);
                $targetNode->addAttribute('type', (string)$tplNode->type);
                $targetNode->addAttribute('area', !empty($tplNode['area']) ? $tplNode['area'] : 'frontend');
                if (!empty($tplNode['module'])) {
                    $targetNode->addAttribute('module', $this->_aliases['modules'][(string)$tplNode['module']]);
                }
            }

            $this->_writeFile('etc/email_templates.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/email_templates.xml', true);
        }
    }

    protected function _convertConfigCatalogAttributes()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Catalog:etc/catalog_attributes.xsd');

        //TODO: other types?
        if (!empty($xml->global->sales->quote->item->product_attributes)) {
            $targetXml = $resultXml->addChild('group');
            $targetXml->addAttribute('name', 'quote_item');
            foreach ($xml->global->sales->quote->item->product_attributes->children() as $fieldName => $_) {
                $attrNode = $targetXml->addChild('attribute');
                $attrNode->setAttribute('name', $fieldName);
            }
        }

        if ($resultXml->children()) {
            $this->_writeFile('etc/catalog_attributes.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/catalog_attributes.xml', true);
        }
    }

    protected function _convertConfigFieldset()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->fieldsets)) {
            $resultXml = $this->_createConfigXml('urn:magento:framework:Object/etc/fieldset.xsd');
            $targetXml = $resultXml->addChild('scope');
            $targetXml->addAttribute('id', 'global');

            foreach ($xml->global->fieldsets->children() as $fsName => $fsNode) {
                $targetFsNode = $targetXml->addChild('fieldset');
                $targetFsNode->addAttribute('id', $fsName);
                foreach ($fsNode->children() as $fName => $fNode) {
                    $targetFieldNode = $targetFsNode->addChild('field');
                    $targetFieldNode->addAttribute('name', $fName);
                    foreach ($fNode->children() as $aName => $aNode) {
                        $targetAspectNode = $targetFieldNode->addChild('aspect');
                        $targetAspectNode->addAttribute('name', $aName);
                        if ('*' !== (string)$aNode) {
                            $targetAspectNode->addAttribute('targetField', (string)$aNode);
                        }
                    }
                }
            }

            $this->_writeFile('etc/fieldset.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/fieldset.xml', true);
        }
    }

    protected function _convertConfigSales()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Sales:etc/sales.xsd');

        if (!empty($xml->global->sales)) {
            foreach ($xml->global->sales->children() as $sectionName => $sectionNode) {
                if (empty($sectionNode->totals)) {
                    continue;
                }
                $targetSectionNode = $resultXml->addChild('section');
                $targetSectionNode->addAttribute('name', $sectionName);
                $targetGroupNode = $targetSectionNode->addChild('group');
                $targetGroupNode->addAttribute('name', 'totals');
                $sortOrder = 10;
                foreach ($sectionNode->totals->children() as $totalName => $totalNode) {
                    if (empty($totalNode->class)) {
                        continue; //TODO: how to handle updates?
                    }
                    $targetItemNode = $targetGroupNode->addChild('item');
                    $class          = $this->_getClassName('models', (string)$totalNode->class);
                    $targetItemNode->addAttribute('instance', $class);
                    $targetItemNode->addAttribute('sort_order', $sortOrder);
                    $sortOrder += 10; //TODO: calculate by before/after attrs??
                }
            }
        }

        if (!empty($xml->adminhtml->sales->order->create->available_product_types)) {
            $targetXml = $resultXml->addChild('order');
            foreach ($xml->adminhtml->sales->order->create->available_product_types->children() as $type => $_) {
                $typeNode = $targetXml->addChild('available_product_type');
                $typeNode->addAttribute('name', $type);
            }
        }

        if ($resultXml->children()) {
            $this->_writeFile('etc/sales.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/sales.xml', true);
        }
    }

    protected function _convertConfigPdf()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->pdf)) {
            $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Sales:etc/pdf_file.xsd');

            $renderersXml = null;

            foreach ($xml->global->pdf->children() as $type => $typeNode) {
                if ('totals' === $type) {
                    $totalsXml = $resultXml->addChild('totals');
                    foreach ($typeNode->children() as $totalName => $totalNode) {
                        $targetTotalNode = $totalsXml->addChild('total');
                        $targetTotalNode->addAttribute('name', $totalName);
                        foreach ($totalNode->children() as $k => $v) {
                            $totalAttrNode = $targetTotalNode->addChild($k, (string)$v);
                            if ('title' === $k) {
                                $totalAttrNode->addAttribute('translate', 'true');
                            }
                        }
                    }
                } else {
                    if (!$renderersXml) {
                        $renderersXml = $resultXml->addChild('renderers');
                    }
                    $targetPageTypeNode = $renderersXml->addChild('page');
                    $targetPageTypeNode->addAttribute('type', $type);
                    foreach ($typeNode->children() as $prodType => $prodTypeNode) {
                        $className          = $this->_getClassName('models', (string)$prodTypeNode);
                        $targetProdTypeNode = $targetPageTypeNode->addChild('renderer', $className);
                        $targetProdTypeNode->addAttribute('product_type', $prodType);
                    }
                }
            }

            $this->_writeFile('etc/pdf.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/pdf.xml', true);
        }
    }

    protected function _convertConfigWidget()
    {
        /** @var SimpleDOM $xml */
        $xml = $this->_readFile("@EXT/etc/widget.xml", true);

        if ($xml) {
            $resultXml = $this->_createConfigXml('urn:magento:module:Magento_Widget:etc/widget.xsd', 'widgets');

            foreach ($xml->children() as $widgetName => $widgetNode) {
                $widgetXml = $resultXml->addChild('widget');
                $widgetXml->addAttribute('id', $widgetName);
                $widgetXml->addAttribute('class', $this->_getClassName('blocks', $widgetNode['type']));
                $widgetXml->addAttribute('is_email_compatible', (bool)$widgetNode->is_email_compatible ? 'true' : 'false');
                $labelXml = $widgetXml->addChild('label', (string)$widgetNode->name);
                $labelXml->addAttribute('translate', 'true');
                $descXml = $widgetXml->addChild('description', (string)$widgetNode->description);
                $descXml->addAttribute('translate', 'true');
                $paramsXml = $widgetXml->addChild('parameters');

                if (empty($widgetNode->parameters)) {
                    continue;
                }
                foreach ($widgetNode->parameters->children() as $paramName => $paramNode) {
                    $paramXml = $paramsXml->addChild('parameter');
                    $paramXml->addAttribute('name', $paramName);
                    $paramType = !empty($paramNode->helper_block) ? 'block' : (string)$paramNode->type;
                    $paramXml->addAttribute('xsi:type', $paramType, $this->_schemas['@XSI']);
                    if ((bool)$paramNode->required) {
                        $paramXml->addAttribute('required', (bool)$paramNode->required ? 'true' : 'false');
                    }
                    $paramXml->addAttribute('visible', (bool)$paramNode->visible ? 'true' : 'false');
                    $labelXml = $paramXml->addChild('label', (string)$paramNode->label);
                    $labelXml->addAttribute('translate', 'true');
                    if (!empty($paramNode->description)) {
                        $descXml = $paramXml->addChild('description', (string)$paramNode->description);
                        $descXml->addAttribute('translate', 'true');
                    }
                    if (!empty($paramNode->source_model)) {
                        $sourceModel = $this->_getClassName('models', (string)$paramNode->source_model);
                        $paramXml->addAttribute('source_model', $sourceModel);
                    }
                    if (!empty($paramNode->depends)) {
                        $dependsXml = $paramXml->addChild('depends');
                        foreach ($paramNode->depends->children() as $depName => $depNode) {
                            $depParamXml = $dependsXml->addChild('parameter');
                            $depParamXml->addAttribute('name', $depName);
                            $depParamXml->addAttribute('value', (string)$depNode->value);
                        }
                    }
                    if (!empty($paramNode->values)) {
                        $optionsXml = $paramXml->addChild('options');
                        foreach ($paramNode->values->children() as $valueName => $valueNode) {
                            $optionXml = $optionsXml->addChild('option');
                            $optionXml->addAttribute('name', $valueName);
                            $optionXml->addAttribute('value', (string)$valueNode->value);
                            if ((string)$valueNode->value == (string)$paramNode->value) {
                                $optionXml->addAttribute('selected', 'true');
                            }
                            $labelXml = $optionXml->addChild('label', (string)$valueNode->label);
                            $labelXml->addAttribute('translate', 'true');
                        }
                    } elseif (!empty($paramNode->value)) {
                        $paramXml->addChild('value', (string)$paramNode->value);
                    }
                    if (!empty($paramNode->helper_block)) {
                        $blockXml   = $paramXml->addChild('block');
                        $blockClass = $this->_getClassName('blocks', (string)$paramNode->helper_block->type);
                        $blockXml->addAttribute('class', $blockClass);
                        if (!empty($paramNode->helper_block->data)) {
                            $dataXml = $blockXml->addChild('data');
                            $this->_convertConfigWidgetDataRecursive($paramNode->helper_block->data, $dataXml);
                        }
                    }
                }
            }

            $this->_writeFile('etc/widget.xml', $resultXml, true);
        } else {
            $this->_deleteFile('etc/widget.xml', true);
        }
    }

    protected function _convertConfigWidgetDataRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        /**
         * @var string $itemName
         * @var SimpleXmlElement $itemNode
         */
        foreach ($sourceXml->children() as $itemName => $itemNode) {
            if ($itemNode->children()) {
                $itemXml = $targetXml->addChild('item');
                $itemXml->addAttribute('xsi:type', 'array', $this->_schemas['@XSI']);
                $this->_convertConfigWidgetDataRecursive($itemNode, $itemXml);
            } else {
                $itemValue = (string)$itemNode;
                $itemXml = $targetXml->addChild('item', $itemValue);
                $itemXml->addAttribute('xsi:type', 'string', $this->_schemas['@XSI']);
                if (!is_numeric($itemValue)) {
                    $itemXml->addAttribute('translate', 'true');
                }
            }
            $itemXml->addAttribute('name', $itemName);
        }
    }
}