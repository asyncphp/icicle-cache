<?php

namespace Icicle\Cache;

interface Driver
{
    public function get($key);

    public function set($key, $value);

    public function forget($key);
}
