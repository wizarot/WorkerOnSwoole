<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午2:33
 */

//服务器主运行文件


if(!extension_loaded('swoole'))
{
    exit("Please install swoole extension. \n");
}

require_once __DIR__.'/Autoloader.php';

use WorkerOnSwoole\GateWay;


$gate_way = new GateWay();

// 运行worker
$gate_way->run();



