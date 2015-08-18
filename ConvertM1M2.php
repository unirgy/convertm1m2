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


if (PHP_SAPI === 'cli') {
    $cwd = getcwd();
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    $sourceDir = !empty($_GET['s']) ? $_GET['s'] : "{$cwd}/source";
    $mage1Dir = !empty($_GET['m']) ? $_GET['m'] : "{$cwd}/../magento";
    $outputDir = !empty($_GET['o']) ? $_GET['o'] : "{$cwd}/../magento2/app/code";
} else {
    $sourceDir = 'source';
    $mage1Dir = '../magento';
    $outputDir = '../magento2/app/code';
    echo "<pre>";
}


include_once __DIR__ . '/SimpleDOM.php';
$converter = new ConvertM1M2($sourceDir, $mage1Dir, $outputDir);
$converter->convertAllExtensions();
$converter->log('ALL DONE')->log('');
die;

class ConvertM1M2
{
    
    protected $_env = [];

    protected $_fileCache = [];

    protected $_classFileCache = [];

    protected $_aliases = [];

    protected $_layouts = [];

    protected $_schemas = [
        '@XSI' => 'http://www.w3.org/2001/XMLSchema-instance',
        '@Framework/' => '../../../../../lib/internal/Magento/Framework/',
        '@Magento/' => '../../../Magento/',
    ];

    const OBJ_MGR = '\Magento\Framework\App\ObjectManager::getInstance()->get';

