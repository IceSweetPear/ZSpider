<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2021/5/16
 * Time: 23:30
 */
namespace IceSweetPear\phpspider\socket;

class SocketServer
{
    public $ip = "127.0.0.1";
    public $port = 1935;

    public function server(){
        set_time_limit(0);

        $ip = $this->ip;
        $port = $this->port;

        if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) { // 创建一个Socket链接
            echo "socket_create() 失败的原因是:" . socket_strerror($socket) . "\n";
            return;
        }

        if (($ret = socket_bind($socket, $ip, $port)) < 0) { //绑定Socket到端口
            echo "socket_bind() 失败的原因是:" . socket_strerror($ret) . "\n";
            return;
        }
        if (($ret = socket_listen($socket, 4)) < 0) { // 开始监听链接链接
            echo "socket_listen() 失败的原因是:" . socket_strerror($ret) . "\n";
            return;
        }

        echo "服务端socket已开启\n";

        return $socket;
    }

    //读取一条连接并给响应
    public function response($socket, $func){
        $msgsock = socket_accept($socket);
        if ($msgsock < 0){
            echo "socket_accept() failed: reason: " . socket_strerror($msgsock) . "\n";
            return;
        }

        $out = socket_read($msgsock, 8192);


        $value_ser = @unserialize($out);
        if (is_object($value_ser) || is_array($value_ser)) {
            $out = $value_ser;
        }
        echo "socket 读出\n";
        print_r($out);

        $msg = $func($out);

        echo "socket 写入 \n";\
        print_r($msg);

        if (is_object($msg) || is_array($msg)) {
            $msg = serialize($msg);
        }

        socket_write($msgsock, $msg, strlen($msg));

        return $out;
    }
}

//通用性  可以当服务端  也可以当客户端
