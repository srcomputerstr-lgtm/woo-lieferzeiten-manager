/**
 * Free-storage settings only. No dependency on jQuery or the generic WLM save.
 */
(function () {
    'use strict';

    function init() {
        var root = document.getElementById('wlm-free-storage-settings');
        if (!root) {
            return;
        }
        var form = root.closest('form');
        var config = window.wlmFreeStorageAdmin || {};
        var strings = config.i18n || {};
        var periods = root.querySelector('#wlm-free-storage-periods');
        var notice = root.querySelector('#wlm-free-storage-notice');
        var warning = root.querySelector('#wlm-free-storage-gateway-warning');
        var emptyPeriods = root.querySelector('#wlm-free-storage-empty-periods');
        var counter = 0;
        var busy = false;
        var disabledControls = [];

        if (!form || !periods || !notice) {
            return;
        }

        function showNotice(message, type) {
            notice.className = 'notice notice-' + type + ' inline';
            notice.setAttribute('role', type === 'error' ? 'alert' : 'status');
            notice.querySelector('p').textContent = message;
            notice.hidden = false;
            if (type === 'error') {
                notice.focus();
            }
        }

        function updateGatewayWarning() {
            warning.hidden = !!root.querySelector('.wlm-free-storage-gateway:checked');
        }

        function rows() {
            return Array.prototype.slice.call(periods.querySelectorAll('.wlm-free-storage-period'));
        }

        function reindexRows() {
            rows().forEach(function (row, index) {
                row.querySelectorAll('[data-field]').forEach(function (input) {
                    input.name = 'wlm_free_storage[periods][' + index + '][' + input.dataset.field + ']';
                });
            });
            emptyPeriods.hidden = rows().length !== 0;
        }

        function validateRow(row) {
            var start = row.querySelector('[data-field="start"]');
            var end = row.querySelector('[data-field="end"]');
            if (start.value) {
                end.min = start.value;
            } else {
                end.removeAttribute('min');
            }
            end.setCustomValidity(start.value && end.value && end.value < start.value
                ? (strings.dateOrder || 'Das Ende muss am oder nach dem Beginn liegen.') : '');
        }

        function makeInput(type, field, label) {
            var input = document.createElement('input');
            input.type = type;
            input.dataset.field = field;
            if (label) {
                input.setAttribute('aria-label', label);
            }
            if (type === 'date') {
                input.required = true;
            }
            return input;
        }

        function addRow() {
            var id;
            do {
                counter += 1;
                id = 'period-' + Date.now() + '-' + counter;
            } while (rows().some(function (row) { return row.dataset.periodId === id; }));

            var row = document.createElement('tr');
            row.className = 'wlm-free-storage-period';
            row.dataset.periodId = id;
            var startCell = document.createElement('td');
            var endCell = document.createElement('td');
            var labelCell = document.createElement('td');
            var skuCell = document.createElement('td');
            var sku = document.createElement('code');
            sku.className = 'wlm-free-storage-sku';
            sku.textContent = 'Wird beim Speichern vergeben';
            skuCell.appendChild(sku);
            var actionsCell = document.createElement('td');
            var idInput = makeInput('hidden', 'id');
            idInput.value = id;
            var start = makeInput('date', 'start', strings.start || 'Beginn (Eintreffen beim Kunden)');
            var end = makeInput('date', 'end', strings.end || 'Ende (Eintreffen beim Kunden)');
            var label = makeInput('text', 'label', strings.label || 'Bezeichnung (optional)');
            label.placeholder = strings.automatic || 'Automatisch aus dem Zeitraum';
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button wlm-free-storage-remove-period';
            remove.textContent = strings.remove || 'Entfernen';
            remove.setAttribute('aria-label', strings.removeRow || 'Lieferzeitfenster entfernen');
            startCell.appendChild(idInput);
            startCell.appendChild(start);
            endCell.appendChild(end);
            labelCell.appendChild(label);
            actionsCell.appendChild(remove);
            row.appendChild(startCell);
            row.appendChild(endCell);
            row.appendChild(labelCell);
            row.appendChild(skuCell);
            row.appendChild(actionsCell);
            periods.appendChild(row);
            reindexRows();
            start.focus();
        }

        function collectSettings() {
            return {
                enabled: root.querySelector('#wlm-free-storage-enabled').checked,
                allowed_gateways: Array.prototype.map.call(
                    root.querySelectorAll('.wlm-free-storage-gateway:checked'),
                    function (input) { return input.value; }
                ),
                periods: rows().map(function (row) {
                    return {
                        id: row.querySelector('[data-field="id"]').value,
                        label: row.querySelector('[data-field="label"]').value,
                        start: row.querySelector('[data-field="start"]').value,
                        end: row.querySelector('[data-field="end"]').value
                    };
                })
            };
        }

        function setBusy(value) {
            busy = value;
            root.setAttribute('aria-busy', value ? 'true' : 'false');
            if (value) {
                var controls = Array.prototype.slice.call(root.querySelectorAll('input, button, select, textarea'));
                // Include all WooCommerce/plugin save controls, even outside our wrapper.
                Array.prototype.forEach.call(form.querySelectorAll('button, input'), function (control) {
                    if ((control.type === 'submit' || control.id === 'wlm-save-settings') && controls.indexOf(control) === -1) {
                        controls.push(control);
                    }
                });
                disabledControls = controls.map(function (control) {
                    var original = { element: control, disabled: control.disabled };
                    control.disabled = true;
                    return original;
                });
            } else {
                disabledControls.forEach(function (original) {
                    original.element.disabled = original.disabled;
                });
                disabledControls = [];
            }
        }

        function applySavedSettings(settings) {
            if (!settings || !Array.isArray(settings.periods)) {
                return;
            }
            root.querySelector('#wlm-free-storage-enabled').checked = !!settings.enabled;
            Array.prototype.forEach.call(root.querySelectorAll('.wlm-free-storage-gateway'), function (input) {
                input.checked = Array.isArray(settings.allowed_gateways) && settings.allowed_gateways.indexOf(input.value) !== -1;
            });
            // Inputs are locked during the request. Preserve the DOM and native date controls.
            rows().forEach(function (row, index) {
                var period = settings.periods[index];
                if (!period) {
                    return;
                }
                ['id', 'label', 'start', 'end'].forEach(function (field) {
                    row.querySelector('[data-field="' + field + '"]').value = period[field] || '';
                });
                row.querySelector('.wlm-free-storage-sku').textContent = period.sku || '';
                var generatedLabel = period.start.slice(8, 10) + '.' + period.start.slice(5, 7) + '.' + period.start.slice(0, 4)
                    + ' – ' + period.end.slice(8, 10) + '.' + period.end.slice(5, 7) + '.' + period.end.slice(0, 4);
                if (period.label === generatedLabel) {
                    row.querySelector('[data-field="label"]').value = '';
                }
                row.dataset.periodId = period.id;
                validateRow(row);
            });
            updateGatewayWarning();
        }

        root.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null;
            var add = target && target.closest('#wlm-free-storage-add-period');
            var remove = target && target.closest('.wlm-free-storage-remove-period');
            if (!add && !remove) {
                return;
            }
            event.preventDefault();
            if (busy) {
                return;
            }
            if (add) {
                addRow();
            } else {
                var row = remove.closest('.wlm-free-storage-period');
                var nextRow = row.nextElementSibling || row.previousElementSibling;
                row.remove();
                reindexRows();
                if (nextRow) {
                    nextRow.querySelector('[data-field="start"]').focus();
                } else {
                    root.querySelector('#wlm-free-storage-add-period').focus();
                }
            }
        });

        root.addEventListener('input', function (event) {
            var row = event.target.closest('.wlm-free-storage-period');
            if (row) {
                validateRow(row);
            }
        });
        root.addEventListener('change', updateGatewayWarning);

        // Existing admin.js delegates CLICK on .submit input[name="save"] to its
        // generic AJAX handler. Stop that click, but do not prevent its default:
        // the browser must still validate and submit the actual settings form.
        document.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null;
            var control = target && target.closest('button, input');
            if (!control || control.form !== form || (control.type !== 'submit' && control.id !== 'wlm-save-settings')) {
                return;
            }
            event.stopImmediatePropagation();
            if (busy) {
                event.preventDefault();
            } else {
                rows().forEach(validateRow);
            }
        }, true);

        document.addEventListener('submit', function (event) {
            if (event.target !== form || !event.target.querySelector('#wlm-free-storage-settings')) {
                return;
            }
            // Browsers without fetch retain the authorized non-JS save path.
            if (!window.fetch || !window.URLSearchParams || !config.ajax_url || !config.nonce) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (busy) {
                return;
            }
            rows().forEach(validateRow);
            if (!form.reportValidity()) {
                return;
            }

            var settings = collectSettings();
            var body = new URLSearchParams();
            body.set('action', 'wlm_save_free_storage');
            body.set('nonce', config.nonce);
            body.set('settings', JSON.stringify(settings));
            setBusy(true);
            showNotice(strings.saving || 'Einstellungen werden gespeichert …', 'info');

            fetch(config.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!payload || typeof payload.success !== 'boolean') {
                        throw new Error(strings.networkError || 'Netzwerk- oder Sitzungsfehler. Bitte laden Sie die Seite neu.');
                    }
                    if (!response.ok || !payload.success) {
                        var message = payload.data && payload.data.message;
                        throw new Error(typeof message === 'string' ? message : (strings.error || 'Die Einstellungen konnten nicht gespeichert werden.'));
                    }
                    return payload;
                }, function () {
                    throw new Error(strings.networkError || 'Netzwerk- oder Sitzungsfehler. Bitte laden Sie die Seite neu.');
                });
            }).then(function (payload) {
                applySavedSettings(payload.data && payload.data.settings);
                showNotice(strings.saved || 'Gratis-Einlagerung-Einstellungen gespeichert.', 'success');
                setBusy(false);
            }).catch(function (error) {
                setBusy(false);
                showNotice(error && error.message ? error.message : (strings.networkError || 'Netzwerkfehler. Bitte versuchen Sie es erneut.'), 'error');
            });
        }, true);

        rows().forEach(validateRow);
        reindexRows();
        updateGatewayWarning();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
