<?php
/**
 * Price Monitor - Módulo PrestaShop
 * Monitoriza precios de la competencia en Google Shopping mediante SerpAPI
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/PriceMonitorScan.php';
require_once dirname(__FILE__) . '/classes/PriceMonitorCompetitor.php';
require_once dirname(__FILE__) . '/classes/PriceMonitorLog.php';
require_once dirname(__FILE__) . '/classes/SerpApiClient.php';
require_once dirname(__FILE__) . '/classes/PriceAnalyzer.php';

class PriceMonitor extends Module
{
    public function __construct()
    {
        $this->name = 'pricemonitor';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'OKVIKI';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('OKVIKI Price Monitor');
        $this->description = $this->l('Monitoriza precios de la competencia en Google Shopping mediante SerpAPI con semaforo visual y ajuste de precios.');
        $this->confirmUninstall = $this->l('¿Estas seguro de que quieres desinstalar Price Monitor? Se eliminaran todos los datos de escaneos.');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => '8.99.99'];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->installDatabase()
            && $this->installTabs()
            && $this->installConfig();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallDatabase()
            && $this->uninstallTabs()
            && $this->uninstallConfig();
    }

    /**
     * Instalar tablas en base de datos
     */
    private function installDatabase()
    {
        $sqlFile = dirname(__FILE__) . '/sql/install.sql';
        if (!file_exists($sqlFile)) {
            return false;
        }

        $sql = file_get_contents($sqlFile);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $sql = str_replace('ENGINE_TYPE', _MYSQL_ENGINE_, $sql);

        $queries = preg_split('/;\s*[\r\n]+/', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Desinstalar tablas
     */
    private function uninstallDatabase()
    {
        $sqlFile = dirname(__FILE__) . '/sql/uninstall.sql';
        if (!file_exists($sqlFile)) {
            return true;
        }

        $sql = file_get_contents($sqlFile);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        $queries = preg_split('/;\s*[\r\n]+/', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                Db::getInstance()->execute($query);
            }
        }

        return true;
    }

    /**
     * Instalar tabs del menú admin
     */
    private function installTabs()
    {
        // Tab principal: Dashboard
        $tabDashboard = new Tab();
        $tabDashboard->active = 1;
        $tabDashboard->class_name = 'AdminPriceMonitorDashboard';
        $tabDashboard->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tabDashboard->name[$lang['id_lang']] = 'Price Monitor';
        }
        $tabDashboard->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tabDashboard->module = $this->name;
        if (!$tabDashboard->add()) {
            return false;
        }

        // Tab secundario: Settings (oculto en menú)
        $tabSettings = new Tab();
        $tabSettings->active = 1;
        $tabSettings->class_name = 'AdminPriceMonitorSettings';
        $tabSettings->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tabSettings->name[$lang['id_lang']] = 'Configuracion PM';
        }
        $tabSettings->id_parent = (int) Tab::getIdFromClassName('AdminPriceMonitorDashboard');
        $tabSettings->module = $this->name;
        if (!$tabSettings->add()) {
            return false;
        }

        return true;
    }

    /**
     * Desinstalar tabs
     */
    private function uninstallTabs()
    {
        $tabs = ['AdminPriceMonitorSettings', 'AdminPriceMonitorDashboard'];
        foreach ($tabs as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        }

        return true;
    }

    /**
     * Instalar configuración por defecto
     */
    private function installConfig()
    {
        return Configuration::updateValue('PRICEMONITOR_API_KEY', '')
            && Configuration::updateValue('PRICEMONITOR_COUNTRY', 'es')
            && Configuration::updateValue('PRICEMONITOR_LANG', 'es')
            && Configuration::updateValue('PRICEMONITOR_THRESHOLD_CHEAP', '5')
            && Configuration::updateValue('PRICEMONITOR_THRESHOLD_EXPENSIVE', '5')
            && Configuration::updateValue('PRICEMONITOR_OWN_DOMAIN', '')
            && Configuration::updateValue('PRICEMONITOR_USE_REFERENCE', '0')
            && Configuration::updateValue('PRICEMONITOR_USE_EAN', '1')
            && Configuration::updateValue('PRICEMONITOR_CRON_BATCH_SIZE', '50')
            && Configuration::updateValue('PRICEMONITOR_CRON_DELAY', '2')
            && Configuration::updateValue('PRICEMONITOR_CRON_TOKEN', md5(uniqid(mt_rand(), true) . _COOKIE_KEY_))
            && Configuration::updateValue('PRICEMONITOR_CRON_DAY_START', '25')
            && Configuration::updateValue('PRICEMONITOR_CRON_DAY_END', '31')
            && Configuration::updateValue('PRICEMONITOR_RETENTION_DAYS', '90');
    }

    /**
     * Desinstalar configuración
     */
    private function uninstallConfig()
    {
        $keys = [
            'PRICEMONITOR_API_KEY', 'PRICEMONITOR_COUNTRY', 'PRICEMONITOR_LANG',
            'PRICEMONITOR_THRESHOLD_CHEAP', 'PRICEMONITOR_THRESHOLD_EXPENSIVE',
            'PRICEMONITOR_OWN_DOMAIN', 'PRICEMONITOR_USE_REFERENCE', 'PRICEMONITOR_USE_EAN',
            'PRICEMONITOR_CRON_BATCH_SIZE', 'PRICEMONITOR_CRON_DELAY',
            'PRICEMONITOR_CRON_TOKEN', 'PRICEMONITOR_CRON_DAY_START',
            'PRICEMONITOR_CRON_DAY_END', 'PRICEMONITOR_RETENTION_DAYS',
        ];

        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Página de configuración del módulo (redirige a Settings)
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPriceMonitorSettings'));
    }

    /**
     * Hook: Cargar CSS/JS en admin
     */
    public function hookActionAdminControllerSetMedia()
    {
        $controller = Tools::getValue('controller');
        $pmControllers = ['AdminPriceMonitorDashboard', 'AdminPriceMonitorSettings', 'AdminProducts'];

        if (in_array($controller, $pmControllers)) {
            $this->context->controller->addCSS($this->_path . 'views/css/pricemonitor.css');

            if ($controller === 'AdminPriceMonitorDashboard') {
                $this->context->controller->addJS($this->_path . 'views/js/dashboard.js');
            }

            if ($controller === 'AdminProducts') {
                $this->context->controller->addJS($this->_path . 'views/js/product-tab.js');
            }
        }
    }

    /**
     * Hook: Header BackOffice
     * Inyecta CSS/JS en la página de listado de productos (Catálogo → Productos)
     */
    public function hookDisplayBackOfficeHeader()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Detectar página de listado de productos (Symfony route)
        $isProductListing = (strpos($uri, '/sell/catalog/products') !== false)
            && (strpos($uri, '/products/') === false);

        if ($isProductListing) {
            $ajaxUrl = $this->context->link->getAdminLink('AdminPriceMonitorDashboard');
            $output = '<link rel="stylesheet" href="' . $this->_path . 'views/css/pricemonitor.css">';
            $output .= '<script>var pmListingAjaxUrl = "' . addslashes($ajaxUrl) . '";</script>';
            $output .= '<script src="' . $this->_path . 'views/js/product-listing.js"></script>';
            return $output;
        }
    }

    /**
     * Hook: Tab en ficha de producto
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = (int) Tools::getValue('id_product', isset($params['id_product']) ? $params['id_product'] : 0);

        if (!$idProduct) {
            return '';
        }

        $latestScan = PriceMonitorScan::getLatestByProduct($idProduct);
        $competitors = [];
        $history = [];

        if ($latestScan) {
            $competitors = PriceMonitorCompetitor::getByScan((int) $latestScan['id_scan']);
            $history = PriceMonitorScan::getHistoryByProduct($idProduct, 10);
        }

        $this->context->smarty->assign([
            'id_product' => $idProduct,
            'latest_scan' => $latestScan,
            'competitors' => $competitors,
            'scan_history' => $history,
            'ajax_url' => $this->context->link->getAdminLink('AdminPriceMonitorDashboard'),
            'module_path' => $this->_path,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/product_tab.tpl');
    }
}
