<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午2:33
 */

//服务器主运行文件
use WorkerOnSwoole\Worker;

require_once __DIR__ .'/Autoloader.php';

//\WorkerOnSwoole\GateWay::addServer(new Worker("http://0.0.0.0:8888"));
//\WorkerOnSwoole\GateWay::addServer(new Worker("ws://0.0.0.0:8000"));

$servers = array();
$workers = array();

$workers = array('workerHttp','workerWs');

foreach($workers as $func){
    $process = new swoole_process($func,FALSE );
    $pid = $process->start();
    $servers[$pid] = $process;//将每一个进程的句柄存起来
}

var_dump($workers);

//function workerHttp(swoole_process $worker){
//    $worker->exec('/usr/local/bin/php' , array('/Users/will/php_productions/WorkerOnSwoole/example/http_test.php' , 'start'));
//}
//
//function workerWs(swoole_process $worker){
//    $worker->exec('/usr/local/bin/php' , array('/Users/will/php_productions/WorkerOnSwoole/example/http_test.php' , 'start'));
//
//}



