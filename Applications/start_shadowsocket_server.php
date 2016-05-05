<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:20
 */


require_once __DIR__ . '/../Autoloader.php';

use WorkerOnSwoole\Worker;
use Applications\event\shadowSocket;


$config = array(
    'db' => array(//可以使用多个数据库连接
                  'wiz-cms' => array(
                      'host'     => '127.0.0.1',
                      'port'     => 1080,
                      'user'     => 'root',
                      'password' => '',
                      'dbname'   => 'shadowsocket',
                      'charset'  => 'utf8',
                  ),
    ),
    'shadow'=>array(
        'method'=>'aes-256-cfb',
        'password'=>'12345678',
        'port'=>'1081'
    ),
);

// 状态相关
define('STAGE_INIT', 0);
define('STAGE_ADDR', 1);
define('STAGE_UDP_ASSOC', 2);
define('STAGE_DNS', 3);
define('STAGE_CONNECTING', 4);
define('STAGE_STREAM', 5);
define('STAGE_DESTROYED', -1);

// 命令
define('CMD_CONNECT', 1);
define('CMD_BIND', 2);
define('CMD_UDP_ASSOCIATE', 3);

// 请求地址类型
define('ADDRTYPE_IPV4', 1);
define('ADDRTYPE_IPV6', 4);
define('ADDRTYPE_HOST', 3);

$server = Worker::listen( 'tcp://127.0.0.1:'.$config['shadow']['port'], $config );// 可用telnet连接

$server->setEvent( new shadowSocket() );
$server->run();
