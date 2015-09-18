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
// 简单压力测试功能
class webServer
{
    function onRequest($request, $response)
    {
        $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."</h1>");
    }


}

$webServer = new webServer();
$server = Worker::listen('http://127.0.0.1:8888');

$server->setEvent($webServer);
$server->run();
