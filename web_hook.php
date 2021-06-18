
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
 
// 打开网站目录下的hooks.log文件 需要在服务器上创建 并给写权限
$fs = fopen('./web_hook.log', 'a');
fwrite($fs, '================ Update Start ===============' . PHP_EOL . PHP_EOL);
// 自定义密码 用于验证 与码云后台设置保持一致
$access_token = 'zkym';
$client_token = $data['password'];
 
// 请求ip
$client_ip = $_SERVER['REMOTE_ADDR'];
// 把请求的IP和时间写进log
fwrite($fs, 'Request on [' . date("Y-m-d H:i:s") . '] from [' . $client_ip . ']' . PHP_EOL);
fwrite($fs, 'php belongs to [' . system("whoami") . ']' . PHP_EOL);
 
// 验证token 有错就写进日志并退出
if ($client_token !== $access_token) {
    echo "error 403";
    fwrite($fs, "Invalid token [{$client_token}]" . PHP_EOL);
    $fs and fclose($fs);
 exit(0);
}
 
// 如果有需要 可以打开下面，把传送过来的信息写进log 可用于调试，测试成功后注释即可
// fwrite($fs, 'Data: ' . print_r($data, true) . PHP_EOL);
 
// 执行shell命令,cd到网站根目录，执行git pull进行拉取代码，并把返回信息写进日志
exec('cd /www/wwwroot/zsplider; git pull 2<&1; chown -R www:www /www/wwwroot/zsplider/*;', $output);

fwrite($fs, 'Info:' . print_r($output, true) . PHP_EOL);
fwrite($fs, PHP_EOL . '================ Update End ===============' . PHP_EOL . PHP_EOL);
$fs and fclose($fs);
