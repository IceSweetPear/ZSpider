<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2021/5/16
 * Time: 17:37
 */

setlocale(LC_ALL, 'zh_CN.UTF-8');

//监听退出
function_exists('pcntl_signal') && pcntl_signal(SIGINT,  'exitSignHandler');

if (!function_exists('exitSignHandler')){
    function exitSignHandler($sign){
        if ($sign == SIGINT){
            ZCache::dieDo();
        }
    }
}

