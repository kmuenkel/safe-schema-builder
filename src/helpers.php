<?php

use Carbon\Carbon;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

if (!function_exists('get_from_cache')) {
    /**
     * @param string $key
     * @param callable $action
     * @param Carbon|null $expiration
     * @return mixed|null
     */
    function get_from_cache($key, callable $action, Carbon $expiration = null)
    {
        /** @var CacheInterface $cache */
        $cache = app('cache');

        try {
            if ($cache->has($key) && !is_null($cache->get($key))) {
                return $cache->get($key);
            }
        } catch (SimpleCacheInvalidArgumentException $e) {
            return null;
        }

        $records = $action();
        $cache->put($key, $records, $expiration);

        return $records;
    }
}
