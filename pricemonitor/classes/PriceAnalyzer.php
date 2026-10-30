<?php
/**
 * Price Monitor - Analizador de precios
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceAnalyzer
{
    private $serpClient;
    private $thresholdCheaper;
    private $thresholdExpensive;

    /**
     * Stopwords en español - palabras demasiado comunes para identificar un producto
     */
    private static $stopwords = [
        'de', 'del', 'el', 'la', 'lo', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'para', 'con', 'por', 'sin', 'sobre', 'entre', 'hacia', 'desde',
        'que', 'como', 'mas', 'muy', 'bien', 'gran', 'todo', 'toda', 'este', 'esta',
        'nuevo', 'nueva', 'nuevos', 'nuevas', 'set', 'pack', 'kit', 'and', 'the',
    ];

    /**
     * Palabras SEO/genéricas que no ayudan a identificar el producto
     */
    private static $genericWords = [
        // Adjetivos SEO
        'espaciosa', 'comodidad', 'estilo', 'confort', 'versatil', 'versátil',
        'moderna', 'moderno', 'funcional', 'resistente', 'proteccion', 'protección',
        'rendimiento', 'premium', 'profesional', 'original', 'originales',
        'elegante', 'ligero', 'ligera', 'ultraligero', 'ultraligera',
        'transpirable', 'ergonomico', 'ergonómico', 'ergonomica', 'ergonómica',
        'ajustable', 'flexible', 'duradero', 'duradera',
        'comodo', 'cómodo', 'comoda', 'cómoda',
        'impermeable', 'antideslizante', 'acolchado', 'acolchada',
        'multifuncional', 'compacto', 'compacta', 'unisex', 'casual',
        'ideal', 'perfecto', 'perfecta', 'edicion', 'edición', 'limitada',
        'temporada', 'exclusivo', 'exclusiva', 'oficial',
        // Género/edad
        'hombre', 'mujer', 'hombres', 'mujeres',
        'niño', 'niña', 'niños', 'niñas', 'nino', 'nina', 'ninos', 'ninas',
        'adulto', 'adulta', 'junior', 'infantil', 'juvenil', 'bebe',
        'men', 'women', 'kids', 'boys', 'girls', 'mens', 'womens',
        // Calidad
        'alta', 'calidad', 'maxima', 'máxima',
        // Actividad genérica
        'deporte', 'deportes', 'sport', 'sports', 'training', 'fitness',
        'running', 'outdoor', 'indoor',
        // Colores (no identifican un producto concreto)
        'negro', 'negra', 'negros', 'negras', 'black',
        'blanco', 'blanca', 'blancos', 'blancas', 'white',
        'azul', 'azules', 'blue', 'navy', 'marino',
        'rojo', 'roja', 'rojos', 'rojas', 'red',
        'verde', 'verdes', 'green',
        'amarillo', 'amarilla', 'yellow',
        'rosa', 'rosas', 'pink',
        'gris', 'grises', 'grey', 'gray',
        'naranja', 'orange',
        'morado', 'morada', 'violeta', 'purple',
        'marron', 'marrón', 'brown', 'beige', 'crema', 'cream',
    ];

    public function __construct(SerpApiClient $serpClient = null)
    {
        $this->serpClient = $serpClient ?: new SerpApiClient();
        $this->thresholdCheaper = (float) Configuration::get('PRICEMONITOR_THRESHOLD_CHEAP') ?: 5.0;
        $this->thresholdExpensive = (float) Configuration::get('PRICEMONITOR_THRESHOLD_EXPENSIVE') ?: 5.0;
    }

    /**
     * Escanear un producto
     *
     * Estrategia de búsqueda:
     * 1. Referencia limpia (sin prefijo GOALKERS-) + marca
     * 2. Fallback: EAN13
     * NUNCA se busca por nombre/título (contaminado por SEO)
     *
     * Pipeline de filtrado:
     * 1. Filtrar dominio propio
     * 2. Filtrar por relevancia de título (palabras clave del producto)
     * 3. Filtrar outliers por mediana (30%-300%)
     */
    public function scanProduct($idProduct, $idShop = null)
    {
        if (!$idShop) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $product = new Product($idProduct, false, $idLang);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception('Producto no encontrado: ' . $idProduct);
        }

        $ourPrice = (float) $product->getPrice(true, null, 2);
        if ($ourPrice <= 0) {
            $ourPrice = (float) $product->price;
        }

        $productName = is_array($product->name) ? $product->name[$idLang] : $product->name;
        $manufacturer = $product->id_manufacturer
            ? Manufacturer::getNameById($product->id_manufacturer)
            : '';

        // NIVEL 1: Buscar por referencia limpia + marca
        $searchQuery = $this->buildReferenceQuery($product);
        $searchType = 'reference';
        $rawResults = [];

        if (!empty($searchQuery)) {
            $rawResults = $this->serpClient->searchGoogleShopping($searchQuery);
        }

        // NIVEL 2: Fallback a EAN si referencia no da resultados
        if (empty($rawResults)) {
            $eanQuery = $this->buildEanQuery($product);
            if (!empty($eanQuery)) {
                $rawResults = $this->serpClient->searchGoogleShopping($eanQuery);
                $searchQuery = $eanQuery;
                $searchType = 'ean';
            }
        }

        // Sin datos para buscar
        if (empty($searchQuery)) {
            throw new Exception('Producto sin referencia ni EAN: ' . $idProduct);
        }

        $rawCount = count($rawResults);

        // PASO 1: Filtrar nuestros propios resultados
        $ownDomain = Configuration::get('PRICEMONITOR_OWN_DOMAIN') ?: '';
        if (!empty($ownDomain)) {
            $rawResults = array_filter($rawResults, function ($item) use ($ownDomain) {
                return stripos($item['source'], $ownDomain) === false
                    && stripos($item['link'], $ownDomain) === false;
            });
            $rawResults = array_values($rawResults);
        }

        // PASO 2: Filtrar por relevancia de título
        $afterRelevance = $this->filterByTitleRelevance($rawResults, $productName, $manufacturer);
        $relevanceFiltered = count($rawResults) - count($afterRelevance);

        // PASO 3: Filtrar outliers (precios absurdos)
        $results = $this->filterOutliers($afterRelevance);
        $outlierFiltered = count($afterRelevance) - count($results);

        $totalFiltered = $rawCount - count($results);

        // Calcular estadísticas usando total_price (precio + envío)
        $totalPrices = array_column($results, 'total_price');
        $minPrice = !empty($totalPrices) ? min($totalPrices) : null;
        $maxPrice = !empty($totalPrices) ? max($totalPrices) : null;
        $avgPrice = !empty($totalPrices) ? round(array_sum($totalPrices) / count($totalPrices), 2) : null;
        $competitorCount = count($results);

        // Calcular estado semáforo (usando total_price)
        $status = $this->calculateStatus($ourPrice, $minPrice, $competitorCount);
        $diffPercent = $minPrice ? round((($ourPrice - $minPrice) / $minPrice) * 100, 2) : null;

        // Guardar escaneo
        $scan = new PriceMonitorScan();
        $scan->id_product = (int) $idProduct;
        $scan->id_shop = (int) $idShop;
        $scan->our_price = $ourPrice;
        $scan->min_price = $minPrice;
        $scan->max_price = $maxPrice;
        $scan->avg_price = $avgPrice;
        $scan->competitor_count = $competitorCount;
        $scan->status = $status;
        $scan->diff_percent = $diffPercent;
        $scan->search_query = $searchQuery;
        $scan->date_scan = date('Y-m-d H:i:s');
        $scan->add();

        // Guardar competidores
        foreach ($results as $result) {
            $competitor = new PriceMonitorCompetitor();
            $competitor->id_scan = (int) $scan->id;
            $competitor->id_product = (int) $idProduct;
            $competitor->competitor_name = $result['source'];
            $competitor->competitor_price = $result['price'];
            $competitor->competitor_shipping = $result['shipping'];
            $competitor->competitor_total_price = $result['total_price'];
            $competitor->competitor_url = $result['link'];
            $competitor->competitor_title = $result['title'];
            $competitor->position = $result['position'];
            $competitor->date_add = date('Y-m-d H:i:s');
            $competitor->add();
        }

        // Log
        PriceMonitorLog::addLog(
            'scan',
            sprintf(
                'Producto #%d [%s]: "%s" - %d brutos, %d por relevancia, %d outliers, %d validos - Estado: %s',
                $idProduct, $searchType, $searchQuery,
                $rawCount, $relevanceFiltered, $outlierFiltered, $competitorCount, $status
            ),
            $idProduct,
            1,
            1
        );

        return [
            'id_scan' => $scan->id,
            'id_product' => $idProduct,
            'product_name' => $productName,
            'our_price' => $ourPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'avg_price' => $avgPrice,
            'competitor_count' => $competitorCount,
            'status' => $status,
            'diff_percent' => $diffPercent,
            'search_query' => $searchQuery,
            'search_type' => $searchType,
            'raw_count' => $rawCount,
            'relevance_filtered' => $relevanceFiltered,
            'outlier_filtered' => $outlierFiltered,
            'filtered_count' => $totalFiltered,
            'competitors' => $results,
        ];
    }

    /**
     * Construir query de búsqueda por REFERENCIA del producto
     *
     * Elimina el prefijo "GOALKERS-" de la referencia interna
     * y antepone el nombre del fabricante.
     *
     * Ejemplo: ref "GOALKERS-IB8655" + marca "Adidas" → "Adidas IB8655"
     */
    public function buildReferenceQuery($product)
    {
        $reference = trim($product->reference);
        if (empty($reference)) {
            return '';
        }

        // Eliminar prefijo GOALKERS-
        $cleanRef = preg_replace('/^GOALKERS-/i', '', $reference);
        if (empty($cleanRef)) {
            return '';
        }

        // Obtener marca
        $manufacturer = '';
        if ($product->id_manufacturer) {
            $manufacturer = Manufacturer::getNameById($product->id_manufacturer);
        }

        return $manufacturer ? $manufacturer . ' ' . $cleanRef : $cleanRef;
    }

    /**
     * Construir query de búsqueda por EAN13 del producto
     */
    public function buildEanQuery($product)
    {
        $ean = trim($product->ean13);
        return !empty($ean) ? $ean : '';
    }

    /**
     * Filtrar resultados por relevancia del título.
     *
     * Extrae palabras clave del nombre del producto y comprueba si
     * el título del competidor contiene al menos una.
     * Esto elimina resultados que son claramente otro producto
     * (ej: zapatillas cuando buscamos una bolsa).
     *
     * Si el filtro elimina TODOS los resultados, devuelve los originales
     * para no perder datos en productos con nombres difíciles.
     */
    public function filterByTitleRelevance(array $competitors, $productName, $manufacturer = '')
    {
        if (empty($competitors) || empty($productName)) {
            return $competitors;
        }

        $keywords = $this->extractProductKeywords($productName, $manufacturer);

        // Sin keywords útiles → no filtrar
        if (empty($keywords)) {
            return $competitors;
        }

        $filtered = array_filter($competitors, function ($c) use ($keywords) {
            $title = mb_strtolower($c['title']);
            foreach ($keywords as $kw) {
                // Para keywords de 5+ chars, usar prefijo de 4 chars
                // Esto captura variantes: bolsa/bolso, mochila/mochilas, zapatilla/zapatillas
                $needle = (mb_strlen($kw) >= 5) ? mb_substr($kw, 0, 4) : $kw;
                if (mb_strpos($title, $needle) !== false) {
                    return true;
                }
            }
            return false;
        });

        // Si filtró TODOS los resultados, devolver originales (fallback seguro)
        if (empty($filtered)) {
            return $competitors;
        }

        return array_values($filtered);
    }

    /**
     * Extraer palabras clave significativas del nombre de un producto.
     *
     * Elimina: marca, stopwords, adjetivos SEO genéricos, palabras cortas (<3 chars).
     * Mantiene: nombre del modelo, tipo de producto, colores, tallas, etc.
     *
     * Ejemplo: "Bolsa de Deporte Adidas Tiro Azul Marino Espaciosa"
     *        → ["bolsa", "deporte", "tiro", "azul", "marino"]
     */
    public function extractProductKeywords($productName, $manufacturer = '')
    {
        $name = mb_strtolower(trim($productName));

        // Eliminar marca del nombre
        if (!empty($manufacturer)) {
            $name = str_ireplace(mb_strtolower($manufacturer), '', $name);
        }

        // Eliminar palabras genéricas/SEO
        foreach (self::$genericWords as $gw) {
            // Reemplazar como palabra completa para no romper substrings
            $name = preg_replace('/\b' . preg_quote($gw, '/') . '\b/ui', '', $name);
        }

        // Separar en palabras
        $words = preg_split('/[\s,\-–—\/\(\)]+/', $name);
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word, ' .');
            // Saltar palabras cortas y stopwords
            if (mb_strlen($word) < 3) {
                continue;
            }
            if (in_array($word, self::$stopwords)) {
                continue;
            }
            $keywords[] = $word;
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Filtrar outliers (resultados con precios absurdos = otro producto)
     *
     * Usa la mediana para detectar precios que están fuera del rango razonable:
     * - Precio < 30% de la mediana → probablemente otro producto más barato
     * - Precio > 300% de la mediana → probablemente otro producto más caro
     */
    public function filterOutliers(array $competitors)
    {
        if (count($competitors) < 3) {
            return $competitors;
        }

        // Usar total_price (precio + envío) para el filtrado
        $prices = array_column($competitors, 'total_price');
        sort($prices);

        $count = count($prices);
        $median = ($count % 2 === 0)
            ? ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2
            : $prices[floor($count / 2)];

        if ($median <= 0) {
            return $competitors;
        }

        $lowerBound = $median * 0.30; // 30% de la mediana
        $upperBound = $median * 3.00; // 300% de la mediana

        $filtered = array_filter($competitors, function ($c) use ($lowerBound, $upperBound) {
            return $c['total_price'] >= $lowerBound && $c['total_price'] <= $upperBound;
        });

        return array_values($filtered);
    }

    /**
     * Calcular el estado semáforo
     * Compara nuestro precio (con envío gratis) vs el total_price del competidor (precio + envío)
     *
     * - cheaper (verde): nuestro precio es menor que el mínimo del mercado
     * - equal (amarillo): nuestro precio está dentro del umbral
     * - expensive (rojo): nuestro precio es mayor que el mínimo
     * - no_data (gris): no hay datos de competidores
     */
    public function calculateStatus($ourPrice, $minTotalPrice, $competitorCount)
    {
        if ($competitorCount === 0 || $minTotalPrice === null) {
            return 'no_data';
        }

        $diffPercent = (($ourPrice - $minTotalPrice) / $minTotalPrice) * 100;

        if ($diffPercent <= -$this->thresholdCheaper) {
            return 'cheaper';
        } elseif ($diffPercent >= $this->thresholdExpensive) {
            return 'expensive';
        }

        return 'equal';
    }

    /**
     * Escaneo por lotes
     */
    public function scanBatch($productIds, $idShop = null, $delay = 2)
    {
        $results = [];
        $errors = [];
        $count = 0;

        foreach ($productIds as $idProduct) {
            try {
                $results[] = $this->scanProduct((int) $idProduct, $idShop);
                $count++;

                if ($delay > 0 && $count < count($productIds)) {
                    sleep($delay);
                }
            } catch (Exception $e) {
                $errors[] = [
                    'id_product' => $idProduct,
                    'error' => $e->getMessage(),
                ];
                PriceMonitorLog::addLog('error', 'Error escaneando producto #' . $idProduct . ': ' . $e->getMessage(), $idProduct);
            }
        }

        return [
            'scanned' => $count,
            'errors' => count($errors),
            'results' => $results,
            'error_details' => $errors,
        ];
    }
}
