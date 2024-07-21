<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        return ['Authorization' => 'electrolytes'];
    }
}
