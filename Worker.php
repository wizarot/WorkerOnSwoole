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
     * socket名称，包括应用层协议+ip+端口号，在初始化worker时设置
     * 值类似 http://0.0.0.0:80
     * @var string
     */
    protected $_socketName = '';
    

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
        // 尝试以守护进程模式运行
        self::daemonize();
        // 初始化所有worker实例，主要是监听端口
        self::initWorkers();
        //  初始化所有信号处理函数
        self::installSignal();
        // 保存主进程pid
        self::saveMasterPid();
        // 创建子进程（worker进程）并运行
        self::forkWorkers();
        // 展示启动界面
        self::displayUI();
        // 尝试重定向标准输入输出
        self::resetStd();
        // 监控所有子进程（worker进程）
        self::monitorWorkers();
    }

    /**
     * worker构造函数
     *
     * @param string $socket_name
     * @param array  $context_option
     */
    public function __construct($socket_name = '', $context_option = array())
    {
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
            self::$pidFile = sys_get_temp_dir()."/workerman.".str_replace('/', '_', self::$_startFile).".pid";
        }
        // 没有设置日志文件，则生成一个默认值
        if(empty(self::$logFile))
        {
            self::$logFile = __DIR__ . '/../workerman.log';
        }
        // 标记状态为启动中
        self::$_status = self::STATUS_STARTING;
        // 启动时间戳
        self::$_globalStatistics['start_timestamp'] = time();
        // 设置status文件位置
        self::$_statisticsFile = sys_get_temp_dir().'/workerman.status';
        // 尝试设置进程名称（需要php>=5.5或者安装了proctitle扩展）
        self::setProcessTitle('WorkerMan: master process  start_file=' . self::$_startFile);

        // 初始化定时器
        Timer::init();
    }
    

}
