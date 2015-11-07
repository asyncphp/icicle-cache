<?php

require __DIR__ . "/../vendor/autoload.php";

//
//
//
//
//print "starting\n";
//
//
//    $cache = new MemcacheDriver([
//        "host" => "127.0.0.1",
//        "port" => 11211,
//    ]);
//
//    print "setting\n";
//
//    yield $cache->set("foo", "bar");
//
//    print "getting\n";
//
//    $foo = (yield $cache->get("foo"));
//
//    print "foo: {$foo}\n";
//    exit();
//}));
//
//Loop\periodic(0.01, function() {
//    print ".";
//});
//
//

use Icicle\Loop;
use Icicle\Cache\Driver\MemoryDriver;
use Icicle\Coroutine;
use Icicle\Promise;

Coroutine\create(function() {
    $cache = new MemoryDriver([
        // TODO
    ]);

    $return = yield $cache->set("foo", Coroutine\create(function() {
        yield Promise\resolve("bar");
    }));
//    print_r($return);
//    exit();

    $cached = yield $cache->get("foo");
//    print_r($cached);
//    exit();

    print "cached: {$cached}\n";
});

Loop\run();
