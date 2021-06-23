<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2020/6/9
 * Time: 13:07
 */

namespace cache;

class ZCache{

    public static $cacheClass;

    public static function init($cache){
        $cacheList = [
            'redis' => ZRedis::class,
            'array' => ZArray::class,
            'file' => ZFile::class,
        ];

        self::$cacheClass = array_get($cacheList, $cache, $cacheList['array']);

        self::$cacheClass::init();
    }

    public static function __callStatic($name, $arguments)
    {
        $cacheClass = self::$cacheClass;
        return call_user_func_array("$cacheClass::$name", $arguments);
    }

    public static function dieDo(){
        $unFinishTask = self::$cacheClass::show();

        if (empty($unFinishTask)){
            exit("No Remaining Tasks");
        }

        $key = substr(md5(time()), 0, 12);

        ZFile::putDieTask($key, $unFinishTask);

        echo "$key\n";
        exit();
    }
}