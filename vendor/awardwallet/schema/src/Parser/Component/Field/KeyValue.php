<?php

namespace AwardWallet\Schema\Parser\Component\Field;


class KeyValue {

	protected static $default = [
		'key' => 'Field',
		'val' => 'Field',
        'cnt' => 1,
		'key_' => [],
		'val_' => [],
		'unique' => false,
	];

    public static function validateArray(&$key, array &$values, $property, array $attr, array $keyAttr, array $allowEmpty, array $allowNull)
    {
        $attr = array_merge(self::$default, $attr);
        if (count(array_unique([count($values), count($allowEmpty), count($allowNull), $attr['cnt']])) !== 1) {
            return 'invalid number of values';
        }
        if (!isset($attr['val0'])) {
            $attr['val0'] = $attr['val'];
        }
        if (!isset($attr['val0_'])) {
            $attr['val0_'] = $attr['val_'];
        }
        $attr['key_'] = array_merge($attr['key_'], $keyAttr);
        $error = Validator::validateField($key, $attr['key'], $property, $attr['key_'], false, false);
        if (!empty($error)) {
            return $error;
        }
        foreach($values as $i => &$value) {
            $attrKey = 'val' . $i . '_';
            if (!isset($attr[$attrKey])) {
                $attr[$attrKey] = [];
            }
            $error = Validator::validateField($value, $attr['val' . $i], $property, $attr[$attrKey], $allowEmpty[$i], $allowNull[$i]);
            if (!empty($error)) {
                return $error;
            }
        }
        return null;
    }
}