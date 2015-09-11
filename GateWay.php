<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午2:31
 */

namespace WorkerOnSwoole;

use WorkerOnSwoole\lib\Console;

/**
 * Class GateWay
 * @package WorkerOnSwoole
 * 通过swoole/process 开启监听多个端口的服务器
 */
class GateWay
{
    public static $bin;
    public static $argv;
    public static $start_file;
    public static $app_dir = 'Applications';
    public static $dir     = __DIR__;
    public static $command;
    public static $daemonize;

    public function __construct( $config = FALSE )
    {
        global $argv;
        self::$argv = $argv;
        self::$bin = exec( 'which php54' );
        self::$start_file = $argv[ 0 ];

        if ( !isset( $argv[ 1 ] ) ) {
            echo Console::error( "Usage: php " . $argv[ 0 ] . " {start|stop|restart|reload|status}" ) . "\n";
            exit();
        }

        self::$command = $argv[ 1 ];
        $command2 = isset( $argv[ 2 ] ) ? $argv[ 2 ] : '';
        self::$daemonize = $command2 ;

        if ( isset( $config[ 'applications' ] ) ) {
            self::$app_dir = $config[ 'applications' ];
        } else {
            self::$app_dir = self::$dir . '/' . self::$app_dir;
        }
    }

    /**
     * 自动加载执行Application根目录下全部start开头的功能
     */
    function run()
    {
        //加载文件
        //使用swoole process执行每个Application下的server文件,也可以手动单独执行
        // 加载所有Applications/*/start.php，以便启动所有服务
        foreach ( glob( self::$app_dir . '/start*.php' ) as $start_file ) {
            $command = self::$command;
            $daemonize = self::$daemonize;
            $process = new \swoole_process( function ( \swoole_process $worker ) use ( $start_file, $command ,$daemonize ) {
                $worker->exec( self::$bin, array( $start_file, $command ,$daemonize ) );
            }, FALSE );

            $pid = $process->start();
            sleep( 1 );//稍等下再继续
        }

        \swoole_process::wait();//防止出现僵尸进程

    }


}