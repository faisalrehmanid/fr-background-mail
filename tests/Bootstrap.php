<?php

/**
 * Bootstrap tests cases
 * Execute this script before executing any test case
 * 
 */

/**
 * Pretty print array/object for debuging
 *
 * @param array|object $params Array/object to be print
 * @param boolean $exit Exit after print
 * @return void
 */
if (!function_exists('\pr')) {
    function pr($params, $exit = true)
    {
        echo "<pre>";
        print_r($params);
        echo "</pre>";

        if ($exit == true) {
            exit();
        }
    }
}


/**
 * Call protected/private method of a class
 *
 * @param object &$object    Instantiated object that we will run method on
 * @param string $methodName Method name to call
 * @param array  $parameters Array of parameters to pass into method
 *
 * @return mixed Method return
 */
if (!function_exists('\invokeMethod')) {
    function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

/**
 * Generate Unique ID of fixed length
 *
 * @param int $length
 * @return string
 */
if (!function_exists('\generateUniqueId')) {
    function generateUniqueId($length)
    {
        $length = intval($length) / 2;
        if ($length == 0) return '';

        if (function_exists('random_bytes')) {
            $random = random_bytes($length);
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $random = openssl_random_pseudo_bytes($length);
        }

        if ($random !== false && strlen($random) === $length) {
            return  bin2hex($random);
        }

        $unique_id = '';
        $characters = '0123456789abcdef';
        for ($i = 0; $i < ($length * 2); $i++) {
            $unique_id .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $unique_id;
    }
}
