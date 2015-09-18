<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */


require_once __DIR__ . '/../Autoloader.php';

use WorkerOnSwoole\Worker;
use WorkerOnSwoole\lib\db;

// 事件处理对象,实际只需要处理这个即可
class EchoServer
{
    function onReceive( $server, $client_id, $from_id, $data )
    {
//        $ret = Db::instance('wiz-cms')->select('*')->from('wiz_wechat_im_log')->where('id<3')->limit(2)->query();
//        var_dump($ret);
//        $server->send($client_id, "WOS: ".$data);
        Worker::sendToAll( "WOS: " . $data );//only for tcp
    }


}

$config = array(
    'db' => array(//可以使用多个数据库连接
                  'wiz-cms' => array(
                      'host'     => '127.0.0.1',
                      'port'     => 3306,
                      'user'     => 'root',
                      'password' => '',
                      'dbname'   => 'wiz-cms',
                      'charset'  => 'utf8',
                  ),
    ),
);

$echoSvr = new EchoServer();
//$server = Worker::listen('udp://127.0.0.1:8888'); // 可用example中的 udp_client.php连接
$server = Worker::listen( 'tcp://127.0.0.1:8888', $config );// 可用telnet连接

$server->setEvent( $echoSvr );
$server->run();
