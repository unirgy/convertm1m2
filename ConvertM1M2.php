<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 Boris Gurvich
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class ConvertM1M2
{
    protected $_env = [];

    protected $_fileCache = [];

    protected $_classFileCache = [];

    protected $_aliases = [
        'models' => [
            'catalog_resource_eav_mysql4' => 'Mage_Catalog',
            'customer_entity' => 'Mage_Customer',
            'review_mysql4' => 'Mage_Review',
        ],
        'blocks' => [
            'centinel' => 'Mage_Centinel',
        ]
    ];

    protected $_layouts = [];

    const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    const OBJ_MGR = '\Magento\Framework\App\ObjectManager::getInstance()->get';

    // Sources: http://mage2.ru, https://wiki.magento.com/display/MAGE2DOC/Class+Mage
    protected $_replace;

    protected $_reservedWordsRe = '
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
'; // Example of use: #^( ... )$#ix

    protected $_skipConvertToDiRegex = '#(\\\\Session$)#';

    protected $_currentFile;

    protected $_autoloadMode = 'm1';

    public function getReplaceMaps()
    {
        $nl = "\n";
        return [
            'modules' => [
                'Mage_Adminhtml' => 'Magento_Backend',
            ],
            'classes' => [
                'Mage_Core_Helper_Abstract' => 'Magento_Framework_App_Helper_AbstractHelper',
                'Mage_Core_Helper_Data' => 'Magento_Framework_App_Helper_AbstractHelper',
                'Mage_Core_Helper_' => 'Magento_Framework_App_Helper_',
                'Mage_Core_Model_Abstract' => 'Magento_Framework_Model_AbstractModel',
                'Mage_Core_Model_Mysql4_Abstract' => 'Magento_Framework_Model_ResourceModel_Db_AbstractDb',
                'Mage_Core_Model_Mysql4_Db_Abstract' => 'Magento_Framework_Model_ResourceModel_Db_AbstractDb',
                'Mage_Core_Model_Mysql4_Collection_Abstract' => 'Magento_Framework_Model_ResourceModel_Db_Collection_AbstractCollection',
                'Mage_Core_Model_Mysql4_Config' => 'Magento_Config_Model_ResourceModel_Config',
                'Mage_Core_Model_Mysql4_Design' => 'Magento_Theme_Model_ResourceModel_Design',
                'Mage_Core_Model_Mysql4_Design_Collection' => 'Magento_Theme_Model_ResourceModel_Design_Collection',
                'Mage_Core_Model_Mysql4_Flag' => 'Magento_Framework_Flag_FlagResource',
                'Mage_Core_Model_Mysql4_Iterator' => 'Magento_Framework_Model_ResourceModel_Iterator',
                'Mage_Core_Model_Mysql4_Resource' => 'Magento_Framework_Module_ModuleResource',
                'Mage_Core_Model_Mysql4_Session' => 'Magento_Framework_Session_SaveHandler_DbTable',
                'Mage_Core_Model_Mysql4_Translate' => 'Magento_Translation_Model_ResourceModel_Translate',
                'Mage_Core_Model_Mysql4_Translate_String' => 'Magento_Translation_Model_ResourceModel_StringUtils',
                'Mage_Core_Model_Mysql4_Url_Rewrite_Collection' => 'Magento_UrlRewrite_Model_ResourceModel_UrlRewriteCollection',
                'Mage_Core_Model_Mysql4_Config_' => 'Magento_Config_Model_ResourceModel_Config_',
                'Mage_Core_Model_Config_Data' => 'Magento_Framework_App_Config_Value',
                'Mage_Core_Model_Config_' => 'Magento_Framework_App_Config_',
                'Mage_Core_Model_Resource_Setup' => 'Magento_Framework_Module_Setup',
                'Mage_Core_Model_Resource_Config_' => 'Magento_Config_Model_ResourceModel_Config_',
                'Mage_Core_Model_Session_Abstract' => 'Magento_Framework_Session_SessionManager',
                'Mage_Core_Model_Translate_String' => 'Magento_Translation_Model_StringUtils',
                'Mage_Core_Block_Abstract' => 'Magento_Framework_View_Element_AbstractBlock',
                'Mage_Core_Block_Template' => 'Magento_Framework_View_Element_Template',
                'Mage_Core_Controller_Front_Action' => 'Magento_Framework_App_Action_Action',
                'Mage_Core_' => 'Magento_Framework_',
                'Mage_Adminhtml_Controller_Action' => 'Magento_Backend_App_Action',
                #'Mage_Adminhtml_Block_Widget_Grid' => 'Magento_Backend_Block_Widget_Grid_Extended',
                'Mage_Adminhtml_Block_Messages' => 'Magento_Framework_View_Element_Messages',
                'Mage_Adminhtml_Block_Report_Filter_Form' => 'Magento_Reports_Block_Adminhtml_Filter_Form',
                'Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract' => 'Magento_Config_Block_System_Config_Form_Field_FieldArray_AbstractFieldArray',
                'Mage_Adminhtml_Block_Text_List' => 'Magento_Backend_Block_Text_ListText',
                'Mage_Adminhtml_Block_System_Config_' => 'Magento_Config_Block_System_Config_',
                'Mage_Adminhtml_Block_System_Variable' => 'Magento_Variable_Block_System_Variable',
                'Mage_Adminhtml_Block_Checkout_Agreement_' => 'Magento_CheckoutAgreements_Block_Adminhtml_Agreement_',
                'Mage_Adminhtml_Model_System_Config_Backend_' => 'Magento_Config_Model_Config_Backend_',
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
                'Mage_Sales_Model_Quote' => 'Magento_Quote_Model_Quote',
                'Mage_Usa_Block_Adminhtml_Dhl_' => 'Magento_Dhl_Block_Adminhtml_',
                'Mage_Usa_Model_Shipping_Carrier_Dhl_International_' => 'Magento_Dhl_Model_',
                'Mage_Usa_Model_Shipping_Carrier_Dhl_International' => 'Magento_Dhl_Model_Carrier',
                'Mage_Usa_Model_Shipping_Carrier_AbstractCarrier' => 'Magento_Shipping_Model_Carrier_AbstractCarrierOnline',
                'Mage_Usa_Model_Shipping_Carrier_AbstractCarrier_Source_Mode' => 'Magento_Shipping_Model_Config_Source_Online_Mode',
                'Mage_Usa_Model_Shipping_Carrier_AbstractCarrier_Source_Requesttype' => 'Magento_Shipping_Model_Config_Source_Online_Requesttype',
                'Mage_Usa_Model_Simplexml_Element' => 'Magento_Shipping_Model_Simplexml_Element',
                'Mage_Index_' => 'Magento_Indexer_',
                'Mage_' => 'Magento_',
                'Varien_Io_' => 'Magento_Framework_Filesystem_Io_',
                'Varien_Object' => 'Magento_Framework_DataObject',
                'Varien_' => 'Magento_Framework_',
                '_Model_Mysql4_' => '_Model_ResourceModel_',
                '_Model_Resource_' => '_Model_ResourceModel_',
                '_Model_ResourceModel_Abstract' => '_Model_ResourceModel_AbstractResource',
                '_Model_ResourceModel_Eav_Mysql4_' => '_Model_ResourceModel_',
                'Zend_Json' => 'Zend_Json_Json',
                'Zend_Log' => 'Zend_Log_Logger',
                'Zend_Db_Adapter_Abstract' => 'Magento_Framework_DB_Adapter_AdapterInterface',
                'Zend_Db_Expr' => '\\Zend_Db_Expr',
            ],
            'classes_regex' => [
                '#_([A-Za-z0-9]+)_(Abstract|New|List)([^A-Za-z0-9_]|$)#' => '_\1_\2\1\3',
                '#_([A-Za-z0-9]+)_(Interface)([^A-Za-z0-9_]|$)#' => '_\1_\1\2\3',
                '#_Protected(?![A-Za-z0-9_])#' => '_ProtectedCode',
                '#(Mage_[A-Za-z0-9_]+)_Grid([^A_Za-z0-9_])#' => '\1\2',
                '#Magento_Backend_Block_(Catalog|Customer|Sales)_#' => 'Magento_\1_Block_Adminhtml_',
                '#Magento_Backend_(Block|Controller)_Promo_Quote#' => 'Magento_SalesRule_\1_Adminhtml_Promo_Quote',
                '#Magento_Backend_(Block|Controller)_Promo_(Catalog|Widget)#' => 'Magento_CatalogRule_\1_Adminhtml_Promo_\2',
                '#Magento_Backend_Block_Widget_Form([^A-Za-z0-9_]|$)#' => 'Magento_Backend_Block_Widget_Form_Generic\1',
                '#Magento_Usa_Model_Shipping_Carrier_(Fedex|Ups|Usps)_#' => 'Magento_\1_Model_',
                '#Magento_Usa_Model_Shipping_Carrier_(Fedex|Ups|Usps)#' => 'Magento_\1_Model_Carrier',
                '#Magento_Shipping_Model_Rate_Result_(Method|Error)#' => 'Magento_Quote_Model_Quote_Address_RateResult_\1',
                '#([^A-Za-z0-9_]|^)([A-Za-z0-9]+_[A-Za-z0-9]+_Controller_)([A-Za-z0-9_]+_)?([A-Za-z0-9]+)Controller([^A-Za-z0-9_]|$)#' => '\1\2\3\4_Abstract\4\5',
                '#([^A-Za-z0-9_]|^)([A-Za-z0-9]+_[A-Za-z0-9]+_)([A-Za-z0-9_]+_)?([A-Za-z0-9]+)Controller([^A-Za-z0-9_]|$)#' => '\1\2Controller_\3\4_Abstract\4\5',
                '#Magento_Framework(_Model(_ResourceModel)?_(Store|Website))#' => 'Magento_Store\1',
                '#Magento_Framework(_Model(_ResourceModel)?)_(Email|Variable)#' => 'Magento_\3\1',
                '#Magento_Framework(_Model(_ResourceModel)?)_Url_Rewrite#' => 'Magento_UrlRewrite\1_UrlRewrite',
                "#([A-Za-z0-9]+_[A-Za-z0-9]+_)([A-Za-z0-9]+)_({$this->_reservedWordsRe})(?![A-Za-z0-9])#ix" => '\1\2_\3\2',
                '#([^A-Za-z0-9_\\\\]|^)Zend_Db_#' => '\1Magento_Framework_DB_',
            ],
            'code' => [
                'Mage_Core_Model_Locale::DEFAULT_LOCALE' => '\Magento\Framework\Locale\Resolver::DEFAULT_LOCALE',
                'Mage_Core_Model_Locale::FORMAT_TYPE_' => '\IntlDateFormatter::',
                'Mage_Core_Model_Translate::CACHE_TAG' => '\Magento\Framework\App\Cache\Type::CACHE_TAG',

                'Mage::log(' => self::OBJ_MGR . '(\'Psr\Log\LoggerInterface\')->debug(',
                'Mage::logException(' => self::OBJ_MGR . '(\'Psr\Log\LoggerInterface\')->error(',
                'Mage::dispatchEvent(' => self::OBJ_MGR . '(\'Magento\Framework\Event\ManagerInterface\')->dispatch(',
                'Mage::app()->getLayout()' => self::OBJ_MGR . '(\'Magento\Framework\View\Layout\')',
                'Mage::app()->getRequest()' => self::OBJ_MGR . '(\'Magento\Framework\App\RequestInterface\')',
                'Mage::app()->getCache()' => self::OBJ_MGR . '(\'Magento\Framework\App\Cache\Proxy\')',
                'Mage::app()->loadCache(' => self::OBJ_MGR . '(\'Magento\Framework\App\CacheInterface\')->load(',
                'Mage::app()->saveCache(' => self::OBJ_MGR . '(\'Magento\Framework\App\CacheInterface\')->save(',
                'Mage::app()->useCache(' => self::OBJ_MGR . '(\'Magento\Framework\App\Cache\StateInterface\')->isEnabled(',
                'Mage::app()->getCacheInstance()->canUse(' => self::OBJ_MGR . '(\'Magento\Framework\App\Cache\StateInterface\')->isEnabled(',
                'Mage::app()->getCacheInstance()' => self::OBJ_MGR . '(\'Magento\Framework\App\CacheInterface\')',
                'Mage::getConfig()->getModuleDir(' => self::OBJ_MGR . '(\'Magento\Framework\Module\Dir\Reader\')->getModuleDir(',
                'Mage::getConfig()->getVarDir(' => self::OBJ_MGR . '(\'Magento\Framework\App\Filesystem\DirectoryList\')->getPath(\'var\') . (',
                'Mage::getConfig()->createDirIfNotExists(' => self::OBJ_MGR . '(\'Magento\Framework\Filesystem\Directory\Write\')->create(',
                'Mage::getConfig()->reinit()' => self::OBJ_MGR . '(\'Magento\Framework\App\Config\ReinitableConfigInterface\')->reinit()',
                'Mage::getDesign()' => self::OBJ_MGR . '(\'Magento\Framework\View\DesignInterface\')',
                'Mage::helper(\'core/url\')->getCurrentUrl()' => self::OBJ_MGR . '(\'Magento\Framework\UrlInterface\')->getCurrentUrl()',
                'Mage::getBaseUrl(' => self::OBJ_MGR . '(\'Magento\Framework\UrlInterface\')->getBaseUrl(',
                'Mage::getSingleton(\'admin/session\')->isAllowed(' => self::OBJ_MGR . '(\'Magento\Backend\Model\Auth\Session\')->isAllowed(',
                'Mage::getSingleton(\'adminhtml/session\')->add' => self::OBJ_MGR . '(\'Magento\Framework\Message\ManagerInterface\')->add',
                'Mage::throwException(' => 'throw new \Exception(',
                'Mage::getVersion()' => self::OBJ_MGR . '(\'Magento\Framework\App\ProductMetadataInterface\')->getVersion()',
                'throw new Exception' => 'throw new \Exception',
                ' extends Exception' => ' extends \Exception',
                '$this->getResponse()->setBody(' => self::OBJ_MGR . '(\'Magento\Framework\Controller\Result\RawFactory\')->create()->setContents(',
                '$this->getLayout()' => self::OBJ_MGR . '(\'Magento\Framework\View\LayoutFactory\')->create()',
                '$this->redirect(' => 'return ' . self::OBJ_MGR . '(\'Magento\Framework\Controller\Result\RedirectFactory\')->create()->setPath(',
                '$this->forward(' => self::OBJ_MGR . '(\'Magento\Backend\Model\View\Result\ForwardFactory\')->create()->forward(',
                '->getReadConnection(' => '->getConnection(',
                '->getWriteConnection(' => '->getConnection(',
            ],
            'code_regex' => [
                '#(Mage::helper\([\'"][A-Za-z0-9/_]+[\'"]\)|\$this)->__\(#' => '__(',
                '#Mage::(registry|register|unregister)\(#' => self::OBJ_MGR . '(\'Magento\Framework\Registry\')->\1(',
                '#Mage::helper\([\'"]core[\'"]\)->(encrypt|decrypt|getHash|hash|validateHash)\(#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Encryption\Encryptor\')->\1(',
                '#Mage::helper\([\'"]core[\'"]\)->(jsonEncode)\(#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Json\EncoderInterface\')->\1(',
                '#Mage::helper\([\'"]core[\'"]\)->(decorateArray)\(#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Stdlib\ArrayUtils\')->\1(',
                '#Mage::getConfig\(\)->getNode\(([\'"][^)]+[\'"])\)#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->getValue(\1\2\3, \'default\')',
                '#Zend_Validate::is\(([^,]+),\s*[\'"]([A-Za-z0-9]+)[\'"]\)#' => '(new \Zend\Validator\\\\\2())->isValid(\1)',
                '#Mage::app\(\)->getConfig\(\)->getNode\([\'"]modules/([A-Za-z0-9]+_[A-Za-z0-9]+)/version[\'"]\)#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Module\ModuleListInterface\')->getOne(\'\1\')["setup_version"]',
                '#Mage::app\(\)->getConfig\(\)->createDirIfNotExists\(([^)]+)\);#' =>
                    'if (' . self::OBJ_MGR . '(\'Magento\Framework\Filesystem\DriverInterface\')->isExists(\1)) {' . $nl
                    . self::OBJ_MGR . '(\'Magento\Framework\Filesystem\DriverInterface\')->createDirectory(\1, 0775);'
                    . $nl . '}',
                '#(}\s*catch\s*\()(Exception\s+)#' => '\1\\\\\2',
                '#Mage::app\(\)\s*->getLocale\(\)\s*->(getLocale|emulate|revert)(Code)?\(#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Locale\Resolver\')->\1(',
                '#Mage::app\(\)\s*->getLocale\(\)\s*->(date|getDateFormat|getDateFormatWithLongYear|getTimeFormat'
                    . '|getDateTimeFormat|storeTimeStamp)\(#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Stdlib\DateTime\TimezoneInterface\')->\1(',
                '#Mage::getBaseDir\(([\'"][a-z]+[\'"])\)#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\Filesystem\')->getDirectoryWrite(\1)->getAbsolutePath()',
                '#Mage::app\(\)->(isSingleStoreMode|getDefaultStoreView)\(\)#' =>
                    self::OBJ_MGR . '(\'Magento\Store\Model\StoreManagerInterface\')->\1()',
                '#Mage::app\(\)->(setIsSingleStoreModeAllowed|hasSingleStore|isSingleStoreMode|getStores?|getWebsites?'
                    . '|reinitStores|getDefaultStoreView|getGroups?|setCurrentStore)\(#' =>
                    self::OBJ_MGR . '(\'Magento\Store\Model\StoreManagerInterface\')->\1(',
                '#([^A-Za-z0-9])DS([^A-Za-z0-9])#' => '\1DIRECTORY_SEPARATOR\2',
                '#Mage::getStoreConfig\(([^,\)]+)#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->getValue(\1, \Magento\Store\Model\ScopeInterface::SCOPE_STORE',
                '#Mage::getStoreConfigFlag\(([^,\)]+)#' =>
                    self::OBJ_MGR . '(\'Magento\Framework\App\Config\ScopeConfigInterface\')->isSetFlag(\1, \Magento\Store\Model\ScopeInterface::SCOPE_STORE',
                '#(<' . 'script.*?>(\s*//\s*<!\[CDATA\[\s*)?)([\s\S]+?)((\s*//\s*\]\]>\s*)?</' . 'script>)#' =>
                    '\1' . $nl . 'require(["jquery", "prototype"], function(jQuery) {' . $nl . '\3' . $nl . '});' . $nl . '\4',
                '#^interface\s+([\\\\A-Za-z0-9_]+)[\\\\_]([A-Za-z0-9]+)\s*\{#m' => "namespace \\1;\r\n\r\ninterface \\2\r\n{",
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
                '#(/Model/)(Mysql4|Resource)/#' => '\1ResourceModel/',
                '#(/Model/ResourceModel/Abstract)(\.php)#' => '\1Resource\2',
                '#(/Protected)(\.php)#' => '\1Code\2',
                '#/([A-Za-z0-9]+)/(Abstract|New|List)(\.php)#' => '/\1/\2\1\3',
                '#/([A-Za-z0-9]+)/(Interface)(\.php)#' => '/\1/\1\2\3',
                "#/([A-Za-z0-9]+)/({$this->_reservedWordsRe})(?![A-Za-z0-9])#ix" => '/\1/\2\1',
            ],
        ];
    }

    public function __construct($sourceDir, $mage1Dir, $mage2Dir)
    {
        $this->_env['source_dir']     = str_replace('\\', '/', $sourceDir);
        $this->_env['mage1_dir']      = str_replace('\\', '/', $mage1Dir);
        $this->_env['mage2_dir']      = str_replace('\\', '/', $mage2Dir);
        $this->_env['mage2_code_dir'] = $this->_env['mage2_dir'] . '/app/code';

        if (!is_dir($this->_env['source_dir'])) {
            $this->log('[ERROR] Invalid modules code source directory: ' . $this->_env['source_dir']);
        }
        if (!is_dir($this->_env['mage1_dir']) || !is_dir($this->_env['mage1_dir'] . '/app/code/core' )) {
            $this->log('[ERROR] Invalid Magento 1.x directory: ' . $this->_env['mage1_dir']);
        }
        if (!is_dir($this->_env['mage2_dir']) || !file_exists($this->_env['mage2_dir'] . '/app/bootstrap.php')) {
            $this->log('[ERROR] Invalid Magento 2.x directory: ' . $this->_env['mage2_dir']);
        }

        spl_autoload_register([$this, 'autoloadCallback']);

        $this->_replace = $this->getReplaceMaps();
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
            $this->collectCoreModulesConfigs();
            $this->collectCoreModulesLayouts();
        }

        $this->log('')->log("[INFO] LOOKING FOR ALL EXTENSIONS IN {$this->_env['source_dir']}")->log('');

        $extDirs = glob($this->_env['source_dir'] . '/*', GLOB_ONLYDIR);
        foreach ($extDirs as $extDir) {
            if (!preg_match('#^(.*)/([A-Za-z0-9]+_[A-Za-z0-9]+)$#', $extDir, $m)) {
                continue;
            }
            switch ($stage) {
                case 1:
                    $this->convertExtensionStage1($m[2], $m[1]);
                    break;

                case 2:
                    $this->convertExtensionStage2($m[2]);
                    break;

                case 3:
                    #$this->convertExtensionStage3($m[2]);
                    break;
            }
        }

        return $this;
    }

    public function convertExtensionStage1($extName, $rootDir)
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

        $this->convertGenerateMetaFiles();
        $this->convertAllConfigs();
        $this->convertAllControllers();
        $this->convertAllObservers();
        $this->convertAllLayouts();
        $this->convertAllTemplates();
        $this->convertAllWebAssets();
        $this->convertAllI18n();
        $this->convertAllOtherFiles();
        $this->convertAllPhpFilesDI();

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

    public function expandSourcePath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_root_dir'] . '/' . $path;
        }
        $target = 'app/code/' . $this->_env['ext_pool'] . '/' . str_replace('_', '/', $this->_env['ext_name']) . '/';
        $path   = str_replace('@EXT/', $target, $path);
        return $path;
    }

    public function expandOutputPath($path)
    {
        if ($path[0] !== '/' && $path[1] !== ':') {
            $path = $this->_env['ext_output_dir'] . '/' . $path;
        }
        return $path;
    }

    public function readFile($filename, $expand = false)
    {
        if ($this->_testMode) {
            if (empty($this->_testInputFiles)) {
                throw new BException('No test files in pipeline');
            }
            $file = array_shift($this->_testInputFiles);
            $this->_currentFile = ['filename' => $file['filename']];
            return $file['contents'];
        }

        $this->_currentFile = [
            'filename' => $filename,
        ];

        if ($expand) {
            $filename = $this->expandSourcePath($filename);
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

    public function writeFile($filename, $contents, $expand = false)
    {
        if ($this->_testMode) {
            $this->_testOutputFiles[] = [
                'filename' => $filename,
                'contents' => ($contents instanceof SimpleXMLElement) ? $contents->asPrettyXml() : $contents,
            ];
            return $this;
        }

        if ($expand) {
            $filename = $this->expandOutputPath($filename);
        }

        $dir = dirname($filename);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if ($contents instanceof SimpleXMLElement) {
            $contents->asPrettyXml($filename);
        } else {
            file_put_contents($filename, $contents);
        }
        return $this;
    }

    public function copyFile($src, $dst, $expand = false)
    {
        if ($this->_testMode) {
            return false; //TODO: what to do in test mode?
        }

        if ($expand) {
            $src = $this->expandSourcePath($src);
            $dst = $this->expandOutputPath($dst);
        }

        $dir = dirname($dst);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        copy($src, $dst);
        return true;
    }

    public function copyRecursive($src, $dst, $expand = false)
    {
        if ($this->_testMode) {
            return false; //TODO: what to do in test mode?
        }

        if ($expand) {
            $src = $this->expandSourcePath($src);
            if (!file_exists($src)) {
                return false;
            }
            $dst = $this->expandOutputPath($dst);
        }

        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copyRecursive($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
        return true;
    }

    public function deleteFile($filename, $expand = false)
    {
        if ($this->_testMode) {
            return false; //TODO: what to do in test mode?
        }

        if ($expand) {
            $filename = $this->expandOutputPath($filename);
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
        return $this;
    }

    public function findFilesRecursive($dir, $expand = false)
    {
        if ($this->_testMode) {
            return []; //TODO: what to do in test mode?
        }

        if ($expand) {
            $dir = $this->expandSourcePath($dir);
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

    public function collectCoreModulesConfigs()
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

    public function collectCoreModulesLayouts()
    {
        $this->log("[INFO] COLLECTING M1 LAYOUTS...")->log('');
        $layoutFiles = glob($this->_env['mage1_dir'] . '/app/design/*/*/*/layout/*.xml');
        foreach ($layoutFiles as $file) {
            preg_match('#/app/design/([^/]+)/([^/]+/[^/]+)#', $file, $m);
            $xml    = simpledom_load_file($file);
            $blocks = $xml->xpath('//block');
            foreach ($blocks as $blockNode) {
                if ($blockNode['type'] && $blockNode['name']) {
                    $className = $this->getClassName('blocks', (string)$blockNode['type'], false);
                    $this->_layouts[$m[1]]['blocks'][(string)$blockNode['name']] = $className;
                }
            }
        }
        return $this;
    }

    public function getClassName($type, $moduleClassKey, $m2 = true)
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

    public function convertGenerateMetaFiles()
    {
        $this->convertGenerateComposerFile();
        $this->convertGenerateRegistrationFile();
    }

    public function convertGenerateComposerFile()
    {
        $xml1    = $this->readFile("@EXT/etc/config.xml", true);
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
            ]
        ];
        $this->writeFile('composer.json', json_encode($data, JSON_PRETTY_PRINT), true);
    }

    public function convertGenerateRegistrationFile()
    {
        $regCode = <<<EOT
<?php

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    '{$this->_env['ext_name']}',
    __DIR__
);

EOT;

        $this->writeFile('registration.php', $regCode, true);
    }

    ///////////////////////////////////////////////////////////

    public function convertAllConfigs()
    {
        $this->convertConfigModule();
        $this->convertConfigDefaults();
        $this->convertConfigAcl();
        $this->convertConfigResources();
        $this->convertConfigDI();
        $this->convertConfigFrontendDI();
        $this->convertConfigAdminhtmlDI();
        $this->convertConfigEvents();
        $this->convertConfigRoutesFrontend();
        $this->convertConfigRoutesAdmin();
        $this->convertConfigMenu();
        $this->convertConfigSystem();
        $this->convertConfigCrontab();
        $this->convertConfigEmailTemplates();
        $this->convertConfigCatalogAttributes();
        $this->convertConfigFieldset();
        $this->convertConfigSales();
        $this->convertConfigPdf();
        $this->convertConfigWidget();
    }

    /**
     * @param string $schemaPath
     * @param string $rootTagName
     * @return SimpleDOM
     */
    public function createConfigXml($schemaPath, $rootTagName = 'config')
    {
        return simpledom_load_string('<?xml version="1.0" encoding="UTF-8"?>
<' . $rootTagName . ' xmlns:xsi="' . self::XSI . '" xsi:noNamespaceSchemaLocation="' . $schemaPath . '">
</' . $rootTagName . '>');
    }

    public function convertConfigModule()
    {
        $extName = $this->_env['ext_name'];
        $xml1    = $this->readFile("@EXT/etc/config.xml", true);
        $xml2    = $this->readFile("app/etc/modules/{$this->_env['ext_name']}.xml", true);

        $resultXml = $this->createConfigXml('urn:magento:framework:Module/etc/module.xsd');
        $targetXml = $resultXml->addChild('module');
        $targetXml->addAttribute('name', $extName);
        if (!empty($xml1->modules->{$extName}->version)) {
            $targetXml->addAttribute('setup_version', (string)$xml1->modules->{$extName}->version);
        } else {
            $this->log("[WARN] Missing module version for {$extName}.");
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

        $this->writeFile('etc/module.xml', $resultXml, true);
    }

    public function convertConfigDefaults()
    {
        $resultXml = $this->createConfigXml('urn:magento:module:Magento_Store:etc/config.xsd');

        $xml1 = $this->readFile("@EXT/etc/config.xml", true);
        if (!empty($xml1->default)) {
            $resultXml->appendChild($xml1->default->cloneNode(true));
        }

        $xml2 = $this->readFile("@EXT/etc/magento2.xml");
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
            $this->writeFile('etc/config.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/config.xml', true);
        }
    }

    public function convertConfigAcl()
    {
        $resultXml = $this->createConfigXml('urn:magento:framework:Acl/etc/acl.xsd');
        $targetXml = $resultXml->addChild('acl')->addChild('resources');

        $xml1 = $this->readFile("@EXT/etc/config.xml", true);
        if (!empty($xml1->adminhtml->acl)) {
            $this->convertConfigAclRecursive($xml1->adminhtml->acl->resources, $targetXml);
        }

        $xml2 = $this->readFile("@EXT/etc/adminhtml.xml", true);
        if ($xml2 && !empty($xml2->acl)) {
            $this->convertConfigAclRecursive($xml2->acl->resources, $targetXml);
        }

        if ($targetXml->children()) {
            $this->writeFile('etc/acl.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/acl.xml', true);
        }
    }

    public function convertConfigAclRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml, $path = '')
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
                $this->convertConfigAclRecursive($sourceNode->children, $targetNode, $path . $key . '/');
            }
        }
    }

    public function convertConfigResources()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->resources)) {

            $resultXml = $this->createConfigXml('urn:magento:framework:App/etc/resources.xsd');

            foreach ($xml->global->resources->children() as $resKey => $resNode) {
                if (empty($resNode->connection->use)) {
                    continue;
                }
                $targetNode = $resultXml->addChild('resource');
                $targetNode->addAttribute('name', $resKey);
                $targetNode->addAttribute('extends', (string)$resNode->connection->use);
            }

            $this->writeFile('etc/resources.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/resources.xml', true);
        }
    }

    public function convertConfigDI()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->createConfigXml('urn:magento:framework:ObjectManager/etc/config.xsd');

        foreach (['models', 'helpers', 'blocks'] as $type) {
            if (empty($xml->global->{$type})) {
                continue;
            }
            foreach ($xml->global->{$type}->children() as $moduleKey => $mNode) {
                if (empty($mNode->rewrite)) {
                    continue;
                }
                foreach ($mNode->rewrite->children() as $classKey => $cNode) {
                    $origClass   = $this->getClassName($type, $moduleKey . '/' . $classKey);
                    $targetClass = str_replace('_', '\\', (string)$cNode);
                    $prefNode    = $resultXml->addChild('preference');
                    $prefNode->addAttribute('for', $origClass);
                    $prefNode->addAttribute('type', $targetClass);
                }
            }
        }

        if ($resultXml->children()) {
            $this->writeFile('etc/di.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/di.xml', true);
        }
    }

    public function convertConfigFrontendDI()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->createConfigXml('urn:magento:framework:ObjectManager/etc/config.xsd');

        if (!empty($xml->frontend->secure_url)) {
            $n1 = $resultXml->addChild('type');
            $n1->addAttribute('name', 'Magento\Framework\Url\SecurityInfo');
            $n2 = $n1->addChild('arguments');
            $n3 = $n2->addChild('argument');
            $n3->addAttribute('name', 'secureUrlList');
            $n3->addAttribute('xsi:type', 'array', self::XSI);
            foreach ($xml->frontend->secure_url->children() as $itemName => $itemNode) {
                $n4 = $n3->addChild('item', (string)$itemNode);
                $n4->addAttribute('name', $itemName);
                $n4->addAttribute('xsi:type', 'string', self::XSI);
            }
        }

        if ($resultXml->children()) {
            $this->writeFile('etc/frontend/di.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/frontend/di.xml', true);
        }
    }

    public function convertConfigAdminhtmlDI()
    {

    }

    public function convertConfigEvents()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        foreach (['global' => '', 'frontend' => 'frontend/', 'adminhtml' => 'adminhtml/'] as $area => $areaDir) {
            $xmlFilename = 'etc/' . $areaDir . 'events.xml';

            if (!empty($xml->{$area}->events)) {
                $resultXml = $this->createConfigXml('urn:magento:framework:Event/etc/events.xsd');

                foreach ($xml->{$area}->events->children() as $eventName => $eventNode) {
                    $targetEventNode = $resultXml->addChild('event');
                    $targetEventNode->addAttribute('name', $eventName);
                    foreach ($eventNode->observers->children() as $obsName => $obsNode) {
                        $targetObsNode = $targetEventNode->addChild('observer');
                        $targetObsNode->addAttribute('name', $obsName);
                        $instance = $this->getClassName('models', (string)$obsNode->class) . '\\'
                            . str_replace(' ', '', ucwords(str_replace('_', ' ', (string)$obsNode->method)));
                        $instance = str_replace('\\Model\\Observer\\', '\\Observer\\', $instance);
                        $targetObsNode->addAttribute('instance', $instance);
                        #$targetObsNode->addAttribute('method', (string)$obsNode->method);
                        if ($obsNode->type == 'model') {
                            $targetObsNode->addAttribute('shared', 'false');
                        }
                    }
                }
                $this->writeFile($xmlFilename, $resultXml, true);
            } else {
                $this->deleteFile($xmlFilename, true);
            }
        }
    }

    public function convertConfigRoutesFrontend()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        $xmlFilename = 'etc/frontend/routes.xml';

        if (!empty($xml->frontend->routers)) {
            $resultXml = $this->createConfigXml('urn:magento:framework:App/etc/routes.xsd');

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
            $this->writeFile($xmlFilename, $resultXml, true);
        } else {
            $this->deleteFile($xmlFilename, true);
        }
    }

    public function convertConfigRoutesAdmin()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        $xmlFilename = 'etc/adminhtml/routes.xml';

        if (!empty($xml->admin->routers->adminhtml->args->modules)) {
            $resultXml = $this->createConfigXml('urn:magento:framework:App/etc/routes.xsd');

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

            $this->writeFile($xmlFilename, $resultXml, true);
        } else {
            $this->deleteFile($xmlFilename, true);
        }
    }

    public function convertConfigMenu()
    {
        $resultXml = $this->createConfigXml('urn:magento:module:Magento_Backend:etc/menu.xsd');
        $targetXml = $resultXml->addChild('menu');

        $xml1 = $this->readFile("@EXT/etc/config.xml", true);
        if (!empty($xml1->adminhtml->menu)) {
            $this->convertConfigMenuRecursive($xml1->adminhtml->menu, $targetXml);
        }

        $xml2 = $this->readFile("@EXT/etc/adminhtml.xml", true);
        if ($xml2 && !empty($xml2->acl)) {
            $this->convertConfigMenuRecursive($xml2->menu, $targetXml);
        }

        if ($targetXml->children()) {
            $this->writeFile('etc/adminhtml/menu.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/adminhtml/menu.xml', true);
        }
    }

    public function convertConfigMenuRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml, $parent = null)
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
                $this->convertConfigMenuRecursive($srcNode->children, $targetXml, $nextParent);
            }
        }
    }

    public function convertConfigSystem()
    {
        $xml = $this->readFile("@EXT/etc/system.xml", true);
        if ($xml) {
            $resultXml = $this->createConfigXml('urn:magento:module:Magento_Config:etc/system_file.xsd');
            $targetXml = $resultXml->addChild('system');

            if (!empty($xml->tabs)) {
                foreach ($xml->tabs->children() as $tabId => $tabNode) {
                    $this->convertConfigSystemNode('tab', $tabNode, $targetXml);
                }
            }
            if (!empty($xml->sections)) {
                foreach ($xml->sections->children() as $sectionId => $sectionNode) {
                    $targetSectionNode = $this->convertConfigSystemNode('section', $sectionNode, $targetXml);
                    $targetSectionNode->addChild('resource', $this->_env['ext_name'] . '::system_config');
                    if (empty($sectionNode->groups)) {
                        continue;
                    }
                    foreach ($sectionNode->groups->children() as $groupId => $groupNode) {
                        $targetGroupNode = $this->convertConfigSystemNode('group', $groupNode, $targetSectionNode);
                        if (empty($groupNode->fields)) {
                            continue;
                        }
                        foreach ($groupNode->fields->children() as $fieldId => $fieldNode) {
                            $this->convertConfigSystemNode('field', $fieldNode, $targetGroupNode);
                        }
                    }
                }
            }

            $this->writeFile('etc/adminhtml/system.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/adminhtml/system.xml', true);
        }
    }

    public function convertConfigSystemNode($type, SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        static $childNodes = [
            'tab' => ['label'],
            'section' => ['label', 'class', 'tab', 'header_css'],
            'group' => [
                'label', 'fieldset_css', 'comment', 'frontend_model', 'clone_model', 'clone_fields',
                'help_url', 'more_url', 'demo_link', 'hide_in_single_store_mode',
            ],
            'field' => [
                'label', 'comment', 'tooltip', 'hint', 'config_path',
                'frontend_model', 'frontend_class', 'source_model', 'backend_model',
                'base_url', 'more_url', 'demo_url', 'button_url', 'button_label',
                'hide_in_single_store_mode', 'upload_dir', 'if_module_enabled', 'can_be_empty', 'validate',
            ],
        ];
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
        foreach ($childNodes[$type] as $childKey) {
            if (!empty($sourceXml->{$childKey})) {
                $value = (string)$sourceXml->{$childKey};
                if ('source_model' === $childKey || 'backend_model' === $childKey) {
                    $value = $this->getClassName('models', $value);
                } elseif ('frontend_model' === $childKey) {
                    $value = $this->getClassName('blocks', $value);
                }
                $targetNode->{$childKey} = $value;
                foreach ($sourceXml->{$childKey}->attributes() as $k => $v) {
                    $targetNode->{$childKey}->addAttribute($k, $v);
                }
            }
        }
        return $targetNode;
    }

    public function convertConfigCrontab()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);
        if (!empty($xml->crontab)) {
            $resultXml = $this->createConfigXml('urn:magento:module:Magento_Cron:etc/crontab.xsd');
            $targetXml = $resultXml->addChild('group');
            $targetXml->addAttribute('id', 'default');

            foreach ($xml->crontab->jobs->children() as $jobName => $jobNode) {
                $targetJobNode = $targetXml->addChild('job');
                $targetJobNode->addAttribute('name', $jobName);
                if (!empty($jobNode->run->model)) {
                    list($classAlias, $method) = explode('::', (string)$jobNode->run->model);
                    $targetJobNode->addAttribute('instance', $this->getClassName('models', $classAlias));
                    $targetJobNode->addAttribute('method', $method);
                }
                $targetJobNode->addChild('schedule', (string)$jobNode->schedule->cron_expr);
            }

            $this->writeFile('etc/crontab.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/crontab.xml', true);
        }
    }

    public function convertConfigEmailTemplates()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->template->email)) {
            $resultXml = $this->createConfigXml('urn:magento:module:Magento_Email:etc/email_templates.xsd');

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

            $this->writeFile('etc/email_templates.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/email_templates.xml', true);
        }
    }

    public function convertConfigCatalogAttributes()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->createConfigXml('urn:magento:module:Magento_Catalog:etc/catalog_attributes.xsd');

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
            $this->writeFile('etc/catalog_attributes.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/catalog_attributes.xml', true);
        }
    }

    public function convertConfigFieldset()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->fieldsets)) {
            $resultXml = $this->createConfigXml('urn:magento:framework:Object/etc/fieldset.xsd');
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

            $this->writeFile('etc/fieldset.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/fieldset.xml', true);
        }
    }

    public function convertConfigSales()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        $resultXml = $this->createConfigXml('urn:magento:module:Magento_Sales:etc/sales.xsd');

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
                    $class          = $this->getClassName('models', (string)$totalNode->class);
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
            $this->writeFile('etc/sales.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/sales.xml', true);
        }
    }

    public function convertConfigPdf()
    {
        $xml = $this->readFile("@EXT/etc/config.xml", true);

        if (!empty($xml->global->pdf)) {
            $resultXml = $this->createConfigXml('urn:magento:module:Magento_Sales:etc/pdf_file.xsd');

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
                        $className          = $this->getClassName('models', (string)$prodTypeNode);
                        $targetProdTypeNode = $targetPageTypeNode->addChild('renderer', $className);
                        $targetProdTypeNode->addAttribute('product_type', $prodType);
                    }
                }
            }

            $this->writeFile('etc/pdf.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/pdf.xml', true);
        }
    }

    public function convertConfigWidget()
    {
        /** @var SimpleDOM $xml */
        $xml = $this->readFile("@EXT/etc/widget.xml", true);

        if ($xml) {
            $resultXml = $this->createConfigXml('urn:magento:module:Magento_Widget:etc/widget.xsd', 'widgets');

            foreach ($xml->children() as $widgetName => $widgetNode) {
                $widgetXml = $resultXml->addChild('widget');
                $widgetXml->addAttribute('id', $widgetName);
                $widgetXml->addAttribute('class', $this->getClassName('blocks', $widgetNode['type']));
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
                    $paramXml->addAttribute('xsi:type', $paramType, self::XSI);
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
                        $sourceModel = $this->getClassName('models', (string)$paramNode->source_model);
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
                        $blockClass = $this->getClassName('blocks', (string)$paramNode->helper_block->type);
                        $blockXml->addAttribute('class', $blockClass);
                        if (!empty($paramNode->helper_block->data)) {
                            $dataXml = $blockXml->addChild('data');
                            $this->convertConfigWidgetDataRecursive($paramNode->helper_block->data, $dataXml);
                        }
                    }
                }
            }

            $this->writeFile('etc/widget.xml', $resultXml, true);
        } else {
            $this->deleteFile('etc/widget.xml', true);
        }
    }

    public function convertConfigWidgetDataRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        /**
         * @var string $itemName
         * @var SimpleXmlElement $itemNode
         */
        foreach ($sourceXml->children() as $itemName => $itemNode) {
            if ($itemNode->children()) {
                $itemXml = $targetXml->addChild('item');
                $itemXml->addAttribute('xsi:type', 'array', self::XSI);
                $this->convertConfigWidgetDataRecursive($itemNode, $itemXml);
            } else {
                $itemValue = (string)$itemNode;
                $itemXml = $targetXml->addChild('item', $itemValue);
                $itemXml->addAttribute('xsi:type', 'string', self::XSI);
                if (!is_numeric($itemValue)) {
                    $itemXml->addAttribute('translate', 'true');
                }
            }
            $itemXml->addAttribute('name', $itemName);
        }
    }

    ///////////////////////////////////////////////////////////

    public function convertAllLayouts()
    {
        $this->convertLayoutAreaTheme('adminhtml', 'base/default');
        $this->convertLayoutAreaTheme('adminhtml', 'default/default');
        $this->convertLayoutAreaTheme('frontend', 'base/default');
        $this->convertLayoutAreaTheme('frontend', 'default/default');
    }

    public function convertLayoutAreaTheme($area, $theme)
    {
        $dir = "{$this->_env['ext_root_dir']}/app/design/{$area}/{$theme}/layout";
        $files = $this->findFilesRecursive($dir);
        $outputDir = $this->expandOutputPath("view/{$area}/layout");
        foreach ($files as $file) {
            $this->convertLayoutFile($area, $dir . '/' . $file, $outputDir);
        }
    }

    public function convertLayoutFile($area, $file, $outputDir)
    {
        $xml = $this->readFile($file);

        foreach ($xml->children() as $layoutName => $layoutNode) {
            $resultXml = $this->createConfigXml('urn:magento:framework:View/Layout/etc/page_configuration.xsd', 'page');
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
                                $this->convertLayoutHeadNode($headNode, $headXml);
                            }
                            break;
                        }
                    //nobreak;

                    case 'block':
                        $this->convertLayoutRecursive($area, $node, $bodyXml);
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

            $this->writeFile("{$outputDir}/{$layoutName}.xml", $resultXml);
        }
    }

    public function convertLayoutHeadNode(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
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

    public function convertLayoutRecursive($area, SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        $tagName = $sourceXml->getName();
        switch ($tagName) {
            case 'reference':
                $nodeName = (string)$sourceXml['name'];
                if (!empty($this->_layouts[$area]['blocks'][$nodeName])) {
                    $className = $this->_layouts[$area]['blocks'][$nodeName];
                    if ($className === 'Mage_Core_Block_Text_List'
                        || class_exists($className) && is_subclass_of($className, 'Mage_Core_Block_Text_List')
                        || $nodeName === 'content'
                    ) {
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
                $blockClass = $this->getClassName('blocks', $nodeType);
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
                                    $this->convertLayoutArgumentRecursive($argNode, $argXml);
                                } else {
                                    $argValue = $this->getOpportunisticArgValue($argNode);
                                    $argXml   = $argumentsXml->addChild('argument', $argValue);
                                    $argXml->addAttribute('xsi:type', 'string', self::XSI);
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
                        $this->convertLayoutRecursive($area, $childXml, $targetChildXml);
                        break;
                }
            }
        }
    }

    public function convertLayoutArgumentRecursive(SimpleXMLElement $sourceXml, SimpleXMLElement $targetXml)
    {
        $targetXml->addAttribute('xsi:type', 'array', self::XSI);
        foreach ($sourceXml->children() as $childTag => $childNode) {
            if ($childNode->children()) {
                $childXml = $targetXml->addChild('item');
                $this->convertLayoutArgumentRecursive($childNode, $childXml);
            } else {
                $argValue = $this->getOpportunisticArgValue($childNode);
                $childXml = $targetXml->addChild('item', $argValue);
                $childXml->addAttribute('xsi:type', 'string', self::XSI);
            }
            $childXml->addAttribute('name', $childTag);
        }
    }

    public function getOpportunisticArgValue($value)
    {
        $value = (string)$value;

        if (preg_match('#\.phtml$#', $value)) {
            return $this->_env['ext_name'] . '::' . $value;
        }

        if (preg_match('#^[A-Za-z_]+/[A-Za-z0-9_]+$#', $value)) {
            $class = $this->getClassName('models', $value);
            if ($class) {
                return $class;
            }
            $class = $this->getClassName('helpers', $value);
            if ($class) {
                return $class;
            }
            $class = $this->getClassName('blocks', $value);
            if ($class) {
                return $class;
            }
        }

        return $value;
    }

    ///////////////////////////////////////////////////////////

    public function convertAllTemplates()
    {
        $this->convertTemplatesAreaTheme('adminhtml', 'default/default');
        $this->convertTemplatesAreaTheme('adminhtml', 'base/default');
        $this->convertTemplatesAreaTheme('frontend', 'default/default');
        $this->convertTemplatesAreaTheme('frontend', 'base/default');
        $this->convertTemplatesEmails();
    }

    public function convertTemplatesAreaTheme($area, $theme)
    {
        $dir = "{$this->_env['ext_root_dir']}/app/design/{$area}/{$theme}/template";
        $files = $this->findFilesRecursive($dir);
        $outputDir = $this->expandOutputPath("view/{$area}/templates");
        foreach ($files as $filename) {
            $contents = $this->readFile("{$dir}/{$filename}");
            $contents = $this->convertCodeContents($contents, 'phtml');
            $this->writeFile("{$outputDir}/{$filename}", $contents);
        }
    }

    public function convertTemplatesEmails()
    {
        $area = 'frontend'; //TODO: any way to know from M1?
        $dir = "{$this->_env['ext_root_dir']}/app/locale/en_US/template/email";
        $outputDir = $this->expandOutputPath("view/{$area}/email");
        #$this->copyRecursive($dir, $outputDir);
        $files = $this->findFilesRecursive($dir);
        foreach ($files as $filename) {
            $this->copyFile("{$dir}/{$filename}", "{$outputDir}/{$filename}");
            #$contents = $this->readFile("{$dir}/{$filename}");
            #$this->writeFile("{$outputDir}/{$filename}", $contents);
        }
    }

    ///////////////////////////////////////////////////////////

    public function convertAllI18n()
    {
        $dir = "{$this->_env['ext_root_dir']}/app/locale";
        $files = glob("{$dir}/*/*.csv");
        $outputDir = $this->expandOutputPath("i18n");
        foreach ($files as $file) {
            if (!preg_match('#([a-z][a-z]_[A-Z][A-Z])[\\\\/].*(\.csv)#', $file, $m)) {
                continue;
            }
            $this->copyFile($file, "{$outputDir}/{$m[1]}{$m[2]}");
            #$contents = $this->readFile($file);
            #$this->writeFile("{$outputDir}/{$m[1]}{$m[2]}", $contents);
        }
    }

    ///////////////////////////////////////////////////////////

    public function convertAllWebAssets()
    {
        $this->copyRecursive('js', 'view/frontend/web/js', true);
        $this->copyRecursive('media', 'view/frontend/web/media', true);
        $this->copyRecursive('skin/adminhtml/default/default', 'view/adminhtml/web', true);
        $this->copyRecursive('skin/frontend/base/default', 'view/frontend/web', true);
    }

    ///////////////////////////////////////////////////////////

    public function convertAllOtherFiles()
    {
        $dir = $this->expandSourcePath("@EXT/");
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
                    $this->convertPhpClasses($basename);
                } else {
                    $this->copyRecursive($file, $targetFile);
                }
            } else {
                if ('php' === $fileExt) {
                    $contents = $this->readFile($file);
                    $contents = $this->convertCodeContents($contents);
                    $this->writeFile($targetFile, $contents);
                } else {
                    copy($file, $targetFile);
                }
            }
        }
    }

    public function convertPhpClasses($folder, $callback = null)
    {
        $dir = $this->expandSourcePath("@EXT/{$folder}");
        $files = $this->findFilesRecursive($dir);
        sort($files);
        $targetDir = $this->expandOutputPath($folder);
        $fromName = array_keys($this->_replace['files_regex']);
        $toName = array_values($this->_replace['files_regex']);
        foreach ($files as $filename) {
            $contents = $this->readFile("{$dir}/{$filename}");
            $targetFile = "{$targetDir}/{$filename}";
            if ($callback) {
                $params = ['source_file' => $filename, 'target_file' => &$targetFile];
                $contents = call_user_func($callback, $contents, $params);
            } else {
                $contents = $this->convertCodeContents($contents);
                $targetFile = preg_replace($fromName, $toName, $targetFile);
            }
            $this->writeFile($targetFile, $contents);
        }
    }

    public function convertCodeContents($contents, $mode = 'php')
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
            $contents = $this->convertCodeContentsPhpMode($contents);
        }
        if ($mode === 'phtml') {
            $contents = str_replace(self::OBJ_MGR . '(\'Magento\Framework\View\LayoutFactory\')->create()',
                '$block->getLayout()', $contents);
        }
        $contents = $this->convertShortArraySyntax($contents);

        // convert block name to block class
        $contents = preg_replace_callback('#(->createBlock\([\'"])([^\'"]+)([\'"]\))#', function($m) {
            return $m[1] . $this->getClassName('blocks', $m[2]) . $m[3];
        }, $contents);
        $contents = preg_replace_callback('#Mage::getBlockSingleton\(([\'"])([A-Za-z_/]+)([\'"])\)#', function($m) {
            return static::OBJ_MGR . "({$m[1]}" . $this->getClassName('blocks', $m[2]) . "{$m[3]})";
        }, $contents);

        // Replace getModel|getSingleton|helper calls with ObjectManager::get calls
        $re = '#(Mage::(getModel|getResourceModel|getSingleton|helper)|\$this->helper)\([\'"]([a-zA-Z0-9/_]+)[\'"]\)#';
        $contents = preg_replace_callback($re, function($m) {
            $classKey = $m[3];
            if ($m[2] === 'helper' || $m[1] === '$this->helper') {
                $class = $this->getClassName('helpers', $classKey, false);
            } else {
                if ($m[2] === 'getResourceModel') {
                    list($modKey, $clsKey) = explode('/', $classKey);
                    if (!empty($this->_aliases['models']["{$modKey}_resource"])) {
                        $classKey = "{$modKey}_resource/{$clsKey}";
                    } elseif (!empty($this->_aliases['models']["{$modKey}_mysql4"])) {
                        $classKey = "{$modKey}_mysql4/{$clsKey}";
                    }
                }
                $class = $this->getClassName('models', $classKey, false);
            }
            $result = self::OBJ_MGR . "('{$class}" . ($m[2] === 'getModel' ? "Factory')->create()" : "')");
            return $result;
        }, $contents);
        $contents = preg_replace_callback('#Mage::get(Model|Singleton)\(#', function($m) {
            $result = self::OBJ_MGR . '(';
            if ($m[1] === 'Model') {
                $result = str_replace('->get(', '->create(', $result);
            }
            return $result;
        }, $contents);
        $contents = preg_replace_callback('#__\(([\"\'])((\\\\\1|.)+)\1\s*,#', function($m) {
            $i = 1;
            $result = $m[2];
            do {
                $prevResult = $result;
                $result = preg_replace("#%s#", "%{$i}", $result, 1);
                $i++;
            } while ($result !== $prevResult);
            return "__({$m[1]}{$result}{$m[1]},";
        }, $contents);

        // Replace M1 classes with M2 classes
        $classTr = $this->_replace['classes'];
        $contents = str_replace(array_keys($classTr), array_values($classTr), $contents);
        $classRegexTr = $this->_replace['classes_regex'];
        $contents = preg_replace(array_keys($classRegexTr), array_values($classRegexTr), $contents);

        // Convert any left underscored class names to backslashed. If class name is in string value, don't prefix
        $contents = preg_replace_callback('#(.)([A-Z][A-Za-z0-9]+_[A-Za-z0-9_]+_[A-Za-z0-9_])#', function($m) {
            if ($m[1] === '\\') { // if the class is already backslash prefixed, skip
                return $m[0];
            }
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
            $nsCode = "namespace {$m[3]};\n\n{$m[1]}class {$m[4]}" . (!empty($m[5]) ? $m[5] : '');
            $contents  = str_replace($m[0], $nsCode, $contents);
        }

        return $contents;
    }

    public function convertCodeContentsPhpMode($contents)
    {
        // Replace $this->init() in models and resources with class names and table names
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
                    $model    = $this->getClassName('models', $m[2], true);
                    $resModel = $this->getClassName('models', $resKey, true);
                    return $m[1] . $model . $m[3] . ', ' . $m[3] . $resModel . $m[3];
                } else {
                    $this->log("[WARN] No resource model for {$m[2]}");
                    return $m[0];
                }
            } elseif (preg_match('#/Model/(Mysql4|Resource)/#', $filename)) {
                return $m[1] . str_replace('/', '_', $m[2]) . $m[3]; //TODO: try to figure out original table name
            } elseif (preg_match('#/Model/#', $filename)) {
                if ($resKey) {
                    $resModel = $this->getClassName('models', $resKey, true);
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

    public function convertCodeParseMethods($contents, $fileType = false, $returnResult = false)
    {
        $nl = $this->_currentFile['nl'];

        $lines = preg_split('#\r?\n#', $contents);
        $linesCnt = sizeof($lines);

        // Find start of the class
        $classStart = null;
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+[A-Za-z0-9_]+(\s+(extends|implements)\s+|\s*(\{|$))#';
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
            if (preg_match('#^\s*\}#', $lines[$i])) {
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
                        if (preg_match('#^\s*(\{\s*)?\}#', $lines[$j])) {
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

    public function convertCodeObjectManagerToDI($contents)
    {
        $objMgrRe = preg_quote(self::OBJ_MGR, '#');
        if (!preg_match_all("#{$objMgrRe}\(['\"]([\\\\A-Za-z0-9]+?)['\"]\)#", $contents, $matches, PREG_SET_ORDER)) {
            return $contents;
        }
        $propertyLines = [];
        $constructLines = [];
        $classVars = [];
        $declared = [];
        $pad = '    ';

        $parentArgs = $this->convertDIGetParentConstructArgs($contents);
        $constructArgs = $parentArgs['args'];
        $constructParentArgs = $parentArgs['parent_args'];
        $optionalArgsStart = $parentArgs['optional'];
        $hasParent = $parentArgs['has_parent'];
        $parentClasses = $parentArgs['classes'];
        foreach ($matches as $m) {
            $class = '\\' . ltrim($m[1], '\\');
            if (!empty($declared[$class])) {
                continue;
            }
            if (preg_match($this->_skipConvertToDiRegex, $class)) {
                continue;
            }
            $declared[$class] = 1;
            $cArr = array_reverse(explode('\\', $class));

            $var = $cArr[1] . $cArr[0];
            for ($i = 2; $i <= 5 && !empty($classVars[$var]) && !empty($cArr[$i]); $i++) {
                $var = $cArr[$i] . $var;
            }
            $classVars[$var] = 1;
            $var[0] = strtolower($var[0]);

            if (empty($parentClasses[$class])) {
                $propertyLines[] = "{$pad}/**";
                $propertyLines[] = "{$pad} * @var {$class}";
                $propertyLines[] = "{$pad} */";
                $propertyLines[] = "{$pad}protected \$_{$var};";
                $propertyLines[] = "";

                if (empty($optionalArgsStart)) {
                    $constructArgs[] = "{$class} \${$var}";
                } else {
                    array_splice($constructArgs, $optionalArgsStart++, 0, ["{$class} \${$var}"]);
                }

                $constructLines[] = "{$pad}{$pad}\$this->_{$var} = \${$var};";
            }

            //$constructParentArgs[] = $var;

            $contents = str_replace($m[0], "\$this->_{$var}", $contents);
        }

        $nl = $this->_currentFile['nl'];
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+[A-Za-z0-9_]+\s+[^{]*\{#m';
        $classStartWith = "\$0{$nl}" . join($nl, $propertyLines);
        $argsStr = join(", {$nl}{$pad}{$pad}", $constructArgs);
        $assignStr = join($nl, $constructLines);
        $constructParentArgsStr = join(', ', $constructParentArgs);
        if (preg_match('#^(\s*public\s+function\s+__construct\()(.*?)(\)\s+\{)#ms', $contents, $m)) {
            $comma = !empty($m[2]) ? ', ' : '';
            $contents = str_replace($m[0], "{$m[1]}{$m[2]}{$comma}{$argsStr}{$m[3]}{$nl}{$assignStr}{$nl}", $contents);
            $contents = preg_replace_callback('#(parent::__construct\s*\()\s*(.)#', function($m) use ($constructParentArgsStr) {
                return $m[1] . $constructParentArgsStr . ($constructParentArgsStr && $m[2] !== ')' ? ', ' : '') . $m[2];
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

    public function convertDIGetParentConstructArgs($contents)
    {
        static $cache = [];

        $result = [
            'args' => [],
            'parent_args' => [],
            'classes' => [],
            'optional' => false,
            'has_parent' => false,
        ];

        $parentResult = $this->convertFindParentConstruct($contents);
        if (!$parentResult) {
            return $result;
        }
        $parentClass = $parentResult['parent_class'];
        $parentConstructClass = $parentResult['construct_class'];
        if (!empty($cache[$parentClass])) {
            return $cache[$parentClass];
        }
        $parentContents = $parentResult['contents'];

        $parentMethods = $this->convertCodeParseMethods($parentContents, false, true);
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
        foreach ($matches as $i => $m) {
            $argClass = $this->convertGetFullClassName($parentContents, $parentConstructClass, $m[1]);
            $result['classes'][$argClass] = 1;
            $result['args'][] = rtrim($argClass . ' ' . $m[2] . $m[3]);
            $result['parent_args'][] = $m[2];
            if (empty($result['optional']) && strpos($m[3], '=') !== false) {
                $result['optional'] = $i;
            }
        }
        $cache[$parentClass] = $cache[$parentConstructClass] = $result;
        return $result;
    }

    public function convertFindParentConstruct($contents, $first = true)
    {
        static $cache = [];
        static $autoloaded = false;
        if (!preg_match('#^\s*namespace\s+(.*);\s*$#m', $contents, $m)) {
            return false;
        }
        $parentNamespace = $m[1];

        if (!preg_match('#^\s*((abstract|final)\s+)?class\s+([^\s]+)\s+extends\s+([^\s]+)#m', $contents, $m)) {
            return false;
        }
        $parentClass = $this->convertGetFullClassName($contents, $parentNamespace . '\\' . $m[3], $m[4]);

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
            if (strpos($parentClass, '\\Controller\\') !== false) {
                $this->log("[INFO] Magento 2 controllers are separated into different classes for each action.");
            }
            return false;
        }
        $parentContents = file_get_contents($parentPath);

        if (preg_match('#function\s+__construct\s*\(#', $parentContents)) {
            $result = [
                'construct_class' => $parentClass,
                'contents' => $parentContents,
            ];
        } else {
            $result = $this->convertFindParentConstruct($parentContents, false);
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
    public function convertGetFullClassName($contents, $contentsClass, $shortClass)
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

    public function convertNamespaceUse($contents)
    {
        #return $contents;#

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
            while ($i > 0 && !empty($mapByAlias[$alias]) || preg_match("#^({$this->_reservedWordsRe}|map|data)$#ix", $alias)) {
                $i--;
                $alias = $parts[$i] . $alias;
                $useAs = true;
            }
            $mapByClass[$class] = $alias;
            $mapByAlias[$alias] = $class;
            array_pop($parts);
            if ('\\' . join('\\', $parts) !== $namespace || $useAs) {
                $useLines[] = 'use ' . trim($class, '\\') . ($useAs ? ' as ' . $alias : '') . ';';
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

    public function convertShortArraySyntax($contents)
    {
        $tokens = token_get_all($contents);
        $arrayIdx = null;
        $stack = [];
        for ($i = 0, $size = sizeof($tokens); $i < $size; $i++) {
            $t = $tokens[$i];
            if (!$arrayIdx) { // `array` keyword not found yet
                if (is_array($t) && $t[0] === T_ARRAY) { // current token is `array`
                    $arrayIdx = $i; // it starts here
                } elseif ($t === '(') { // unrelated opening parenthesis found
                    array_unshift($stack, false); // add to stack that it's not array
                } elseif ($t === ')') { // closing parenthesis found
                    if ($stack[0]) { // is this closing array?
                        $tokens[$i] = ']';
                    }
                    array_shift($stack); // remove the current flag from stack
                }
            } else { // we're right after `array` keyword
                if ($t === '(') { // does opening parentheses follow?
                    $tokens[$i] = '['; // replace current token with opening bracket
                    $removeLength = $i - $arrayIdx; // calculate length to be removed from tokens
                    array_splice($tokens, $arrayIdx, $removeLength); // remove `array` and anything that follows before parenthesis
                    $i -= $removeLength; // adjust current position for removed tokens
                    $size -= $removeLength;
                    array_unshift($stack, true); // add a flag to stack that it's array context
                    $arrayIdx = false; // reset context state
                } elseif (!(is_array($t) && $t[0] === T_WHITESPACE)) { // the `array` that we found earlier is not start of actual array
                    $arrayIdx = false; // reset context state
                }
            }
        }
        $result = [];
        for ($i = 0; $i < $size; $i++) {
            if (isset($tokens[$i])) {
                $result[] = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
            }
        }
        return join('', $result);
    }
/*
    public function convertShortArraySyntax1($contents)
    {
        $tokens = token_get_all($contents);
        for ($i = 0, $size = sizeof($tokens); $i < $size; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_ARRAY) {
                $this->convertShortArraySyntaxRecursive($tokens, $i, $size);
            }
        }
        $result = [];
        for ($i = 0; $i < $size; $i++) {
            if (isset($tokens[$i])) {
                $result[] = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
            }
        }
        return join('', $result);
    }

    public function convertShortArraySyntaxRecursive(&$tokens, &$start, $size)
    {
        for ($i = $start + 1; $i < $size; $i++) {
            if ($tokens[$i] === '(') {
                break;
            } elseif (!is_array($tokens[$i]) && $tokens[$i][0] !== T_WHITESPACE) {
                return;
            }
        }
        $tokens[$start] = '[';
        for ($j = $start + 1; $j <= $i; $j++) {
            unset($tokens[$j]);
        }
        $bracketLevel = 0;
        for ($i = $i + 1; $i < $size; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_ARRAY) {
                $this->convertShortArraySyntaxRecursive($tokens, $i, $size);
            } elseif ($tokens[$i] === '(') {
                $bracketLevel++;
            } elseif ($tokens[$i] === ')') {
                if ($bracketLevel) {
                    $bracketLevel--;
                } else {
                    $tokens[$i] = ']';
                    break;
                }
            }
        }
        $start = $i + 1;
    }
*/
    ///////////////////////////////////////////////////////////

    public function convertAllControllers()
    {
        $dir = $this->expandSourcePath('@EXT/controllers');
        $files = $this->findFilesRecursive($dir);
        $targetDir = $this->expandOutputPath('Controller');
        foreach ($files as $file) {
            $this->convertController($file, $dir, $targetDir);
        }
    }

    public function convertController($file, $sourceDir, $targetDir)
    {
        $targetFile = preg_replace(['#Controller\.php$#', '#/[^/]+admin/#'], ['.php', '/Adminhtml/'],
            "{$targetDir}/{$file}");

        $fileClass = preg_replace(['#/#', '#\.php$#'], ['_', ''], $file);
        $origClass = "{$this->_env['ext_name']}_{$fileClass}";

        $fileClass = preg_replace(['#Controller$#', '#[^_]+admin_#'], ['', 'Adminhtml_'], $fileClass);
        $ctrlClass = "{$this->_env['ext_name']}_Controller_{$fileClass}";

        $contents = $this->readFile("{$sourceDir}/{$file}");

        if (strpos($file, 'Controller.php') === false) {
            $contents = str_replace($origClass, $ctrlClass, $contents);
            $contents = $this->convertCodeContents($contents);
            $this->writeFile($targetFile, $contents, false);
            return;
        }

        $targetFile = preg_replace('#([^/]+)\.php$#', '\1/Abstract\1.php', $targetFile);
        $abstractClass = preg_replace('#([^_]+)$#', '\1_Abstract\1', $ctrlClass);

        #$this->log('CONTROLLER: ' . $origClass);
        $contents = str_replace($origClass, $abstractClass, $contents);
        $contents = $this->convertCodeContents($contents);
        $contents = $this->convertCodeParseMethods($contents, 'controller');
        $contents = $this->convertControllerContext($contents);

        $contents = preg_replace('#^\s*require(_once)?\s*(\(| ).+Controller\.php[\'"]\s*\)?\s*;\s*$#m', '', $contents);

        $this->writeFile($targetFile, $contents);

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

            $classContents = $this->convertCodeContents($classContents);
            $classContents = $this->convertControllerContext($classContents);

            $actionFile = str_replace([$this->_env['ext_name'] . '_', '_'], ['', '/'], $actionClass) . '.php';
            $targetActionFile = "{$this->_env['ext_output_dir']}/{$actionFile}";

            $this->writeFile($targetActionFile, $classContents, false);
        }
    }

    public function convertControllerContext($contents)
    {
        $map = [
            self::OBJ_MGR . '(\'Magento\Framework\Message\ManagerInterface\')' => '$this->messageManager',
            self::OBJ_MGR . '(\'Magento\Framework\Event\ManagerInterface\')' => '$this->_eventManager',
            self::OBJ_MGR . '(\'Magento\Backend\Model\Auth\Session\')->isAllowed(' => '$this->_authorization->isAllowed(',
            self::OBJ_MGR . '(\'Magento\Backend\Model\Auth\')' => '$this->_auth',
            self::OBJ_MGR . '(\'Magento\Backend\Model\Session\')' => '$this->_session',
        ];
        $contents = str_replace(array_keys($map), array_values($map), $contents);
        return $contents;
    }

    ///////////////////////////////////////////////////////////

    public function convertAllObservers()
    {
        //TODO: scan config.xml for observer callbacks?
        $path = $this->expandSourcePath('@EXT/Model/Observer.php');
        if (file_exists($path)) {
            $targetDir = $this->expandOutputPath('Observer');
            $this->convertObserver($path, $targetDir);
        }
    }

    public function convertObserver($sourceFile, $targetDir)
    {
        $contents = $this->readFile($sourceFile);
        $classStartRe = '#^\s*((abstract|final)\s+)?class\s+([A-Za-z0-9_]+)(\s+extends\s+([A-Za-z0-9_]+))?#m';
        if (!preg_match($classStartRe, $contents, $m)) {
            $this->log('[WARN] Invalid observer class: ' . $sourceFile);
            return;
        }

        $origClass = $m[3];
        $obsBaseClass = str_replace('_Model_Observer', '_Observer', $origClass);
        $abstractClass = $obsBaseClass . '_AbstractObserver';

        $contents = str_replace($origClass, $abstractClass, $contents);

        #$this->log('CONTROLLER: ' . $origClass);
        $contents = $this->convertCodeContents($contents);
        $contents = $this->convertCodeParseMethods($contents, 'observer');

        $this->writeFile($targetDir . '/AbstractObserver.php', $contents);

        $nl = $this->_currentFile['nl'];
        $funcRe = '#(public\s+function\s+)([a-zA-Z0-9_]+)(\([^)]+\))#';
        $funcExecute = '$1execute(\Magento\Framework\Event\Observer \$observer)';
        foreach ($this->_currentFile['methods'] as $method) {
            if (!preg_match('#^[A-Za-z]+_#', $method['name'], $m)) {
                continue;
            }
            $obsName = str_replace(' ', '', ucwords(str_replace('_', ' ', $method['name'])));
            $obsClass = "{$obsBaseClass}_{$obsName}";
            $methodContents = join($nl, $method['lines']);
            $txt = preg_replace($funcRe, $funcExecute, $methodContents);
            $classContents = "<?php{$nl}{$nl}class {$obsClass} extends {$abstractClass} implements "
                ."\\Magento\\Framework\\Event\\ObserverInterface{$nl}{{$nl}{$txt}{$nl}}{$nl}";

            $classContents = $this->convertCodeContents($classContents);

            $targetObsFile = "{$targetDir}/{$obsName}.php";

            $this->writeFile($targetObsFile, $classContents, false);
        }
    }

    ///////////////////////////////////////////////////////////

    public function convertAllPhpFilesDI()
    {
        #spl_autoload_unregister([$this, 'autoloadCallback']);
        $this->_autoloadMode = 'm2';
        $files = $this->findFilesRecursive($this->_env['ext_output_dir']);
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
            $contents = $this->convertCodeObjectManagerToDI($contents);
            $contents = $this->convertNamespaceUse($contents);
            $this->writeFile($fullFilename, $contents);
        }
        $this->_autoloadMode = 'm1';
        #spl_autoload_register([$this, 'autoloadCallback']);
    }

    ///////////////////////////////////////////////////////////

    public function convertExtensionStage2($extName)
    {
        $this->_autoloadMode = 'm2';

        $this->log("[INFO] EXTENSION (STAGE 2): {$extName}");

        $autoloadFile = realpath($this->_env['mage2_dir'] . '/vendor/autoload.php');
        if (file_exists($autoloadFile)) {
            include_once($autoloadFile);
        }

        $extDir = str_replace('_', '/', $extName);
        $files = $this->findFilesRecursive("{$this->_env['mage2_code_dir']}/{$extDir}");
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

    ///////////////////////////////////////////////////////////

    protected $_testMode = false;

    protected $_testInputFiles = [];

    protected $_testOutputFiles = [];

    public function setTestMode($testMode = true)
    {
        $this->_testMode = $testMode;
        return $this;
    }

    public function addTestInputFile($contents, $filename = null)
    {
        $this->_testInputFiles[] = ['contents' => $contents, 'filename' => $filename];
        return $this;
    }

    public function getTestOutputFile($filename = null)
    {
        if (empty($this->_testOutputFiles)) {
            return null; // no files in output queue
        }
        foreach ($this->_testOutputFiles as $file) {
            if ($filename === null || $filename === $file['filename']) {
                return $file['contents'];
            }
        }
        return false; // file not found
    }
}
