<?php

/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/27
 * Time: 下午5:21
 */


namespace WorkerOnSwoole;

use WorkerOnSwoole\lib\Console;

/**
 * Class ServerContainer
 * Server服务的装载容器
 * @package WorkerOnSwoole
 */
class ServerContainer
{
    // 实际swoole的server对象(实际是传入的protocol对象)
    public $server;
    // 记录下监听的IP端口和业务
    public static $listen = array();
    // 用户自定义的Event事件-实际的逻辑
    public $user_event;
    // 临时注册事件,用来记录事件名称的变量
    public $events;
    // 服务器类型(这里多少有点问题,因为listen尝试接收多个)
    public $type;
    // 业务配置信息
    public $config = array('server' => array());
    // 服务容器的版本号
    const  VERSION = '0.0.3';
    // pid 存储文件
    protected $pid_file;
    // 业务主入口文件名
    protected $start_file;

    static function listen($socket_name = '', $config = array())
    {
        if (class_exists('\\swoole_server', FALSE)) {
            if (!isset($this)) {
                $obj = new self($socket_name, $config);
            } else {
                $obj = $this;// 实际可能没什么用,因为根本没实现多个监听服务器
            }

            ServerContainer::$listen[] = $socket_name;

            return $obj;

        } else {
            echo Console::render("<bg=red>Error must install php swoole extended model</>") . "\n";
            die;
        }
    }

    // 目前这个容器只能运行一个swoole的服务器对象,因此多了也没用~
    function __construct($socket_name = '', $config = array('server' => array()))
    {
        // 这类给个默认配置,方便查询使用,也算个例子
        $default_server_config = array(
            'reactor_num' => '',
            'worker_num' => '',
            'max_request' => 500,
            'max_conn' => '',
            'task_worker_num' => '',
            'task_ipc_mode' => '',
            'task_max_request' => '',
            'task_tmpdir' => '',
            'dispatch_mode' => '',
            'message_queue_key' => '',
            'daemonize' => 0,
            'backlog' => '',
            'log_file' => '',
            'heartbeat_check_interval' => '',
            'heartbeat_idle_time' => '',
            'open_eof_check' => '',
            'open_eof_split' => '',
            'package_eof' => '',
            'open_length_check' => '',
            'package_length_type' => '',
            'package_max_length' => '',
            'open_cpu_affinity' => '',
            'cpu_affinity_ignore' => '',
            'open_tcp_nodelay' => '',
            'tcp_defer_accept' => '',
            'ssl_cert_file' => '',
            'user' => '',
            'group' => '',
            'chroot' => '/data/server/',
            'pipe_buffer_size' => '',
            'buffer_output_size' => '',
            'enable_unsafe_event' => '',
            'discard_timeout_request' => '',
        );
//        var_dump($config);
        if (empty($config)) {
            $server_config = $default_server_config;
        } else {
            $server_config = array_merge($default_server_config, $config['server']);
        }
        $merge_config = array(
            'server' => $server_config,
        );
        $this->config = $merge_config;
//        var_dump($this->config);

        if (!isset($this->config['server']['pid_file'])) {
            $backtrace = debug_backtrace();
            $this->start_file = $backtrace[count($backtrace) - 1]['file'];
            $this->config['server']['pid_file'] = sys_get_temp_dir() . "/WOS_" . str_replace('/', '_', $this->start_file) . ".pid";
        }

        $this->pid_file = $this->config['server']['pid_file'];


    }

    /**
     * 这里通过魔术方法来处理callback的设置
     *
     **/
    function __set($property, $value)
    {
        if (strpos($property, 'on') !== FALSE) {
            // 处理绑定的on事件
            $name = strtolower(ltrim($property, 'on'));
            $this->events[$name] = $value;
        }
    }

