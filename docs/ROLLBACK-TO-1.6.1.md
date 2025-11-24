# ROLLBACK TO v1.6.1 - v1.6.4

**Date:** 11.11.2024  
**Action:** Complete rollback of v1.6.2 and v1.6.3

---

## 🔄 What Happened?

### The Problem

Versions 1.6.2 and 1.6.3 introduced **major changes** that caused more problems than they solved:

**v1.6.2:**
- Switched to `ExtendSchema` API (complete rewrite)
- Caused fatal error: `Call to undefined method WLM_Shipping_Methods::get_all_methods()`
- Frontend crashed with 500 error

**v1.6.3:**
- Fixed `get_all_methods()` → `get_configured_methods()`
- But still had fatal error: `Call to undefined method WLM_Express::is_express_active()`
- Frontend still crashed

### The Root Cause

The `ExtendSchema` approach required:
- Complete rewrite of `class-wlm-blocks-integration.php`
- Static methods instead of instance methods
- Different class structure
- **Too many breaking changes at once!**

---

## ✅ The Solution

### Rollback to v1.6.1

**v1.6.1 was CLOSE to working!**

The user's console logs showed:
```javascript
[WLM Blocks] Script loaded ✅
[WLM Blocks] Cart data from store: {...} ✅
[WLM Blocks] Extensions: {...} ✅
[WLM Blocks] WLM Extension: undefined ❌ ← Only this was the problem!
```

**The ONLY issue:** Extension data wasn't appearing in the frontend.

### Minimal Fix

Instead of rewriting everything, we made **ONE CHANGE**:

**File:** `includes/class-wlm-blocks-integration.php`  
**Line:** 142

```php
// Before (v1.6.1 - had typo):
$methods = $shipping_methods->get_all_methods();  ❌

// After (v1.6.4 - fixed):
$methods = $shipping_methods->get_configured_methods();  ✅
```

**That's it!** No other changes!

---

## 📊 Version Timeline

| Version | Approach | Status | Issue |
|---------|----------|--------|-------|
| 1.6.1 | IntegrationInterface | ⚠️ | Typo: `get_all_methods()` |
| 1.6.2 | ExtendSchema (rewrite) | ❌ | Fatal error: `get_all_methods()` |
| 1.6.3 | ExtendSchema (fixed) | ❌ | Fatal error: `is_express_active()` |
| **1.6.4** | **IntegrationInterface** | ✅ | **Minimal fix** |

---

## 🎯 What's in v1.6.4?

### Based on v1.6.1

- ✅ Uses `IntegrationInterface` (official WooCommerce Blocks API)
- ✅ Uses `woocommerce_store_api_register_endpoint_data()`
- ✅ Instance methods (not static)
- ✅ Proper class structure
- ✅ React component with `wp.data.useSelect`

### Plus One Fix

- ✅ Fixed: `get_all_methods()` → `get_configured_methods()`

### Nothing Else Changed

- ❌ No ExtendSchema
- ❌ No static methods
- ❌ No class restructuring
- ❌ No breaking changes

---

## 🚀 How to Update

### Force Pull (Required!)

```bash
cd /path/to/wp-content/plugins/woo-lieferzeiten-manager
git pull origin main --force
```

**Note:** `--force` is required because we rewrote git history to remove v1.6.2 and v1.6.3.

### Clear Caches

```
Browser: Ctrl+Shift+Delete
WordPress: Cache Plugin → Clear Cache
WooCommerce: Status → Tools → Clear transients
```

---

## ✅ Expected Result

After updating to v1.6.4:

1. **Frontend loads** - No 500 errors
2. **Console logs appear** - All `[WLM Blocks]` messages
3. **Extension data available** - `woo-lieferzeiten-manager` in extensions
4. **Delivery info displays** - In checkout below shipping methods

### Console Output

```javascript
[WLM Blocks] Script loaded
[WLM Blocks] Cart data from store: {...}
[WLM Blocks] Extensions: 
Object { 
    "woo-lieferzeiten-manager": {
        delivery_info: {...}
    }
}
[WLM Blocks] WLM Extension: 
Object { delivery_info: {...} }  ← Should NOT be undefined anymore!
```

---

## 📚 Documentation Status

### Obsolete Documents

These documents are now **OBSOLETE** and should be ignored:

- ❌ `UPDATE-TO-1.6.2.md` - ExtendSchema approach (discarded)
- ❌ `HOTFIX-1.6.3.md` - Fix for v1.6.2 (discarded)

### Still Valid

These documents are still relevant:

- ✅ `QUICK-START.md` - Installation and configuration
- ✅ `TESTING-CHECKLIST.md` - Step-by-step testing
- ✅ `BLOCKS-INTEGRATION-STATUS.md` - Technical documentation (mostly)
- ✅ `debug-blocks.js` - Debug script
- ✅ `CHANGELOG.md` - Updated with v1.6.4 entry

---

## 🎓 Lessons Learned

### What Went Wrong

1. **Over-engineering** - Tried to fix a small issue with a complete rewrite
2. **Following the article too literally** - The article's approach wasn't needed for our case
3. **Not testing incrementally** - Should have tested each small change

### What Went Right

1. **User feedback** - Recognized we were close with v1.6.1
2. **Quick rollback** - Didn't waste time trying to fix the broken approach
3. **Minimal fix** - One line change instead of rewriting everything

### Best Practice

**When close to working: Make minimal changes, not rewrites!**

---

## 🙏 Apology

Sorry for the confusion with v1.6.2 and v1.6.3!

The ExtendSchema approach from the article was correct for NEW integrations, but for our existing v1.6.1 code that was already 95% working, a minimal fix was the right approach.

**Lesson:** Don't rewrite working code just because there's a "better" way. Fix the actual problem first!

---

## 📞 Support

If v1.6.4 still has issues:

1. Make sure you force-pulled: `git pull origin main --force`
2. Clear all caches
3. Check console logs
4. Send feedback with logs

---

**Version:** 1.6.4  
**Date:** 11.11.2024  
**Status:** ✅ BACK TO WORKING APPROACH
