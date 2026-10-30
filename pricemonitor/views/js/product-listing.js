/**
 * Price Monitor - Product Listing Column
 * Adds "Competencia" column to Catalogo > Productos
 * @version 1.0.0
 */

$(document).ready(function () {
    // Detect product listing table (PS 1.7 Symfony product catalog)
    var $table = $('table.product');
    if (!$table.length) return;

    // Get all product IDs from rows
    var productIds = [];
    $table.find('tbody tr[data-product-id]').each(function () {
        productIds.push(parseInt($(this).data('product-id')));
    });

    if (productIds.length === 0) return;

    // Insert column header before Actions column
    var $headerRow = $table.find('thead tr.column-headers');
    var $actionsHeader = $headerRow.find('th:last');
    $actionsHeader.before('<th scope="col" class="text-center pm-col-competition" style="width:130px;">Competencia</th>');

    // Insert empty filter cell
    var $filterRow = $table.find('thead tr.column-filters');
    var $actionsFilter = $filterRow.find('th:last');
    $actionsFilter.before('<th class="pm-col-competition"></th>');

    // Insert cells in each product row
    $table.find('tbody tr[data-product-id]').each(function () {
        var pid = $(this).data('product-id');
        var $actionsCell = $(this).find('td:last');
        $actionsCell.before(
            '<td class="text-center pm-listing-cell" data-pm-pid="' + pid + '">' +
            '<i class="material-icons" style="color:#ccc;font-size:14px;animation:pm-pulse 1s infinite;">hourglass_empty</i>' +
            '</td>'
        );
    });

    // Fix colspan on empty result row
    $table.find('tbody tr td[colspan]').each(function () {
        var current = parseInt($(this).attr('colspan')) || 11;
        $(this).attr('colspan', current + 1);
    });

    // Fetch scan data via AJAX
    if (typeof pmListingAjaxUrl === 'undefined') return;

    $.ajax({
        url: pmListingAjaxUrl,
        type: 'POST',
        data: {
            ajax: 1,
            action: 'getBulkScanData',
            product_ids: JSON.stringify(productIds)
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderCompetitionCells(response.data);
            } else {
                clearLoadingCells();
            }
        },
        error: function () {
            clearLoadingCells();
        }
    });

    function renderCompetitionCells(scanData) {
        $table.find('.pm-listing-cell').each(function () {
            var pid = $(this).data('pm-pid');
            var data = scanData[pid];

            if (!data) {
                $(this).html('<span style="color:#bbb;font-size:11px;">--</span>');
                return;
            }

            var statusColors = {
                cheaper: '#4CAF50',
                equal: '#FF9800',
                expensive: '#f44336',
                no_data: '#9E9E9E'
            };

            var color = statusColors[data.status] || '#9E9E9E';
            var html = '<span class="pm-listing-dot" style="background:' + color + ';"></span> ';

            if (data.min_price) {
                html += '<span style="font-size:12px;font-weight:600;">' + parseFloat(data.min_price).toFixed(2) + '&euro;</span>';

                if (data.diff_percent !== null && data.diff_percent !== undefined) {
                    var diffStyle = parseFloat(data.diff_percent) > 0 ? 'color:#c62828' : 'color:#2e7d32';
                    var prefix = parseFloat(data.diff_percent) > 0 ? '+' : '';
                    html += ' <span style="font-size:10px;' + diffStyle + ';">' + prefix + parseFloat(data.diff_percent).toFixed(1) + '%</span>';
                }

                if (data.cheapest_store) {
                    html += '<br><span style="font-size:10px;color:#666;">' + escapeHtml(truncate(data.cheapest_store, 18)) + '</span>';
                }

                if (data.cheapest_url) {
                    html += ' <a href="' + escapeHtml(data.cheapest_url) + '" target="_blank" rel="noopener" style="font-size:10px;"><i class="material-icons" style="font-size:12px;vertical-align:middle;">open_in_new</i></a>';
                }
            } else {
                html += '<span style="font-size:11px;color:#999;">Sin datos</span>';
            }

            $(this).html(html);
        });
    }

    function clearLoadingCells() {
        $table.find('.pm-listing-cell').html('<span style="color:#bbb;">--</span>');
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
