<?php

namespace AsyncPHP\Icicle\Cache\Test\Driver;

use AsyncPHP\Icicle\Cache\Driver\MemoryDriver;
use PHPUnit_Framework_TestCase;
use Icicle\Loop;
use Icicle\Coroutine;

class MemoryDriverTest extends PHPUnit_Framework_TestCase
{
    public function testValuesNotSet()
    {
        // create a coroutine to wrap the generator

        Coroutine\create(function() {
            $cache = new MemoryDriver();
            $actual = (yield $cache->get("foo"));

            $this->assertEqualsAfterDelay(null, $actual);
        })->done();

        // start the loop, so the coroutine runs

        Loop\run();
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    private function assertEqualsAfterDelay($expected, $actual)
    {
        // after a small delay, check for a value

        Loop\timer(1, function() use ($expected, $actual) {
            $this->assertEquals($expected, $actual);

            // remember to stop the loop when the test if done

            Loop\stop();
        });
    }

    public function testValueSet()
    {
        Coroutine\create(function() {
            $cache = new MemoryDriver();
            $actual = (yield $cache->set("foo", "bar"));

            $this->assertEqualsAfterDelay("bar", $actual);
        })->done();

        Loop\run();
    }

    public function testCallbackValuesSet()
    {
        Coroutine\create(function() {
            $cache = new MemoryDriver();
            $actual = (yield $cache->set("foo", function() {
                yield "bar";
            }));

            $this->assertEqualsAfterDelay("bar", $actual);
        })->done();

        Loop\run();
    }

    public function testValueForgotten()
    {
        Coroutine\create(function() {
            $cache = new MemoryDriver();
            yield $cache->set("foo", "bar");
            yield $cache->forget("foo");
            $actual = (yield $cache->get("foo"));

            $this->assertEqualsAfterDelay(null, $actual);
        })->done();

        Loop\run();
    }

    public function testStampedeProtection()
    {
        Coroutine\create(function() {
            $cache = new MemoryDriver();

            file_put_contents(__DIR__ . "/stampede", 1);

            $factory = function() use (&$counter) {
                $count = (int) file_get_contents(__DIR__ . "/stampede");

                file_put_contents(__DIR__ . "/stampede", ++$count);

                yield $count;
            };

            $cache->set("counter", $factory);
            $cache->set("counter", $factory);
            $cache->set("counter", $factory);

            $cache->get("counter");
            $cache->get("counter");

            // resolve the first "counter" value

            yield $cache->get("counter");

            // fetch the second "counter" value from the cache memory store

            $actual = (yield $cache->get("counter"));

            // first check to see that the count stored in the filesystem
            // is correct...

            Loop\timer(0.5, function() {
                $count = (int) file_get_contents(__DIR__ . "/stampede");

                $this->assertEquals(2, $count);

                unlink(__DIR__ . "/stampede");
            });

            // then check to see that the count stored in the cache
            // is correct...

            $this->assertEqualsAfterDelay(2, $actual);
        })->done();

        Loop\run();
    }
}
