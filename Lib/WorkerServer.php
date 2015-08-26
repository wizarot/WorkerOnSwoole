<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/26
 * Time: 下午3:26
 */

namespace WorkerOnSwoole\lib;


class WorkerServer extends Server implements IFace\Server
{
    static $sw_mode = SWOOLE_PROCESS;
    /**
     * @var \swoole_server
     */
    const  VERSION = '0.0.2';
    protected $sw;
    protected $pid_file;
    protected $_startFile;
    protected $socketName;

    /**
     * 自动推断扩展支持
     * 仅支持swoole扩展,如果有问题则直接退出
     * @param      $host
     * @param      $port
     * @param bool $ssl
     * @return Server
     */
    static function listen( $host, $port, $ssl = FALSE )
    {
        if ( class_exists( '\\swoole_server', FALSE ) ) {
            return new self( $host, $port, $ssl );
        } else {
            echo Console::render( "<bg=red>Error must install php swoole extended model</>" ) . "\n";
            die;
        }
    }

    function __construct( $host, $port, $ssl = FALSE )
    {
        $flag = $ssl ? ( SWOOLE_SOCK_TCP | SWOOLE_SSL ) : SWOOLE_SOCK_TCP;
        $this->sw = new \swoole_server( $host, $port, self::$sw_mode, $flag );
        $this->host = $host;
        $this->port = $port;
        $this->socketName = "{$host}:{$port}";


        $this->runtimeSetting = array(
            //'reactor_num' => 4,      //reactor thread num
            //'worker_num' => 4,       //worker process num
            'backlog' => 128,        //listen backlog
            //'open_cpu_affinity' => 1,
            //'open_tcp_nodelay' => 1,
            //'log_file' => '/tmp/swoole.log',
        );

        if ( !isset( $this->runtimeSetting[ 'pid_file' ] ) ) {
            $backtrace = debug_backtrace();
            $this->_startFile = $backtrace[ count( $backtrace ) - 1 ][ 'file' ];
            $this->runtimeSetting[ 'pid_file' ] = sys_get_temp_dir() . "/workerOnSwoole." . str_replace( '/', '_', $this->_startFile ) . "{$host}_{$port}.pid";
        }

    }

    function daemonize()
    {
        $this->runtimeSetting[ 'daemonize' ] = 1;
    }

    function onMasterStart( $server )
    {
        global $argv;
        Console::setProcessName( 'php ' . $argv[ 0 ] . ': master -host=' . $this->host . ' -port=' . $this->port );
        echo Console::success( "master  process is running at : {$server->master_pid} " ) . "\n";
        echo Console::success( "manager process is running at : {$server->manager_pid} " ) . "\n";

        if ( !empty( $this->runtimeSetting[ 'pid_file' ] ) ) {
            file_put_contents( $this->pid_file, $server->master_pid );
        }
    }


    function onMasterStop( $serv )
    {
        if ( !empty( $this->runtimeSetting[ 'pid_file' ] ) ) {
            unlink( $this->pid_file );
        }
        echo Console::info( "master process is stoped.. " ) . "\n";

    }

    function onManagerStop()
    {
        if ( !empty( $this->runtimeSetting[ 'pid_file' ] ) ) {
            unlink( $this->pid_file );
        }
        echo Console::info( "manager process is stoped.. " ) . "\n";

    }

    function onWorkerStart( $serv, $worker_id )
    {
        global $argv;
        if ( $worker_id >= $serv->setting[ 'worker_num' ] ) {
            Console::setProcessName( 'php ' . $argv[ 0 ] . ': task' );
            echo Console::success( "task   is running at : {$serv->worker_pid} " ) . "\n";
        } else {
            Console::setProcessName( 'php ' . $argv[ 0 ] . ': worker' );
            echo Console::success( "worker is running at : {$serv->worker_pid} " ) . "\n";
        }
        if ( method_exists( $this->protocol, 'onStart' ) ) {
            $this->protocol->onStart( $serv, $worker_id );
        }
    }

    function run( $setting = array() )
    {


        $this->runtimeSetting = array_merge( $this->runtimeSetting, $setting );
        if ( !empty( $this->runtimeSetting[ 'pid_file' ] ) ) {
            $this->pid_file = $this->runtimeSetting[ 'pid_file' ];
        }
        // 首先处理命令
        $this->parseCommand();
        $this->displayUI();

        $this->sw->set( $this->runtimeSetting );
        $version = explode( '.', SWOOLE_VERSION );
        //1.7.0
        if ( $version[ 1 ] >= 7 ) {
            $this->sw->on( 'ManagerStart', function ( $serv ) {
                global $argv;
                Console::setProcessName( 'php ' . $argv[ 0 ] . ': manager' );
            } );
        }
        $this->sw->on( 'Start', array( $this, 'onMasterStart' ) );
        $this->sw->on( 'Shutdown', array( $this, 'onMasterStop' ) );
        $this->sw->on( 'ManagerStop', array( $this, 'onManagerStop' ) );
        $this->sw->on( 'WorkerStart', array( $this, 'onWorkerStart' ) );
        $this->sw->on( 'Connect', array( $this->protocol, 'onConnect' ) );
        $this->sw->on( 'Receive', array( $this->protocol, 'onReceive' ) );
        $this->sw->on( 'Close', array( $this->protocol, 'onClose' ) );
        $this->sw->on( 'WorkerStop', array( $this->protocol, 'onShutdown' ) );
        if ( is_callable( array( $this->protocol, 'onTimer' ) ) ) {
            $this->sw->on( 'Timer', array( $this->protocol, 'onTimer' ) );
        }
        if ( is_callable( array( $this->protocol, 'onTask' ) ) ) {
            $this->sw->on( 'Task', array( $this->protocol, 'onTask' ) );
            $this->sw->on( 'Finish', array( $this->protocol, 'onFinish' ) );
        }
        $this->sw->start();
    }

