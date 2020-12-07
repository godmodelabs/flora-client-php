<?php declare(strict_types=1);

namespace Flora;

use InvalidArgumentException;

if (!function_exists('Flora\stringify_select')) {
    /**
     * @param array $spec
     * @return string
     * @throws InvalidArgumentException
     */
    function stringify_select(array $spec): string
    {
        $items = array_map(static function ($item, $key) {
            if (!is_numeric($key) && is_array($item)) {
                $str = stringify_select($item);
                return $key . (count($item) > 1 ? "[{$str}]" : ".{$str}");
            }
            if (!is_numeric($key) && is_string($item)) return $key . '.' . $item;

            if (is_string($item)) return $item;
            if (is_numeric($key) && is_array($item)) return stringify_select($item);

            throw new InvalidArgumentException(sprintf(
                'Cannot handle given select specification. "%s" cannot be stringified',
                print_r($item, true)
            ));
        }, $spec, array_keys($spec));

        return implode(',', $items);
    }
}
