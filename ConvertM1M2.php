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
    $mage2Dir = !empty($_GET['o']) ? $_GET['o'] : "{$cwd}/../magento2";
    $stage = !empty($_GET['a']) ? (int)$_GET['a'] : 1;
} else {
    $sourceDir = 'source';
    $mage1Dir = '../magento';
    $mage2Dir = '../magento2';
    $stage = !empty($_GET['a']) ? (int)$_GET['a'] : 1;
    echo "<pre>";
}

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

$time = microtime(true);
include_once __DIR__ . '/SimpleDOM.php';
$converter = new ConvertM1M2($sourceDir, $mage1Dir, $mage2Dir);
$converter->convertAllExtensions($stage);
$converter->log('[SUCCESS] ALL DONE (' . (microtime(true) - $time) . ' sec)')->log('');
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
        '@Framework/' => '../../../../../lib/internal/Magento/Framework/', //deprecated
        '@Magento/' => '../../../Magento/', //deprecated
    ];

    const OBJ_MGR = '\Magento\Framework\App\ObjectManager::getInstance()->get';

    // Sources: http://mage2.ru, https://wiki.magento.com/display/MAGE2DOC/Class+Mage
    protected $_replace;

    protected $_reservedWordsRe = '#^(
(a(bstract|nd|rray|s))|
(c(a(llable|se|tch)|l(ass|one)|on(st|tinue)))|
(d(e(clare|fault)|ie|o))|
(e(cho|lse(if)?|mpty|nd(declare|for(each)?|if|switch|while)|val|x(it|tends)))|
(f(inal|or(each)?|unction))|
(g(lobal|oto))|
(i(f|mplements|n(clude(_once)?|st(anceof|eadof)|terface)|sset))|
(n(amespace|ew))|
(p(r(i(nt|vate)|otected)|ublic))|
(re(quire(_once)?|turn))|
(s(tatic|witch))|
(t(hrow|r(ait|y)))|
(u(nset|se))|
(__halt_compiler|break|list|(x)?or|var|while)
|map|data
)$#ix';

    protected function _getReplaceMaps()
    {
        return [
            'modules' => [
                'Mage_Adminhtml' => 'Magento_Backend',
            ],
            'classes' => [
                'Mage_Core_Helper_Abstract' => 'Magento_Framework_App_Helper_AbstractHelper',
                'Mage_Core_Helper_Data' => 'Magento_Framework_App_Helper_AbstractHelper',
                'Mage_Core_Helper_' => 'Magento_Framework_App_Helper_',
                'Mage_Core_Model_Abstract' => 'Magento_Framework_Model_AbstractModel',
                'Mage_Core_Model_Mysql4_Abstract' => 'Magento_Framework_Model_Resource_Db_AbstractDb',
                'Mage_Core_Model_Mysql4_Collection_Abstract' => 'Magento_Framework_Model_ResourceModel_Db_Collection_AbstractCollection',
                'Mage_Core_Model_Resource_Setup' => 'Magento_Framework_Module_Setup',
                'Mage_Core_Model_Url_Rewrite' => 'Magento_UrlRewrite_Model_UrlRewrite',
                'Mage_Core_Model_Config_Data' => 'Magento_Framework_App_Config_Value',
                'Mage_Core_Model_Mysql4_Config_' => 'Magento_Config_Model_ResourceModel_Config_',
                'Mage_Core_Model_Resource_Config_' => 'Magento_Config_Model_ResourceModel_Config_',
                'Mage_Core_Block_Abstract' => 'Magento_Framework_View_Element_AbstractBlock',
                'Mage_Core_Block_Template' => 'Magento_Framework_View_Element_Template',
                'Mage_Core_Controller_Front_Action' => 'Magento_Framework_App_Action_Action',
                'Mage_Core_' => 'Magento_Framework_',
                'Mage_Adminhtml_Controller_Action' => 'Magento_Backend_App_Action',
                #'Mage_Adminhtml_Block_Widget_Grid' => 'Magento_Backend_Block_Widget_Grid_Extended',
                'Mage_Adminhtml_Block_Catalog_' => 'Magento_Catalog_Block_Adminhtml_',
                'Mage_Adminhtml_Block_Customer_' => 'Magento_Customer_Block_Adminhtml_',
                'Mage_Adminhtml_Block_Sales_' => 'Magento_Sales_Block_Adminhtml_',
                'Mage_Adminhtml_Block_Messages' => 'Magento_Framework_View_Element_Messages',
                'Mage_Adminhtml_Block_Report_Filter_Form' => 'Magento_Reports_Block_Adminhtml_Filter_Form',
                'Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract' => 'Magento_Config_Block_System_Config_Form_Field_FieldArray_AbstractFieldArray',
                'Mage_Adminhtml_Block_Text_List' => 'Magento_Backend_Block_Text_ListText',
                'Mage_Adminhtml_Block_System_Config_' => 'Magento_Config_Block_System_Config_',
                'Mage_Adminhtml_Block_System_Variable' => 'Magento_Variable_Block_System_Variable',
                'Mage_Adminhtml_Block_Checkout_Agreement_' => 'Magento_CheckoutAgreements_Block_Adminhtml_Agreement_',
                'Mage_Adminhtml_Model_System_Config_Source_Country' => 'Magento_Directory_Model_Config_Source_Country',
                'Mage_Adminhtml_Model_System_Config_Source_Allregion' => 'Magento_Directory_Model_Config_Source_Allregion',
                'Mage_Adminhtml_Model_System_Config_Source_' => 'Magento_Config_Model_Config_Source_',
                'Mage_Adminhtml_Model_System_Store' => 'Magento_Store_Model_System_Store',
                'Mage_Adminhtml_Controller_System_Config_System_Storage' => 'Magento_Config_Controller_Adminhtml_System_Config_System_Storage',
                'Mage_Adminhtml_Controller_Checkout_Agreement' => 'Magento_Checkout_Controller_Adminhtml_Agreement',
                'Mage_Adminhtml_' => 'Magento_Backend_',
                'Mage_Admin_Model_Acl' => 'Magento_Framework_Acl',
                'Mage_Admin_Model_Roles' => 'Magento_Authorization_Model_Role',
                'Mage_Admin_Model_Resource_Roles_Collection' => 'Magento_Authorization_Model_ResourceModel_Role_Collection',
                'Mage_Admin_' => 'Magento_Backend_',
                'Mage_MediaStorage_Controller_System_Config_System_Storage' => 'Magento_MediaStorage_Controller_Adminhtml_System_Config_System_Storage',
                'Mage_Page_Model_Source_Layout' => 'Magento_Cms_Model_Page_Source_PageLayout',
                'Mage_Page_' => 'Magento_Framework_',
                'Mage_Rule_Model_Rule' => 'Magento_Rule_Model_AbstractModel',
                'Mage_Usa_Block_Adminhtml_Dhl_' => 'Magento_Dhl_Block_Adminhtml_',
                'Mage_Usa_Model_Shipping_Carrier_Dhl_Abstract' => 'Magento_Dhl_Model_AbstractDhl',
                'Mage_Usa_Model_Shipping_Carrier_Dhl_International_' => 'Magento_Dhl_Model_',
                'Mage_Usa_Model_Shipping_Carrier_Dhl_International' => 'Magento_Dhl_Model_Carrier',
                'Mage_Usa_Model_Simplexml_Element' => 'Magento_Shipping_Model_Simplexml_Element',
                'Mage_Usa_Model_Shipping_Carrier_AbstractCarrier' => 'Magento_Shipping_Model_Carrier_AbstractCarrierOnline',
                'Mage_Usa_Model_Shipping_Carrier_AbstractCarrier_Source_Mode' => 'Magento_Shipping_Model_Config_Source_Online_Mode',
                'Mage_Usa_Model_Shipping_Carrier_AbstractCarrier_Source_Requesttype' => 'Magento_Shipping_Model_Config_Source_Online_Requesttype',
                'Mage_Index_' => 'Magento_Indexer_',
                'Mage_' => 'Magento_',
                'Varien_Io_' => 'Magento_Framework_Filesystem_Io_',
                'Varien_Object' => 'Magento_Framework_DataObject',
                'Varien_' => 'Magento_Framework_',
                '_Model_Mysql4_' => '_Model_ResourceModel_',
                '_Model_Resource_' => '_Model_ResourceModel_',
                '_Model_ResourceModel_Abstract' => '_Model_ResourceModel_AbstractResource',
                'Zend_Json' => 'Zend_Json_Json',
                'Zend_Log' => 'Zend_Log_Logger',
                'Zend_Db' => 'Magento_Framework_Db',
            ],
            'classes_regex' => [
                '#_([A-Za-z0-9]+)_(Abstract|New|List)([^A-Za-z0-9_]|$)#' => '_\1_\2\1\3',
                '#_([A-Za-z0-9]+)_(Interface)([^A-Za-z0-9_]|$)#' => '_\1_\1\2\3',
                '#_Protected(?![A-Za-z0-9_])#' => '_ProtectedCode',
                #'#(Mage_[A-Za-z0-9_]+)_Grid([^A_Za-z0-9_])#' => '\1\2',
                '#Magento_Backend_Block_(Catalog)_#' => 'Magento_\2_Block_Adminhtml_',
                '#Magento_Backend_(Block|Controller)_Promo_Quote#' => 'Magento_SalesRule_\1_Adminhtml_Promo_Quote',
                '#Magento_Backend_(Block|Controller)_Promo_(Catalog|Widget)#' => 'Magento_CatalogRule_\1_Adminhtml_Promo_\2',
                '#Magento_Usa_Model_Shipping_Carrier_(Fedex|Ups|Usps)_#' => 'Magento_\1_Model_',
                '#Magento_Usa_Model_Shipping_Carrier_(Fedex|Ups|Usps)#' => 'Magento_\1_Model_Carrier',
                '#([^A-Za-z0-9_]|^)([A-Za-z0-9]+_[A-Za-z0-9]+_Controller_)([A-Za-z0-9_]+_)?([A-Za-z0-9]+)Controller([^A-Za-z0-9_]|$)#' => '\1\2\3\4_Abstract\4\5',
                '#([^A-Za-z0-9_]|^)([A-Za-z0-9]+_[A-Za-z0-9var]+_)([A-Za-z0-9_]+_)?([A-Za-z0-9]+)Controller([^A-Za-z0-9_]|$)#' => '\1\2Controller_\3\4_Abstract\4\5',
            ],
            'code' => [
                'Mage_Core_Model_Locale::DEFAULT_LOCALE' => '\Magento\Framework\Locale\Resolver::DEFAULT_LOCALE',
                'Mage_Core_Model_Translate::CACHE_TAG' => '\Magento\Framework\App\Cache\Type::CACHE_TAG',

                'Mage::log(' => self::OBJ_MGR . '(\'Psr\Log\LoggerInterface\')->log(',
                'Mage::logException(' => self::OBJ_MGR . '(\'Psr\Log\LoggerInterface\')->error(',
                'Mage::dispatchEvent(' => self::OBJ_MGR . '(\'Magento\Framework\Event\ManagerInterface\')->dispatch(',
                'Mage::app()->getRequest()' => self::OBJ_MGR . '(\'Magento\Framework\App\RequestInterface\')',
                'Mage::app()->getLocale()->getLocaleCode()' => self::OBJ_MGR . '(\'Magento\Framework\Locale\Resolver\')->getLocale()',
                'Mage::app()->getStore(' => self::OBJ_MGR . '(\'Magento\Store\Model\StoreManagerInterface\')->getStore(',
                'Mage::app()->getCacheInstance()->canUse(' => self::OBJ_MGR . '(\'Magento\Framework\App\Cache\StateInterface\')->isEnabled(',
                'Mage::app()->getCacheInstance()' => self::OBJ_MGR . '(\'Magento\Framework\App\CacheInterface\')',
                'Mage::getConfig()->getModuleDir(' => self::OBJ_MGR . '(\'Magento\Framework\Module\Dir\Reader\')->getModuleDir(',
                'Mage::getConfig()->getVarDir(' => self::OBJ_MGR . '(\'Magento\Framework\App\Filesystem\DirectoryList\')->getPath(\'var\') . (',
                'Mage::getConfig()->createDirIfNotExists(' => self::OBJ_MGR . '(\'Magento\Framework\Filesystem\Directory\Write\')->create(',
                'Mage::getStoreConfig(' => self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->getValue(',
                'Mage::getStoreConfigFlag(' => self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->isSetFlag(',
                'Mage::getDesign()' => self::OBJ_MGR . '(\'Magento\Framework\View\DesignInterface\')',
                'Mage::helper(\'core/url\')->getCurrentUrl()' => self::OBJ_MGR . '(\'Magento\Framework\UrlInterface\')->getCurrentUrl()',
                'Mage::getBaseUrl(' => self::OBJ_MGR . '(\'Magento\Framework\UrlInterface\')->getBaseUrl(',
                'Mage::getBaseDir(' => self::OBJ_MGR . '(\'Magento\Framework\Filesystem\')->getDirPath(',
                'Mage::getSingleton(\'admin/session\')->isAllowed(' => self::OBJ_MGR . '(\'Magento\Backend\Model\Auth\Session\')->isAllowed(',
                'Mage::getSingleton(\'adminhtml/session\')->add' => self::OBJ_MGR . '(\'Magento\Framework\Message\ManagerInterface\')->add',
                'Mage::throwException(' => 'throw new \Exception(',
                ' extends Exception' => ' extends \Exception',
                '$this->getResponse()->setBody(' => self::OBJ_MGR . '(\'Magento\Framework\Controller\Result\RawFactory\')->create()->setContents(',
                '$this->getLayout()' => self::OBJ_MGR . '(\'Magento\Framework\View\LayoutFactory\')->create()',
                '$this->_redirect(' => self::OBJ_MGR . '(\'Magento\Framework\Controller\Result\RedirectFactory\')->create()->setPath(',
                '$this->_forward(' => self::OBJ_MGR . '(\'Magento\Backend\Model\View\Result\ForwardFactory\')->create()->forward(',
                //TODO: Need help with:
                #'Mage::app()->getConfig()->getNode(' => '',
            ],
            'code_regex' => [
                '#(Mage::helper\([\'"][A-Za-z0-9/_]+[\'"]\)|\$this)->__\(#' => '__(',
                '#Mage::(registry|register|unregister)\(#' => self::OBJ_MGR . '(\'Magento\Framework\Registry\')->\1(',
                '#Mage::helper\(\'core\'\)->(encrypt|decrypt|getHash|hash|validateHash)\(#' => self::OBJ_MGR . '(\'Magento\Framework\Encryption\Encryptor\')->\1(',
                '#Mage::getConfig\(\)->getNode\(([\'"][^)]+[\'"])\)#' => self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->getValue(\1\2\3, \'default\')',
                '#Zend_Validate::is\(([^,]+),\s*[\'"]([A-Za-z0-9]+)[\'"]\)#' => '(new \Zend\Validator\\\\\2())->isValid(\1)',
            ],
            'acl_keys' => [
                'admin' => 'Magento_Backend::admin',
                'admin/sales' => 'Magento_Sales::sales',
                'admin/reports' => 'Magento_Reports::report',
                'admin/system' => 'Magento_Backend::stores',
                'admin/system/config' => ['Magento_Backend::stores_settings', 'Magento_Config::config'],
            ],
            'menu' => [
                'sales' => 'Magento_Sales::sales',
                'report' => 'Magento_Reports:report',
            ],
            'files_regex' => [
                '#/Model/(Mysql4|Resource)/#' => '/Model/ResourceModel/',
                '#/Model/ResourceModel/Abstract#' => '/Model/ResourceModel/AbstractResource',
                '#/Protected\.php#' => '/ProtectedCode.php',
                '#/([A-Za-z0-9]+)/(Abstract|New|List)([^A-Za-z0-9/]|$)#' => '/\1/\2\1\3',
                '#/([A-Za-z0-9]+)/(Interface)([^A-Za-z0-9/]|$)#' => '/\1/\1\2\3',
            ],
        ];
    }

    protected $_currentFile;

    protected $_autoloadMode = 'm1';

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

    public function autoloadCallback($class)
    {
        if (!empty($this->_classFileCache[$class])) {
            return;
        }
        $m1Pools = [
            "{$this->_env['mage1_dir']}/lib",
            "{$this->_env['mage1_dir']}/app/code/core",
            "{$this->_env['mage1_dir']}/app/code/community",
            "{$this->_env['mage1_dir']}/app/code/local",
        ];
        $m2Pools = [
            "{$this->_env['mage2_dir']}/lib/internal",
            "{$this->_env['mage2_code_dir']}",
        ];
        switch ($this->_autoloadMode) {
            case 'm1':
                $pools = $m1Pools;
                break;

            case 'm2':
                $pools = $m2Pools;#array_merge($m2Pools, $m1Pools);
                break;

            default:
                $pools = [];
        }
        foreach ($pools as $pool) {
            $classFile = str_replace(['_', '\\'], ['/', '/'], $class);
            $filename  = "{$pool}/{$classFile}.php";
            if (file_exists($filename)) {
                include_once $filename;
                $this->_classFileCache[$class] = $filename;
                return;
            }
        }
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
        $this->_convertAllMigrations();
        $this->_convertAllLayouts();
        $this->_convertAllTemplates();
        $this->_convertAllWebAssets();
        $this->_convertAllI18n();
        $this->_convertAllOtherFiles();
        $this->_convertAllPhpFilesDI();

        $this->log("[SUCCESS] FINISHED: {$extName}")->log('');

        return $this;
    }

    public function log($msg, $continue = false)
    {
        static $htmlColors = [
            'ERROR' => 'red',
            'WARN' => 'orange',
            'INFO' => 'black',
            'DEBUG' => 'gray',
            'SUCCESS' => 'green',
        ];
        if (!is_scalar($msg)) {
            $msg = print_r($msg, 1);
        }
        if (!$continue) {
            echo "\n";
        }
        if (empty($msg)) {
            return $this;
        }
        if ('cli' === PHP_SAPI) {
            echo '[' . date("Y-m-d H:i:s") . ']' . $msg;
        } else {
            preg_match('#\[([A-Z]+)\]#', $msg, $type);
            echo '<span style="color:' . $htmlColors[$type[1]] . '">';
            echo '[' . date("Y-m-d H:i:s") . ']' . $msg;
            echo '</span>';
        }

        return $this;
    }

    protected function _expandSourcePath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_root_dir'] . '/' . $path;
        }
        $target = 'app/code/' . $this->_env['ext_pool'] . '/' . str_replace('_', '/', $this->_env['ext_name']) . '/';
        $path   = str_replace('@EXT/', $target, $path);
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
        $this->_currentFile = [
            'filename' => $filename,
        ];

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
        $iter    = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);
        $files   = [];
        foreach ($iter as $file) {
            if (!is_dir((string)$file)) {
                $files[] = str_replace($dir . '/', '', str_replace('\\', '/', (string)$file));
            }
        }
        return $files;
    }

    protected function _collectCoreModulesConfigs()
    {
        $this->log("[INFO] COLLECTING M1 CONFIGURATION...")->log('');
        $configFiles = glob($this->_env['mage1_dir'] . '/app/code/*/*/*/etc/config.xml');
        foreach ($configFiles as $file) {
            $xml = simpledom_load_file($file);
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
            $xml    = simpledom_load_file($file);
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
                $this->log('[WARN] Unknown module key: ' . $type . ' :: ' . $moduleKey);
                return 'UNKNOWN\\' . $moduleKey . '\\' . $classKey;
            }
        }

        $classKeyCapped = strtr(ucwords(strtr($classKey, ['_' => ' '])), [' ' => '_']);
        $className = $this->_aliases[$type][$moduleKey] . '_' . $classKeyCapped;

        if ($m2) {
            $classTr      = $this->_replace['classes'];
            $className    = str_replace(array_keys($classTr), array_values($classTr), $className);
            $classRegexTr = $this->_replace['classes_regex'];
            $className    = preg_replace(array_keys($classRegexTr), array_values($classRegexTr), $className);
            $className    = str_replace('_', '\\', $className);
        }

        return $className;
    }

    ///////////////////////////////////////////////////////////

    protected function _convertGenerateMetaFiles()
    {
        $this->_convertGenerateComposerFile();
        $this->_convertGenerateRegistrationFile();
    }

    protected function _convertGenerateComposerFile()
    {
        $xml1    = $this->_readFile("@EXT/etc/config.xml", true);
        $extName = $this->_env['ext_name'];
        $version = !empty($xml1->modules->{$extName}->version) ? (string)$xml1->modules->{$extName}->version : '0.0.1';

        $data = [
            'name' => str_replace('_', '/', $extName),
            'description' => '',
            'require' => [
                'php' => '~5.5.0|~5.6.0',
            ],
            'type' => 'magento2-module',
            'version' => $version,
            'license' => [
                'Proprietary'
            ],
            'autoload' => [
                'files' => [
                    'registration.php',
                ],
                'psr-4' => [
                    str_replace('_', '\\', $extName) . '\\' => '',
                ],
            ],
            'extra' => [
                'map' => [
                    [
                        '*',
                        str_replace('_', '/', $extName)
                    ]
                ]
            ]
        ];
        $this->_writeFile('composer.json', json_encode($data, JSON_PRETTY_PRINT), true);
    }

    protected function _convertGenerateRegistrationFile()
    {
        $regCode = <<<EOT
<?php

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    '{$this->_env['ext_name']}',
    __DIR__
);

