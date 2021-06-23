<?php

namespace IceSweetPear\cache;

interface CacheInterface{
    static function get($key);

    static function put($key, $value);

    static function lpush($key, $value);

    static function rpush($key, $value);

    static function lpop($key);

    static function rpop($key);

    static function delete($key);
}

