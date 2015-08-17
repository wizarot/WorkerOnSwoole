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
use WorkerOnSwoole\Worker;
use Applications\event\http1Event;

require_once __DIR__.'/../Autoloader.php';


// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("http://0.0.0.0:8888");

// 事件只要在Application中即可,具体放哪,怎么写随意.只要完成要求的事件即可.
$http_worker->setEvent(new http1Event());// 读取并用户自定义事件

// 启动4个进程对外提供服务
$http_worker->count = 4;


 
// 运行worker
$http_worker->runAll();