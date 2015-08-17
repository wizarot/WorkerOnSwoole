<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */

use WorkerOnSwoole\Worker;
require_once __DIR__.'/../Autoloader.php';

// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("http://0.0.0.0:8888");

// 启动4个进程对外提供服务
$http_worker->count = 4;


// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onRequest = function ($request, $response) {
    $response->end ("<h1>Hello Swoole. #" . rand (1000, 9999) . "</h1>");
};

 
// 运行worker
$http_worker->runAll();