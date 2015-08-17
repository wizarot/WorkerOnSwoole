<?php

namespace WorkerOnSwoole;

use \Swoole;
use \Exception;

/**
 * Worker 类
 * 是一个容器，用于监听端口，维持客户端连接
 */
class Worker
{
    /**
     * 版本号
     * @var string
     */
    const VERSION = '0.0.1';

    /**
     * 状态 启动中
     * @var int
     */
    const STATUS_STARTING = 1;

    /**
     * 状态 运行中
     * @var int
     */
    const STATUS_RUNNING = 2;

    /**
     * 状态 停止
     * @var int
     */
    const STATUS_SHUTDOWN = 4;

    /**
     * 状态 平滑重启中
     * @var int
     */
    const STATUS_RELOADING = 8;

    /**
     * 给子进程发送重启命令 KILL_WORKER_TIMER_TIME 秒后
     * 如果对应进程仍然未重启则强行杀死
     * @var int
     */
    const KILL_WORKER_TIMER_TIME = 1;

    /**
     * 默认的backlog，即内核中用于存放未被进程认领（accept）的连接队列长度
     * @var int
     */
    const DEFAUL_BACKLOG = 1024;

    /**
     * udp最大包长
     * @var int
     */
    const MAX_UDP_PACKEG_SIZE = 65535;

    /**
     * swoole生成的服务器实际对象
     * @var obj
     */
    public $server;

    /**
     * socket名称，包括应用层协议+ip+端口号，在初始化worker时设置
     * 值类似 http://0.0.0.0:80
     * @var string
     */
    public static $socketName = '';

    /**
     * 当前worker实例初始化目录位置，用于设置应用自动加载的根目录
     * @var string
     */
    protected $_appInitPath = '';

    /**
     * pid文件的路径及名称
     * 例如 Worker::$pidFile = '/tmp/workerOnSwoole.pid';
     * 注意 此属性一般不必手动设置，默认会放到php临时目录中
     * @var string
     */
    public static $pidFile = '';

    /**
     * pid 主进程id
     * @var string
     */
    public static $_masterPid = '';

    /**
     * 启动的全局入口文件
     * 例如 php start.php start ，则入口文件为start.php
     * @var string
     */
    protected static $_startFile = '';

    /**
     * 日志目录，默认在WOS根目录下，与Applications同级
     * 可以手动设置
     * 例如 Worker::$logFile = '/tmp/workerOnSwoole.log';
     * @var mixed
     */
    public static $logFile = '';

    /**
     * 是否以守护进程的方式运行。运行start时加上-d参数会自动以守护进程方式运行
     * 例如 php start.php start -d
     * @var bool
     */
    public static $daemonize = FALSE;

    /**
     * 当前worker状态
     * @var int
     */
    protected static $_status = self::STATUS_STARTING;

    /**
     * 全局统计数据，用于在运行 status 命令时展示
     * 统计的内容包括 WOS启动的时间戳及每组worker进程的退出次数及退出状态码
     * @var array
     */
    protected static $_globalStatistics
        = array(
            'start_timestamp'  => 0,
            'worker_exit_info' => array(),
        );

    /**
     * 运行 status 命令时用于保存结果的文件名
     * @var string
     */
    protected static $_statisticsFile = '';

    /**
     * 设置worker number 处理器数量
     * @var int
     */
    public $count = 1;

    /*
     * 生成的回调方法
     */
    public $callbacks = array();

    // 服务器类型
    public $type;

    public $mode = SWOOLE_PROCESS;


    /**
     * 运行所有worker实例
     * @return void
     */
    public function runAll()
    {
        // 初始化环境变量
        self::init();
        // 解析命令
        self::parseCommand();
        //根据配置选择并初始化worker对象
        $this->initWorkers();
        //设置运行参数
        $this->setParams();
        //设置回调
        $this->setCallbacks();
        // 展示启动界面
        self::displayUI();
        //启动服务器
        self::$_globalStatistics[ 'start_timestamp' ] = time();
        $this->server->start();

//
//        // 尝试重定向标准输入输出
//        self::resetStd();
//        // 监控所有子进程（worker进程）
//        self::monitorWorkers();

        //
    }

    /**
     * worker构造函数
     *
     * @param string $socket_name
     * @param array  $context_option
     */
    public function __construct( $socket_name = '', $context_option = array() )
    {
        // 获得实例化文件路径，用于自动加载设置根目录
        $backrace = debug_backtrace();
        $this->_appInitPath = dirname( $backrace[ 0 ][ 'file' ] );

        // 设置socket上下文
        if ( $socket_name ) {
            self::$socketName = $socket_name;

//            if(!isset($context_option['socket']['backlog']))
//            {
//                $context_option['socket']['backlog'] = self::DEFAUL_BACKLOG;
//            }
//            $this->_context = stream_context_create($context_option);
        }


    }

