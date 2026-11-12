<?php
/** Numeric WooCommerce shipping-dimension conditions for surcharges only. */
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Dimension_Condition {
    /** Normalize only the new condition type; invalid rules never become unconditional. */
    public static function normalize($condition) {
        if (!is_array($condition) || ($condition['type'] ?? '') !== 'dimension') {
            return false;
        }
        $dimension = $condition['dimension'] ?? '';
        $operator = $condition['operator'] ?? 'gt';
        $unit = $condition['unit'] ?? get_option('woocommerce_dimension_unit', 'cm');
        $threshold = $condition['threshold'] ?? null;
        if (!in_array($dimension, array('length', 'width', 'height', 'max'), true)
            || $operator !== 'gt'
            || !in_array($unit, array('mm', 'cm', 'm', 'in', 'yd'), true)
            || (!is_string($threshold) && !is_int($threshold) && !is_float($threshold))) {
            return false;
        }
        $number = trim((string) $threshold);
        // Accept one decimal separator, not thousands/unit suffixes/ambiguous input.
        if (!preg_match('/^(?:\d+(?:[.,]\d+)?|[.,]\d+)$/D', $number)) {
            return false;
        }
        $number = str_replace(',', '.', $number);
        if (!is_finite((float) $number) || (float) $number < 0) {
            return false;
        }
        $condition['dimension'] = $dimension;
        $condition['operator'] = 'gt';
        $condition['threshold'] = $number;
        $condition['unit'] = $unit;
        $condition['attribute'] = '';
        $condition['values'] = array();
        $condition['logic'] = 'at_least_one';
        return $condition;
    }

    /** Read-only preflight, including the legacy flat-key transport supported by Admin. */
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
                if (is_array($condition) && ($condition['type'] ?? '') === 'dimension' && !self::normalize($condition)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Getters in view context retain WooCommerce's own variation/parent fallback. */
    public static function matches($product, $condition) {
        $condition = self::normalize($condition);
        if (!$condition || !is_object($product)) {
            return false;
        }
        $current_unit = get_option('woocommerce_dimension_unit', 'cm');
        if (!in_array($current_unit, array('mm', 'cm', 'm', 'in', 'yd'), true)) {
            return false;
        }
        // A common base avoids the native rounded inverse factors for inches/yards.
        $threshold = wc_get_dimension((float) $condition['threshold'], 'cm', $condition['unit']);
        $dimensions = $condition['dimension'] === 'max'
            ? array('length', 'width', 'height') : array($condition['dimension']);
        foreach ($dimensions as $dimension) {
            $getter = 'get_' . $dimension;
            if (!is_callable(array($product, $getter))) {
                continue;
            }
            $value = $product->$getter();
            // An absent/invalid dimension does not silently turn into a matching value.
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
                continue;
            }
            $value = wc_get_dimension((float) $value, 'cm', $current_unit);
            if ($value > $threshold) {
                return true;
            }
        }
        return false;
    }
}
