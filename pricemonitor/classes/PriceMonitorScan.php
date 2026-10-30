<?php
/**
 * Price Monitor - Modelo de escaneos
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceMonitorScan extends ObjectModel
{
    public $id_scan;
    public $id_product;
    public $id_shop;
    public $our_price;
    public $min_price;
    public $max_price;
    public $avg_price;
    public $competitor_count;
    public $status;
    public $diff_percent;
    public $search_query;
    public $date_scan;

    public static $definition = [
        'table' => 'pricemonitor_scan',
        'primary' => 'id_scan',
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'our_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'min_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'max_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'avg_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'competitor_count' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'diff_percent' => ['type' => self::TYPE_FLOAT],
            'search_query' => ['type' => self::TYPE_STRING, 'size' => 512],
            'date_scan' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Obtener el escaneo más reciente de un producto
     */
    public static function getLatestByProduct($idProduct, $idShop = null)
    {
        if (!$idShop) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pricemonitor_scan');
        $sql->where('id_product = ' . (int) $idProduct);
        $sql->where('id_shop = ' . (int) $idShop);
        $sql->orderBy('date_scan DESC');

        return Db::getInstance()->getRow($sql);
    }

    /**
     * Obtener estadísticas por estado
     */
    public static function getStatsByStatus($idShop = null)
    {
        if (!$idShop) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        // Subconsulta para obtener el último escaneo por producto
        $sql = 'SELECT s.status, COUNT(*) as total
                FROM `' . _DB_PREFIX_ . 'pricemonitor_scan` s
                INNER JOIN (
                    SELECT id_product, MAX(date_scan) as max_date
                    FROM `' . _DB_PREFIX_ . 'pricemonitor_scan`
                    WHERE id_shop = ' . (int) $idShop . '
                    GROUP BY id_product
                ) latest ON s.id_product = latest.id_product AND s.date_scan = latest.max_date
                WHERE s.id_shop = ' . (int) $idShop . '
                GROUP BY s.status';

        $results = Db::getInstance()->executeS($sql);
        $stats = [
            'cheaper' => 0,
            'equal' => 0,
            'expensive' => 0,
            'no_data' => 0,
            'total' => 0,
        ];

        if ($results) {
            foreach ($results as $row) {
                $stats[$row['status']] = (int) $row['total'];
                $stats['total'] += (int) $row['total'];
            }
        }

        return $stats;
    }

    /**
     * Obtener historial de escaneos de un producto
     */
    public static function getHistoryByProduct($idProduct, $limit = 10)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pricemonitor_scan');
        $sql->where('id_product = ' . (int) $idProduct);
        $sql->orderBy('date_scan DESC');
        $sql->limit((int) $limit);

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener productos escaneados con datos del último escaneo
     */
    public static function getProductsWithLatestScan($idShop, $idLang, $page = 1, $perPage = 50, $filters = [])
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT p.id_product, pl.name as product_name, p.reference,
                    ps.price as product_price, ps.active,
                    m.name as manufacturer_name,
                    cl.name as category_name,
                    s.id_scan, s.our_price, s.min_price, s.avg_price, s.max_price,
                    s.competitor_count, s.status, s.diff_percent, s.date_scan,
                    IF(ex.id_exclusion IS NOT NULL, 1, 0) as is_excluded
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                    ON p.id_product = ps.id_product AND ps.id_shop = ' . (int) $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON p.id_product = pl.id_product AND pl.id_lang = ' . (int) $idLang . ' AND pl.id_shop = ' . (int) $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m
                    ON p.id_manufacturer = m.id_manufacturer
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                    ON p.id_category_default = cl.id_category AND cl.id_lang = ' . (int) $idLang . ' AND cl.id_shop = ' . (int) $idShop . '
                LEFT JOIN (
                    SELECT sc.* FROM `' . _DB_PREFIX_ . 'pricemonitor_scan` sc
                    INNER JOIN (
                        SELECT id_product, MAX(date_scan) as max_date
                        FROM `' . _DB_PREFIX_ . 'pricemonitor_scan`
                        WHERE id_shop = ' . (int) $idShop . '
                        GROUP BY id_product
                    ) latest ON sc.id_product = latest.id_product AND sc.date_scan = latest.max_date
                    WHERE sc.id_shop = ' . (int) $idShop . '
                ) s ON p.id_product = s.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'pricemonitor_exclusion` ex
                    ON p.id_product = ex.id_product
                WHERE 1 ' . self::buildFilterWhere($filters) . '
                ORDER BY p.id_product DESC
                LIMIT ' . (int) $offset . ', ' . (int) $perPage;

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Contar productos con filtros
     */
    public static function countProductsWithFilters($idShop, $idLang, $filters = [])
    {
        $sql = 'SELECT COUNT(DISTINCT p.id_product)
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                    ON p.id_product = ps.id_product AND ps.id_shop = ' . (int) $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON p.id_product = pl.id_product AND pl.id_lang = ' . (int) $idLang . ' AND pl.id_shop = ' . (int) $idShop . '
                LEFT JOIN (
                    SELECT sc.* FROM `' . _DB_PREFIX_ . 'pricemonitor_scan` sc
                    INNER JOIN (
                        SELECT id_product, MAX(date_scan) as max_date
                        FROM `' . _DB_PREFIX_ . 'pricemonitor_scan`
                        WHERE id_shop = ' . (int) $idShop . '
                        GROUP BY id_product
                    ) latest ON sc.id_product = latest.id_product AND sc.date_scan = latest.max_date
                    WHERE sc.id_shop = ' . (int) $idShop . '
                ) s ON p.id_product = s.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'pricemonitor_exclusion` ex
                    ON p.id_product = ex.id_product
                WHERE 1 ' . self::buildFilterWhere($filters);

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Construir condiciones WHERE para filtros
     */
    protected static function buildFilterWhere($filters)
    {
        $where = '';

        if (isset($filters['active']) && $filters['active'] !== '') {
            $where .= ' AND ps.active = ' . (int) $filters['active'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'not_scanned') {
                $where .= ' AND s.id_scan IS NULL';
            } else {
                $where .= ' AND s.status = "' . pSQL($filters['status']) . '"';
            }
        }

        if (!empty($filters['id_manufacturer'])) {
            $where .= ' AND p.id_manufacturer = ' . (int) $filters['id_manufacturer'];
        }

        if (!empty($filters['id_category'])) {
            $where .= ' AND p.id_category_default = ' . (int) $filters['id_category'];
        }

        if (!empty($filters['search'])) {
            $search = pSQL($filters['search']);
            $where .= ' AND (pl.name LIKE "%' . $search . '%" OR p.id_product = ' . (int) $filters['search'] . ' OR p.reference LIKE "%' . $search . '%")';
        }

        if (!empty($filters['excluded'])) {
            if ($filters['excluded'] === '1') {
                $where .= ' AND ex.id_exclusion IS NOT NULL';
            } else {
                $where .= ' AND ex.id_exclusion IS NULL';
            }
        }

        return $where;
    }

    /**
     * Eliminar escaneos antiguos
     */
    public static function cleanOldScans($days = 90)
    {
        $date = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));

        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pricemonitor_competitor`
             WHERE id_scan IN (
                SELECT id_scan FROM `' . _DB_PREFIX_ . 'pricemonitor_scan`
                WHERE date_scan < "' . pSQL($date) . '"
             )'
        );

        return Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pricemonitor_scan` WHERE date_scan < "' . pSQL($date) . '"'
        );
    }
}
