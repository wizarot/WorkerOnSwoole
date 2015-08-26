<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/26
 * Time: 下午3:38
 */
namespace WorkerOnSwoole\lib\Protocol;


abstract class Base implements \WorkerOnSwoole\lib\IFace\Protocol
{
    public $default_port;
    public $default_host;
    /**
     * @var \WorkerOnSwoole\lib\IFace\Log
     */
    public $log;

    /**
     * @var \WorkerOnSwoole\lib\Server
     */
    public $server;

    /**
     * @var array
     */
    protected $clients;

    /**
     * 设置Logger
     * @param $log
     */
    function setLogger($log)
    {
        $this->log = $log;
    }

    function run($array)
    {
//        \Swoole\Error::$echo_html = true;
        $this->server->run($array);
    }

    function setConfigJS($config)
    {

    }

    function daemonize()
    {
        $this->server->daemonize();
    }

    /**
     * 打印Log信息
     * @param $msg
     * @param string $type
     */
    function log($msg)
    {
        $this->log->info($msg);
    }

    function onStart($server)
    {

    }
    function onConnect($server, $client_id, $from_id)
    {

    }
    function onClose($server, $client_id, $from_id)
    {

    }
    function onShutdown($server)
    {

    }
}