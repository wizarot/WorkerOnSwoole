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

global $php;


require_once __DIR__.'/../Autoloader.php';

use WorkerOnSwoole\lib\Server;

//class EchoServer extends Swoole\Protocol\Base
//{
//    function onReceive($server,$client_id, $from_id, $data)
//    {
//        $this->server->send($client_id, "Swoole: ".$data);
//    }
//}


$server = Server::autoCreate('0.0.0.0', 9505);
