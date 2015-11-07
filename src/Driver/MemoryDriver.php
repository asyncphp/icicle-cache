<?php

namespace Icicle\Cache\Driver;

use Generator;
use Icicle\Cache\Driver;
use Icicle\Concurrent\Forking\Fork;
use Icicle\Loop;
use Icicle\Promise\Deferred;

class MemoryDriver implements Driver
{
    /**
     * @var array
     */
    private $items = [];

    /**
     * @var array
     */
    private $wait = [];

    /**
     * @param string $key
     *
     * @return Generator
     */
    public function get($key)
    {
        if ($this->waitingFor($key)) {
            yield $this->deferredFor($key);
        } else {
            if (isset($this->items[$key])) {
                yield $this->items[$key];
            }

            yield null;
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expires
     *
     * @return Generator
     */
    public function set($key, $value, $expires = 0)
    {
        if ($this->waitingFor($key)) {
            yield $this->deferredFor($key);
        } else {
            $this->wait[$key] = true;

            if (!isset($this->items[$key])) {
                if (is_callable($value)) {
                    $fork = Fork::spawn($value);
                    $yielded = (yield $fork->join());
                }

                $this->items[$key] = $yielded;
            }

            if ($expires > 0) {
                $this->scheduleForget($key, $expires);
            }

            $this->wait[$key] = false;

            yield $this->items[$key];
        }
    }

    /**
     * @param string $key
     *
     * @return Generator
     */
    public function forget($key)
    {
        unset($this->items[$key]);

        yield;
    }

    /**
     * @param string $key
     * @param int $expires
     */
    private function scheduleForget($key, $expires)
    {
        Loop\timer($expires, function () use ($key) {
            $this->forget($key);
        });
    }

    /**
     * @param $key
     *
     * @return bool
     */
    private function waitingFor($key)
    {
        return isset($this->wait[$key]) and $this->wait[$key];
    }

    /**
     * @param string $key
     *
     * @return Deferred
     */
    private function deferredFor($key)
    {
        $deferred = new Deferred();

        $timer = Loop\periodic(0.1, function () use (&$timer, $key, $deferred) {
            if (isset($this->items[$key])) {
                $timer->stop();
                $deferred->resolve($this->items[$key]);
            }
        });

        return $deferred;
    }
}
