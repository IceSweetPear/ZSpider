<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2020/6/9
 * Time: 13:42
 */

namespace IceSweetPear\cache;

class ZArray implements CacheInterface{

    public static $array;

    public static function show(){
        return self::$array;
    }

    public static function init(){
        self::$array = [];
    }

    public static function get($key){
        return self::$array[$key];
    }

    public static function put($key, $value){
        self::$array[$key] = $value;
    }

    public static function lpush($key, $value){
        return array_unshift(self::$array[$key], $value);
    }

    public static function rpush($key, $value){
        self::$array[$key][] = $value;
    }

    public static function lpop($key){
        return array_shift(self::$array[$key]);
    }

    public static function rpop($key){
        return array_pop(self::$array[$key]);
    }

    public static function delete($key){
        unset(self::$array[$key]);
    }

    public static function zzz(){
        exit("ccccccccccccc");
    }
}
