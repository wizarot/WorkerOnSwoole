<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */


require_once __DIR__ . '/../Autoloader.php';

use WorkerOnSwoole\Worker;

use Applications\event\todpole;



$config = array(
    'server' => array(
        'daemonize' => 0,
    ),
);
$server = Worker::listen('ws://192.168.30.93:9503', $config);
$server->setEvent(new todpole());
$server->run();
