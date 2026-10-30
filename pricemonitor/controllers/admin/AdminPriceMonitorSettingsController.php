<?php
/**
 * Price Monitor - Settings Controller
 *
 * @author    OKVIKI <https://okviki.com>
 * @version 1.0.0
 */

class AdminPriceMonitorSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        if (Tools::isSubmit('submitPriceMonitorSettings')) {
            $this->processForm();
        }

        $this->renderSettingsForm();
    }

    /**
     * Procesar formulario
     */
    protected function processForm()
    {
        $apiKey = Tools::getValue('PRICEMONITOR_API_KEY');
        $country = Tools::getValue('PRICEMONITOR_COUNTRY');
        $lang = Tools::getValue('PRICEMONITOR_LANG');
        $thresholdCheap = Tools::getValue('PRICEMONITOR_THRESHOLD_CHEAP');
        $thresholdExpensive = Tools::getValue('PRICEMONITOR_THRESHOLD_EXPENSIVE');
        $ownDomain = Tools::getValue('PRICEMONITOR_OWN_DOMAIN');
        $useReference = Tools::getValue('PRICEMONITOR_USE_REFERENCE');
        $useEan = Tools::getValue('PRICEMONITOR_USE_EAN');
        $cronBatchSize = Tools::getValue('PRICEMONITOR_CRON_BATCH_SIZE');
        $cronDelay = Tools::getValue('PRICEMONITOR_CRON_DELAY');
        $cronDayStart = Tools::getValue('PRICEMONITOR_CRON_DAY_START');
        $cronDayEnd = Tools::getValue('PRICEMONITOR_CRON_DAY_END');
        $retentionDays = Tools::getValue('PRICEMONITOR_RETENTION_DAYS');

        Configuration::updateValue('PRICEMONITOR_API_KEY', $apiKey);
        Configuration::updateValue('PRICEMONITOR_COUNTRY', $country);
        Configuration::updateValue('PRICEMONITOR_LANG', $lang);
        Configuration::updateValue('PRICEMONITOR_THRESHOLD_CHEAP', $thresholdCheap);
        Configuration::updateValue('PRICEMONITOR_THRESHOLD_EXPENSIVE', $thresholdExpensive);
        Configuration::updateValue('PRICEMONITOR_OWN_DOMAIN', $ownDomain);
        Configuration::updateValue('PRICEMONITOR_USE_REFERENCE', $useReference);
        Configuration::updateValue('PRICEMONITOR_USE_EAN', $useEan);
        Configuration::updateValue('PRICEMONITOR_CRON_BATCH_SIZE', $cronBatchSize);
        Configuration::updateValue('PRICEMONITOR_CRON_DELAY', $cronDelay);
        Configuration::updateValue('PRICEMONITOR_CRON_DAY_START', $cronDayStart);
        Configuration::updateValue('PRICEMONITOR_CRON_DAY_END', $cronDayEnd);
        Configuration::updateValue('PRICEMONITOR_RETENTION_DAYS', $retentionDays);

        // Regenerar token si se solicita
        if (Tools::getValue('regenerate_token')) {
            Configuration::updateValue('PRICEMONITOR_CRON_TOKEN', md5(uniqid(mt_rand(), true) . _COOKIE_KEY_));
        }

        $this->confirmations[] = $this->l('Configuracion guardada correctamente.');
    }

    /**
     * Renderizar formulario de configuración
     */
    protected function renderSettingsForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = 'pricemonitor';
        $helper->module = $this->module;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = 'id_pricemonitor';
        $helper->submit_action = 'submitPriceMonitorSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminPriceMonitorSettings', false);
        $helper->token = Tools::getAdminTokenLite('AdminPriceMonitorSettings');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $cronToken = Configuration::get('PRICEMONITOR_CRON_TOKEN');
        $cronUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'modules/pricemonitor/cron/scan.php?token=' . $cronToken;
        $dashboardUrl = $this->context->link->getAdminLink('AdminPriceMonitorDashboard');

        $this->context->smarty->assign([
            'settings_form' => $helper->generateForm([$this->getFormFields()]),
            'cron_url' => $cronUrl,
            'cron_token' => $cronToken,
            'dashboard_url' => $dashboardUrl,
            'api_key_configured' => !empty(Configuration::get('PRICEMONITOR_API_KEY')),
        ]);

        $this->setTemplate('settings.tpl');
    }

    /**
     * Definir campos del formulario
     */
    protected function getFormFields()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuracion de Price Monitor'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    // API
                    [
                        'type' => 'text',
                        'label' => $this->l('SerpAPI Key'),
                        'name' => 'PRICEMONITOR_API_KEY',
                        'desc' => $this->l('Tu clave API de SerpAPI (serpapi.com)'),
                        'class' => 'fixed-width-xxl',
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Pais de busqueda'),
                        'name' => 'PRICEMONITOR_COUNTRY',
                        'desc' => $this->l('Pais para los resultados de Google Shopping'),
                        'options' => [
                            'query' => [
                                ['id' => 'es', 'name' => 'Espana'],
                                ['id' => 'us', 'name' => 'Estados Unidos'],
                                ['id' => 'uk', 'name' => 'Reino Unido'],
                                ['id' => 'de', 'name' => 'Alemania'],
                                ['id' => 'fr', 'name' => 'Francia'],
                                ['id' => 'it', 'name' => 'Italia'],
                                ['id' => 'pt', 'name' => 'Portugal'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Idioma de busqueda'),
                        'name' => 'PRICEMONITOR_LANG',
                        'options' => [
                            'query' => [
                                ['id' => 'es', 'name' => 'Espanol'],
                                ['id' => 'en', 'name' => 'English'],
                                ['id' => 'de', 'name' => 'Deutsch'],
                                ['id' => 'fr', 'name' => 'Francais'],
                                ['id' => 'it', 'name' => 'Italiano'],
                                ['id' => 'pt', 'name' => 'Portugues'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    // Umbrales
                    [
                        'type' => 'text',
                        'label' => $this->l('Umbral "mas barato" (%)'),
                        'name' => 'PRICEMONITOR_THRESHOLD_CHEAP',
                        'desc' => $this->l('Porcentaje por debajo del cual se considera que somos mas baratos (semaforo verde)'),
                        'class' => 'fixed-width-sm',
                        'suffix' => '%',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Umbral "mas caro" (%)'),
                        'name' => 'PRICEMONITOR_THRESHOLD_EXPENSIVE',
                        'desc' => $this->l('Porcentaje por encima del cual se considera que somos mas caros (semaforo rojo)'),
                        'class' => 'fixed-width-sm',
                        'suffix' => '%',
                    ],
                    // Dominio propio
                    [
                        'type' => 'text',
                        'label' => $this->l('Dominio propio'),
                        'name' => 'PRICEMONITOR_OWN_DOMAIN',
                        'desc' => $this->l('Tu dominio para excluirlo de los resultados (ej: goalkers.com)'),
                        'class' => 'fixed-width-xl',
                    ],
                    // Búsqueda
                    [
                        'type' => 'switch',
                        'label' => $this->l('Usar referencia en busqueda'),
                        'name' => 'PRICEMONITOR_USE_REFERENCE',
                        'desc' => $this->l('Incluir la referencia del producto en la consulta de busqueda'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'ref_on', 'value' => 1, 'label' => $this->l('Si')],
                            ['id' => 'ref_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Usar EAN/codigo de barras'),
                        'name' => 'PRICEMONITOR_USE_EAN',
                        'desc' => $this->l('Si el producto tiene EAN, usarlo como unica consulta (busqueda mas precisa)'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'ean_on', 'value' => 1, 'label' => $this->l('Si')],
                            ['id' => 'ean_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    // Cron
                    [
                        'type' => 'text',
                        'label' => $this->l('Lote cron (productos)'),
                        'name' => 'PRICEMONITOR_CRON_BATCH_SIZE',
                        'desc' => $this->l('Numero de productos a escanear por ejecucion del cron'),
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Delay entre llamadas (seg)'),
                        'name' => 'PRICEMONITOR_CRON_DELAY',
                        'desc' => $this->l('Segundos de espera entre cada llamada a la API'),
                        'class' => 'fixed-width-sm',
                        'suffix' => 's',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Dia inicio ventana cron'),
                        'name' => 'PRICEMONITOR_CRON_DAY_START',
                        'desc' => $this->l('Dia del mes a partir del cual se ejecuta el cron (ej: 25)'),
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Dia fin ventana cron'),
                        'name' => 'PRICEMONITOR_CRON_DAY_END',
                        'desc' => $this->l('Dia del mes hasta el cual se ejecuta el cron (ej: 31)'),
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Retencion de datos (dias)'),
                        'name' => 'PRICEMONITOR_RETENTION_DAYS',
                        'desc' => $this->l('Dias que se conservan los escaneos antiguos antes de limpiarlos'),
                        'class' => 'fixed-width-sm',
                        'suffix' => $this->l('dias'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
                ],
            ],
        ];
    }

    /**
     * Obtener valores de configuración
     */
    protected function getConfigValues()
    {
        return [
            'PRICEMONITOR_API_KEY' => Configuration::get('PRICEMONITOR_API_KEY'),
            'PRICEMONITOR_COUNTRY' => Configuration::get('PRICEMONITOR_COUNTRY'),
            'PRICEMONITOR_LANG' => Configuration::get('PRICEMONITOR_LANG'),
            'PRICEMONITOR_THRESHOLD_CHEAP' => Configuration::get('PRICEMONITOR_THRESHOLD_CHEAP'),
            'PRICEMONITOR_THRESHOLD_EXPENSIVE' => Configuration::get('PRICEMONITOR_THRESHOLD_EXPENSIVE'),
            'PRICEMONITOR_OWN_DOMAIN' => Configuration::get('PRICEMONITOR_OWN_DOMAIN'),
            'PRICEMONITOR_USE_REFERENCE' => Configuration::get('PRICEMONITOR_USE_REFERENCE'),
            'PRICEMONITOR_USE_EAN' => Configuration::get('PRICEMONITOR_USE_EAN'),
            'PRICEMONITOR_CRON_BATCH_SIZE' => Configuration::get('PRICEMONITOR_CRON_BATCH_SIZE'),
            'PRICEMONITOR_CRON_DELAY' => Configuration::get('PRICEMONITOR_CRON_DELAY'),
            'PRICEMONITOR_CRON_DAY_START' => Configuration::get('PRICEMONITOR_CRON_DAY_START'),
            'PRICEMONITOR_CRON_DAY_END' => Configuration::get('PRICEMONITOR_CRON_DAY_END'),
            'PRICEMONITOR_RETENTION_DAYS' => Configuration::get('PRICEMONITOR_RETENTION_DAYS'),
        ];
    }
}
