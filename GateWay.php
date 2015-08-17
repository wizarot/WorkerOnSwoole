<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午2:31
 */

namespace WorkerOnSwoole;

use \Swoole;
use \WorkerOnSwoole\Worker;

/**
 * Class GateWay
 * @package WorkerOnSwoole
 * 通过swoole/process 开启监听多个端口的服务器
 */
class GateWay
{
    public static $servers = array();// 每个监听的对象服务器
    public static $tmp_server;

    /**
     * @param \WorkerOnSwoole\Worker $server
     */
    public static function addServer(worker $server){
        self::$tmp_server = $server;
        $process = new \swoole_process(array('GateWay','serverFunc') ,FALSE );
        $pid = $process->start();
        self::$servers[$pid] = $process;
    }

    public static function serverFunc(swoole_process $worker){
        //加载配置,注册事件
//        self::$tmp_server->runAll();
        echo 'hello';
    }
}