<?php

namespace AsyncPHP\Icicle\Cache;

use Icicle\Promise\PromiseInterface;

interface Driver
{
    /**
     * Gets a cached value, or null if the value has not yet been cached. If the value is busy
     * being cached
     * (in the case of a coroutine) then this will only resolve once that value is cached, and with
     * the resultant value of the coroutine.
     *
     * @param string $key
     *
     * @return PromiseInterface
     *
     * @resolve mixed
     */
    public function get($key);

    /**
     * Sets a value, returns a promise which resolves to the value that was set. If a coroutine
     * value is given, the resolved value will be the last yielded value of that coroutine.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return PromiseInterface
     *
     * @resolve mixed
     */
    public function set($key, $value);

    /**
     * Forgets a cached value.
     *
     * @param string $key
     *
     * @return PromiseInterface
     *
     * @resolve void
     */
    public function forget($key);
}