    // Sources: http://mage2.ru, https://wiki.magento.com/display/MAGE2DOC/Class+Mage
    protected $_replace = [
        'classes' => [
            'Mage_Core_Helper_Abstract' => 'Magento_Framework_App_Helper_AbstractHelper',
            'Mage_Core_Model_Abstract' => 'Magento_Framework_Model_AbstractModel',
            'Mage_Core_Model_Mysql4_Abstract' => 'Magento_Framework_Model_Resource_Db_AbstractDb',
            'Mage_Core_Block_Abstract' => 'Magento_Framework_View_Element_AbstractBlock',
            'Mage_Core_Block_Template' => 'Magento_Framework_View_Element_Template',
            'Mage_Core_Controller_Front_Action' => 'Magento_Framework_App_Action_Action',
            'Mage_Adminhtml_Controller_Action' => 'Magento_Backend_App_Action',
            'Mage_Adminhtml_' => 'Magento_Backend_',
            'Mage_Admin_' => 'Magento_Backend_',
            'Mage_Core_' => 'Magento_Framework_',
            'Mage_Page_' => 'Magento_Framework_',
            'Mage_' => 'Magento_',
            'Varien_Object' => 'Magento_Framework_DataObject',
            'Varien_Io_' => 'Magento_Framework_Filesystem_Io_',
            'Varien_' => 'Magento_Framework_',
            '_Mysql4_' => '_Resource_',
            'Zend_Json' => 'Zend_Json_Json',
        ],
        'classes_regex' => [
            '#_([A-Za-z0-9]+)_Abstract([^A-Z])#' => '_\1_Abstract\1\2',
        ],
        'code' => [
            'Mage_Core_Model_Locale::DEFAULT_LOCALE' => '\Magento\Framework\Locale\Resolver::DEFAULT_LOCALE',
            'Mage_Core_Model_Translate::CACHE_TAG' => '\Magento\Framework\App\Cache\Type::CACHE_TAG',

            'Mage::log(' => self::OBJ_MGR . '(\'Psr\Log\LoggerInterface\')->log(',
            'Mage::logException(' => self::OBJ_MGR . '(\'Psr\Log\LoggerInterface\')->error(',
            'Mage::dispatchEvent(' =>  self::OBJ_MGR . '(\'Magento\Framework\Event\ManagerInterface\')->dispatch(',
            'Mage::app()->getRequest()' => self::OBJ_MGR . '(\'Magento\Framework\App\RequestInterface\')',
            'Mage::app()->getLocale()->getLocaleCode()' => self::OBJ_MGR . '(\'Magento\Framework\Locale\Resolver\')->getLocale()',
            'Mage::app()->getStore(' => self::OBJ_MGR . '(\'Magento\Store\Model\StoreManagerInterface\')->getStore(',
            'Mage::app()->getCacheInstance()->canUse(' => self::OBJ_MGR . '(\'Magento\Framework\App\Cache\StateInterface\')->isEnabled(',
            'Mage::app()->getCacheInstance()' => self::OBJ_MGR . '(\'Magento\Framework\App\CacheInterface\')',
            'Mage::getConfig()->getModuleDir(' => self::OBJ_MGR . '(\'Magento\Framework\Module\Dir\Reader\')->getModuleDir(',
            'Mage::getStoreConfig(' => self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->getValue(',
            'Mage::getStoreConfigFlag(' => self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->isSetFlag(',
            'Mage::getDesign()' => self::OBJ_MGR . '(\'Magento\Framework\View\DesignInterface\')',
            "Mage::helper('core/url')->getCurrentUrl()" => self::OBJ_MGR . '(\'Magento\Framework\UrlInterface\')->getCurrentUrl()',
            'Mage::getBaseUrl(' => self::OBJ_MGR . '(\'Magento\Framework\UrlInterface\')->getBaseUrl(',
            'Mage::getBaseDir(' => self::OBJ_MGR . '(\'Magento\Framework\Filesystem\')->getDirPath(',
            "Mage::getSingleton('admin/session')->isAllowed(" => self::OBJ_MGR . '(\'Magento\Backend\Model\Auth\Session\')->isAllowed(',
        ],
        'code_regex' => [
            '#(Mage::helper\([\'"][A-Za-z0-9/_]+[\'"]\)|\$this)->__\(#' => '__(',
            '#Mage::(registry|register|unregister)\(#' => self::OBJ_MGR . '(\'Magento\Framework\Registry\')->\1(',
        ],
        'acl_keys' => [
            'admin' => 'Magento_Backend::admin',
            'admin/sales' => 'Magento_Sales:sales',
            'admin/reports' => 'Magento_Reports:report',
            'admin/system' => 'Magento_Backend::stores',
            'admin/system/config' => 'Magento_Backend::stores_settings',
        ],
        'menu' => [
            'sales' => 'Magento_Sales::sales',
            'report' => 'Magento_Reports:report',
        ],
    ];

    public function __construct($rootDir, $mageDir, $outputDir)
    {
        $this->_env['source_dir'] = str_replace('\\', '/', $rootDir);
        $this->_env['mage1_dir'] = str_replace('\\', '/', $mageDir);
        $this->_env['output_dir'] = str_replace('\\', '/', $outputDir);

        spl_autoload_register([$this, 'autoloadCallback']);

        $this->_collectCoreModulesConfigs();
        $this->_collectCoreModulesLayouts();
    }

    public function autoloadCallback($class)
    {
        if (!empty($this->_classFileCache[$class])) {
            return;
        }
        foreach (['lib', 'app/code/core', 'app/code/community', 'app/code/local'] as $pool) {
            $classFile = str_replace(['_', '\\'], ['/', '/'], $class);
            $filename = "{$this->_env['mage1_dir']}/{$pool}/{$classFile}.php";
            if (file_exists($filename)) {
                include_once $filename;
                $this->_classFileCache[$class] = $filename;
                return;
            }
        }
    }

    public function convertAllExtensions()
    {
        $this->log('')->log("LOOKING FOR ALL EXTENSIONS IN {$this->_env['source_dir']}")->log('');

        $extDirs = glob($this->_env['source_dir'] . '/*', GLOB_ONLYDIR);
        foreach ($extDirs as $extDir) {
            if (!preg_match('#^(.*)/([A-Za-z0-9]+_[A-Za-z0-9]+)$#', $extDir, $m)) {
                continue;
            }
            $this->convertExtension($m[2], $m[1]);
        }

        return $this;
    }

    public function convertExtension($extName, $rootDir)
    {
        $this->log("EXTENSION: {$extName}");

        $this->_env['ext_name'] = $extName;
        $folders = glob($this->_env['mage1_dir'] . '/app/code/*/' . str_replace('_', '/', $extName));
        if ($folders && preg_match('#app/code/(core|community|local)/#', $folders[0], $m)) {
            $this->_env['ext_pool'] = $m[1];
        } else {
            $this->_env['ext_pool'] = 'community';
        }
        $this->_env['ext_root_dir'] = $rootDir . '/' . $extName;
        #$this->_env['ext_output_dir'] = $rootDir . '/output/' . $extName;
        $this->_env['ext_output_dir'] = $this->_env['output_dir'] . '/' . str_replace('_', '/', $extName);

        $this->_fileCache = [];

        $this->_convertAllConfigs();
        $this->_convertAllControllers();
        $this->_convertAllMigrations();
        $this->_convertAllLayouts();
        $this->_convertAllTemplates();
        $this->_convertAllWebAssets();
        $this->_convertAllI18n();
        $this->_convertAllOtherFiles();

        $this->log("FINISHED: {$extName}")->log('');

        return $this;
    }

    public function log($msg, $continue = false)
    {
        if (!is_scalar($msg)) {
            $msg = print_r($msg, 1);
        }
        if (!$continue) {
            echo "\n";
        }
        if (!empty($msg)) {
            echo date("Y-m-d H:i:s") . ' ' . $msg;
        }

        return $this;
    }

    protected function _expandSourcePath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_root_dir'] . '/' . $path;
        }
        $target = 'app/code/' . $this->_env['ext_pool'] . '/' . str_replace('_', '/', $this->_env['ext_name']) . '/';
        $path = str_replace('@EXT/', $target, $path);
        return $path;
    }

