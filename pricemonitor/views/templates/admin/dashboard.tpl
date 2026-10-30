{**
 * Price Monitor - Dashboard
 * @version 1.0.0
 *}

{if !$api_key_configured}
<div class="alert alert-warning">
    <i class="icon-warning-sign"></i>
    {l s='La API Key de SerpAPI no esta configurada.' mod='pricemonitor'}
    <a href="{$settings_url|escape:'html':'UTF-8'}" class="btn btn-default btn-sm">
        <i class="icon-cog"></i> {l s='Configurar ahora' mod='pricemonitor'}
    </a>
</div>
{/if}

<!-- KPI Cards -->
<div class="row" id="kpi-cards">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel pm-kpi-card pm-kpi-total">
            <div class="pm-kpi-value">{$stats.total|intval}</div>
            <div class="pm-kpi-label">{l s='Escaneados' mod='pricemonitor'}</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel pm-kpi-card pm-kpi-cheaper">
            <div class="pm-kpi-value">{$stats.cheaper|intval}</div>
            <div class="pm-kpi-label">{l s='Mas baratos' mod='pricemonitor'}</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel pm-kpi-card pm-kpi-equal">
            <div class="pm-kpi-value">{$stats.equal|intval}</div>
            <div class="pm-kpi-label">{l s='Precio similar' mod='pricemonitor'}</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel pm-kpi-card pm-kpi-expensive">
            <div class="pm-kpi-value">{$stats.expensive|intval}</div>
            <div class="pm-kpi-label">{l s='Mas caros' mod='pricemonitor'}</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel pm-kpi-card pm-kpi-nodata">
            <div class="pm-kpi-value">{$stats.no_data|intval}</div>
            <div class="pm-kpi-label">{l s='Sin datos' mod='pricemonitor'}</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel pm-kpi-card pm-kpi-credits">
            <div class="pm-kpi-value">{$api_credits|intval}</div>
            <div class="pm-kpi-label">{l s='Creditos API (30d)' mod='pricemonitor'}</div>
        </div>
    </div>
</div>

