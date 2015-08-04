<?php

namespace WorkerOnSwoole;

//use \Workerman\Events\Libevent;
//use \Workerman\Events\Select;
//use \Workerman\Events\EventInterface;
//use \Workerman\Connection\ConnectionInterface;
//use \Workerman\Connection\TcpConnection;
//use \Workerman\Connection\UdpConnection;
//use \Workerman\Lib\Timer;
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
     * socket名称，包括应用层协议+ip+端口号，在初始化worker时设置
     * 值类似 http://0.0.0.0:80
     * @var string
     */
    protected $_socketName = '';

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
     * 当前worker状态
     * @var int
     */
    protected static $_status = self::STATUS_STARTING;

    /**
     * 全局统计数据，用于在运行 status 命令时展示
     * 统计的内容包括 WOS启动的时间戳及每组worker进程的退出次数及退出状态码
     * @var array
     */
    protected static $_globalStatistics = array(
        'start_timestamp' => 0,
        'worker_exit_info' => array()
    );

    /**
     * 运行 status 命令时用于保存结果的文件名
     * @var string
     */
    protected static $_statisticsFile = '';



    /**
     * 运行所有worker实例
     * @return void
     */
    public static function runAll()
    {
        // 初始化环境变量
        self::init();
        // 解析命令
        self::parseCommand();
//        // 尝试以守护进程模式运行
//        self::daemonize();
//        // 初始化所有worker实例，主要是监听端口
//        self::initWorkers();
//        //  初始化所有信号处理函数
//        self::installSignal();
//        // 保存主进程pid
//        self::saveMasterPid();
//        // 创建子进程（worker进程）并运行
//        self::forkWorkers();
//        // 展示启动界面
//        self::displayUI();
//        // 尝试重定向标准输入输出
//        self::resetStd();
//        // 监控所有子进程（worker进程）
//        self::monitorWorkers();
    }

    /**
     * worker构造函数
     *
     * @param string $socket_name
     * @param array  $context_option
     */
    public function __construct($socket_name = '', $context_option = array())
    {
        // 获得实例化文件路径，用于自动加载设置根目录
        $backrace = debug_backtrace();
        $this->_appInitPath = dirname($backrace[0]['file']);

        // 设置socket上下文
        if($socket_name)
        {
            $this->_socketName = $socket_name;
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
        if(empty(self::$pidFile))
        {
            $backtrace = debug_backtrace();
            self::$_startFile = $backtrace[count($backtrace)-1]['file'];
            self::$pidFile = sys_get_temp_dir()."/workerOnSwoole.".str_replace('/', '_', self::$_startFile).".pid";
        }
        // 没有设置日志文件，则生成一个默认值
        if(empty(self::$logFile))
        {
            self::$logFile = __DIR__ . '/tmp/workeOnSwoole.log';
        }
        // 标记状态为启动中
        self::$_status = self::STATUS_STARTING;
        // 启动时间戳
        self::$_globalStatistics['start_timestamp'] = time();
        // 设置status文件位置
        self::$_statisticsFile = sys_get_temp_dir().'/workerOnSwoole.status';
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
        $start_file = $argv[0];
        if(!isset($argv[1]))
        {
            exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
        }

        // 命令
        $command = trim($argv[1]);

        // 子命令，目前只支持-d
        $command2 = isset($argv[2]) ? $argv[2] : '';

        // 记录日志
        $mode = '';
        if($command === 'start')
        {
            if($command2 === '-d')
            {
                $mode = 'in DAEMON mode';
            }
            else
            {
                $mode = 'in DEBUG mode';
            }
        }

//未实现        self::log("Workerman[$start_file] $command $mode");

        // 检查主进程是否在运行
        $master_pid = @file_get_contents(self::$pidFile);
        $master_is_alive = $master_pid && @posix_kill($master_pid, 0);
        if($master_is_alive)
        {
            if($command === 'start')
            {
                self::log("Workerman[$start_file] is running");
            }
        }
        elseif($command !== 'start' && $command !== 'restart')
        {
            self::log("Workerman[$start_file] not run");
        }

        // 根据命令做相应处理
        switch($command)
        {
            // 启动 workerman
            case 'start':
                if($command2 === '-d')
                {
                    Worker::$daemonize = true;
                }
                break;
            /*// 显示 workerman 运行状态
            case 'status':
                // 尝试删除统计文件，避免脏数据
                if(is_file(self::$_statisticsFile))
                {
                    @unlink(self::$_statisticsFile);
                }
                // 向主进程发送 SIGUSR2 信号 ，然后主进程会向所有子进程发送 SIGUSR2 信号
                // 所有进程收到 SIGUSR2 信号后会向 $_statisticsFile 写入自己的状态
                posix_kill($master_pid, SIGUSR2);
                // 睡眠100毫秒，等待子进程将自己的状态写入$_statisticsFile指定的文件
                usleep(100000);
                // 展示状态
                readfile(self::$_statisticsFile);
                exit(0);
            // 重启 workerman
            case 'restart':
                // 停止 workeran
            case 'stop':
                self::log("Workerman[$start_file] is stoping ...");
                // 想主进程发送SIGINT信号，主进程会向所有子进程发送SIGINT信号
                $master_pid && posix_kill($master_pid, SIGINT);
                // 如果 $timeout 秒后主进程没有退出则展示失败界面
                $timeout = 5;
                $start_time = time();
                while(1)
                {
                    // 检查主进程是否存活
                    $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                    if($master_is_alive)
                    {
                        // 检查是否超过$timeout时间
                        if(time() - $start_time >= $timeout)
                        {
                            self::log("Workerman[$start_file] stop fail");
                            exit;
                        }
                        usleep(10000);
                        continue;
                    }
                    self::log("Workerman[$start_file] stop success");
                    // 是restart命令
                    if($command === 'stop')
                    {
                        exit(0);
                    }
                    // -d 说明是以守护进程的方式启动
                    if($command2 === '-d')
                    {
                        Worker::$daemonize = true;
                    }
                    break;
                }
                break;
            // 平滑重启 workerman
            case 'reload':
                posix_kill($master_pid, SIGUSR1);
                self::log("Workerman[$start_file] reload");
                exit;*/
            // 未知命令
            default :
                exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
        }
    }



}
