<?php
/**
 * Price Monitor - Modelo de competidores
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceMonitorCompetitor extends ObjectModel
{
    public $id_competitor;
    public $id_scan;
    public $id_product;
    public $competitor_name;
    public $competitor_price;
    public $competitor_shipping;
    public $competitor_total_price;
    public $competitor_url;
    public $competitor_title;
    public $position;
    public $date_add;

    public static $definition = [
        'table' => 'pricemonitor_competitor',
        'primary' => 'id_competitor',
        'fields' => [
            'id_scan' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'competitor_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255, 'required' => true],
            'competitor_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'competitor_shipping' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'competitor_total_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'competitor_url' => ['type' => self::TYPE_STRING, 'size' => 65535],
            'competitor_title' => ['type' => self::TYPE_STRING, 'size' => 512],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Obtener competidores de un escaneo
     */
    public static function getByScan($idScan)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pricemonitor_competitor');
        $sql->where('id_scan = ' . (int) $idScan);
        $sql->orderBy('competitor_price ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener competidores del último escaneo de un producto
     */
    public static function getLatestByProduct($idProduct)
    {
        $lastScan = PriceMonitorScan::getLatestByProduct($idProduct);
        if (!$lastScan) {
            return [];
        }

        return self::getByScan((int) $lastScan['id_scan']);
    }

    /**
     * Eliminar competidores de un escaneo
     */
    public static function deleteByScan($idScan)
    {
        return Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pricemonitor_competitor` WHERE id_scan = ' . (int) $idScan
        );
    }
}
