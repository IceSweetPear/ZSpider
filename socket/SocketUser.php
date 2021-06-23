<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2021/5/16
 * Time: 23:30
 */
namespace socket;

class SocketUser
{
    public $ip = "127.0.0.1";
    public $port = 1935;

    public function request($out){
        $ip = $this->ip;
        $port = $this->port;

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $result = socket_connect($socket, $ip, $port);

        if ($result < 0) {
            echo "socket_connect() failed.\nReason: ($result) " . socket_strerror($result) . "\n";
        }

        echo "写入的内容为:\n";
        print_r($out);

        if (is_object($out) || is_array($out)) {
            $out = serialize($out);
        }

        socket_write($socket, $out, strlen($out));


        $out = socket_read($socket, 8192);

        $value_ser = @unserialize($out);
        if (is_object($value_ser) || is_array($value_ser)) {
            $out = $value_ser;
        }

        echo "返回的内容为：\n";
        print_r($out);

        socket_close($socket);

        return $out;
    }
}