    protected function _expandOutputPath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_output_dir'] . '/' . $path;
        }
        return $path;
    }

    protected function _readFile($filename, $expand = false)
    {
        if ($expand) {
            $filename = $this->_expandSourcePath($filename);
        }

        if (isset($this->_fileCache[$filename])) {
            return $this->_fileCache[$filename];
        }

        if (!file_exists($filename)) {
            $this->_fileCache[$filename] = false;
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'xml':
                $content = simpledom_load_file($filename);
                break;

            default:
                $content = file_get_contents($filename);
        }
        $this->_fileCache[$filename] = $content;
        return $content;
    }

    protected function _writeFile($filename, $content, $expand = false)
    {
        if ($expand) {
            $filename = $this->_expandOutputPath($filename);
        }

        $dir = dirname($filename);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if ($content instanceof SimpleXMLElement) {
            $content->asPrettyXml($filename);
        } else {
            file_put_contents($filename, $content);
        }
    }

    protected function _copyFile($src, $dst, $expand = false)
    {
        if ($expand) {
            $src = $this->_expandSourcePath($src);
            $dst = $this->_expandOutputPath($dst);
        }

        $dir = dirname($dst);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        copy($src, $dst);
    }
    protected function _copyRecursive($src, $dst, $expand = false)
    {
        if ($expand) {
            $src = $this->_expandSourcePath($src);
            if (!file_exists($src)) {
                return;
            }
            $dst = $this->_expandOutputPath($dst);
        }

        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->_copyRecursive($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    protected function _deleteFile($filename, $expand = false)
    {
        if ($expand) {
            $filename = $this->_expandOutputPath($filename);
        }

        if (file_exists($filename)) {
            unlink($filename);
        }
/*
        $dir = dirname($filename);
        if (file_exists($dir)) {
            $empty = true;
            $dirFiles = glob($dir . '/*');
            foreach ($dirFiles as $file) {
                if (!preg_match('#(^|/)\.+$#', $file)) {
                    $empty = false;
                    break;
                }
            }
            if ($empty) {
                unlink($dir);
            }
        }
*/
    }

    protected function _findFilesRecursive($dir, $expand = false)
    {
        if ($expand) {
            $dir = $this->_expandSourcePath($dir);
        }
        if (!file_exists($dir)) {
            return [];
        }
        $dirIter = new RecursiveDirectoryIterator($dir);
        $iter = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);
        $files = [];
        foreach ($iter as $file) {
            if (!is_dir((string)$file)) {
                $files[] = str_replace($dir . '/', '', str_replace('\\', '/', (string)$file));
            }
        }
        return $files;
    }

    protected function _collectCoreModulesConfigs()
    {
        $this->log("COLLECTING M1 CONFIGURATION...")->log('');
        $configFiles = glob($this->_env['mage1_dir'] . '/app/code/*/*/*/etc/config.xml');
        foreach ($configFiles as $file) {
            $xml = simpledom_load_file($file);
            foreach (['models', 'helpers', 'blocks'] as $type) {
                if (!empty($xml->global->{$type})) {
                    foreach ($xml->global->{$type}->children() as $key => $node) {
                        if (!empty($node->class)) {
                            $this->_aliases[$type][$key] = $node->class;
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
        $this->log("COLLECTING M1 LAYOUTS...")->log('');
        $layoutFiles = glob($this->_env['mage1_dir'] . '/app/design/*/*/*/layout/*.xml');
        foreach ($layoutFiles as $file) {
            preg_match('#/app/design/([^/]+)/([^/]+/[^/]+)#', $file, $m);
            $xml = simpledom_load_file($file);
            $blocks = $xml->xpath('//block');
            foreach ($blocks as $blockNode) {
                if ($blockNode['type'] && $blockNode['name']) {
                    $className = $this->_getClassName('blocks', (string)$blockNode['type'], false);
                    $this->_layouts[$m[1]]['blocks'][(string)$blockNode['name']] = $className;
                }
            }
        }
    }

    protected function _getClassName($type, $moduleClassKey, $m2 = true)
    {
        $mk = explode('/', strtolower($moduleClassKey), 2);
        if (empty($mk[1])) {
            if ($type === 'helpers') {
                $mk[1] = 'data';
            } else {
                return false;
            }
        }
        list($moduleKey, $classKey) = $mk;

        if (empty($this->_aliases[$type][$moduleKey])) {
            $substTypes = ['models' => '_Model', 'blocks' => '_Block', 'helpers' => '_Helper'];
            foreach ($substTypes as $substType => $substPart) {
                if (!empty($this->_aliases[$substType][$moduleKey])) {
                    $this->_aliases[$type][$moduleKey] =
                        str_replace($substPart, $substTypes[$type], $this->_aliases[$substType][$moduleKey]);
                    break;
                }
            }
            if (empty($this->_aliases[$type][$moduleKey])) {
                $this->log('ERROR: Unknown module key: ' . $type . ' :: ' . $moduleKey);
                return 'UNKNOWN\\' . $moduleKey . '\\' . $classKey;
            }
        }

        $classKeyCapped = strtr(ucwords(strtr($classKey, ['_' => ' '])), [' ' => '_']);
        $className = $this->_aliases[$type][$moduleKey] . '_' . $classKeyCapped;

        if ($m2) {
            $classTr = $this->_replace['classes'];
            $className = str_replace(array_keys($classTr), array_values($classTr), $className);
            $classRegexTr = $this->_replace['classes_regex'];
            $className = preg_replace(array_keys($classRegexTr), array_values($classRegexTr), $className);
            $className = str_replace('_', '\\', $className);
        }

        return $className;
    }

    ///////////////////////////////////////////////////////////

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
        $this->_convertConfigRoutes();
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

    protected function _createConfigXml($schemaPath)
    {
        $schemaPath = str_replace(array_keys($this->_schemas), array_values($this->_schemas), $schemaPath);
        return simpledom_load_string('<?xml version="1.0"?>
<config xmlns:xsi="' . $this->_schemas['@XSI'] . '" xsi:noNamespaceSchemaLocation="' . $schemaPath . '">
</config>');
    }

    protected function _convertConfigModule()
    {
        $xml1 = $this->_readFile("@EXT/etc/config.xml", true);
        $xml2 = $this->_readFile("app/etc/modules/{$this->_env['ext_name']}.xml", true);

        $extName = $this->_env['ext_name'];
        $version = $xml1->modules->{$extName}->version;

        $resultXml = $this->_createConfigXml('@Framework/Module/etc/module.xsd');
        $targetXml = $resultXml->addChild('module');
        $targetXml->addAttribute('name', $extName);
        $targetXml->addAttribute('setup_version', $version);
        if (!empty($xml2->modules->{$extName}->depends)) {
            $sequenceXml = $targetXml->addChild('sequence');
            foreach ($xml2->modules->{$extName}->depends->children() as $depName => $_) {
                $sequenceXml->addChild('module')->addAttribute('name', $depName);
            }
        }

        $this->_writeFile('etc/module.xml', $resultXml, true);
    }

    protected function _convertConfigDefaults()
    {
        $resultXml = $this->_createConfigXml('@Magento/Store/etc/config.xsd');

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
        $resultXml = $this->_createConfigXml('@Framework/Acl/etc/acl.xsd');
        $targetXml = $resultXml->addChild('acl')->addChild('resources');

        $xml1 = $this->_readFile("@EXT/etc/config.xml");
        if (!empty($xml1->adminhtml->acl)) {
            $this->_convertConfigAclRecursive($xml1->adminhtml->acl->resources, $targetXml);
        }

        $xml2 = $this->_readFile("@EXT/etc/adminhtml.xml");
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
                $attr['id'] = $this->_replace['acl_keys'][$path . $key];
            } else {
                $attr['id'] = $this->_env['ext_name'] . ':' . $key;
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

            $resultXml = $this->_createConfigXml('@Framework/App/etc/resources.xsd');

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

        $resultXml = $this->_createConfigXml('@Framework/ObjectManager/etc/config.xsd');

        foreach (['models', 'helpers', 'blocks'] as $type) {
            if (empty($xml->global->{$type})) {
                continue;
            }
            foreach ($xml->global->{$type}->children() as $moduleKey => $mNode) {
                if (empty($mNode->rewrite)) {
                    continue;
                }
                foreach ($mNode->rewrite->children() as $classKey => $cNode) {
                    $origClass = $this->_getClassName($type, $moduleKey . '/' . $classKey);
                    $targetClass = str_replace('_', '\\', (string)$cNode);
                    $prefNode = $resultXml->addChild('preference');
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

        $resultXml = $this->_createConfigXml('@Framework/ObjectManager/etc/config.xsd');

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
                $resultXml = $this->_createConfigXml('@Framework/Event/etc/events.xsd');

                foreach ($xml->{$area}->events->children() as $eventName => $eventNode) {
                    $targetEventNode = $resultXml->addChild('event');
                    $targetEventNode->addAttribute('name', $eventName);
                    foreach ($eventNode->observers->children() as $obsName => $obsNode) {
                        $targetObsNode = $targetEventNode->addChild('observer');
                        $targetObsNode->addAttribute('name', $obsName);
                        $targetObsNode->addAttribute('instance', $this->_getClassName('models', (string)$obsNode->class));
                        $targetObsNode->addAttribute('method', (string)$obsNode->method);
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

    protected function _convertConfigRoutes()
    {
        $xml = $this->_readFile("@EXT/etc/config.xml", true);

        foreach (['frontend' => 'frontend/', 'admin' => 'adminhtml/'] as $area => $areaDir) {
            $xmlFilename = 'etc/' . $areaDir . 'routes.xml';

            if (!empty($xml->{$area}->routers)) {
                $resultXml = $this->_createConfigXml('@Framework/App/etc/routes.xsd');

                $targetRouters = [];
                foreach ($xml->{$area}->routers->children() as $routeName => $routeNode) {
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
    }

    protected function _convertConfigMenu()
    {
        $resultXml = $this->_createConfigXml('@Magento/Backend/etc/menu.xsd');
        $targetXml = $resultXml->addChild('menu');

        $xml1 = $this->_readFile("@EXT/etc/config.xml");
        if (!empty($xml1->adminhtml->menu)) {
            $this->_convertConfigMenuRecursive($xml1->adminhtml->menu, $targetXml);
        }

        $xml2 = $this->_readFile("@EXT/etc/adminhtml.xml");
        if ($xml2 && !empty($xml2->acl)) {
            $this->_convertConfigMenuRecursive($xml2->menu, $targetXml);
        }

        if ($targetXml->children()) {
            $this->_writeFile('etc/menu.xml', $resultXml);
        } else {
            $this->_deleteFile('etc/menu.xml');
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
                    $this->log('ERROR: Unknown module alias: ' . $moduleName);
                }
            }
            if ($parent) {
                $attr['parent'] = $attr['id'];
            }

            $targetNode = $targetXml->addChild('add');
            foreach ($attr as $k => $v) {
                $targetNode->addAttribute($k, $v);
            }
            if (!empty($srcNode->children)) {
                $this->_convertConfigMenuRecursive($srcNode->children(), $targetXml, $attr['id']);
            }
        }
    }

    protected function _convertConfigSystem()
    {
        $xml = $this->_readFile("@EXT/etc/system.xml", true);
        if ($xml) {
            $resultXml = $this->_createConfigXml('@Magento/Config/etc/system_file.xsd');
            $targetXml = $resultXml->addChild('system');

            if (!empty($xml->tabs)) {
                foreach ($xml->tabs->children() as $tabId => $tabNode) {
                    $this->_convertConfigSystemNode('tab', $tabNode, $targetXml);
                }
            }
            if (!empty($xml->sections)) {
                foreach ($xml->sections->children() as $sectionId => $sectionNode) {
                    $targetSectionNode = $this->_convertConfigSystemNode('section', $sectionNode, $targetXml);
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
        $attr = ['id' => $sourceXml->getName()];
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
            $resultXml = $this->_createConfigXml('@Magento/Cron/etc/crontab.xsd');
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
            $resultXml = $this->_createConfigXml('@Magento/Email/etc/email_templates.xsd');

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

        $resultXml = $this->_createConfigXml('@Magento/Catalog/etc/catalog_attributes.xsd');

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
            $resultXml = $this->_createConfigXml('@Framework/Object/etc/fieldset.xsd');
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

        $resultXml = $this->_createConfigXml('@Magento/Sales/etc/sales.xsd');

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
            $resultXml = $this->_createConfigXml('@Magento/Sales/etc/pdf_file.xsd');

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
                        $className = $this->_getClassName('models', (string)$prodTypeNode);
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

    }

    ///////////////////////////////////////////////////////////

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
            $resultXml = $this->_createConfigXml('@Framework/View/Layout/etc/page_configuration.xsd');
            $bodyXml = null;
            foreach ($layoutNode->children() as $nodeTag => $node) {
                if (!$bodyXml && ('remove' === $nodeTag || 'reference' === $nodeTag || 'block' === $nodeTag)) {
                    $bodyXml = $resultXml->addChild('body');
                }
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
                    case 'block':
                        $this->_convertLayoutRecursive($area, $node, $bodyXml);
                        break;


                    default:
                        //$bodyXml->
                }
            }

            $this->_writeFile("{$outputDir}/{$layoutName}.xml", $resultXml);
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
                    if (is_subclass_of($className, 'Mage_Core_Block_Text_List')) {
                        $targetChildXml = $targetXml->addChild('referenceContainer');
                    } else {
                        $targetChildXml = $targetXml->addChild('referenceBlock');
                    }
                } else {
                    $targetChildXml = $targetXml->addChild('referenceBlock');
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

        if (preg_match('#^([a-z_]+)/(a-z0-9_]+)$#i', $value)) {
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

    ///////////////////////////////////////////////////////////

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
            $this->_convertTemplateFile($dir, $filename, $outputDir);
        }
    }

    protected function _convertTemplateFile($dir, $filename, $outputDir)
    {
        $contents = $this->_readFile("{$dir}/{$filename}");

        $contents = $this->_convertCodeContents($contents, 'phtml');

        $this->_writeFile("{$outputDir}/{$filename}", $contents);
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

    ///////////////////////////////////////////////////////////

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

    ///////////////////////////////////////////////////////////

    protected function _convertAllWebAssets()
    {
        $this->_copyRecursive('js', 'view/frontend/web/js', true);
        $this->_copyRecursive('media', 'view/frontend/web/media', true);
        $this->_copyRecursive('skin/adminhtml/default/default', 'view/adminhtml/web', true);
        $this->_copyRecursive('skin/frontend/base/default', 'view/frontend/web', true);
    }

    ///////////////////////////////////////////////////////////

    protected function _convertAllOtherFiles()
    {
        $dir = $this->_expandSourcePath("@EXT/");
        $files = glob("{$dir}*");
        $targetDir = $this->_env['ext_output_dir'];
        foreach ($files as $file) {
            $basename = basename($file);
            if ('etc' === $basename || 'controllers' === $basename || 'sql' === $basename) {
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

    protected function _convertPhpClasses($folder, $callback = null)
    {
        $dir = $this->_expandSourcePath("@EXT/{$folder}");
        $files = $this->_findFilesRecursive($dir);
        $targetDir = $this->_expandOutputPath($folder);
        foreach ($files as $filename) {
            $contents = $this->_readFile("{$dir}/{$filename}");
            $targetFile = "{$targetDir}/{$filename}";
            if ($callback) {
                $params = ['source_file' => $filename, 'target_file' => &$targetFile];
                $contents = call_user_func($callback, $contents, $params);
            } else {
                $contents = $this->_convertCodeContents($contents);
                $targetFile = str_replace('/Model/Mysql4/', '/Model/Resource/', $targetFile);
            }
            $this->_writeFile($targetFile, $contents);
        }
    }

    protected function _convertCodeContents($contents, $mode = 'php')
    {
        // Replace code snippets
        $codeTr = $this->_replace['code'];
        $contents = str_replace(array_keys($codeTr), array_values($codeTr), $contents);
        $codeTr = $this->_replace['code_regex'];
        $contents = preg_replace(array_keys($codeTr), array_values($codeTr), $contents);

        if ($mode === 'php') {
            // Replace $this->_init() in models and resources with class names and table names
            $contents = preg_replace_callback('#(\$this->_init\([\'"])([A-Za-z0-9_/]+)([\'"][,\)])#', function ($m) {
                if ($m[3] === ')') {
                    return $m[1] . $this->_getClassName('models', $m[2]) . $m[3];
                } else {
                    return $m[1] . str_replace('/', '_', $m[2]) . $m[3]; //TODO: try to figure out original table name
                }
            }, $contents);
        }

        // Replace getModel|getSingleton|helper calls with ObjectManager::get calls
        $re = '#(Mage::getModel|Mage::getSingleton|Mage::helper|\$this->helper)\([\'"]([a-zA-Z0-9/_]+)[\'"]\)#';
        $contents = preg_replace_callback($re, function($m) {
            $class = $this->_getClassName(strpos($m[1], 'helper') !== false ? 'helpers' : 'models', $m[2], false);
            $result = self::OBJ_MGR . "('{$class}')";
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

        // Add namespace to class declarations
        $from = '#^class \\\\([A-Z][\\\\A-Za-z0-9]+)\\\\([A-Za-z0-9]+)((\s+)extends\s|\s*$)?#ms';
        $to = "namespace \$1;\n\nclass \$2\$3";
        $contents = preg_replace($from, $to, $contents);

        return $contents;
    }

    ///////////////////////////////////////////////////////////

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
        $targetFile = preg_replace('#Controller\.php$#', '.php', "{$targetDir}/{$file}");
        $fileClass = preg_replace(['#/#', '#\.php$#'], ['_', ''], $file);
        $origClass = "{$this->_env['ext_name']}_{$fileClass}";
        $fileClass = preg_replace('#Controller$#', '', $fileClass);
        $ctrlClass = "{$this->_env['ext_name']}_Controller_{$fileClass}";

        $contents = $this->_readFile("{$sourceDir}/{$file}");
        $contents = str_replace($origClass, $ctrlClass, $contents);

        if (strpos($file, 'Controller.php') === false) {
            $contents = $this->_convertCodeContents($contents);
            $this->_writeFile($targetFile, $contents, false);
            return;
        }

        $this->log('CONTROLLER: ' . $origClass);

        $nl = preg_match('#\r\n#', $contents) ? "\r\n" : "\n";
        $actions = $this->_convertControllerFindActions($contents, $nl);

        foreach ($actions as $action) {
            if ('new' === $action['name']) {
                $action['name'] = 'newAction';
            }
            $actionName = ucwords($action['name']);
            $actionClass = "{$ctrlClass}_{$actionName}";
            $txt = preg_replace('#(public\s+function\s+)([a-zA-Z0-9_]+)(\()#', '$1execute$3', $action['contents']);
            $classContents = "<?php{$nl}{$nl}class {$actionClass} extends {$ctrlClass}{$nl}{{$nl}{$txt}{$nl}}{$nl}";
            $classContents = $this->_convertCodeContents($classContents);
            $actionFile = str_replace([$this->_env['ext_name'] . '_', '_'], ['', '/'], $actionClass) . '.php';
            $this->_writeFile("{$this->_env['ext_output_dir']}/{$actionFile}", $classContents, false);
        }

        $contents = $this->_convertCodeContents($contents);
        $this->_writeFile($targetFile, $contents);
    }

    protected function _convertControllerFindActions(&$contents, $nl)
    {
        $lines = preg_split('#\r?\n#', $contents);

        // Find start of the class
        for ($i = 0, $l = sizeof($lines); $i < $l; $i++) {
            if (preg_match('#^\s*class\s+[A-Za-z0-9_]+(\s+extends|\s*$)#', $lines[$i])) {
                $classStart = $i;
                break;
            }
        }

        // Find starts of all methods
        $methods = [];
        for ($i = 0; $i < $l; $i++) {
            if (preg_match('#(public|protected|private)\s+function\s+([a-zA-Z0-9_]+?)(Action)?\(#', $lines[$i], $m)) {
                $methods[] = ['name' => $m[2], 'is_action' => !empty($m[3]), 'start' => $i, 'code_start' => $i];
            }
        }

        // Find end of the last method
        $pastEndOfClass = null;
        for ($i = sizeof($lines) - 1; $i > 0; $i--) {
            if (preg_match('#^\s*\}\s*$#', $lines[$i])) {
                if (!$pastEndOfClass) {
                    $pastEndOfClass = true;
                } else {
                    $methods[sizeof($methods) - 1]['end'] = $i;
                    break;
                }
            }
        }

        // Find phpdocs and ends of rest of the methods
        for ($i = sizeof($methods) - 1; $i >= 0; $i--) {
            $method =& $methods[$i];
            if (empty($method['end'])) {
                for ($j = $methods[$i + 1]['start'] - 1; $j > $method['start']; $j--) {
                    if (preg_match('#^\s*\}\s*$#', $lines[$j])) {
                        $method['end'] = $j;
                        break;
                    }
                }
            }
            for ($j = $method['start'] - 1; $j > $method['end']; $j--) {
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

        for ($i = sizeof($methods) - 1; $i >= 0; $i--) {
            $method =& $methods[$i];
            if ($method['is_action']) {
                $length = $method['end'] - $method['start'] + 1;
                $method['contents'] = join($nl, array_splice($lines, $method['start'], $length));
            } else {
                unset($methods[$i]);
            }
        }
        unset($method);
        $contents = join($nl, $lines);
        return $methods;
    }

    ///////////////////////////////////////////////////////////

    protected function _convertAllMigrations()
    {

    }
}