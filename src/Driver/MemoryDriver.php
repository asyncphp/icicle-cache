<?php

namespace Icicle\Cache\Driver;

use Icicle\Cache\Driver;
use Icicle\Concurrent\Forking\Fork;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\PromiseInterface;

class MemoryDriver implements Driver
{
    /**
     * @var array
     */
    private $get = [];

    /**
     * @var array
     */
    private $set = [];

    /**
     * @var array
     */
    private $forget = [];

    /**
     * @var array
     */
    private $items = [];

    /**
     * @var array
     */
    private $busy = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $interval = $this->getInterval($options);

        Loop\periodic($interval, function () {
            if (count($this->forget)) {
                $this->handleForget(array_pop($this->forget));

                return;
            }

            if (count($this->set)) {
                $this->handleSet(array_pop($this->set));

                return;
            }

            if (count($this->get)) {
                $this->handleGet(array_pop($this->get));

                return;
            }
        });
    }

    /**
     * @param array $options
     *
     * @return float
     */
    private function getInterval(array $options = [])
    {
        if (isset($options["interval"])) {
            return $options["interval"];
        }

        return 0.0001;
    }

    /**
     * @param array $parameters
     */
    private function handleForget(array $parameters)
    {
        list($deferred, $key) = $parameters;

        unset($this->items[$key]);

        $deferred->resolve();
    }

    /**
     * @param array $parameters
     */
    private function handleSet(array $parameters)
    {
        list($deferred, $key, $value) = $parameters;

        if ($this->isBusy($deferred, $key)) {
            $this->waitFor($deferred, $key);
        } else {
            $coroutine = Coroutine\create(function () use ($key, $value) {
                if (is_callable($value)) {
                    $fork = Fork::spawn($value);
                    $value = (yield $fork->join());
                }

                $this->items[$key] = $value;

                yield $value;
            });

            $coroutine->done(function ($value) use ($deferred) {
                $deferred->resolve($value);
            });
        }
    }

    /**
     * @param Deferred $deferred
     * @param string $key
     *
     * @return bool
     */
    private function isBusy(Deferred $deferred, $key)
    {
        return isset($this->busy[$key]) && $this->busy[$key] !== $deferred;
    }

    /**
     * @param Deferred $deferred
     * @param string $key
     */
    private function waitFor(Deferred $deferred, $key)
    {
        $timer = Loop\periodic($this->getInterval(), function () use (&$timer, $deferred, $key) {
            if (isset($this->items[$key])) {
                $timer->stop();
                $deferred->resolve($this->items[$key]);
            }
        });
    }

    /**
     * @param array $parameters
     */
    private function handleGet(array $parameters)
    {
        list($deferred, $key) = $parameters;

        if ($this->isBusy($deferred, $key)) {
            $this->waitFor($deferred, $key);
        } else {
            if (isset($this->items[$key])) {
                $deferred->resolve($this->items[$key]);
            }

            $deferred->resolve(null);
        }
    }

    /**
     * @param string $key
     *
     * @return PromiseInterface
     */
    public function get($key)
    {
        $deferred = new Deferred();

        $this->get[] = [$deferred, $key];

        return $deferred->getPromise();
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return PromiseInterface
     */
    public function set($key, $value)
    {
        $deferred = new Deferred();

        $this->busy[$key] = $deferred;

        $this->set[] = [$deferred, $key, $value];

        return $deferred->getPromise();
    }

    /**
     * @param string $key
     *
     * @return PromiseInterface
     */
    public function forget($key)
    {
        $deferred = new Deferred();

        $this->forget[] = [$deferred, $key];

        return $deferred->getPromise();
    }
}
