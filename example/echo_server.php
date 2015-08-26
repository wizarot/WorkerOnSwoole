<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */

if(!extension_loaded('swoole'))
{
    exit("Please install swoole extension. \n");
}



require_once __DIR__.'/../Autoloader.php';

use WorkerOnSwoole\lib\WorkerServer;
use WorkerOnSwoole\lib\Protocol;

class EchoServer extends Protocol\Base
{
    function onReceive($server,$client_id, $from_id, $data)
    {
        $this->server->send($client_id, "WOS: ".$data);
    }
}

$echoSvr = new EchoServer();
$server = WorkerServer::listen('0.0.0.0', 9505);
$server->setProtocol($echoSvr);
$server->run(array('worker_num' => 1));