    function run()
    {
        // 首先处理命令
        $this->parseCommand();
        //根据配置选择并初始化worker对象
        foreach (self::$listen as $listen) {
            $this->initWorkers($listen);
        }

        //设置回调
        $this->setCallbacks();


        $this->server->set($this->config['server']);// 仅加载针对server的配置


        //显示命令行状态
        $this->displayUI();

        $this->server->start();
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
        $start_file = $argv[0];
        if (!isset($argv[1])) {
            echo Console::alert("Usage: php " . $argv[0] . " {start|stop|restart|reload|status}") . "\n";
            exit();
        }

        // 命令
        $command = trim($argv[1]);

        // 子命令，目前只支持-d
        $command2 = isset($argv[2]) ? $argv[2] : '';

//未实现        self::log("Workerman[$start_file] $command $mode");

        // 检查主进程是否在运行
        $master_pid = @file_get_contents($this->pid_file);
        $master_is_alive = $master_pid && @posix_kill($master_pid, 0);
        if ($master_is_alive) {
            if ($command === 'start') {
                echo Console::alert("WorkerOnSwoole [$start_file] is already running") . "\n";
                exit(0);
//                self::log ("Workerman[$start_file] is running");
            }
        } elseif ($command !== 'start' && $command !== 'restart') {
            echo Console::error("WorkerOnSwoole [$start_file] not run") . "\n";
//            self::log ("Workerman[$start_file] not run");
        }

        // 根据命令做相应处理
        switch ($command) {
            // 启动 workerman
            case 'start':
                if ($command2 === '-d') {
                    $this->config['server']['daemonize'] = 1;
                }
                break;
            // 显示 workerman 运行状态 - 暂时没想到办法起作用
            case 'status':
                // 装载的信号,发送看下回应

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
                exit(0);
            // 重启 workerman
            case 'restart':
                // 停止 workeran
            case 'stop':
                echo Console::info("Server is shutdown now!") . "\n";
                posix_kill($master_pid, SIGTERM);
                posix_kill($master_pid + 1, SIGTERM);
                sleep(5);
                posix_kill($master_pid, 9);// 如果是不是守护进程,这里最后发送个强制停止的信号.
                if ($command == 'stop') {
                    exit();
                    // 如果是restart ,那么继续执行后续逻辑,会再起一个进程
                }

                echo Console::info("Server is restart now! ") . "\n";
                break;
            // 平滑重启 workerman
            case 'reload':
                echo Console::info("Server worker reload now! ") . "\n";
                posix_kill($master_pid, SIGUSR1);//重启worker进程,可以测试装载功能
                exit;
            // 未知命令
            default :
                echo Console::alert("Usage: php " . $argv[0] . " {start|stop|restart|reload|status}") . "\n";
                exit();
        }
    }

    /**
     * 根据配置初始化worker对象
     * @throws Exception
     */
    public function initWorkers($listen)
    {

        // 获得应用层通讯协议以及监听的地址
        $url = parse_url($listen);

        if (!isset($url['scheme'])) {
            throw new Exception("scheme not exist");
        }
        if (!isset($url['host'])) {
            throw new Exception("host not exist");
        }
        if (!isset($url['port'])) {
            throw new Exception("port not exist");
        }

        // 如果有指定应用层协议，则检查对应的协议类是否存在
        if ($url) {
            switch (strtolower($url['scheme'])) {
                case 'ws':
                case 'wss':
                    if ($this->server) {
                        if ($this->type == "ws" || $this->type == "wss") {
                            $this->server->addListener($url['host'], $url['port']);
                        } else {
                            throw new \Exception($this->type . " server didn't support add " . $url['scheme'] . " scheme");
                        }
                    } else {
                        $this->server = new \swoole_websocket_server($url['host'], $url['port'], SWOOLE_PROCESS);
                        $this->type = strtolower($url['scheme']);
                    }
                    break;
                case 'http':
                case 'https':
                    $this->server->addListener($url['host'], $url['port'], SWOOLE_TCP);

                    /*if ( $this->server ) {
                        if ( $this->type == "http" || $this->type == "https" ) {
                            $this->server->addListener( $url[ 'host' ], $url[ 'port' ] );
                        } else {
                            throw new \Exception( $this->type . " server didn't support add " . $url[ 'scheme' ] . " scheme" );
                        }
                    } else {
                        $this->server = new \swoole_http_server( $url[ 'host' ], $url[ 'port' ], SWOOLE_PROCESS );
                        $this->type = strtolower( $url[ 'scheme' ] );
                    }*/
                    break;
                case 'tcp':
                case 'tcp4':
                    $type = SWOOLE_SOCK_TCP;
                    break;
                case 'tcp6':
                    $type = SWOOLE_SOCK_TCP6;
                    break;
                case 'udp':
                case 'udp4':
                    $type = SWOOLE_SOCK_UDP;
                    break;
                case 'udp6':
                    $type = SWOOLE_SOCK_UDP6;
//                case 'unix':

                    break;
                default:
                    throw new \Exception("Only support ws, wss, http, https, tcp, udp or unix scheme");
            }
            // 统一处理socket
            if (isset($type)) {
                if ($this->server) {
                    $this->server->addListener($url['host'], $url['port'], $type);

                    /* if ( $this->type == "socket" ) {
                         $this->server->addListener( $url[ 'host' ], $url[ 'port' ], $type );
                     } else {
                         throw new \Exception( $this->type . " server didn't support add " . $url[ 'scheme' ] . " scheme" );
                     }*/
                } else {
                    $this->server = new \swoole_server($url['host'], $url['port'], $type);
                    $this->type = "socket";
                }
            }
        } else {
            throw new \Exception("Can't parse this url: " . $url);
        }


    }

