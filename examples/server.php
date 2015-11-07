<?php

require "vendor/autoload.php";

use Icicle\Cache\Driver\MemoryDriver;
use Icicle\Http\Client\Client;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Socket\SocketInterface;
use Icicle\Stream\MemorySink;

$cache = new MemoryDriver();

$server = new Server(function (RequestInterface $request, SocketInterface $socket) use ($cache) {
    try {
        $cached = (yield $cache->get("foo"));

        if (!$cached) {
            $cached = (yield $cache->set("foo", function () {
                $client = new Client();

                /** @var ResponseInterface $response */
                $response = (yield $client->request("GET", "https://icicle.io/"));

                $data = "";
                $stream = $response->getBody();

                while ($stream->isReadable()) {
                    $data .= (yield $stream->read());
                }

                yield $data;
            }));

//            $cached = (yield $cache->set("foo", function() {
//                yield "bar";
//            }));
        }

        $stream = new MemorySink();
        yield $stream->end($cached);

        $response = new Response(200, [
            "content-type"   => "text/html",
            "content-length" => $stream->getLength(),
        ], $stream);

        yield $response;

        $stream = null;
        $response = null;
    } catch (Exception $e) {
        print $e->getMessage();
    }
});

$server->listen(8000);

Loop\run();
