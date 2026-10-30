{**
 * Price Monitor - Settings
 * @version 1.0.0
 *}

<div class="row">
    <div class="col-lg-9">
        {$settings_form}
    </div>
    <div class="col-lg-3">
        <!-- Info Panel -->
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-info-circle"></i> {l s='Informacion' mod='pricemonitor'}
            </div>
            <div class="panel-body">
                <p>
                    <a href="{$dashboard_url|escape:'html':'UTF-8'}" class="btn btn-primary btn-block">
                        <i class="icon-line-chart"></i> {l s='Ir al Dashboard' mod='pricemonitor'}
                    </a>
                </p>
                <hr>
                <h4>{l s='API SerpAPI' mod='pricemonitor'}</h4>
                <p class="text-muted">
                    {l s='Necesitas una cuenta en' mod='pricemonitor'}
                    <a href="https://serpapi.com" target="_blank" rel="noopener">serpapi.com</a>
                    {l s='para obtener tu API Key.' mod='pricemonitor'}
                </p>
                {if $api_key_configured}
                    <p><span class="label label-success"><i class="icon-check"></i> {l s='API Key configurada' mod='pricemonitor'}</span></p>
                {else}
                    <p><span class="label label-danger"><i class="icon-times"></i> {l s='API Key no configurada' mod='pricemonitor'}</span></p>
                {/if}
            </div>
        </div>

        <!-- Cron Panel -->
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-clock-o"></i> {l s='Cron' mod='pricemonitor'}
            </div>
            <div class="panel-body">
                <p class="text-muted">{l s='URL para configurar el cron job:' mod='pricemonitor'}</p>
                <div class="well well-sm" style="word-break: break-all; font-size: 11px;">
                    {$cron_url|escape:'html':'UTF-8'}
                </div>
                <p class="text-muted">
                    <small>{l s='Token actual:' mod='pricemonitor'} <code>{$cron_token|escape:'html':'UTF-8'|truncate:20:'...'}</code></small>
                </p>
            </div>
        </div>
    </div>
</div>