    /**
     * 解析运行命令
     * php yourfile.php start | stop | restart | reload | status
     * @return void
     */
    public function parseCommand()
    {
        // 检查运行命令的参数
        global $argv;
        $start_file = $argv[ 0 ];
        if ( !isset( $argv[ 1 ] ) ) {
            echo Console::alert("Usage: php ".$argv[0]." {start|stop|restart|reload|status}")."\n";
            exit();
        }

        // 命令
        $command = trim( $argv[ 1 ] );

        // 子命令，目前只支持-d
        $command2 = isset( $argv[ 2 ] ) ? $argv[ 2 ] : '';

//未实现        self::log("Workerman[$start_file] $command $mode");

        // 检查主进程是否在运行
        $master_pid = @file_get_contents( $this->pid_file );
        $master_is_alive = $master_pid && @posix_kill( $master_pid, 0 );
        if ( $master_is_alive ) {
            if ( $command === 'start' ) {
                echo Console::alert("WorkerOnSwoole [$start_file] is already running")."\n";
                exit( 0 );
//                self::log ("Workerman[$start_file] is running");
            }
        } elseif ( $command !== 'start' && $command !== 'restart' ) {
            echo Console::error("WorkerOnSwoole [$start_file] not run")."\n";
//            self::log ("Workerman[$start_file] not run");
        }

        // 根据命令做相应处理
        switch ( $command ) {
            // 启动 workerman
            case 'start':
                if ( $command2 === '-d' ) {
                    $this->daemonize();
                }
                break;
            // 显示 workerman 运行状态 - 暂时没想到办法起作用
            case 'status':
//                var_dump($master_pid);
                // 尝试删除统计文件，避免脏数据
//                if(is_file(self::$_statisticsFile))
//                {
//                    @unlink(self::$_statisticsFile);
//                }
//                // 向主进程发送 SIGUSR2 信号 ，然后主进程会向所有子进程发送 SIGUSR2 信号
                //所有进程收到 SIGUSR2 信号后会向 $_statisticsFile 写入自己的状态
//                posix_kill ($master_pid, SIGUSR2);
//                // 睡眠100毫秒，等待子进程将自己的状态写入$_statisticsFile指定的文件
//                usleep (100000);
//                // 展示状态
//                readfile (self::$_statisticsFile);
                exit( 0 );
            // 重启 workerman
            case 'restart':
                // 停止 workeran
            case 'stop':
                echo Console::info("Server is shutdown now!")."\n";
                posix_kill( $master_pid, SIGTERM );
                posix_kill( $master_pid +1, SIGTERM );
                sleep( 5 );
                posix_kill( $master_pid, 9 );// 如果是不是守护进程,这里最后发送个强制停止的信号.
                if ( $command == 'stop' ) {
                    exit();
                    // 如果是restart ,那么继续执行后续逻辑,会再起一个进程
                }

                echo Console::info("Server is restart now! ")."\n";
                break;
            // 平滑重启 workerman
            case 'reload':
                echo Console::info("Server worker reload now! ")."\n";
                posix_kill( $master_pid, SIGUSR1 );//重启worker进程,可以测试装载功能
                exit;
            // 未知命令
            default :
                echo Console::alert("Usage: php ".$argv[0]." {start|stop|restart|reload|status}")."\n";
                exit();
        }
    }

    function shutdown()
    {
        return $this->sw->shutdown();
    }

    function close( $client_id )
    {
        return $this->sw->close( $client_id );
    }

    function addListener( $host, $port, $type )
    {
        return $this->sw->addlistener( $host, $port, $type );
    }

    function send( $client_id, $data )
    {
        return $this->sw->send( $client_id, $data );
    }


    /**
     * 展示启动界面
     * @return void
     */
    protected function displayUI()
    {
        $listen = $this->socketName;
        global $argv;


        $ui = Console::table()->setSlice('  ')->td2('<bg=lightBlue>WorkerOnSwoole</>','center')->br('-')
            ->td("WorkerServer version : ". self::VERSION )->td("PHP version : ". PHP_VERSION)->br()
            ->td("Swoole version : " . SWOOLE_VERSION)->td()->br()
            ->td("Server listen :  {$listen}")->td()->br()
            ->td("Server file :  {$argv[0]} ")->td()->br('-')
            ->td2("<bg=lightBlue>WORKERS</>",'center')->br('-');

        if ( isset( $this->runtimeSetting[ 'daemonize' ] ) && $this->runtimeSetting[ 'daemonize' ] == 1 ) {
            $start_file = $argv[ 0 ];
            $ui->td2("Input \"php $start_file stop\" to quit. Start success.\n")->br(' ');
        } else {
            $ui->td2("Press Ctrl-C to quit.")->br(' ');
        }

        echo $ui;
    }

}