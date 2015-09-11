<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */


require_once __DIR__ . '/../Autoloader.php';

use WorkerOnSwoole\Worker;

use Applications\event\httpEvent;


// 实现一个简易的http web服务器可以处理静态请求和简单php.
// 做个例子,实际处理静态请求最好还是用nginx,复杂的php没测试不知道会不会有问题
$config = array(
    'server' => array(
        'daemonize' => 0,//是否为后台守护进程
        'worker_num'=> 4,
    ),
);
$server = Worker::listen( 'http://192.168.30.93:9501', $config );
$server->setEvent( new httpEvent() );
//$server->addRoot( 'www.test.com', __DIR__ . '/web/' );
$server->addRoot( '192.168.30.93:9501', __DIR__ . '/web/todpole/' );
$server->run();
