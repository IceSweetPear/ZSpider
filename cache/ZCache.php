<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2020/6/9
 * Time: 13:07
 */

namespace cache;

class ZCache{

    public static $cache;

    public static function init($cache){
        $cacheList = [
            'redis' => 'ZRedis',
            'array' => 'ZArray',
            'file' => 'ZFile',
        ];
        self::$cache = array_get($cacheList, $cache, $cacheList['array']);

        $cacheClass = "\cache\\" . self::$cache;

        $cacheClass::init();
    }

    public static function __callStatic($name, $arguments)
    {
        $cacheClass = "\cache\\" . self::$cache;
        return call_user_func_array("$cacheClass::$name", $arguments);
    }

    public static function dieDo(){
        $unFinishTask = self::$cache::show();

        if (empty($unFinishTask)){
            exit("No Remaining Tasks");
        }

        $key = substr(md5(time()), 0, 12);

        ZFile::putDieTask($key, $unFinishTask);

        echo "$key\n";
        exit();
    }
}