<!-- Panel principal -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-line-chart"></i> {l s='Price Monitor - Comparativa de Precios' mod='pricemonitor'}
        <span class="badge badge-info" id="total-products-badge">{$pagination.total_products|intval}</span>
        <div class="panel-heading-action">
            <a href="{$settings_url|escape:'html':'UTF-8'}" class="btn btn-default btn-sm">
                <i class="icon-cog"></i> {l s='Configuracion' mod='pricemonitor'}
            </a>
        </div>
    </div>

    <div class="panel-body">
        <!-- FILTROS -->
        <div class="well" id="filters-panel">
            <div class="row">
                <div class="col-lg-2">
                    <div class="form-group">
                        <label>{l s='Buscar:' mod='pricemonitor'}</label>
                        <input type="text" class="form-control" id="filter-search" placeholder="{l s='ID, nombre o referencia' mod='pricemonitor'}" value="{$filters.search|escape:'html':'UTF-8'}">
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label>{l s='Estado precio:' mod='pricemonitor'}</label>
                        <select class="form-control" id="filter-status">
                            <option value="">{l s='Todos' mod='pricemonitor'}</option>
                            <option value="cheaper" {if $filters.status === 'cheaper'}selected{/if}>{l s='Mas barato' mod='pricemonitor'}</option>
                            <option value="equal" {if $filters.status === 'equal'}selected{/if}>{l s='Similar' mod='pricemonitor'}</option>
                            <option value="expensive" {if $filters.status === 'expensive'}selected{/if}>{l s='Mas caro' mod='pricemonitor'}</option>
                            <option value="no_data" {if $filters.status === 'no_data'}selected{/if}>{l s='Sin datos' mod='pricemonitor'}</option>
                            <option value="not_scanned" {if $filters.status === 'not_scanned'}selected{/if}>{l s='No escaneado' mod='pricemonitor'}</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label>{l s='Marca:' mod='pricemonitor'}</label>
                        <select class="form-control" id="filter-manufacturer">
                            <option value="0">{l s='Todas' mod='pricemonitor'}</option>
                            {foreach from=$manufacturers item=manufacturer}
                                <option value="{$manufacturer.id_manufacturer|intval}" {if $filters.id_manufacturer == $manufacturer.id_manufacturer}selected{/if}>
                                    {$manufacturer.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label>{l s='Categoria:' mod='pricemonitor'}</label>
                        <select class="form-control" id="filter-category">
                            <option value="0">{l s='Todas' mod='pricemonitor'}</option>
                            {foreach from=$categories item=category}
                                <option value="{$category.id_category|intval}" {if $filters.id_category == $category.id_category}selected{/if}>
                                    {$category.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="col-lg-1">
                    <div class="form-group">
                        <label>{l s='Activo:' mod='pricemonitor'}</label>
                        <select class="form-control" id="filter-active">
                            <option value="">{l s='Todos' mod='pricemonitor'}</option>
                            <option value="1" {if $filters.active === '1'}selected{/if}>{l s='Si' mod='pricemonitor'}</option>
                            <option value="0" {if $filters.active === '0'}selected{/if}>{l s='No' mod='pricemonitor'}</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-default btn-block" id="btn-clear-filters" title="{l s='Limpiar filtros' mod='pricemonitor'}">
                            <i class="icon-eraser"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCIONES MASIVAS -->
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-lg-6">
                <button type="button" class="btn btn-primary" id="btn-scan-selected" disabled>
                    <i class="icon-search"></i> {l s='Escanear seleccionados' mod='pricemonitor'}
                </button>
                <button type="button" class="btn btn-default" id="btn-export-csv">
                    <i class="icon-download"></i> {l s='Exportar CSV' mod='pricemonitor'}
                </button>
            </div>
            <div class="col-lg-6 text-right">
                <span class="text-muted" id="selection-info"></span>
            </div>
        </div>

        <!-- BARRA DE PROGRESO -->
        <div id="scan-progress" style="display: none;">
            <div class="progress">
                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%;">
                    <span class="progress-text">0%</span>
                </div>
            </div>
            <p class="text-center" id="scan-status"></p>
        </div>

        <!-- LOADING -->
        <div id="table-loading" style="display: none;">
            <div class="text-center" style="padding: 40px;">
                <i class="icon-spinner icon-spin icon-3x"></i>
                <p>{l s='Cargando productos...' mod='pricemonitor'}</p>
            </div>
        </div>

        <!-- TABLA DE PRODUCTOS -->
        <div class="table-responsive" id="table-container">
            <table class="table table-striped table-hover" id="products-table">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all"></th>
                        <th width="50">{l s='ID' mod='pricemonitor'}</th>
                        <th>{l s='Producto' mod='pricemonitor'}</th>
                        <th width="100">{l s='Marca' mod='pricemonitor'}</th>
                        <th width="90" class="text-right">{l s='Nuestro' mod='pricemonitor'}</th>
                        <th width="90" class="text-right">{l s='Min. Total' mod='pricemonitor'}</th>
                        <th width="90" class="text-right">{l s='Medio Total' mod='pricemonitor'}</th>
                        <th width="50" class="text-center">{l s='Comp.' mod='pricemonitor'}</th>
                        <th width="70" class="text-center">{l s='Dif. %' mod='pricemonitor'}</th>
                        <th width="80" class="text-center">{l s='Estado' mod='pricemonitor'}</th>
                        <th width="100" class="text-center">{l s='Escaneo' mod='pricemonitor'}</th>
                        <th width="140" class="text-center">{l s='Acciones' mod='pricemonitor'}</th>
                    </tr>
                </thead>
                <tbody id="products-tbody">
                    {foreach from=$products item=product}
                        <tr data-id="{$product.id_product|intval}" class="{if $product.is_excluded}pm-row-excluded{/if}">
                            <td><input type="checkbox" class="product-checkbox" value="{$product.id_product|intval}"></td>
                            <td>{$product.id_product|intval}</td>
                            <td>
                                <strong>{$product.product_name|escape:'html':'UTF-8'|truncate:60:'...'}</strong>
                                {if $product.reference}
                                    <br><small class="text-muted">{$product.reference|escape:'html':'UTF-8'}</small>
                                {/if}
                            </td>
                            <td><span class="text-muted">{$product.manufacturer_name|escape:'html':'UTF-8'|default:'-'}</span></td>
                            <td class="text-right">
                                <strong>{$product.our_price|default:$product.product_price|string_format:"%.2f"} &euro;</strong>
                            </td>
                            <td class="text-right">
                                {if $product.min_price}
                                    {$product.min_price|string_format:"%.2f"} &euro;
                                {else}
                                    <span class="text-muted">-</span>
                                {/if}
                            </td>
                            <td class="text-right">
                                {if $product.avg_price}
                                    {$product.avg_price|string_format:"%.2f"} &euro;
                                {else}
                                    <span class="text-muted">-</span>
                                {/if}
                            </td>
                            <td class="text-center">{$product.competitor_count|intval|default:'-'}</td>
                            <td class="text-center">
                                {if $product.diff_percent !== null && $product.diff_percent !== ''}
                                    <span class="pm-diff {if $product.diff_percent > 0}pm-diff-positive{elseif $product.diff_percent < 0}pm-diff-negative{/if}">
                                        {if $product.diff_percent > 0}+{/if}{$product.diff_percent|string_format:"%.1f"}%
                                    </span>
                                {else}
                                    <span class="text-muted">-</span>
                                {/if}
                            </td>
                            <td class="text-center">
                                {if $product.status}
                                    <span class="pm-status pm-status-{$product.status|escape:'html':'UTF-8'}">
                                        {if $product.status == 'cheaper'}{l s='Barato' mod='pricemonitor'}
                                        {elseif $product.status == 'equal'}{l s='Similar' mod='pricemonitor'}
                                        {elseif $product.status == 'expensive'}{l s='Caro' mod='pricemonitor'}
                                        {else}{l s='Sin datos' mod='pricemonitor'}
                                        {/if}
                                    </span>
                                {else}
                                    <span class="pm-status pm-status-none">{l s='Pendiente' mod='pricemonitor'}</span>
                                {/if}
                            </td>
                            <td class="text-center">
                                {if $product.date_scan}
                                    <small>{$product.date_scan|date_format:"%d/%m/%y %H:%M"}</small>
                                {else}
                                    <small class="text-muted">{l s='Nunca' mod='pricemonitor'}</small>
                                {/if}
                            </td>
                            <td class="text-center pm-actions">
                                <button type="button" class="btn btn-xs btn-primary btn-scan-single" data-id="{$product.id_product|intval}" title="{l s='Escanear' mod='pricemonitor'}">
                                    <i class="icon-search"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-info btn-show-competitors" data-id="{$product.id_product|intval}" title="{l s='Ver competidores' mod='pricemonitor'}" {if !$product.id_scan}disabled{/if}>
                                    <i class="icon-users"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-warning btn-adjust-price" data-id="{$product.id_product|intval}" data-price="{$product.our_price|default:$product.product_price|string_format:"%.2f"}" data-min="{$product.min_price|string_format:"%.2f"}" title="{l s='Ajustar precio' mod='pricemonitor'}">
                                    <i class="icon-euro"></i>
                                </button>
                                <a href="{$link->getAdminLink('AdminProducts', true, ['id_product' => $product.id_product, 'updateproduct' => 1])|escape:'html':'UTF-8'}" class="btn btn-xs btn-default" target="_blank" title="{l s='Editar producto' mod='pricemonitor'}">
                                    <i class="icon-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr id="no-products-row">
                            <td colspan="12" class="text-center">
                                <p class="text-muted" style="padding: 20px;">
                                    <i class="icon-info-circle"></i> {l s='No se encontraron productos' mod='pricemonitor'}
                                </p>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        <!-- PAGINACION -->
        <div class="row" id="pagination-container">
            <div class="col-lg-6">
                <p class="text-muted pagination-info">
                    {l s='Mostrando' mod='pricemonitor'}
                    <strong id="showing-from">{if $pagination.total_products > 0}1{else}0{/if}</strong> -
                    <strong id="showing-to">{if $pagination.total_products < $pagination.per_page}{$pagination.total_products|intval}{else}{$pagination.per_page|intval}{/if}</strong>
                    {l s='de' mod='pricemonitor'}
                    <strong id="showing-total">{$pagination.total_products|intval}</strong>
                    {l s='productos' mod='pricemonitor'}
                </p>
            </div>
            <div class="col-lg-6">
                <nav class="pull-right">
                    <ul class="pagination" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Modal Competidores -->
<div class="modal fade" id="competitors-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="icon-users"></i> {l s='Competidores' mod='pricemonitor'} - <span id="modal-product-name"></span></h4>
            </div>
            <div class="modal-body" id="competitors-content">
                <div class="text-center"><i class="icon-spinner icon-spin icon-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Cerrar' mod='pricemonitor'}</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajustar Precio -->
<div class="modal fade" id="price-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="icon-euro"></i> {l s='Ajustar Precio' mod='pricemonitor'}</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{l s='Precio actual:' mod='pricemonitor'}</label>
                    <p class="form-control-static"><strong id="modal-current-price">0.00</strong> &euro;</p>
                </div>
                <div class="form-group">
                    <label>{l s='Precio minimo competencia:' mod='pricemonitor'}</label>
                    <p class="form-control-static"><strong id="modal-min-price">0.00</strong> &euro;</p>
                </div>
                <div class="form-group">
                    <label for="modal-new-price">{l s='Nuevo precio:' mod='pricemonitor'}</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="modal-new-price" step="0.01" min="0.01">
                        <span class="input-group-addon">&euro;</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>{l s='Metodo:' mod='pricemonitor'}</label>
                    <select class="form-control" id="modal-price-method">
                        <option value="specific_price">{l s='Precio especifico (no modifica precio base)' mod='pricemonitor'}</option>
                        <option value="base_price">{l s='Modificar precio base' mod='pricemonitor'}</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Cancelar' mod='pricemonitor'}</button>
                <button type="button" class="btn btn-success" id="btn-apply-price">
                    <i class="icon-check"></i> {l s='Aplicar precio' mod='pricemonitor'}
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var pmAjaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var pmCurrentPage = {$pagination.current_page|intval};
    var pmTotalPages = {$pagination.total_pages|intval};
    var pmPerPage = {$pagination.per_page|intval};
    var pmTotalProducts = {$pagination.total_products|intval};
</script>
