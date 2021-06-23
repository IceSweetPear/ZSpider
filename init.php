<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2021/5/16
 * Time: 17:37
 */

setlocale(LC_ALL, 'zh_CN.UTF-8');

//监听退出
!function_exists('pcntl_signal') ?: pcntl_signal(SIGINT,  'exitSignHandler');

function exitSignHandler($sign)
{
    if ($sign == SIGINT){
        ZCache::dieDo();
    }
}

spl_autoload_register(function ($className) {

    $className = ltrim($className, '\\');
    $fileName = '';
    if ($lastNsPos = strripos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName = __DIR__ . DIRECTORY_SEPARATOR . $fileName . $className . '.php';

    if (file_exists($fileName)) {
        require $fileName;

        return true;
    }

    return false;
});

require './vendor/autoload.php';


