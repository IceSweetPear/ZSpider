<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2020/6/9
 * Time: 13:42
 */

namespace IceSweetPear\phpspider\cache;

class ZRedis implements CacheInterface{

    public static $redis;

    public static function init()
    {
        self::$redis = new \Redis();
        self::$redis->connect('127.0.0.1', 6379);
    }

    public static function put($key, $value)
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        return self::$redis->set($key, $value);
    }

    public static function get($key)
    {
        $value = self::$redis->get($key);
        $value_ser = @unserialize($value);
        if (is_object($value_ser) || is_array($value_ser)) {
            return $value_ser;
        }
        return $value;
    }

    public static function lpush($key, $value)
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        return self::$redis->lpush($key, $value);
    }

    public static function rpush($key, $value)
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }

        return self::$redis->rpush($key, $value);
    }

    public static function lpop($key)
    {
        $value = self::$redis->lpop($key);
        $value_ser = @unserialize($value);
        if (is_object($value_ser) || is_array($value_ser)) {
            return $value_ser;
        }
        return $value;
    }

    public static function rpop($key)
    {
        $value = self::$redis->rpop($key);
        $value_ser = @unserialize($value);
        if (is_object($value_ser) || is_array($value_ser)) {
            return $value_ser;
        }
        return $value;
    }

    public static function delete($key)
    {
        return self::$redis->del($key);
    }

}
