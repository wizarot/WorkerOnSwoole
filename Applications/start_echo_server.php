<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */


require_once __DIR__ . '/../Autoloader.php';

use WorkerOnSwoole\Worker;

// 事件处理对象,实际只需要处理这个即可
class EchoServer
{
    function onReceive($server,$client_id, $from_id, $data)
    {
//        $server->send($client_id, "WOS: ".$data);
        Worker::sendToAll("WOS: ".$data);//only for tcp
    }


}

$echoSvr = new EchoServer();
//$server = Worker::listen('udp://127.0.0.1:8888'); // 可用example中的 udp_client.php连接
$server = Worker::listen('tcp://127.0.0.1:8888');// 可用telnet连接

$server->setEvent($echoSvr);
$server->run();
