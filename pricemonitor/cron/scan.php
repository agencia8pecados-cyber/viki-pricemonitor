<?php
/**
 * Price Monitor - Cron Scanner
 *
 * Uso: curl "https://www.goalkers.com/modules/pricemonitor/cron/scan.php?token=TOKEN"
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

// Bootstrap PrestaShop
$psRootDir = dirname(__FILE__) . '/../../../';

require_once $psRootDir . 'config/config.inc.php';
require_once $psRootDir . 'init.php';

// Verificar token
$token = Tools::getValue('token');
$validToken = Configuration::get('PRICEMONITOR_CRON_TOKEN');

if (empty($token) || $token !== $validToken) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied');
}

// Verificar ventana de dias del mes
$currentDay = (int) date('j');
$dayStart = (int) Configuration::get('PRICEMONITOR_CRON_DAY_START') ?: 25;
$dayEnd = (int) Configuration::get('PRICEMONITOR_CRON_DAY_END') ?: 31;

if ($currentDay < $dayStart || $currentDay > $dayEnd) {
    echo json_encode([
        'success' => true,
        'message' => 'Fuera de la ventana de ejecucion (dias ' . $dayStart . '-' . $dayEnd . ', hoy es dia ' . $currentDay . ')',
        'scanned' => 0,
    ]);
    exit;
}

// Configuracion
$batchSize = (int) Configuration::get('PRICEMONITOR_CRON_BATCH_SIZE') ?: 50;
$delay = (int) Configuration::get('PRICEMONITOR_CRON_DELAY') ?: 2;
$retentionDays = (int) Configuration::get('PRICEMONITOR_RETENTION_DAYS') ?: 90;

$startTime = microtime(true);

try {
    // Cargar clases del modulo
    require_once dirname(__FILE__) . '/../classes/PriceMonitorScan.php';
    require_once dirname(__FILE__) . '/../classes/PriceMonitorCompetitor.php';
    require_once dirname(__FILE__) . '/../classes/PriceMonitorLog.php';
    require_once dirname(__FILE__) . '/../classes/SerpApiClient.php';
    require_once dirname(__FILE__) . '/../classes/PriceAnalyzer.php';

    $idShop = (int) Configuration::get('PS_SHOP_DEFAULT') ?: 1;
    $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

    // Obtener productos a escanear (los mas antiguos o nunca escaneados)
    // Excluir productos excluidos
    $sql = 'SELECT p.id_product
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON p.id_product = ps.id_product AND ps.id_shop = ' . (int) $idShop . '
            LEFT JOIN (
                SELECT id_product, MAX(date_scan) as last_scan
                FROM `' . _DB_PREFIX_ . 'pricemonitor_scan`
                WHERE id_shop = ' . (int) $idShop . '
                GROUP BY id_product
            ) s ON p.id_product = s.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'pricemonitor_exclusion` ex
                ON p.id_product = ex.id_product
            WHERE ps.active = 1
                AND ex.id_exclusion IS NULL
            ORDER BY s.last_scan ASC, p.id_product ASC
            LIMIT ' . (int) $batchSize;

    $products = Db::getInstance()->executeS($sql);

    if (!$products || empty($products)) {
        echo json_encode([
            'success' => true,
            'message' => 'No hay productos para escanear',
            'scanned' => 0,
        ]);
        exit;
    }

    $productIds = array_column($products, 'id_product');

    // Escanear
    $analyzer = new PriceAnalyzer();
    $result = $analyzer->scanBatch($productIds, $idShop, $delay);

    $executionTime = round(microtime(true) - $startTime, 2);

    // Log de ejecucion
    PriceMonitorLog::addLog(
        'cron',
        sprintf(
            'Cron ejecutado: %d productos escaneados, %d errores, %.2f segundos',
            $result['scanned'],
            $result['errors'],
            $executionTime
        ),
        null,
        $result['scanned'],
        $result['scanned'],
        $executionTime
    );

    // Limpiar datos antiguos
    PriceMonitorScan::cleanOldScans($retentionDays);
    PriceMonitorLog::cleanOldLogs($retentionDays);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'scanned' => $result['scanned'],
        'errors' => $result['errors'],
        'execution_time' => $executionTime,
        'batch_size' => $batchSize,
    ]);

} catch (Exception $e) {
    PriceMonitorLog::addLog('error', 'Error cron: ' . $e->getMessage());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