EOT;

        $this->_writeFile('registration.php', $regCode, true);
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
            if ('etc' === $basename || 'controllers' === $basename || 'sql' === $basename || 'Observer.php' === $basename) {
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
        sort($files);
        $targetDir = $this->_expandOutputPath($folder);
        $fromName = array_keys($this->_replace['files_regex']);
        $toName = array_values($this->_replace['files_regex']);
        foreach ($files as $filename) {
            $contents = $this->_readFile("{$dir}/{$filename}");
            $targetFile = "{$targetDir}/{$filename}";
            if ($callback) {
                $params = ['source_file' => $filename, 'target_file' => &$targetFile];
                $contents = call_user_func($callback, $contents, $params);
            } else {
                $contents = $this->_convertCodeContents($contents);
                $targetFile = preg_replace($fromName, $toName, $targetFile);
            }
            $this->_writeFile($targetFile, $contents);
        }
    }

    protected function _convertCodeContents($contents, $mode = 'php')
    {
        $this->_currentFile = [
            'filename' => $this->_currentFile['filename'],
        ];


        if (preg_match('#(\\r\\n|\\r|\\n)#', $contents, $m)) {
            $this->_currentFile['nl'] = $m[0];
        } else {
            $this->_currentFile['nl'] = "\r\n";
        }

        // Replace code snippets
        $codeTr = $this->_replace['code'];
        $contents = str_replace(array_keys($codeTr), array_values($codeTr), $contents);
        $codeTr = $this->_replace['code_regex'];
        $contents = preg_replace(array_keys($codeTr), array_values($codeTr), $contents);

        if ($mode === 'php') {
            $contents = $this->_convertCodeContentsPhpMode($contents);
        }
        if ($mode === 'phtml') {
            $contents = str_replace(self::OBJ_MGR . '(\'Magento\Framework\View\LayoutFactory\')->create()',
                '$block->getLayout()', $contents);
        }

        // convert block name to block class
        $contents = preg_replace_callback('#(->createBlock\([\'"])([^\'"]+)([\'"]\))#', function($m) {
            return $m[1] . $this->_getClassName('blocks', $m[2]) . $m[3];
        }, $contents);

        // Replace getModel|getSingleton|helper calls with ObjectManager::get calls
        $re = '#(Mage::getModel|Mage::getResourceModel|Mage::getSingleton|Mage::helper|\$this->helper)\([\'"]([a-zA-Z0-9/_]+)[\'"]\)#';
        $contents = preg_replace_callback($re, function($m) {
            $classKey = $m[2];
            if (strpos($m[1], 'helper') !== false) {
                $class = $this->_getClassName('helpers', $classKey, false);
            } else {
                if (strpos($m[1], 'getResourceModel') !== false) {
                    list($modKey, $clsKey) = explode('/', $classKey);
                    if (!empty($this->_aliases['models']["{$modKey}_resource"])) {
                        $classKey = "{$modKey}_resource/{$clsKey}";
                    } elseif (!empty($this->_aliases['models']["{$modKey}_mysql4"])) {
                        $classKey = "{$modKey}_mysql4/{$clsKey}";
                    }
                }
                $class = $this->_getClassName('models', $classKey, false);
            }
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

        // add template prefix for files existing in module
        $contents = preg_replace_callback('#(->setTemplate\([\'"])([A-Za-z0-9_/.]+)([\'"]\))#', function($m) {
            $pattern = "{$this->_env['ext_root_dir']}/app/design/*/*/*/template/{$m[2]}";
            $file = glob($pattern);
            if ($file) {
                return "{$m[1]}{$this->_env['ext_name']}::{$m[2]}{$m[3]}";
            } else{
                return $m[0];
            }
        }, $contents);

        // Add namespace to class declarations
        $classPattern = '#^((final|abstract)\s+)?class \\\\([A-Z][\\\\A-Za-z0-9]+)\\\\([A-Za-z0-9]+)((\s+)(extends|implements)\s|\s*$)?#ms';
        #$contents = preg_replace($classPattern, "namespace \$3;\n\n\$1\$2class \$4\$5", $contents);
        if (preg_match($classPattern, $contents, $m)) {
            $this->_currentFile['namespace'] = $m[3];
            $this->_currentFile['class'] = $m[3] . '\\' . $m[4];
            $contents  = str_replace($m[0], "namespace {$m[3]};\n\n{$m[1]}class {$m[4]}{$m[5]}", $contents);
        }

        return $contents;
    }

    protected function _convertCodeContentsPhpMode($contents)
    {
        // Replace $this->_init() in models and resources with class names and table names
        $contents = preg_replace_callback('#(\$this->_init\([\'"])([A-Za-z0-9_/]+)([\'"])#', function ($m) {
            $filename = $this->_currentFile['filename'];
            $cls = explode('/', $m[2]);
            if (!empty($this->_aliases['models']["{$cls[0]}_resource"]) && !empty($cls[1])) {
                $resKey = "{$cls[0]}_resource/{$cls[1]}";
            } elseif (!empty($this->_aliases['models']["{$cls[0]}_mysql4"]) && !empty($cls[1])) {
                $resKey = "{$cls[0]}_mysql4/{$cls[1]}";
            } else {
                $resKey = false;
            }
            if (preg_match('#/Model/(Mysql4|Resource)/.*/Collection\.php$#', $filename)) {
                if ($resKey) {
                    $model    = $this->_getClassName('models', $m[2], true);
                    $resModel = $this->_getClassName('models', $resKey, true);
                    return $m[1] . $model . $m[3] . ', ' . $m[3] . $resModel . $m[3];
                } else {
                    $this->log("[WARN] No resource model for {$m[2]}");
                    return $m[0];
                }
            } elseif (preg_match('#/Model/(Mysql4|Resource)/#', $filename)) {
                return $m[1] . str_replace('/', '_', $m[2]) . $m[3]; //TODO: try to figure out original table name
            } elseif (preg_match('#/Model/#', $filename)) {
                if ($resKey) {
                    $resModel = $this->_getClassName('models', $resKey, true);
                    return $m[1] . $resModel . $m[3];
                } else {
                    $this->log("[WARN] No resource model for {$m[2]}");
                    return $m[0];
                }
            } else {
                return $m[0];
            }
        }, $contents);

        return $contents;
    }

    protected function _convertCodeParseMethods($contents, $fileType = false, $returnResult = false)
    {
        $nl = $this->_currentFile['nl'];

        $lines = preg_split('#\r?\n#', $contents);
        $linesCnt = sizeof($lines);

        // Find start of the class
        $classStart = null;
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+[A-Za-z0-9_]+(\s+(extends|implements)\s+|\s*$)#';
        for ($i = 0; $i < $linesCnt; $i++) {
            if (preg_match($classStartRe, $lines[$i])) {
                $classStart = $i;
                break;
            }
        }
        if (null === $classStart) { // not a class
            if ($returnResult) {
                return [];
            } else {
                $this->_currentFile['methods'] = [];
                $this->_currentFile['lines']   = $lines;
                return $contents;
            }
        }

        // Find starts of all methods
        $methods = [];
        $methodStartRe = '#(public|protected|private)\s+(static\s+)?function\s+([a-zA-Z0-9_]+?)?\(#';
        for ($i = 0; $i < $linesCnt; $i++) {
            if (preg_match($methodStartRe, $lines[$i], $m)) {
                $method = ['name' => $m[3], 'start' => $i, 'code_start' => $i];
                if (preg_match('#\}\s*$#', $lines[$i])) {
                    $method['end'] = $i;
                }
                $methods[] = $method;
            }
        }
        if (!$methods) {
            if ($returnResult) {
                return [];
            } else {
                $this->_currentFile['methods'] = [];
                $this->_currentFile['lines']   = $lines;
                return $contents;
            }
        }

        $lastMethodIdx = sizeof($methods) - 1;

        // Find end of the last method
        $pastEndOfClass = null;
        for ($i = $linesCnt - 1; $i > 0; $i--) {
            if (preg_match('#^\s*\}\s*$#', $lines[$i])) {
                if (!$pastEndOfClass) {
                    $pastEndOfClass = true;
                } else {
                    $methods[$lastMethodIdx]['end'] = $i;
                    break;
                }
            }
        }

        // Find phpdocs and ends of rest of the methods
        for ($i = $lastMethodIdx; $i >= 0; $i--) {
            $method =& $methods[$i];
            if (empty($method['end'])) {
                if (preg_match('#\s+abstract\s+#', $lines[$method['start']])) {
                    $method['end'] = $method['start'];
                } else {
                    for ($j = $methods[$i + 1]['start'] - 1; $j > $method['start']; $j--) {
                        if (preg_match('#^\s*(\{\s*)?\}\s*$#', $lines[$j])) {
                            $method['end'] = $j;
                            break;
                        }
                    }
                }
            }
        }
        for ($i = $lastMethodIdx; $i >= 0; $i--) {
            $method =& $methods[$i];
            for ($j = $method['start'] - 1; $j > ($i ? $methods[$i - 1]['end'] : $classStart); $j--) {
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

        // Find each method contents and remove controller actions from $lines if requested
        for ($i = $lastMethodIdx; $i >= 0; $i--) {
            $method =& $methods[$i];
            $length = $method['end'] - $method['start'] + 1;
            if ($fileType === 'controller' && preg_match('#Action$#', $method['name'])) {
                $method['lines'] = array_splice($lines, $method['start'], $length);
            } elseif ($fileType === 'observer' && preg_match('#^[A-Za-z]+_#', $method['name'])) {
                $method['lines'] = array_splice($lines, $method['start'], $length);
            } else {
                $method['lines'] = array_slice($lines, $method['start'], $length);
            }
        }
        unset($method);

        if ($fileType === 'controller' || $fileType === 'observer') {
            $contents = join($nl, $lines);
            $contents = preg_replace('#^(\s*)(class\s+.*)$#m', '$1abstract $2', $contents);
        }
        if ($returnResult) {
            return $methods;
        }

        $this->_currentFile['methods'] = $methods;
        $this->_currentFile['lines'] = $lines;
        return $contents;
    }

    protected function _convertCodeObjectManagerToDI($contents)
    {
        $objMgrRe = preg_quote(self::OBJ_MGR, '#');
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
                $useLines[] = 'use ' . $class . ($useAs ? ' as ' . $alias : '') . ';';
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

    ///////////////////////////////////////////////////////////

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

    ///////////////////////////////////////////////////////////

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

    ///////////////////////////////////////////////////////////

    protected function _convertAllMigrations()
    {

    }

    ///////////////////////////////////////////////////////////

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
