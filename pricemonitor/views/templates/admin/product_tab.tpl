{**
 * Price Monitor - Tab en ficha de producto
 * @version 1.0.0
 *}

<div class="panel" id="pricemonitor-product-tab">
    <div class="panel-heading">
        <i class="icon-line-chart"></i> {l s='Price Monitor' mod='pricemonitor'}
        <span class="panel-heading-action">
            <button type="button" class="btn btn-primary btn-sm" id="pm-btn-scan" data-id="{$id_product|intval}">
                <i class="icon-search"></i> {l s='Escanear ahora' mod='pricemonitor'}
            </button>
        </span>
    </div>

    <div class="panel-body">
        {if $latest_scan}
            <!-- Resumen ultimo escaneo -->
            <div class="row">
                <div class="col-lg-3">
                    <div class="pm-product-stat">
                        <span class="pm-stat-label">{l s='Nuestro precio' mod='pricemonitor'}</span>
                        <span class="pm-stat-value">{$latest_scan.our_price|string_format:"%.2f"} &euro;</span>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="pm-product-stat">
                        <span class="pm-stat-label">{l s='Precio min. competencia' mod='pricemonitor'}</span>
                        <span class="pm-stat-value">
                            {if $latest_scan.min_price}{$latest_scan.min_price|string_format:"%.2f"} &euro;{else}-{/if}
                        </span>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="pm-product-stat">
                        <span class="pm-stat-label">{l s='Estado' mod='pricemonitor'}</span>
                        <span class="pm-status pm-status-{$latest_scan.status|escape:'html':'UTF-8'}">
                            {if $latest_scan.status == 'cheaper'}{l s='Mas barato' mod='pricemonitor'}
                            {elseif $latest_scan.status == 'equal'}{l s='Precio similar' mod='pricemonitor'}
                            {elseif $latest_scan.status == 'expensive'}{l s='Mas caro' mod='pricemonitor'}
                            {else}{l s='Sin datos' mod='pricemonitor'}
                            {/if}
                        </span>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="pm-product-stat">
                        <span class="pm-stat-label">{l s='Diferencia' mod='pricemonitor'}</span>
                        <span class="pm-stat-value">
                            {if $latest_scan.diff_percent !== null}
                                <span class="pm-diff {if $latest_scan.diff_percent > 0}pm-diff-positive{elseif $latest_scan.diff_percent < 0}pm-diff-negative{/if}">
                                    {if $latest_scan.diff_percent > 0}+{/if}{$latest_scan.diff_percent|string_format:"%.1f"}%
                                </span>
                            {else}-{/if}
                        </span>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Competidores -->
            {if $competitors && count($competitors) > 0}
                <h4>{l s='Competidores' mod='pricemonitor'} ({$competitors|count})</h4>
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th width="30">#</th>
                                <th>{l s='Tienda' mod='pricemonitor'}</th>
                                <th>{l s='Titulo' mod='pricemonitor'}</th>
                                <th class="text-right">{l s='Precio' mod='pricemonitor'}</th>
                                <th class="text-right">{l s='Envio' mod='pricemonitor'}</th>
                                <th class="text-right">{l s='Total' mod='pricemonitor'}</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$competitors item=comp}
                                <tr>
                                    <td>{$comp.position|intval}</td>
                                    <td><strong>{$comp.competitor_name|escape:'html':'UTF-8'}</strong></td>
                                    <td><small>{$comp.competitor_title|escape:'html':'UTF-8'|truncate:80:'...'}</small></td>
                                    <td class="text-right">
                                        {$comp.competitor_price|string_format:"%.2f"} &euro;
                                    </td>
                                    <td class="text-right">
                                        {if $comp.competitor_shipping > 0}
                                            +{$comp.competitor_shipping|string_format:"%.2f"} &euro;
                                        {else}
                                            <span class="text-success">{l s='Gratis' mod='pricemonitor'}</span>
                                        {/if}
                                    </td>
                                    <td class="text-right">
                                        <strong>{$comp.competitor_total_price|string_format:"%.2f"} &euro;</strong>
                                    </td>
                                    <td>
                                        {if $comp.competitor_url}
                                            <a href="{$comp.competitor_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener" class="btn btn-xs btn-default">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {/if}

            <!-- Historial -->
            {if $scan_history && count($scan_history) > 1}
                <h4>{l s='Historial de escaneos' mod='pricemonitor'}</h4>
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>{l s='Fecha' mod='pricemonitor'}</th>
                                <th class="text-right">{l s='Nuestro' mod='pricemonitor'}</th>
                                <th class="text-right">{l s='Min.' mod='pricemonitor'}</th>
                                <th class="text-right">{l s='Medio' mod='pricemonitor'}</th>
                                <th class="text-center">{l s='Comp.' mod='pricemonitor'}</th>
                                <th class="text-center">{l s='Estado' mod='pricemonitor'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$scan_history item=scan}
                                <tr>
                                    <td>{$scan.date_scan|date_format:"%d/%m/%Y %H:%M"}</td>
                                    <td class="text-right">{$scan.our_price|string_format:"%.2f"} &euro;</td>
                                    <td class="text-right">{if $scan.min_price}{$scan.min_price|string_format:"%.2f"} &euro;{else}-{/if}</td>
                                    <td class="text-right">{if $scan.avg_price}{$scan.avg_price|string_format:"%.2f"} &euro;{else}-{/if}</td>
                                    <td class="text-center">{$scan.competitor_count|intval}</td>
                                    <td class="text-center">
                                        <span class="pm-status pm-status-{$scan.status|escape:'html':'UTF-8'}">
                                            {if $scan.status == 'cheaper'}{l s='Barato' mod='pricemonitor'}
                                            {elseif $scan.status == 'equal'}{l s='Similar' mod='pricemonitor'}
                                            {elseif $scan.status == 'expensive'}{l s='Caro' mod='pricemonitor'}
                                            {else}{l s='Sin datos' mod='pricemonitor'}
                                            {/if}
                                        </span>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {/if}

            <p class="text-muted">
                <small><i class="icon-clock-o"></i> {l s='Ultimo escaneo:' mod='pricemonitor'} {$latest_scan.date_scan|date_format:"%d/%m/%Y %H:%M:%S"}</small>
                {if $latest_scan.search_query}
                    <br><small><i class="icon-search"></i> {l s='Busqueda:' mod='pricemonitor'} "{$latest_scan.search_query|escape:'html':'UTF-8'}"</small>
                {/if}
            </p>
        {else}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i>
                {l s='Este producto no ha sido escaneado aun. Pulsa "Escanear ahora" para buscar precios de la competencia.' mod='pricemonitor'}
            </div>
        {/if}

        <!-- Contenedor para resultados AJAX -->
        <div id="pm-scan-result" style="display: none;"></div>
    </div>
</div>

<script type="text/javascript">
    var pmProductAjaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var pmProductId = {$id_product|intval};
</script>
