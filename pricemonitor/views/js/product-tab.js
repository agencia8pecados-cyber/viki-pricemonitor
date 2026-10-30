/**
 * Price Monitor - Product Tab JS
 * @version 1.0.0
 */

$(document).ready(function () {
    // Escanear desde la ficha de producto
    $('#pm-btn-scan').on('click', function () {
        var btn = $(this);
        var idProduct = btn.data('id');

        btn.prop('disabled', true);
        btn.find('i').removeClass('icon-search').addClass('icon-spinner icon-spin');

        $.ajax({
            url: pmProductAjaxUrl,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'scanProduct',
                id_product: idProduct
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderProductScanResult(response.data);
                } else {
                    showProductNotification('error', response.error || 'Error desconocido');
                }
            },
            error: function () {
                showProductNotification('error', 'Error de conexion');
            },
            complete: function () {
                btn.prop('disabled', false);
                btn.find('i').removeClass('icon-spinner icon-spin').addClass('icon-search');
            }
        });
    });

    function renderProductScanResult(data) {
        var statusLabels = {
            cheaper: 'Mas barato', equal: 'Precio similar', expensive: 'Mas caro', no_data: 'Sin datos'
        };

        var html = '<div class="alert alert-success"><i class="icon-check"></i> Escaneo completado</div>';

        // Stats
        html += '<div class="row">';
        html += '<div class="col-lg-3"><div class="pm-product-stat"><span class="pm-stat-label">Nuestro precio</span><span class="pm-stat-value">' + parseFloat(data.our_price).toFixed(2) + ' &euro;</span></div></div>';
        html += '<div class="col-lg-3"><div class="pm-product-stat"><span class="pm-stat-label">Precio min. competencia</span><span class="pm-stat-value">' + (data.min_price ? parseFloat(data.min_price).toFixed(2) + ' &euro;' : '-') + '</span></div></div>';
        html += '<div class="col-lg-3"><div class="pm-product-stat"><span class="pm-stat-label">Estado</span><span class="pm-status pm-status-' + data.status + '">' + (statusLabels[data.status] || 'Sin datos') + '</span></div></div>';
        html += '<div class="col-lg-3"><div class="pm-product-stat"><span class="pm-stat-label">Diferencia</span><span class="pm-stat-value">';
        if (data.diff_percent !== null && data.diff_percent !== undefined) {
            var diffClass = data.diff_percent > 0 ? 'pm-diff-positive' : (data.diff_percent < 0 ? 'pm-diff-negative' : '');
            var prefix = data.diff_percent > 0 ? '+' : '';
            html += '<span class="pm-diff ' + diffClass + '">' + prefix + parseFloat(data.diff_percent).toFixed(1) + '%</span>';
        } else {
            html += '-';
        }
        html += '</span></div></div>';
        html += '</div>';

        // Competitors
        if (data.competitors && data.competitors.length > 0) {
            html += '<hr><h4>Competidores (' + data.competitors.length + ')';
            if (data.raw_count !== undefined && data.filtered_count !== undefined) {
                html += ' <small class="text-muted">(' + data.raw_count + ' brutos, ' + data.filtered_count + ' filtrados como outliers)</small>';
            }
            html += '</h4>';
            html += '<div class="table-responsive"><table class="table table-striped table-condensed">';
            html += '<thead><tr><th>#</th><th>Tienda</th><th>Titulo</th><th class="text-right">Precio</th><th class="text-right">Envio</th><th class="text-right">Total</th><th></th></tr></thead><tbody>';

            $.each(data.competitors, function (i, c) {
                var shipping = parseFloat(c.shipping || 0);
                var totalPrice = parseFloat(c.total_price || c.price);
                var shippingHtml = shipping > 0
                    ? '+' + shipping.toFixed(2) + ' &euro;'
                    : '<span class="text-success">Gratis</span>';

                html += '<tr>';
                html += '<td>' + c.position + '</td>';
                html += '<td><strong>' + escapeHtml(c.source) + '</strong></td>';
                html += '<td><small>' + escapeHtml(truncate(c.title || '', 80)) + '</small></td>';
                html += '<td class="text-right">' + parseFloat(c.price).toFixed(2) + ' &euro;</td>';
                html += '<td class="text-right">' + shippingHtml + '</td>';
                html += '<td class="text-right"><strong>' + totalPrice.toFixed(2) + ' &euro;</strong></td>';
                html += '<td>';
                if (c.link) {
                    html += '<a href="' + escapeHtml(c.link) + '" target="_blank" rel="noopener" class="btn btn-xs btn-default"><i class="icon-external-link"></i></a>';
                }
                html += '</td></tr>';
            });

            html += '</tbody></table></div>';
        }

        html += '<p class="text-muted"><small><i class="icon-search"></i> Busqueda: "' + escapeHtml(data.search_query || '') + '"</small></p>';

        $('#pm-scan-result').html(html).show();
    }

    function showProductNotification(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? 'icon-check' : 'icon-warning-sign';
        $('#pm-scan-result').html('<div class="alert ' + alertClass + '"><i class="' + icon + '"></i> ' + message + '</div>').show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function truncate(str, max) {
        return str.length > max ? str.substring(0, max) + '...' : str;
    }
});
