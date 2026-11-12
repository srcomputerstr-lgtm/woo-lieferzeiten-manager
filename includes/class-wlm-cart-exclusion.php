<?php
/** Opt-in surcharge exclusions; legacy condition operators are intentionally untouched. */
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Cart_Exclusion {
    const LOGIC = 'none_in_cart';

    public static function is_valid($condition) {
        if (!is_array($condition) || ($condition['logic'] ?? '') !== self::LOGIC
            || !in_array($condition['type'] ?? 'attribute', array('attribute', 'taxonomy', 'shipping_class'), true)) {
            return false;
        }
        if (($condition['type'] ?? 'attribute') !== 'shipping_class'
            && (!isset($condition['attribute']) || !is_string($condition['attribute']) || trim($condition['attribute']) === '')) {
            return false;
        }
        $values = $condition['values'] ?? array();
        if (!is_array($values)) {
            $values = array($values);
        }
        if (empty($values)) {
            return false;
        }
        foreach ($values as $value) {
            if ((!is_string($value) && !is_int($value) && !is_float($value)) || trim((string) $value) === '') {
                return false;
            }
        }
        return true;
    }

    /** Read-only preflight of both existing supported Admin transport shapes. */
    public static function valid_surcharges($surcharges) {
        if (!is_array($surcharges)) {
            return true;
        }
        foreach ($surcharges as $surcharge) {
            if (!is_array($surcharge)) {
                continue;
            }
            $conditions = isset($surcharge['attribute_conditions']) && is_array($surcharge['attribute_conditions'])
                ? $surcharge['attribute_conditions'] : array();
            $flat = array();
            foreach ($surcharge as $key => $value) {
                if (preg_match('/^attribute_conditions\]\[(\d+)\]\[([^\]]+)\](\[\])?$/', $key, $matches)) {
                    $flat[$matches[1]][$matches[2]] = $value;
                }
            }
            foreach (array_merge($conditions, array_values($flat)) as $condition) {
                if (is_array($condition) && ($condition['logic'] ?? '') === self::LOGIC && !self::is_valid($condition)) {
                    return false;
                }
            }
        }
        return true;
    }

    private static function normalize_values($values) {
        return array_map(function($value) {
            return strtolower(trim((string) $value));
        }, $values);
    }

    /** Slug and name are both accepted only for this new operator. */
    private static function term_values($product_id, $taxonomy) {
        $terms = wp_get_post_terms($product_id, $taxonomy);
        if (is_wp_error($terms)) {
            return false;
        }
        $values = array();
        foreach ($terms as $term) {
            $values[] = $term->slug;
            $values[] = $term->name;
        }
        return $values;
    }

    private static function attribute_values($product, $attribute) {
        if ($product->is_type('variation')) {
            $attributes = $product->get_attributes();
            if (isset($attributes[$attribute]) && (string) $attributes[$attribute] !== '') {
                $value = (string) $attributes[$attribute];
                if (taxonomy_exists($attribute)) {
                    $term = get_term_by('slug', $value, $attribute);
                    return $term ? array($value, $term->name) : array($value);
                }
                return array($value);
            }
            $product = wc_get_product($product->get_parent_id());
            if (!$product) {
                return false;
            }
        }
        if (taxonomy_exists($attribute)) {
            return self::term_values($product->get_id(), $attribute);
        }
        $raw = $product->get_attribute($attribute);
        // Native custom text attributes use pipes; older WLM-compatible comma values are retained.
        return $raw === '' ? array() : preg_split('/\s*[,|]\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }

    /** Missing values are not forbidden; unreadable/invalid new rules fail closed. */
    public static function allows($product, $condition) {
        if (!self::is_valid($condition) || !is_object($product) || !is_a($product, 'WC_Product')) {
            return false;
        }
        $type = $condition['type'] ?? 'attribute';
        if ($type === 'shipping_class') {
            $slug = $product->get_shipping_class();
            $values = $slug ? array($slug) : array();
            if ($slug) {
                $term = get_term_by('slug', $slug, 'product_shipping_class');
                if ($term) {
                    $values[] = $term->name;
                }
            }
        } elseif ($type === 'taxonomy') {
            $id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
            $values = self::term_values($id, $condition['attribute']);
        } else {
            $values = self::attribute_values($product, $condition['attribute']);
        }
        if ($values === false) {
            return false;
        }
        $required = is_array($condition['values']) ? $condition['values'] : array($condition['values']);
        return empty(array_intersect(self::normalize_values($values), self::normalize_values($required)));
    }
}
