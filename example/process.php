<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/9/8
 * Time: 下午1:54
 */

$bin = exec( 'which php54' );
global $argv;
$start_file = $argv[ 0 ];

if( !isset($argv[ 1 ]) ){
    echo  "Usage: php " . $argv[ 0 ] . " {start|stop|restart|reload|status}"  . "\n";
    exit();
}

$command = $argv[1];

$process = new \swoole_process(function(swoole_process $worker) use($command){
    $worker->exec('/usr/local/bin/php54',array('echo_server.php',$command));
} ,FALSE );

$pid = $process->start();
sleep(1);//少等下再继续
echo PHP_EOL;

$process1 = new \swoole_process(function(swoole_process $worker) use($command){
    $worker->exec('/usr/local/bin/php54',array('server3.php',$command));
} ,FALSE );

$pid = $process1->start();
echo PHP_EOL;



//$pid = $process->start();
swoole_process::wait();//防止出现僵尸进程