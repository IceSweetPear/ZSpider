
<?php
/**
 * Description:钩子
 * Created by PhpStorm.
 * User: Vijay <1937832819@qq.com>
 * Date: 2020/4/29
 * Time: 22:27
 */
 
// 接收码云POST过来的信息
$json = $GLOBALS['HTTP_RAW_POST_DATA'];
$data = json_decode($json, true);

chmod('web_hook.log', 755);

// 打开网站目录下的hooks.log文件 需要在服务器上创建 并给写权限
$fs = fopen('web_hook.log', 'w');

// 请求ip
$client_ip = $_SERVER['REMOTE_ADDR'];
// 把请求的IP和时间写进log
fwrite($fs, 'Request on [' . date("Y-m-d H:i:s") . '] from [' . $client_ip . ']' . PHP_EOL);
fwrite($fs, 'php belongs to [' . system("whoami") . ']' . PHP_EOL);

 fwrite($fs, 'Data: ' . print_r($data, true) . PHP_EOL);
 
// 执行shell命令,cd到网站根目录，执行git pull进行拉取代码，并把返回信息写进日志
exec('cd /www/wwwroot/zsplider; git pull 2<&1; chown -R www:www /www/wwwroot/zsplider/*;', $output);

fwrite($fs, 'Info:' . print_r($output, true) . PHP_EOL);
fwrite($fs, PHP_EOL . '================ Update End ===============' . PHP_EOL . PHP_EOL);
$fs and fclose($fs);
