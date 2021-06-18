
<?php
/**
 * Description:钩子
 * Created by PhpStorm.
 * User: Vijay <1937832819@qq.com>
 * Date: 2020/4/29
 * Time: 22:27
 */

echo "zzz";

// 接收码云POST过来的信息
$json = $GLOBALS['HTTP_RAW_POST_DATA'];

// 请求ip
$client_ip = $_SERVER['REMOTE_ADDR'];

// 执行shell命令,cd到网站根目录，执行git pull进行拉取代码，并把返回信息写进日志
exec('cd /www/wwwroot/zsplider; git pull');

