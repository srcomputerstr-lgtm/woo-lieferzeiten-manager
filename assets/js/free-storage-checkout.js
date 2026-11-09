/** Optional free-storage checkout inner blocks. Uses the official Store API update flow. */
(function () {
    'use strict';
    var wp = window.wp;
    var wc = window.wc;
    if (!wp || !wp.element || !wp.data || !wc || !wc.blocksCheckout) {
        console.error('[WLM Einlagerung] WooCommerce Checkout-Schnittstelle ist nicht verfügbar.');
        return;
    }
    var api = wc.blocksCheckout;
    var h = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var namespace = 'wlm-free-storage';
    var registeredPayments = {};

    function getStorage(cart) {
        return cart && cart.extensions && cart.extensions[namespace] || {};
    }
    function useCart() {
        return useSelect(function (select) {
            var store = select('wc/store/cart');
            return store && store.getCartData ? store.getCartData() : {};
        }, []);
    }
    function setValidation(message) {
        var validation = wp.data.dispatch('wc/store/validation');
        if (!validation) { return; }
        if (message && validation.setValidationErrors) {
            var errors = {};
            errors[namespace] = { message: message, hidden: false };
            validation.setValidationErrors(errors);
        } else if (!message && validation.clearValidationError) {
            validation.clearValidationError(namespace);
        }
    }
    function registerPaymentFilters() {
        var registry = wc.wcBlocksRegistry;
        if (!registry || !registry.registerPaymentMethodExtensionCallbacks) { return; }
        var methods = Object.assign({},
            registry.getPaymentMethods ? registry.getPaymentMethods() : {},
            registry.getExpressPaymentMethods ? registry.getExpressPaymentMethods() : {});
        Object.keys(methods).forEach(function (name) {
            if (registeredPayments[name]) { return; }
            var method = methods[name];
            var id = method && method.paymentMethodId || name;
            var callbacks = {};
            callbacks[name] = function (args) {
                var cart = args && args.cart;
                var storage = getStorage(cart);
                if (!storage.enabled || !storage.selected || storage.selected === 'asap') { return true; }
                if (!storage.selected_valid) { return false; }
                var allowed = storage.allowed_gateways || [];
                return allowed.indexOf(id) !== -1;
            };
            registry.registerPaymentMethodExtensionCallbacks(namespace + '-' + name, callbacks);
            registeredPayments[name] = true;
        });
    }
    function StorageSelector() {
        var cart = useCart();
        var storage = getStorage(cart);
        var busyState = useState(false);
        var busy = busyState[0];
        var setBusy = busyState[1];
        var errorState = useState('');
        var error = errorState[0];
        var setError = errorState[1];
        var selected = storage.selected || 'asap';
        var invalid = storage.enabled && selected !== 'asap' && !storage.selected_valid;
        var invalidMessage = storage.invalid_reason || 'Der ausgewählte Lieferzeitraum ist nicht mehr möglich. Bitte wählen Sie einen anderen Termin oder „So schnell wie möglich“.';

        useEffect(function () {
            registerPaymentFilters();
        }, [cart]);
        useEffect(function () {
            if (busy) {
                setValidation('Die Lieferterminauswahl wird aktualisiert. Bitte warten.');
            } else {
                setValidation(invalid ? invalidMessage : '');
            }
            return function () { setValidation(''); };
        }, [busy, invalid, invalidMessage]);

        if (!storage.enabled) { return null; }
        function choose(value) {
            if (busy || value === selected) { return; }
            if (typeof api.extensionCartUpdate !== 'function') {
                setError('Die Lieferterminauswahl kann nicht aktualisiert werden. Bitte laden Sie die Kasse neu.');
                return;
            }
            setError('');
            setBusy(true);
            setValidation('Die Lieferterminauswahl wird aktualisiert. Bitte warten.');
            api.extensionCartUpdate({ namespace: namespace, data: { selection: value }, overwriteDirtyCustomerData: false })
                .then(function () { setBusy(false); })
                .catch(function (reason) {
                    setBusy(false);
                    setError(reason && reason.message || 'Die Auswahl konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.');
                });
        }
        var defaultLabel = 'So schnell wie möglich';
        if (storage.default_window && storage.default_window.window) {
            defaultLabel += ' – ' + storage.default_window.window;
        }
        var options = [{ id: 'asap', label: defaultLabel, available: true }].concat(storage.options || []);
        return h('section', { className: 'wlm-storage-selector', 'aria-busy': busy },
            h('fieldset', { disabled: busy },
                h('legend', null, 'Liefertermin / Gratis Einlagerung'),
                h('p', { className: 'wlm-storage-description' }, 'Wählen Sie, wann die gesamte Bestellung bei Ihnen eintreffen soll. Die Einlagerung bis zum gewählten Lieferzeitraum ist kostenlos.'),
                options.map(function (option) {
                    var blocked = option.available === false;
                    return h('label', { key: option.id, className: 'wlm-storage-option' + (blocked ? ' is-unavailable' : '') },
                        h('input', {
                            type: 'radio', name: 'wlm-free-storage-selection', value: option.id,
                            checked: selected === option.id, disabled: busy || blocked,
                            onChange: function () { choose(option.id); },
                            'aria-describedby': option.reason ? 'wlm-storage-reason-' + option.id : undefined
                        }),
                        h('span', null, option.label,
                            option.reason ? h('small', { id: 'wlm-storage-reason-' + option.id }, option.reason) : null));
                })),
            busy ? h('p', { role: 'status' }, 'Liefertermin wird aktualisiert …') : null,
            selected !== 'asap' && storage.selected_valid ? h('p', { className: 'wlm-storage-payment-notice', role: 'status' }, storage.payment_notice || 'Durch die ausgewählte Einlagerungsoption können nur bestimmte Zahlungsarten angeboten werden.') : null,
            invalid ? h('p', { className: 'wlm-storage-error', role: 'alert' }, invalidMessage) : null,
            error ? h('p', { className: 'wlm-storage-error', role: 'alert' }, error) : null);
    }
    function StorageSummary() {
        var cart = useCart();
        var storage = getStorage(cart);
        if (!storage.enabled || storage.selected === 'asap' || !storage.selected_valid || !storage.line_name) { return null; }
        var totals = cart.totals || {};
        var prefix = totals.currency_prefix || '';
        var suffix = totals.currency_suffix || '';
        var decimals = typeof totals.currency_minor_unit === 'number' ? totals.currency_minor_unit : 2;
        var decimal = totals.currency_decimal_separator || ',';
        var zero = decimals ? '0' + decimal + '0'.repeat(decimals) : '0';
        if (!prefix && !suffix) { suffix = ' ' + (totals.currency_symbol || '€'); }
        return h('div', { className: 'wlm-storage-summary wc-block-components-order-summary-item', 'aria-live': 'polite' },
            h('div', { className: 'wlm-storage-summary-name' },
                h('strong', null, storage.line_name),
                h('small', null, '1 × Gratis Einlagerung')),
            h('span', { className: 'wlm-storage-summary-price' }, prefix + zero + suffix));
    }
    if (typeof api.registerCheckoutBlock === 'function') {
        api.registerCheckoutBlock({
            metadata: { name: 'wlm/free-storage-selector', parent: ['woocommerce/checkout-fields-block'] },
            component: StorageSelector
        });
        api.registerCheckoutBlock({
            metadata: { name: 'wlm/free-storage-summary', parent: ['woocommerce/checkout-order-summary-block'] },
            component: StorageSummary
        });
    } else {
        console.error('[WLM Einlagerung] Native Checkout-Blockregistrierung wird von dieser WooCommerce-Version nicht unterstützt.');
    }
})();
