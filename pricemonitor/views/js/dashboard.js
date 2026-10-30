/**
 * Price Monitor - Dashboard JS
 * @version 1.0.0
 */

$(document).ready(function () {
    // Inicializar paginacion
    renderPagination();
    updateSelectionInfo();

    // ====== FILTROS ======
    var filterTimer = null;
    $('#filter-search').on('keyup', function () {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(function () { loadProducts(1); }, 500);
    });

    $('#filter-status, #filter-manufacturer, #filter-category, #filter-active').on('change', function () {
        loadProducts(1);
    });

    $('#btn-clear-filters').on('click', function () {
        $('#filter-search').val('');
        $('#filter-status, #filter-manufacturer, #filter-category').val('');
        $('#filter-active').val('');
        loadProducts(1);
    });

    // ====== SELECT ALL ======
    $('#select-all').on('change', function () {
        var checked = $(this).prop('checked');
        $('.product-checkbox').prop('checked', checked);
        updateSelectionInfo();
    });

    $(document).on('change', '.product-checkbox', function () {
        updateSelectionInfo();
    });

    // ====== ESCANEO INDIVIDUAL ======
    $(document).on('click', '.btn-scan-single', function () {
        var btn = $(this);
        var idProduct = btn.data('id');
        var row = btn.closest('tr');

        btn.prop('disabled', true).addClass('pm-scanning');
        btn.find('i').removeClass('icon-search').addClass('icon-spinner icon-spin');

        $.ajax({
            url: pmAjaxUrl,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'scanProduct',
                id_product: idProduct
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    updateProductRow(row, response.data);
                    showNotification('success', 'Producto escaneado correctamente');
                } else {
                    showNotification('error', response.error || 'Error desconocido');
                }
            },
            error: function () {
                showNotification('error', 'Error de conexion');
            },
            complete: function () {
                btn.prop('disabled', false).removeClass('pm-scanning');
                btn.find('i').removeClass('icon-spinner icon-spin').addClass('icon-search');
            }
        });
    });

    // ====== ESCANEO MASIVO ======
    $('#btn-scan-selected').on('click', function () {
        var selected = getSelectedProducts();
        if (selected.length === 0) return;

        if (!confirm('Escanear ' + selected.length + ' productos? Esto consumira ' + selected.length + ' creditos API.')) {
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);
        $('#scan-progress').show();

        var completed = 0;
        var total = selected.length;
        var errors = 0;

        // Escanear uno a uno para mostrar progreso
        function scanNext() {
            if (completed >= total) {
                btn.prop('disabled', false);
                updateScanProgress(100, 'Completado: ' + completed + ' escaneados, ' + errors + ' errores');
                setTimeout(function () { loadProducts(pmCurrentPage); }, 1000);
                return;
            }

            var idProduct = selected[completed];
            var percent = Math.round((completed / total) * 100);
            updateScanProgress(percent, 'Escaneando producto #' + idProduct + ' (' + (completed + 1) + '/' + total + ')');

            $.ajax({
                url: pmAjaxUrl,
                type: 'POST',
                data: { ajax: 1, action: 'scanProduct', id_product: idProduct },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        var row = $('tr[data-id="' + idProduct + '"]');
                        if (row.length) updateProductRow(row, response.data);
                    } else {
                        errors++;
                    }
                },
                error: function () { errors++; },
                complete: function () {
                    completed++;
                    scanNext();
                }
            });
        }

        scanNext();
    });

    // ====== VER COMPETIDORES ======
    $(document).on('click', '.btn-show-competitors', function () {
        var idProduct = $(this).data('id');
        var productName = $(this).closest('tr').find('td:eq(2) strong').text();

        $('#modal-product-name').text(productName);
        $('#competitors-content').html('<div class="text-center"><i class="icon-spinner icon-spin icon-2x"></i></div>');
        $('#competitors-modal').modal('show');

        $.ajax({
            url: pmAjaxUrl,
            type: 'POST',
            data: { ajax: 1, action: 'getCompetitors', id_product: idProduct },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderCompetitorsModal(response.data);
                } else {
                    $('#competitors-content').html('<div class="alert alert-danger">' + (response.error || 'Error') + '</div>');
                }
            },
            error: function () {
                $('#competitors-content').html('<div class="alert alert-danger">Error de conexion</div>');
            }
        });
    });

    // ====== AJUSTAR PRECIO ======
    var currentPriceProductId = null;

    $(document).on('click', '.btn-adjust-price', function () {
        currentPriceProductId = $(this).data('id');
        var currentPrice = $(this).data('price');
        var minPrice = $(this).data('min');

        $('#modal-current-price').text(parseFloat(currentPrice).toFixed(2));
        $('#modal-min-price').text(minPrice ? parseFloat(minPrice).toFixed(2) : '-');
        $('#modal-new-price').val(minPrice ? parseFloat(minPrice).toFixed(2) : currentPrice);
        $('#price-modal').modal('show');
    });

    $('#btn-apply-price').on('click', function () {
        if (!currentPriceProductId) return;

        var newPrice = parseFloat($('#modal-new-price').val());
        var method = $('#modal-price-method').val();

        if (isNaN(newPrice) || newPrice <= 0) {
            showNotification('error', 'Precio no valido');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: pmAjaxUrl,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'updatePrice',
                id_product: currentPriceProductId,
                new_price: newPrice,
                method: method
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showNotification('success', 'Precio actualizado correctamente');
                    $('#price-modal').modal('hide');
                    loadProducts(pmCurrentPage);
                } else {
                    showNotification('error', response.error || 'Error');
                }
            },
            error: function () {
                showNotification('error', 'Error de conexion');
            },
            complete: function () {
                btn.prop('disabled', false);
            }
        });
    });

    // ====== EXPORTAR CSV ======
    $('#btn-export-csv').on('click', function () {
        var params = getFilterParams();
        params.ajax = 1;
        params.action = 'exportCsv';
        window.location.href = pmAjaxUrl + '&' + $.param(params);
    });

    // ====== FUNCIONES AUXILIARES ======

    function loadProducts(page) {
        pmCurrentPage = page;
        $('#table-loading').show();
        $('#table-container').hide();

        var params = getFilterParams();
        params.ajax = 1;
        params.action = 'getProducts';
        params.page = page;

        $.ajax({
            url: pmAjaxUrl,
            type: 'POST',
            data: params,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderProductsTable(response.data.products);
                    pmTotalPages = response.data.pagination.total_pages;
                    pmTotalProducts = response.data.pagination.total_products;
                    pmPerPage = response.data.pagination.per_page;
                    pmCurrentPage = response.data.pagination.current_page;

                    $('#total-products-badge').text(pmTotalProducts);
                    updatePaginationInfo();
                    renderPagination();
                }
            },
            error: function () {
                showNotification('error', 'Error cargando productos');
            },
            complete: function () {
                $('#table-loading').hide();
                $('#table-container').show();
            }
        });
    }

    function getFilterParams() {
        return {
            filter_search: $('#filter-search').val(),
            filter_status: $('#filter-status').val(),
            filter_manufacturer: $('#filter-manufacturer').val(),
            filter_category: $('#filter-category').val(),
            filter_active: $('#filter-active').val()
        };
    }

    function getSelectedProducts() {
        var ids = [];
        $('.product-checkbox:checked').each(function () {
            ids.push($(this).val());
        });
        return ids;
    }

    function updateSelectionInfo() {
        var count = getSelectedProducts().length;
        $('#selection-info').text(count > 0 ? count + ' productos seleccionados' : '');
        $('#btn-scan-selected').prop('disabled', count === 0);
    }

    function updateScanProgress(percent, text) {
        $('#scan-progress .progress-bar').css('width', percent + '%');
        $('#scan-progress .progress-text').text(percent + '%');
        $('#scan-status').text(text);
    }

    function updateProductRow(row, data) {
        var statusLabels = {
            cheaper: 'Barato', equal: 'Similar', expensive: 'Caro', no_data: 'Sin datos'
        };

        row.find('td:eq(4)').html('<strong>' + parseFloat(data.our_price).toFixed(2) + ' &euro;</strong>');
        row.find('td:eq(5)').html(data.min_price ? parseFloat(data.min_price).toFixed(2) + ' &euro;' : '<span class="text-muted">-</span>');
        row.find('td:eq(6)').html(data.avg_price ? parseFloat(data.avg_price).toFixed(2) + ' &euro;' : '<span class="text-muted">-</span>');
        row.find('td:eq(7)').text(data.competitor_count || '0');

        // Diff
        if (data.diff_percent !== null && data.diff_percent !== undefined) {
            var diffClass = data.diff_percent > 0 ? 'pm-diff-positive' : (data.diff_percent < 0 ? 'pm-diff-negative' : '');
            var prefix = data.diff_percent > 0 ? '+' : '';
            row.find('td:eq(8)').html('<span class="pm-diff ' + diffClass + '">' + prefix + parseFloat(data.diff_percent).toFixed(1) + '%</span>');
        }

        // Status
        var statusLabel = statusLabels[data.status] || 'Sin datos';
        row.find('td:eq(9)').html('<span class="pm-status pm-status-' + data.status + '">' + statusLabel + '</span>');

        // Date
        var now = new Date();
        var dateStr = ('0' + now.getDate()).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + ('' + now.getFullYear()).slice(-2) + ' ' + ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
        row.find('td:eq(10)').html('<small>' + dateStr + '</small>');

        // Enable competitors button
        row.find('.btn-show-competitors').prop('disabled', false);

        // Update price adjust button data
        row.find('.btn-adjust-price')
            .data('price', data.our_price)
            .data('min', data.min_price);

        // Flash row
        row.css('background-color', '#e8f5e9');
        setTimeout(function () { row.css('background-color', ''); }, 2000);
    }

    function renderProductsTable(products) {
        var tbody = $('#products-tbody');
        tbody.empty();

        if (!products || products.length === 0) {
            tbody.html('<tr><td colspan="12" class="text-center"><p class="text-muted" style="padding:20px;"><i class="icon-info-circle"></i> No se encontraron productos</p></td></tr>');
            return;
        }

        var statusLabels = {
            cheaper: 'Barato', equal: 'Similar', expensive: 'Caro', no_data: 'Sin datos'
        };

        $.each(products, function (i, p) {
            var price = p.our_price || p.product_price;
            var statusHtml = '';
            if (p.status) {
                statusHtml = '<span class="pm-status pm-status-' + p.status + '">' + (statusLabels[p.status] || 'Sin datos') + '</span>';
            } else {
                statusHtml = '<span class="pm-status pm-status-none">Pendiente</span>';
            }

            var diffHtml = '-';
            if (p.diff_percent !== null && p.diff_percent !== '' && p.diff_percent !== undefined) {
                var diffClass = p.diff_percent > 0 ? 'pm-diff-positive' : (p.diff_percent < 0 ? 'pm-diff-negative' : '');
                var prefix = p.diff_percent > 0 ? '+' : '';
                diffHtml = '<span class="pm-diff ' + diffClass + '">' + prefix + parseFloat(p.diff_percent).toFixed(1) + '%</span>';
            }

            var dateHtml = p.date_scan ? '<small>' + p.date_scan.substring(8, 10) + '/' + p.date_scan.substring(5, 7) + '/' + p.date_scan.substring(2, 4) + ' ' + p.date_scan.substring(11, 16) + '</small>' : '<small class="text-muted">Nunca</small>';

            var excludedClass = p.is_excluded == 1 ? ' pm-row-excluded' : '';

            var row = '<tr data-id="' + p.id_product + '" class="' + excludedClass + '">' +
                '<td><input type="checkbox" class="product-checkbox" value="' + p.id_product + '"></td>' +
                '<td>' + p.id_product + '</td>' +
                '<td><strong>' + escapeHtml(truncate(p.product_name || '', 60)) + '</strong>' +
                (p.reference ? '<br><small class="text-muted">' + escapeHtml(p.reference) + '</small>' : '') + '</td>' +
                '<td><span class="text-muted">' + escapeHtml(p.manufacturer_name || '-') + '</span></td>' +
                '<td class="text-right"><strong>' + parseFloat(price).toFixed(2) + ' &euro;</strong></td>' +
                '<td class="text-right">' + (p.min_price ? parseFloat(p.min_price).toFixed(2) + ' &euro;' : '<span class="text-muted">-</span>') + '</td>' +
                '<td class="text-right">' + (p.avg_price ? parseFloat(p.avg_price).toFixed(2) + ' &euro;' : '<span class="text-muted">-</span>') + '</td>' +
                '<td class="text-center">' + (p.competitor_count || '-') + '</td>' +
                '<td class="text-center">' + diffHtml + '</td>' +
                '<td class="text-center">' + statusHtml + '</td>' +
                '<td class="text-center">' + dateHtml + '</td>' +
                '<td class="text-center pm-actions">' +
                '<button type="button" class="btn btn-xs btn-primary btn-scan-single" data-id="' + p.id_product + '" title="Escanear"><i class="icon-search"></i></button> ' +
                '<button type="button" class="btn btn-xs btn-info btn-show-competitors" data-id="' + p.id_product + '" title="Ver competidores"' + (!p.id_scan ? ' disabled' : '') + '><i class="icon-users"></i></button> ' +
                '<button type="button" class="btn btn-xs btn-warning btn-adjust-price" data-id="' + p.id_product + '" data-price="' + parseFloat(price).toFixed(2) + '" data-min="' + (p.min_price ? parseFloat(p.min_price).toFixed(2) : '') + '" title="Ajustar precio"><i class="icon-euro"></i></button>' +
                '</td></tr>';

            tbody.append(row);
        });

        $('#select-all').prop('checked', false);
        updateSelectionInfo();
    }

    function renderCompetitorsModal(data) {
        var html = '';

        if (data.scan) {
            html += '<div class="row" style="margin-bottom:15px;">';
            html += '<div class="col-sm-3"><strong>Nuestro precio:</strong> ' + parseFloat(data.scan.our_price).toFixed(2) + ' &euro; <small class="text-success">(envio gratis)</small></div>';
            html += '<div class="col-sm-3"><strong>Min. total:</strong> ' + (data.scan.min_price ? parseFloat(data.scan.min_price).toFixed(2) + ' &euro;' : '-') + '</div>';
            html += '<div class="col-sm-3"><strong>Medio total:</strong> ' + (data.scan.avg_price ? parseFloat(data.scan.avg_price).toFixed(2) + ' &euro;' : '-') + '</div>';
            html += '<div class="col-sm-3"><strong>Busqueda:</strong> <em>' + escapeHtml(data.scan.search_query || '') + '</em></div>';
            html += '</div>';
        }

        if (!data.competitors || data.competitors.length === 0) {
            html += '<div class="alert alert-info">No hay competidores registrados para este producto.</div>';
        } else {
            html += '<table class="table table-striped table-condensed">';
            html += '<thead><tr><th>#</th><th>Tienda</th><th>Titulo</th><th class="text-right">Precio</th><th class="text-right">Envio</th><th class="text-right">Total</th><th></th></tr></thead><tbody>';

            $.each(data.competitors, function (i, c) {
                var shipping = parseFloat(c.competitor_shipping || 0);
                var totalPrice = parseFloat(c.competitor_total_price || c.competitor_price);
                var shippingHtml = shipping > 0
                    ? '+' + shipping.toFixed(2) + ' &euro;'
                    : '<span class="text-success">Gratis</span>';

                html += '<tr>';
                html += '<td>' + c.position + '</td>';
                html += '<td><strong>' + escapeHtml(c.competitor_name) + '</strong></td>';
                html += '<td><small>' + escapeHtml(truncate(c.competitor_title || '', 80)) + '</small></td>';
                html += '<td class="text-right">' + parseFloat(c.competitor_price).toFixed(2) + ' &euro;</td>';
                html += '<td class="text-right">' + shippingHtml + '</td>';
                html += '<td class="text-right"><strong>' + totalPrice.toFixed(2) + ' &euro;</strong></td>';
                html += '<td>';
                if (c.competitor_url) {
                    html += '<a href="' + escapeHtml(c.competitor_url) + '" target="_blank" rel="noopener" class="btn btn-xs btn-default"><i class="icon-external-link"></i></a>';
                }
                html += '</td></tr>';
            });

            html += '</tbody></table>';
        }

        $('#competitors-content').html(html);
    }

    function renderPagination() {
        var ul = $('#pagination');
        ul.empty();

        if (pmTotalPages <= 1) return;

        // Previous
        ul.append('<li class="' + (pmCurrentPage <= 1 ? 'disabled' : '') + '"><a href="#" data-page="' + (pmCurrentPage - 1) + '">&laquo;</a></li>');

        // Pages
        var start = Math.max(1, pmCurrentPage - 3);
        var end = Math.min(pmTotalPages, pmCurrentPage + 3);

        if (start > 1) {
            ul.append('<li><a href="#" data-page="1">1</a></li>');
            if (start > 2) ul.append('<li class="disabled"><span>...</span></li>');
        }

        for (var i = start; i <= end; i++) {
            ul.append('<li class="' + (i === pmCurrentPage ? 'active' : '') + '"><a href="#" data-page="' + i + '">' + i + '</a></li>');
        }

        if (end < pmTotalPages) {
            if (end < pmTotalPages - 1) ul.append('<li class="disabled"><span>...</span></li>');
            ul.append('<li><a href="#" data-page="' + pmTotalPages + '">' + pmTotalPages + '</a></li>');
        }

        // Next
        ul.append('<li class="' + (pmCurrentPage >= pmTotalPages ? 'disabled' : '') + '"><a href="#" data-page="' + (pmCurrentPage + 1) + '">&raquo;</a></li>');

        // Click handler
        ul.find('a').on('click', function (e) {
            e.preventDefault();
            var page = parseInt($(this).data('page'));
            if (page >= 1 && page <= pmTotalPages && page !== pmCurrentPage) {
                loadProducts(page);
            }
        });
    }

    function updatePaginationInfo() {
        var from = pmTotalProducts > 0 ? ((pmCurrentPage - 1) * pmPerPage) + 1 : 0;
        var to = Math.min(pmCurrentPage * pmPerPage, pmTotalProducts);
        $('#showing-from').text(from);
        $('#showing-to').text(to);
        $('#showing-total').text(pmTotalProducts);
    }

    function showNotification(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? 'icon-check' : 'icon-warning-sign';
        var html = '<div class="alert ' + alertClass + '" style="position:fixed;top:60px;right:20px;z-index:9999;min-width:300px;box-shadow:0 2px 10px rgba(0,0,0,0.2);">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            '<i class="' + icon + '"></i> ' + message + '</div>';
        var el = $(html).appendTo('body');
        setTimeout(function () { el.fadeOut(function () { el.remove(); }); }, 4000);
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
