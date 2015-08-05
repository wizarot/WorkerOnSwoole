<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */

use WorkerOnSwoole\Worker;
require_once '../Autoloader.php';

// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("http://0.0.0.0:8888");

// 启动4个进程对外提供服务
$http_worker->count = 4;


// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onRequest = function ($request, $response) {
    $response->end ("<h1>Hello Swoole. #" . rand (1000, 9999) . "</h1>");
};

//主进程启动事件,和worker进程启动,没先后顺序
$http_worker->onStart = function ($serv){

    echo "master  is running at : {$serv->master_pid} \n";
    echo "manager is running at : {$serv->manager_pid} \n";
};

//Server结束时发生
//强制kill进程不会回调onShutdown，如kill -9
//需要使用kill -15来发送SIGTREM信号到主进程才能按照正常的流程终止
$http_worker->onShutdown = function($server){
    echo "master is stoped.. \n";
};

// worker进程启动事件
$http_worker->onWorkerStart = function($serv, $worker_id){
    // 据文档说,如果reload,那么只有在这里require的文件reload才能重新加载.
    global $argv;// 全局变量中包含参数
    echo "worker - {$argv[0]} is running at: {$serv->worker_pid} \n";
};

$http_worker->onWorkerStop = function($serv, $worker_id){

    echo "worker - {$serv->worker_pid} is stoped..  \n";
};
 
// 运行worker
$http_worker->runAll();