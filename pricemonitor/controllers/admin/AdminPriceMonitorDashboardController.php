<?php
/**
 * Price Monitor - Dashboard Controller
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

class AdminPriceMonitorDashboardController extends ModuleAdminController
{
    protected $perPage = 50;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
    }

    protected function getShopId()
    {
        if (Shop::isFeatureActive()) {
            return (int) $this->context->shop->id;
        }
        return 1;
    }

    public function initContent()
    {
        parent::initContent();

        if (Tools::getValue('ajax')) {
            $this->handleAjaxRequest();
            exit;
        }

        $this->renderProductList();
    }

    /**
     * Manejar peticiones AJAX
     */
    protected function handleAjaxRequest()
    {
        header('Content-Type: application/json');

        try {
            $action = Tools::getValue('action');

            switch ($action) {
                case 'scanProduct':
                    $this->ajaxScanProduct();
                    break;
                case 'scanBatch':
                    $this->ajaxScanBatch();
                    break;
                case 'updatePrice':
                    $this->ajaxUpdatePrice();
                    break;
                case 'getProducts':
                    $this->ajaxGetProducts();
                    break;
                case 'getCompetitors':
                    $this->ajaxGetCompetitors();
                    break;
                case 'exportCsv':
                    $this->ajaxExportCsv();
                    break;
                case 'toggleExclusion':
                    $this->ajaxToggleExclusion();
                    break;
                case 'getBulkScanData':
                    $this->ajaxGetBulkScanData();
                    break;
                default:
                    throw new Exception('Accion no valida: ' . $action);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Escanear un producto individual
     */
    protected function ajaxScanProduct()
    {
        $idProduct = (int) Tools::getValue('id_product');
        if (!$idProduct) {
            throw new Exception('ID de producto no valido');
        }

        $analyzer = new PriceAnalyzer();
        $result = $analyzer->scanProduct($idProduct, $this->getShopId());

        echo json_encode([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Escaneo masivo
     */
    protected function ajaxScanBatch()
    {
        $productIds = Tools::getValue('product_ids');
        if (is_string($productIds)) {
            $productIds = json_decode($productIds, true);
        }
        if (!$productIds || !is_array($productIds)) {
            throw new Exception('IDs de producto no validos');
        }

        $delay = (int) Configuration::get('PRICEMONITOR_CRON_DELAY') ?: 2;
        $analyzer = new PriceAnalyzer();
        $result = $analyzer->scanBatch($productIds, $this->getShopId(), $delay);

        echo json_encode([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Ajustar precio de un producto (specific_price)
     */
    protected function ajaxUpdatePrice()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $newPrice = (float) Tools::getValue('new_price');
        $method = Tools::getValue('method', 'specific_price');

        if (!$idProduct || $newPrice <= 0) {
            throw new Exception('Parametros no validos');
        }

        if ($method === 'base_price') {
            // Actualizar precio base del producto
            $product = new Product($idProduct);
            if (!Validate::isLoadedObject($product)) {
                throw new Exception('Producto no encontrado');
            }
            $product->price = $newPrice;
            $product->update();
        } else {
            // Crear o actualizar specific_price
            // Primero eliminar specific_price del módulo si existe
            $existingId = Db::getInstance()->getValue(
                'SELECT id_specific_price FROM `' . _DB_PREFIX_ . 'specific_price`
                 WHERE id_product = ' . (int) $idProduct . '
                 AND id_shop = ' . (int) $this->getShopId() . '
                 AND id_customer = 0
                 AND id_group = 0
                 AND id_country = 0
                 AND id_currency = 0
                 AND `from` = "0000-00-00 00:00:00"
                 AND `to` = "0000-00-00 00:00:00"
                 AND reduction_type = "amount"
                 AND id_cart = 0'
            );

            if ($existingId) {
                $specificPrice = new SpecificPrice((int) $existingId);
            } else {
                $specificPrice = new SpecificPrice();
            }

            $product = new Product($idProduct);
            $reduction = $product->price - $newPrice;

            if ($reduction <= 0) {
                // Si el nuevo precio es mayor o igual al base, eliminar specific_price
                if ($existingId) {
                    $specificPrice->delete();
                }
            } else {
                $specificPrice->id_product = $idProduct;
                $specificPrice->id_shop = $this->getShopId();
                $specificPrice->id_currency = 0;
                $specificPrice->id_country = 0;
                $specificPrice->id_group = 0;
                $specificPrice->id_customer = 0;
                $specificPrice->id_product_attribute = 0;
                $specificPrice->price = -1;
                $specificPrice->from_quantity = 1;
                $specificPrice->reduction = $reduction;
                $specificPrice->reduction_type = 'amount';
                $specificPrice->reduction_tax = 1;
                $specificPrice->from = '0000-00-00 00:00:00';
                $specificPrice->to = '0000-00-00 00:00:00';

                if ($existingId) {
                    $specificPrice->update();
                } else {
                    $specificPrice->add();
                }
            }
        }

        PriceMonitorLog::addLog(
            'price_update',
            sprintf('Precio del producto #%d actualizado a %.2f EUR (metodo: %s)', $idProduct, $newPrice, $method),
            $idProduct
        );

        echo json_encode([
            'success' => true,
            'message' => 'Precio actualizado correctamente',
        ]);
    }

    /**
     * Obtener productos con filtros (AJAX)
     */
    protected function ajaxGetProducts()
    {
        $page = (int) Tools::getValue('page', 1);
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = $this->getShopId();
        $filters = $this->getFiltersFromRequest();

        $products = PriceMonitorScan::getProductsWithLatestScan($idShop, $idLang, $page, $this->perPage, $filters);
        $totalProducts = PriceMonitorScan::countProductsWithFilters($idShop, $idLang, $filters);

        echo json_encode([
            'success' => true,
            'data' => [
                'products' => $products,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalProducts / $this->perPage),
                    'total_products' => $totalProducts,
                    'per_page' => $this->perPage,
                ],
            ],
        ]);
    }

    /**
     * Obtener competidores de un escaneo
     */
    protected function ajaxGetCompetitors()
    {
        $idProduct = (int) Tools::getValue('id_product');
        if (!$idProduct) {
            throw new Exception('ID de producto no valido');
        }

        $competitors = PriceMonitorCompetitor::getLatestByProduct($idProduct);
        $scan = PriceMonitorScan::getLatestByProduct($idProduct);

        echo json_encode([
            'success' => true,
            'data' => [
                'competitors' => $competitors,
                'scan' => $scan,
            ],
        ]);
    }

    /**
     * Exportar a CSV
     */
    protected function ajaxExportCsv()
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = $this->getShopId();
        $filters = $this->getFiltersFromRequest();

        $products = PriceMonitorScan::getProductsWithLatestScan($idShop, $idLang, 1, 99999, $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pricemonitor_export_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        fputcsv($output, [
            'ID', 'Producto', 'Referencia', 'Marca', 'Categoria',
            'Nuestro Precio', 'Precio Min.', 'Precio Medio', 'Precio Max.',
            'Competidores', 'Estado', 'Diferencia %', 'Ultimo Escaneo',
        ], ';');

        foreach ($products as $p) {
            fputcsv($output, [
                $p['id_product'],
                $p['product_name'],
                $p['reference'],
                $p['manufacturer_name'],
                $p['category_name'],
                $p['our_price'] ?: $p['product_price'],
                $p['min_price'],
                $p['avg_price'],
                $p['max_price'],
                $p['competitor_count'],
                $p['status'],
                $p['diff_percent'],
                $p['date_scan'],
            ], ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Activar/desactivar exclusión de producto
     */
    protected function ajaxToggleExclusion()
    {
        $idProduct = (int) Tools::getValue('id_product');
        if (!$idProduct) {
            throw new Exception('ID de producto no valido');
        }

        $existing = Db::getInstance()->getValue(
            'SELECT id_exclusion FROM `' . _DB_PREFIX_ . 'pricemonitor_exclusion` WHERE id_product = ' . (int) $idProduct
        );

        if ($existing) {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'pricemonitor_exclusion` WHERE id_product = ' . (int) $idProduct
            );
            $excluded = false;
        } else {
            Db::getInstance()->insert('pricemonitor_exclusion', [
                'id_product' => (int) $idProduct,
                'reason' => 'Excluido manualmente',
                'date_add' => date('Y-m-d H:i:s'),
            ]);
            $excluded = true;
        }

        echo json_encode([
            'success' => true,
            'excluded' => $excluded,
        ]);
    }

    /**
     * Renderizar lista de productos
     */
    protected function renderProductList()
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = $this->getShopId();
        $page = (int) Tools::getValue('page', 1);
        $filters = $this->getFiltersFromRequest();

        $products = PriceMonitorScan::getProductsWithLatestScan($idShop, $idLang, $page, $this->perPage, $filters);
        $totalProducts = PriceMonitorScan::countProductsWithFilters($idShop, $idLang, $filters);
        $totalPages = ceil($totalProducts / $this->perPage);

        $stats = PriceMonitorScan::getStatsByStatus($idShop);
        $apiCredits = PriceMonitorLog::getTotalCreditsUsed(30);
        $recentLogs = PriceMonitorLog::getLatest(5);

        $apiKeyConfigured = !empty(Configuration::get('PRICEMONITOR_API_KEY'));
        $settingsUrl = $this->context->link->getAdminLink('AdminPriceMonitorSettings');
        $cronToken = Configuration::get('PRICEMONITOR_CRON_TOKEN');
        $cronUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'modules/pricemonitor/cron/scan.php?token=' . $cronToken;

        $this->context->smarty->assign([
            'products' => $products,
            'ajax_url' => $this->context->link->getAdminLink('AdminPriceMonitorDashboard'),
            'settings_url' => $settingsUrl,
            'manufacturers' => Manufacturer::getManufacturers(false, $idLang, true),
            'categories' => $this->getCategoriesForFilter($idLang),
            'filters' => $filters,
            'stats' => $stats,
            'api_credits' => $apiCredits,
            'recent_logs' => $recentLogs,
            'api_key_configured' => $apiKeyConfigured,
            'cron_url' => $cronUrl,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_products' => $totalProducts,
                'per_page' => $this->perPage,
            ],
        ]);

        $this->setTemplate('dashboard.tpl');
    }

    /**
     * Obtener filtros del request
     */
    protected function getFiltersFromRequest()
    {
        return [
            'active' => Tools::getValue('filter_active', ''),
            'status' => Tools::getValue('filter_status', ''),
            'id_manufacturer' => (int) Tools::getValue('filter_manufacturer', 0),
            'id_category' => (int) Tools::getValue('filter_category', 0),
            'search' => Tools::getValue('filter_search', ''),
            'excluded' => Tools::getValue('filter_excluded', ''),
        ];
    }

    /**
     * Obtener datos de escaneo en lote (para columna en listado de productos)
     */
    protected function ajaxGetBulkScanData()
    {
        $productIds = Tools::getValue('product_ids');
        if (is_string($productIds)) {
            $productIds = json_decode($productIds, true);
        }
        if (!$productIds || !is_array($productIds)) {
            throw new Exception('IDs de producto no validos');
        }

        $idShop = $this->getShopId();
        $data = [];

        $ids = array_map('intval', $productIds);
        $idsStr = implode(',', $ids);

        // Obtener último escaneo de cada producto
        $sql = 'SELECT s.id_product, s.our_price, s.min_price, s.avg_price, s.status, s.diff_percent, s.id_scan
                FROM `' . _DB_PREFIX_ . 'pricemonitor_scan` s
                INNER JOIN (
                    SELECT id_product, MAX(date_scan) as max_date
                    FROM `' . _DB_PREFIX_ . 'pricemonitor_scan`
                    WHERE id_shop = ' . (int) $idShop . '
                    AND id_product IN (' . $idsStr . ')
                    GROUP BY id_product
                ) latest ON s.id_product = latest.id_product AND s.date_scan = latest.max_date
                WHERE s.id_shop = ' . (int) $idShop;

        $scans = Db::getInstance()->executeS($sql);

        if ($scans) {
            $scanIds = [];
            foreach ($scans as $scan) {
                $data[$scan['id_product']] = [
                    'our_price' => $scan['our_price'],
                    'min_price' => $scan['min_price'],
                    'avg_price' => $scan['avg_price'],
                    'status' => $scan['status'],
                    'diff_percent' => $scan['diff_percent'],
                    'cheapest_store' => null,
                    'cheapest_url' => null,
                ];
                $scanIds[$scan['id_product']] = $scan['id_scan'];
            }

            // Obtener competidor más barato de cada escaneo
            foreach ($scanIds as $idProduct => $idScan) {
                $cheapest = Db::getInstance()->getRow(
                    'SELECT competitor_name, competitor_url, competitor_total_price
                     FROM `' . _DB_PREFIX_ . 'pricemonitor_competitor`
                     WHERE id_scan = ' . (int) $idScan . '
                     ORDER BY competitor_total_price ASC
                     LIMIT 1'
                );
                if ($cheapest) {
                    $data[$idProduct]['cheapest_store'] = $cheapest['competitor_name'];
                    $data[$idProduct]['cheapest_url'] = $cheapest['competitor_url'];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Obtener categorías para filtro
     */
    protected function getCategoriesForFilter($idLang)
    {
        $idShop = $this->getShopId();

        $sql = 'SELECT c.id_category, c.level_depth, cl.name
                FROM `' . _DB_PREFIX_ . 'category` c
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                    ON c.id_category = cl.id_category AND cl.id_lang = ' . (int) $idLang . ' AND cl.id_shop = ' . (int) $idShop . '
                WHERE c.active = 1
                ORDER BY c.level_depth, cl.name';

        $categories = Db::getInstance()->executeS($sql);
        $result = [];

        if ($categories) {
            foreach ($categories as $cat) {
                $depth = max(0, (int) $cat['level_depth'] - 1);
                $result[] = [
                    'id_category' => $cat['id_category'],
                    'name' => str_repeat('-- ', $depth) . $cat['name'],
                ];
            }
        }

        return $result;
    }
}
