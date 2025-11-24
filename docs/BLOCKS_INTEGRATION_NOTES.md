# WooCommerce Blocks Integration Notes

## ExperimentalOrderShippingPackages Slot

### What it does:
- Renders inside the shipping step of Checkout
- Renders inside the shipping options in Cart
- Perfect for adding custom content below/around shipping methods

### Passed Parameters:
- `cart`: wc/store/cart data (camelCase)
- `extensions`: External data from ExtendSchema
- `components`: Object with `ShippingRatesControlPackage`
- `renderOption`: Function to render shipping rates
- `context`: 'woocommerce/cart' or 'woocommerce/checkout'

### Example Code (from GitHub #10895):

```js
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { ExperimentalOrderShippingPackages } from '@woocommerce/blocks-checkout';
 
const render = () => {
  return (
    <ExperimentalOrderShippingPackages>
      <div>
        {
          __( 'Express Shipping', 'YOUR-TEXTDOMAIN' )
        }  
      </div>
    </ExperimentalOrderShippingPackages>
  );
};
 
registerPlugin( 'slot-and-fill-examples', {
  render,
  scope: 'woocommerce-checkout',
} );
```

## Implementation Plan:

1. Create React component for delivery window display
2. Register component using `registerPlugin`
3. Use `ExperimentalOrderShippingPackages` slot
4. Access cart data to get selected shipping method
5. Render delivery window + express option dynamically

## Benefits:

- ✅ Native WooCommerce Blocks integration
- ✅ No JavaScript hacks needed
- ✅ Labels stay clean
- ✅ Proper React integration
- ✅ Future-proof
- ✅ Works with both Cart and Checkout blocks

## Next Steps:

1. Create `assets/js/blocks/` directory
2. Create `delivery-info-slot-fill.js` React component
3. Build with webpack/npm
4. Enqueue in PHP
5. Test in Cart/Checkout
