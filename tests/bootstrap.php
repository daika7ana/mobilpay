<?php

$GLOBALS['mobilpay_test_config'] = [];

if (!function_exists('config')) {
    /**
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        return $GLOBALS['mobilpay_test_config'][$key] ?? $default;
    }
}

if (!function_exists('url')) {
    /**
     * @param string|null $path
     * @return string
     */
    function url($path = null)
    {
        return 'https://app.test/' . ltrim((string) $path, '/');
    }
}
