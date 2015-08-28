<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */


require_once __DIR__ . '/../Autoloader.php';

use WorkerOnSwoole\ServerContainer;

use Applications\event\todpole;

class EchoServer
{
    function onReceive($server, $client_id, $from_id, $data)
    {
        $this->server->send($client_id, "WOS: " . $data);
    }
}


$config = array(
    'server' => array(
        'daemonize' => 0,
    ),
);
$server = ServerContainer::listen('ws://127.0.0.1:8888', $config);
$server = ServerContainer::listen( 'tcp://0.0.0.0:9505' );
//$server->setProtocol( $echoSvr );//可选,自定义类型服务器需要设定一下
$server->setEvent(new todpole());
$server->run();
