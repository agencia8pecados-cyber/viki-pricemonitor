<?php
/**
 * Price Monitor - Modelo de logs
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceMonitorLog extends ObjectModel
{
    public $id_log;
    public $type;
    public $message;
    public $id_product;
    public $api_credits_used;
    public $products_scanned;
    public $execution_time;
    public $date_add;

    public static $definition = [
        'table' => 'pricemonitor_log',
        'primary' => 'id_log',
        'fields' => [
            'type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'message' => ['type' => self::TYPE_HTML, 'required' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'api_credits_used' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'products_scanned' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'execution_time' => ['type' => self::TYPE_FLOAT],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Obtener últimos logs
     */
    public static function getLatest($limit = 20, $type = null)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pricemonitor_log');
        if ($type) {
            $sql->where('type = "' . pSQL($type) . '"');
        }
        $sql->orderBy('date_add DESC');
        $sql->limit((int) $limit);

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Registrar una entrada de log
     */
    public static function addLog($type, $message, $idProduct = null, $creditsUsed = 0, $productsScanned = 0, $executionTime = null)
    {
        return Db::getInstance()->insert('pricemonitor_log', [
            'type' => pSQL($type),
            'message' => pSQL($message, true),
            'id_product' => $idProduct ? (int) $idProduct : null,
            'api_credits_used' => (int) $creditsUsed,
            'products_scanned' => (int) $productsScanned,
            'execution_time' => $executionTime !== null ? (float) $executionTime : null,
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtener total de créditos API usados en un periodo
     */
    public static function getTotalCreditsUsed($days = 30)
    {
        $date = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
        return (int) Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(api_credits_used), 0) FROM `' . _DB_PREFIX_ . 'pricemonitor_log` WHERE date_add >= "' . pSQL($date) . '"'
        );
    }

    /**
     * Limpiar logs antiguos
     */
    public static function cleanOldLogs($days = 90)
    {
        $date = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
        return Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pricemonitor_log` WHERE date_add < "' . pSQL($date) . '"'
        );
    }
}
