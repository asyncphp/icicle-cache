# Deprecated

Please don't use this. Icicle is no longer in active development, and as such, neither is this.

# Icicle Cache

[![Build Status](http://img.shields.io/travis/asyncphp/icicle-cache.svg?style=flat-square)](https://travis-ci.org/asyncphp/icicle-cache)
[![Code Quality](http://img.shields.io/scrutinizer/g/asyncphp/icicle-cache.svg?style=flat-square)](https://scrutinizer-ci.com/g/asyncphp/icicle-cache)
[![Code Coverage](http://img.shields.io/scrutinizer/coverage/g/asyncphp/icicle-cache.svg?style=flat-square)](https://scrutinizer-ci.com/g/asyncphp/icicle-cache)
[![Version](http://img.shields.io/packagist/v/asyncphp/icicle-cache.svg?style=flat-square)](https://packagist.org/packages/asyncphp/icicle-cache)
[![License](http://img.shields.io/packagist/l/asyncphp/icicle-cache.svg?style=flat-square)](license.md)

A simple cache library, built for Icicle, with anti-stampede and promises.

## Usage

```php
// get the value from the cache...
$cached = (yield $cache->get("foo"));

if (!$cached) {
    $cached = (yield $cache->set("foo", function () {
        // this coroutine is run in a separate process,
        // the last yielded value is cached...
        yield $data;
    }));
}

// use the $cached value for something...
```

## Versioning

This library follows [Semver](http://semver.org). According to Semver, you will be able to upgrade to any minor or patch version of this library without any breaking changes to the public API. Semver also requires that we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All other methods are not part of the public API. Where possible, we'll try to keep `protected` methods backwards-compatible in minor/patch versions, but if you're overriding methods then please test your work before upgrading.