    /**
     * 导入用户自定义的事件处理对象
     * @param $user_event
     */
    public function setEvent($user_event)
    {
        $methods = get_class_methods($user_event);
        // 没办法了.既然使用 __call动态加载不行,那么暂时能想到的只能是遍历event对象中所有on开头的方法,都注册过来!
        foreach ($methods as $event) {
            if (strpos($event, 'on') === 0) {
                // 还是直接处理吧....
                $name = strtolower(ltrim($event, 'on'));
//                var_dump($user_event->on);die;
//                $this->server->on( $name , array( $user_event, $event ) );
                $this->events[$name] = array($user_event, $event);
            }
        }

        $this->user_event = $user_event;
    }

    // 设定回调和注入用户自定义Events
    function setCallbacks()
    {
        $version = explode('.', SWOOLE_VERSION);
        //1.7.0
        if ($version[1] >= 7) {
            $this->server->on('ManagerStart', function ($serv) {
                global $argv;
//                Console::setProcessName('php ' . $argv[0] . ': manager');
            });
        }
        $this->server->on('Start', array($this, 'onMasterStart'));
        $this->server->on('Shutdown', array($this, 'onMasterStop'));
        $this->server->on('ManagerStop', array($this, 'onManagerStop'));
        $this->server->on('WorkerStart', array($this, 'onWorkerStart'));


        //加载外部自定义事件
        foreach ($this->events as $env => $func) {
            $this->server->on($env, $func);
        }

    }

    /**
     * 展示启动界面
     * @return void
     */
    protected function displayUI()
    {
        $listen = self::$listen;
        global $argv;


        $ui = Console::table()->setSlice('  ')->td4('<bg=lightBlue>WorkerOnSwoole</>', 'center')->br('-')
            ->td("WorkerServer version:", 'right')->td(self::VERSION)->td("PHP version:", 'right')->td(PHP_VERSION)->br()
            ->td("Swoole version:", 'right')->td(SWOOLE_VERSION, 'left')->td2('')->br();
        foreach ($listen as $value) {
            $ui->td("Server listen:", 'right')->td($value);
        }

        $ui->td("Server file:", 'right')->td($argv[0])->td2('')->br('-')
            ->td4("<bg=lightBlue>WORKERS</>", 'center')->br('-');

        if (isset($this->config['server']['daemonize']) && $this->config['server']['daemonize'] == 1) {
            $start_file = $argv[0];
            $ui->td4("Input \"php $start_file stop\" to quit. Start success.\n")->br(' ');
        } else {
            $ui->td4("Press Ctrl-C to quit.")->br(' ');
        }

        echo $ui;
    }

    //----------------------------------服务器事件-----------------------------------------------------------------------
    function onMasterStart($server)
    {
        global $argv;
//        Console::setProcessName('php ' . $argv[0] . ': master -host=' . $this->host . ' -port=' . $this->port);
        echo Console::success("master  process is running at : {$server->master_pid} ") . "\n";
        echo Console::success("manager process is running at : {$server->manager_pid} ") . "\n";

        if (!empty($this->config['server']['pid_file'])) {
            file_put_contents($this->pid_file, $server->master_pid);
        }
    }


    function onMasterStop($serv)
    {
        if (!empty($this->config['server']['pid_file'])) {
            unlink($this->pid_file);
        }
        echo Console::info("master process is stoped.. ") . "\n";

    }

    function onManagerStop()
    {
        if (!empty($this->config['server']['pid_file'])) {
            unlink($this->pid_file);
        }
        echo Console::info("manager process is stoped.. ") . "\n";

    }

    function onWorkerStart($serv, $worker_id)
    {
//        var_dump($serv);
        global $argv;
        if ($worker_id >= $serv->setting['worker_num']) {
//            Console::setProcessName('php ' . $argv[0] . ': task');
            echo Console::success("task   is running at : {$serv->worker_pid} ") . "\n";
        } else {
//            Console::setProcessName('php ' . $argv[0] . ': worker');
            echo Console::success("worker is running at : {$serv->worker_pid} ") . "\n";
        }
//        if (method_exists($this->events, 'onStart')) {
//            $this->events->onStart($serv, $worker_id);
//        }
    }


}