    /**
     * 初始化一些环境变量
     * @return void
     */
    public static function init()
    {
        // 如果没设置$pidFile，则生成默认值
        if ( empty( self::$pidFile ) ) {
            $backtrace = debug_backtrace();
            self::$_startFile = $backtrace[ count( $backtrace ) - 1 ][ 'file' ];
            self::$pidFile = sys_get_temp_dir() . "/workerOnSwoole." . str_replace( '/', '_', self::$_startFile ) . ".pid";
        }
        // 没有设置日志文件，则生成一个默认值
        if ( empty( self::$logFile ) ) {
            self::$logFile = __DIR__ . '/tmp/workeOnSwoole.log';
        }
        // 标记状态为启动中
        self::$_status = self::STATUS_STARTING;
        // 启动时间戳
        self::$_globalStatistics[ 'start_timestamp' ] = time();
        // 设置status文件位置
        self::$_statisticsFile = sys_get_temp_dir() . '/workerOnSwoole.status';

        // 尝试设置进程名称（需要php>=5.5或者安装了proctitle扩展）
//        self::setProcessTitle('WorkerMan: master process  start_file=' . self::$_startFile);

    }


    /**
     * 解析运行命令
     * php yourfile.php start | stop | restart | reload | status
     * @return void
     */
    public static function parseCommand()
    {
        // 检查运行命令的参数
        global $argv;
        $start_file = $argv[ 0 ];
        if ( !isset( $argv[ 1 ] ) ) {
            exit( "Usage: php yourfile.php {start|stop|restart|reload|status}\n" );
        }

        // 命令
        $command = trim( $argv[ 1 ] );

        // 子命令，目前只支持-d
        $command2 = isset( $argv[ 2 ] ) ? $argv[ 2 ] : '';

//未实现        self::log("Workerman[$start_file] $command $mode");

        // 检查主进程是否在运行
        $master_pid = @file_get_contents( self::$pidFile );
        $master_is_alive = $master_pid && @posix_kill( $master_pid, 0 );
        if ( $master_is_alive ) {
            if ( $command === 'start' ) {
                echo "WOS[$start_file] is running";
                exit( 0 );
//                self::log ("Workerman[$start_file] is running");
            }
        } elseif ( $command !== 'start' && $command !== 'restart' ) {
            echo "WOS[$start_file] not run";
//            self::log ("Workerman[$start_file] not run");
        }

        // 根据命令做相应处理
        switch ( $command ) {
            // 启动 workerman
            case 'start':
                if ( $command2 === '-d' ) {
                    Worker::$daemonize = TRUE;
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
                echo "Server is shutdown now!\n";
                posix_kill( $master_pid, SIGTERM );
                sleep( 5 );
                posix_kill( $master_pid, 9 );// 如果是不是守护进程,这里最后发送个强制停止的信号.
                if ( $command == 'stop' ) {
                    exit();
                    // 如果是restart ,那么继续执行后续逻辑,会再起一个进程
                }

                echo "Server is restart now! \n";
                break;
            // 平滑重启 workerman
            case 'reload':
                echo "Server worker reload now! \n";
                posix_kill( $master_pid, SIGUSR1 );//重启worker进程,可以测试装载功能
//                posix_kill ($master_pid, SIGUSR2);//1.7.7+仅重启task_worker进程
                exit;
            // 未知命令
            default :
                exit( "Usage: php yourfile.php {start|stop|restart|reload|status}\n" );
        }
    }

    /**
     * 根据配置初始化worker对象
     * @throws Exception
     */
    public function initWorkers()
    {

        if ( !self::$socketName ) {
            return;
        }
        // 获得应用层通讯协议以及监听的地址
        $url = parse_url( self::$socketName );

        if ( !isset( $url[ 'scheme' ] ) ) {
            throw new Exception( "scheme not exist" );
        }
        if ( !isset( $url[ 'host' ] ) ) {
            throw new Exception( "host not exist" );
        }
        if ( !isset( $url[ 'port' ] ) ) {
            throw new Exception( "port not exist" );
        }

        // 如果有指定应用层协议，则检查对应的协议类是否存在
        if ( $url ) {
            switch ( strtolower( $url[ 'scheme' ] ) ) {
                case 'ws':
                case 'wss':
                    if ( $this->server ) {
                        if ( $this->type == "ws" || $this->type == "wss" ) {
                            $this->server->addListener( $url[ 'host' ], $url[ 'port' ] );
                        } else {
                            throw new \Exception( $this->type . " server didn't support add " . $url[ 'scheme' ] . " scheme" );
                        }
                    } else {
                        $this->server = new \swoole_websocket_server( $url[ 'host' ], $url[ 'port' ], $this->mode );
                        $this->type = strtolower( $url[ 'scheme' ] );
                    }
                    break;
                case 'http':
                case 'https':
                    if ( $this->server ) {
                        if ( $this->type == "http" || $this->type == "https" ) {
                            $this->server->addListener( $url[ 'host' ], $url[ 'port' ] );
                        } else {
                            throw new \Exception( $this->type . " server didn't support add " . $url[ 'scheme' ] . " scheme" );
                        }
                    } else {
                        $this->server = new \swoole_http_server( $url[ 'host' ], $url[ 'port' ], $this->mode );
                        $this->type = strtolower( $url[ 'scheme' ] );
                    }
                    break;
                case 'tcp':
                case 'tcp4':
                case 'tcp6':
                case 'unix':
                    if ( $this->server ) {
                        if ( $this->type == "socket" ) {
                            $this->server->addListener( $url );
                        } else {
                            throw new \Exception( $this->type . " server didn't support add " . $url[ 'scheme' ] . " scheme" );
                        }
                    } else {
                        $this->server = new \Hprose\Swoole\Socket\Server( $url, $this->mode );
                        $this->type = "socket";
                    }
                    break;
                default:
                    throw new \Exception( "Only support ws, wss, http, https, tcp, tcp4, tcp6 or unix scheme" );
            }
        } else {
            throw new \Exception( "Can't parse this url: " . $url );
        }


    }

    /**
     * 根据配置项目,对服务器对象进行设置运行参数
     */
    public function setParams()
    {
        // 很多,回头都写在这里,方便开发者掉用
        $config = array(
            'worker_num' => $this->count,
            'daemonize'  => self::$daemonize,
            'chroot'     => '/tmp/root',
        );

        $this->server->set( $config );
    }

    /**
     * 这里通过魔术方法来处理callback的设置
     *
     **/
    function __set( $property, $value )
    {
        if ( strpos( $property, 'on' ) !== FALSE ) {
            // 处理绑定的on事件
            $name = strtolower( ltrim( $property, 'on' ) );
            $this->callbacks[ $name ] = $value;
        }

    }

    /**
     * 设定之前注册的回调函数
     */
    function setCallbacks()
    {
        // 注册下基础的callback,如果另外又改写了,那么就覆盖掉默认的

        $this->server->on( 'start', array( $this, 'onStart' ) );

        $this->server->on( 'shutdown', array( $this, 'onShutDown' ) );

        $this->server->on( 'workerstart', array( $this, 'onWorkerStart' ) );

        $this->server->on( 'workerstop', array( $this, 'onWorkerStop' ) );

        //加载外部自定义事件
        foreach ( $this->callbacks as $env => $func ) {
            $this->server->on( $env, $func );
        }

        unset( $this->callbacks );//注册后就没用了.释放内存
    }

    //主进程启动事件,和worker进程启动,没先后顺序
    function onStart( $server )
    {
        Worker::$_masterPid = $server->master_pid;
        if ( FALSE === @file_put_contents( Worker::$pidFile, Worker::$_masterPid ) ) {
            throw new Exception( 'can not save pid to ' . Worker::$pidFile );
        }

        echo "master  is running at : {$server->master_pid} \n";
        echo "manager is running at : {$server->manager_pid} \n";
    }

    //Server结束时发生
    //强制kill进程不会回调onShutdown，如kill -9
    //需要使用kill -15来发送SIGTREM信号到主进程才能按照正常的流程终止
    function onShutDown( $server )
    {
        @unlink( self::$pidFile );
        echo "master is stoped.. \n";
    }

    function onWorkerStart( $server, $worker_id )
    {
        // 据文档说,如果reload,那么只有在这里require的文件reload才能重新加载.
        // 加载所有Applications/*/start.php，以便启动所有服务
        //    foreach(glob(__DIR__.'/Applications/*/start*.php') as $start_file)
        //    {
        //        require_once $start_file;
        //    }
        global $argv;// 全局变量中包含参数
        echo "worker - {$argv[0]} is running at: {$server->worker_pid} \n";
    }

    function onWorkerStop( $server, $worker_id )
    {

        echo "worker - {$server->worker_pid} is stoped..  \n";
    }

    /**
     * 展示启动界面
     * @return void
     */
    protected static function displayUI()
    {
        echo "\033[1A\n\033[K-----------------------\033[47;30m WORKERONSWOOLE \033[0m-----------------------------\n\033[0m";
        echo 'Workerman version:', Worker::VERSION, "          PHP version:", PHP_VERSION, "\n";
        echo 'Swoole version:', swoole_version(), "\n";
        $listen = self::$socketName;
        echo "Server listen  {$listen}\n";

        if ( self::$daemonize ) {
            global $argv;
            $start_file = $argv[ 0 ];
            echo "Input \"php $start_file stop\" to quit. Start success.\n";
        } else {
            echo "Press Ctrl-C to quit.\n";
        }

        echo "------------------------\033[47;30m WORKERS \033[0m-------------------------------\n";
    }


}
