<?php
/**
 * Price Monitor - Cliente SerpAPI
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SerpApiClient
{
    private $apiKey;
    private $baseUrl = 'https://serpapi.com/search.json';

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: Configuration::get('PRICEMONITOR_API_KEY');
    }

    /**
     * Buscar en Google Shopping
     */
    public function searchGoogleShopping($query, $params = [])
    {
        $defaultParams = [
            'engine' => 'google_shopping',
            'q' => $query,
            'gl' => Configuration::get('PRICEMONITOR_COUNTRY') ?: 'es',
            'hl' => Configuration::get('PRICEMONITOR_LANG') ?: 'es',
            'num' => 20,
        ];

        $requestParams = array_merge($defaultParams, $params);
        $response = $this->makeRequest($requestParams);

        return $this->parseResults($response);
    }

    /**
     * Realizar la petición HTTP a SerpAPI
     */
    protected function makeRequest($params)
    {
        if (empty($this->apiKey)) {
            throw new Exception('API Key de SerpAPI no configurada');
        }

        $params['api_key'] = $this->apiKey;
        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('Error de conexion SerpAPI: ' . $error);
        }

        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            $errorMsg = isset($data['error']) ? $data['error'] : 'Error HTTP ' . $httpCode;
            throw new Exception('Error SerpAPI (' . $httpCode . '): ' . $errorMsg);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Respuesta JSON invalida de SerpAPI');
        }

        return $data;
    }

    /**
     * Parsear los resultados de Google Shopping
     */
    protected function parseResults($response)
    {
        $results = [];

        if (!isset($response['shopping_results']) || !is_array($response['shopping_results'])) {
            return $results;
        }

        $position = 0;
        foreach ($response['shopping_results'] as $item) {
            $position++;
            $price = $this->extractPrice($item);
            if ($price === null) {
                continue;
            }

            $shipping = $this->extractShipping($item);
            $totalPrice = round($price + $shipping, 2);

            $results[] = [
                'title' => isset($item['title']) ? $item['title'] : '',
                'price' => $price,
                'shipping' => $shipping,
                'total_price' => $totalPrice,
                'source' => isset($item['source']) ? $item['source'] : 'Desconocido',
                'link' => isset($item['link']) ? $item['link'] : '',
                'position' => $position,
            ];
        }

        return $results;
    }

    /**
     * Extraer coste de envio de un resultado
     */
    protected function extractShipping($item)
    {
        // SerpAPI devuelve delivery como string: "+3,99 € de envío", "Envío gratuito", "Free delivery"
        if (!isset($item['delivery']) || empty($item['delivery'])) {
            return 0.0;
        }

        $delivery = $item['delivery'];

        // Envio gratuito
        if (preg_match('/gratu[ií]to|free|gratis|incluid/i', $delivery)) {
            return 0.0;
        }

        // Extraer precio del string: "+3,99 €", "3.99€", "€3.99"
        if (preg_match('/[\d.,]+/', $delivery, $matches)) {
            $shippingStr = $matches[0];
            // Formato europeo: 3,99
            if (preg_match('/^\d+,\d{1,2}$/', $shippingStr)) {
                $shippingStr = str_replace(',', '.', $shippingStr);
            }
            // Formato europeo: 1.234,56
            if (preg_match('/\d+\.\d{3},/', $shippingStr)) {
                $shippingStr = str_replace('.', '', $shippingStr);
                $shippingStr = str_replace(',', '.', $shippingStr);
            }

            if (is_numeric($shippingStr) && $shippingStr > 0) {
                return (float) $shippingStr;
            }
        }

        return 0.0;
    }

    /**
     * Extraer precio de un resultado
     */
    protected function extractPrice($item)
    {
        // Primero intentar extracted_price (numérico directo)
        if (isset($item['extracted_price']) && is_numeric($item['extracted_price'])) {
            return (float) $item['extracted_price'];
        }

        // Luego intentar price como string
        if (isset($item['price'])) {
            $price = $item['price'];
            // Eliminar símbolos de moneda y espacios
            $price = preg_replace('/[^\d.,]/', '', $price);
            // Manejar formato europeo (1.234,56) vs americano (1,234.56)
            if (preg_match('/\d+\.\d{3},/', $price)) {
                // Formato europeo
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } elseif (preg_match('/\d+,\d{1,2}$/', $price)) {
                // Formato con coma decimal
                $price = str_replace(',', '.', $price);
            }

            if (is_numeric($price) && $price > 0) {
                return (float) $price;
            }
        }

        return null;
    }

    /**
     * Verificar que la API key es válida
     */
    public function testConnection()
    {
        try {
            $params = [
                'engine' => 'google_shopping',
                'q' => 'test',
                'gl' => 'es',
                'num' => 1,
            ];
            $this->makeRequest($params);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
