<?php

namespace AsyncPHP\Icicle\Cache\Driver;

use AsyncPHP\Icicle\Cache\Driver;
use Icicle\Concurrent\Forking\Fork;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\Deferred;
use Icicle\Promise\PromiseInterface;

class MemoryDriver implements Driver
{
    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var array
     */
    private $waiting = [];

    /**
     * @var array
     */
    private $busy = [];

    /**
     * @inheritdoc
     *
     * @param string $key
     *
     * @return PromiseInterface
     *
     * @resolve mixed
     */
    public function get($key)
    {
        $deferred = new Deferred();

        Loop\queue(function () use ($deferred, $key) {
            if ($this->busy($deferred, $key)) {
                $this->wait($deferred, $key);
            } else {
                $deferred->resolve($this->value($key));
            }
        });

        return $deferred->getPromise();
    }

    /**
     * @param Deferred $deferred
     * @param string $key
     *
     * @return bool
     */
    private function busy(Deferred $deferred, $key)
    {
        return isset($this->busy[$key]) and spl_object_hash($this->busy[$key]) !== spl_object_hash($deferred);
    }

    /**
     * @param Deferred $deferred
     * @param string $key
     */
    private function wait(Deferred $deferred, $key)
    {
        if (!isset($this->waiting[$key])) {
            $this->waiting[$key] = [];
        }

        $this->waiting[$key][] = $deferred;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function value($key)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return null;
    }

    /**
     * @inheritdoc
     *
     * @param string $key
     * @param mixed $value
     *
     * @return PromiseInterface
     *
     * @resolve mixed
     */
    public function set($key, $value)
    {
        $deferred = new Deferred();

        Loop\queue(function () use ($deferred, $key, $value) {
            if ($this->busy($deferred, $key)) {
                $this->wait($deferred, $key);
            } else {
                Coroutine\create(function () use ($deferred, $key, $value) {
                    $this->busy[$key] = $deferred;

                    if (is_callable($value)) {
                        $fork = Fork::spawn($value);
                        $value = (yield $fork->join());
                    }

                    $this->cache[$key] = $value;
                    unset($this->busy[$key]);

                    if (isset($this->waiting[$key])) {
                        while (count($this->waiting[$key])) {
                            /** @var Deferred $next */
                            $next = array_shift($this->waiting[$key]);

                            $next->resolve($value);
                        }
                    }

                    $deferred->resolve($value);
                });
            }
        });

        return $deferred->getPromise();
    }

    /**
     * @inheritdoc
     *
     * @param string $key
     *
     * @return PromiseInterface
     *
     * @resolve void
     */
    public function forget($key)
    {
        $deferred = new Deferred();

        Loop\queue(function () use ($deferred, $key) {
            unset($this->cache[$key]);
            $deferred->resolve();
        });

        return $deferred->getPromise();
    }
}
