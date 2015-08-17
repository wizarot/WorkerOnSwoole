<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午2:33
 */

//服务器主运行文件
//use WorkerOnSwoole\Worker;
//require_once __DIR__ .'/Autoloader.php';

//\WorkerOnSwoole\GateWay::addServer(new Worker("http://0.0.0.0:8888"));
//\WorkerOnSwoole\GateWay::addServer(new Worker("ws://0.0.0.0:8000"));

//$servers = array();
//$workers = array();
//
//$workers = array('workerHttp','workerWs');
//
//foreach($workers as $func){
//    $process = new swoole_process($func,FALSE );
//    $pid = $process->start();
//    $servers[$pid] = $process;//将每一个进程的句柄存起来
//}
//
//var_dump($workers);

//function workerHttp(swoole_process $worker){
//    $worker->exec('/usr/local/bin/php' , array('/Users/will/php_productions/WorkerOnSwoole/example/http_test.php' , 'start'));
//}
//
//function workerWs(swoole_process $worker){
//    $worker->exec('/usr/local/bin/php' , array('/Users/will/php_productions/WorkerOnSwoole/example/http_test.php' , 'start'));
//
//}

if(!extension_loaded('swoole'))
{
    exit("Please install swoole extension. \n");
}

global $php;
use WorkerOnSwoole\Worker;
//use Applications\event\http1Event;
use Applications\event\todpole;

require_once __DIR__.'/Autoloader.php';


// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("ws://0.0.0.0:8888");

// 事件只要在Application中即可,具体放哪,怎么写随意.只要完成要求的事件即可.
$http_worker->setEvent(new todpole());// 读取并用户自定义事件

// 启动4个进程对外提供服务
$http_worker->count = 4;



// 运行worker
$http_worker->runAll();



