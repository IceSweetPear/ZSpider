<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2020/6/9
 * Time: 13:42
 */

namespace IceSweetPear\cache;

class ZFile implements CacheInterface{
    public static $path;
    public static $runPath;
    public static $pausePath;

    public static function init(){
        $tempPath = sys_get_temp_dir() . "/ZSpider";

        $runTaskPath = $tempPath . "/run";
        $pauseTaskPath = $tempPath . "/pause";

        is_dir($tempPath) ?: mkdir($tempPath);

        is_dir($runTaskPath) ?: mkdir($runTaskPath);
        delDir($runTaskPath, 1);

        is_dir($pauseTaskPath) ?: mkdir($pauseTaskPath);

        self::$runPath = $runTaskPath;
        self::$pausePath = $pauseTaskPath;
    }

    public static function show(){
        return self::getFilesData(self::$runPath);
    }

    public static function get($key){
        return self::readFile($key);
    }

    public static function put($key, $value){
        self::writeFile($key, $value);
    }

    public static function lpush($key, $value){
        $data = (array)self::readFile($key);
        array_unshift($data, $value);
        self::writeFile($key, $data);
    }

    public static function rpush($key, $value){
        $data = (array)self::readFile($key);
        array_push($data, $value);
        self::writeFile($key, $data);
    }

    public static function lpop($key){
        $data = (array)self::readFile($key);
        $value = array_shift($data);
        self::writeFile($key, $data);
        return $value;
    }

    public static function rpop($key){
        $data = (array)self::readFile($key);
        $value = array_pop($data);
        self::writeFile($key, $data);
        return $value;
    }

    public static function delete($key){
        self::delFile($key);
    }

    public static function readFile($key){
        $file = self::$runPath . "/$key";
        if (is_file($file)){
            $value = file_get_contents($file);
            $value_ser = @unserialize($value);
            if (is_object($value_ser) || is_array($value_ser)) {
                return $value_ser;
            }
            return $value;
        }else{
            return null;
        }
    }

    public static function writeFile($key, $value){
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        $file = self::$runPath . "/$key";
        !is_file($file) ?: touch($file);
        file_put_contents($file, $value);
    }

    public static function delFile($key){
        $file = self::$runPath . "/$key";
        !is_file($file) ?: unlink($file);
    }

    public static function getFilesData($runPath){
        $fileList = array_filter((array)scandir($runPath), function ($value){
            return !in_array($value, ['.', '..']);
        });
        $data = [];
        array_walk($fileList, function($value)use(&$data){
            $data[$value] = self::readFile($value);
        });
        return $data;
    }

    public static function putDieTask($key, $value){
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        $file = self::$pausePath . "/$key";
        !is_file($file) ?: touch($file);
        file_put_contents($file, $value);
    }

    public static function getDieTask($key){
        $file = self::$pausePath . "/$key";
        if (is_file($file)){
            $value = file_get_contents($file);
            $value_ser = @unserialize($value);
            if (is_object($value_ser) || is_array($value_ser)) {
                return $value_ser;
            }
            return $value;
        }else{
            return null;
        }
    }
}