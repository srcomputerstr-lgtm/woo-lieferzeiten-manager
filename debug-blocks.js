/**
 * Debug Script für WooCommerce Blocks Integration
 * 
 * Kopieren Sie diesen Code in die Browser-Konsole auf der Checkout-Seite
 * um zu testen, ob die WooCommerce Blocks Store API funktioniert.
 */

console.log('=== WLM Blocks Debug Test ===');

// 1. Check if WordPress and WooCommerce globals are available
console.log('\n1. Checking globals...');
console.log('wp:', typeof wp);
console.log('wc:', typeof wc);
console.log('wp.data:', typeof wp.data);
console.log('wp.plugins:', typeof wp.plugins);
console.log('wc.blocksCheckout:', typeof wc.blocksCheckout);

if (typeof wp === 'undefined' || typeof wc === 'undefined') {
    console.error('❌ WordPress or WooCommerce globals not available!');
} else {
    console.log('✅ Globals available');
}

// 2. Check if store is registered
console.log('\n2. Checking WooCommerce store...');
if (typeof wp.data !== 'undefined') {
    const storeSelect = wp.data.select('wc/store/cart');
    console.log('Store select:', storeSelect);
    
    if (storeSelect) {
        console.log('✅ wc/store/cart is registered');
        
        // 3. Get cart data
        console.log('\n3. Getting cart data...');
        const cartData = storeSelect.getCartData();
        console.log('Cart data:', cartData);
        
        if (cartData) {
            console.log('✅ Cart data available');
            
            // 4. Check extensions
            console.log('\n4. Checking extensions...');
            console.log('Extensions:', cartData.extensions);
            
            if (cartData.extensions) {
                console.log('✅ Extensions available');
                
                // 5. Check WLM extension
                console.log('\n5. Checking WLM extension...');
                const wlmExtension = cartData.extensions['woo-lieferzeiten-manager'];
                console.log('WLM Extension:', wlmExtension);
                
                if (wlmExtension) {
                    console.log('✅ WLM extension found');
                    console.log('Delivery info:', wlmExtension.delivery_info);
                } else {
                    console.error('❌ WLM extension NOT found in extensions');
                    console.log('Available extensions:', Object.keys(cartData.extensions));
                }
            } else {
                console.error('❌ No extensions in cart data');
            }
            
            // 6. Check shipping rates
            console.log('\n6. Checking shipping rates...');
            console.log('Shipping rates:', cartData.shippingRates);
            
            if (cartData.shippingRates && cartData.shippingRates[0]) {
                const rates = cartData.shippingRates[0].shipping_rates;
                console.log('Available rates:', rates);
                
                const selectedRate = rates.find(r => r.selected);
                console.log('Selected rate:', selectedRate);
                
                if (selectedRate) {
                    console.log('✅ Shipping method selected');
                    console.log('Rate ID:', selectedRate.rate_id);
                    console.log('Method ID:', selectedRate.rate_id.split(':')[0]);
                } else {
                    console.warn('⚠️ No shipping method selected');
                }
            } else {
                console.warn('⚠️ No shipping rates available');
            }
        } else {
            console.error('❌ Cart data is null/undefined');
        }
    } else {
        console.error('❌ wc/store/cart is NOT registered');
    }
} else {
    console.error('❌ wp.data not available');
}

// 7. Check if ExperimentalOrderShippingPackages is available
console.log('\n7. Checking ExperimentalOrderShippingPackages...');
if (typeof wc !== 'undefined' && wc.blocksCheckout) {
    console.log('ExperimentalOrderShippingPackages:', wc.blocksCheckout.ExperimentalOrderShippingPackages);
    
    if (wc.blocksCheckout.ExperimentalOrderShippingPackages) {
        console.log('✅ ExperimentalOrderShippingPackages available');
        console.log('Fill:', wc.blocksCheckout.ExperimentalOrderShippingPackages.Fill);
    } else {
        console.error('❌ ExperimentalOrderShippingPackages NOT available');
    }
} else {
    console.error('❌ wc.blocksCheckout not available');
}

// 8. Check if our plugin is registered
console.log('\n8. Checking plugin registration...');
if (typeof wp.plugins !== 'undefined') {
    const plugins = wp.plugins.getPlugins();
    console.log('Registered plugins:', plugins);
    
    const wlmPlugin = plugins.find(p => p.name === 'wlm-delivery-info-slot-fill');
    if (wlmPlugin) {
        console.log('✅ WLM plugin registered:', wlmPlugin);
    } else {
        console.error('❌ WLM plugin NOT registered');
    }
} else {
    console.error('❌ wp.plugins not available');
}

console.log('\n=== Debug Test Complete ===');
