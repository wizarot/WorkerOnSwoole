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
 * Class Worker
 * Server服务的装载容器
 * @package WorkerOnSwoole
 */
class Worker
{
    // Worker的实例
    public static $instance;
    // 实际swoole的server对象(实际是传入的protocol对象)
    public static $server;
    // 每隔555毫秒向文件中写入服务器状态数据,由于定时器时间值不能重复因此可根据需要微调
    public static $status_interval = 555;
    // 记录下监听的IP端口和业务
    public static $listen = array();
    // web服务器设定域名对应文件夹
    public $web_root = array(
                            '127.0.0.1:8080' => "/tmp/",
                        );
    // 用户自定义的Event事件-实际的逻辑
    public $user_event;
    // 临时注册事件,用来记录事件名称的变量
    public $events;
    // 服务器类型(这里多少有点问题,因为listen尝试接收多个)
    public static $type;
    // 业务配置信息
    public $config = array( 'server' => array() );
    // 服务容器的版本号
    const  VERSION = '0.0.6';
    // pid 存储文件
    protected $pid_file;
    // 业务主入口文件名
    public static $start_file;

    static function listen( $socket_name = '', $config = array() )
    {
        if ( class_exists( '\\swoole_server', FALSE ) ) {
            if ( !isset( Worker::$instance ) ) {
                $obj = new self( $socket_name, $config );
                self::$instance = $obj;
            } else {
                $obj = self::$instance;// 实际可能没什么用,因为根本没实现多个监听服务器
            }

            Worker::$listen[] = $socket_name;

            return $obj;

        } else {
            echo Console::render( "<bg=red>Error must install php swoole extended model</>" ) . "\n";
            die;
        }
    }

    // web服务器,配置域名和业务目录
    function addRoot( $host, $dir )
    {
        $this->web_root[ $host ] = $dir;
        // 任何需要超全局传递的固定参数,都可以这样用.但要注意别互相覆盖了
        global $php;
        $php[ 'web_root' ] = $this->web_root;
    }

    // 目前这个容器只能运行一个swoole的服务器对象,因此多了也没用~
    function __construct( $socket_name = '', $config = array( 'server' => array() ) )
    {
        // 这类给个默认配置,方便查询使用,也算个例子
        $default_server_config = array(
            'reactor_num'     => 2,// reactor线程数,一般设置为CPU核数的1-4倍 ---http://wiki.swoole.com/wiki/page/281.html
            'worker_num'      => 1,//设置启动的worker进程数 ---http://wiki.swoole.com/wiki/page/275.html
            'max_request'     => 500,//设置worker进程的最大任务数 ---http://wiki.swoole.com/wiki/page/300.html
            //            'max_conn'                 => '',//服务器程序，最大允许的连接数,超过服务器会拒绝 ---http://wiki.swoole.com/wiki/page/282.html
            'task_worker_num' => 2,//配置task进程的数量 ---http://wiki.swoole.com/wiki/page/276.html
            //            'task_ipc_mode' => '',//设置task进程与worker进程之间通信的方式。---http://wiki.swoole.com/wiki/page/296.html
            //            'task_max_request' => '',//设置task进程的最大任务数 ---http://wiki.swoole.com/wiki/page/295.html
            //            'task_tmpdir' => '',//设置task的数据临时目录 ---http://wiki.swoole.com/wiki/page/314.html
            //                        'dispatch_mode' => 2,//数据包分发策略。 ---http://wiki.swoole.com/wiki/page/277.html
            //            'message_queue_key' => '',//设置消息队列的KEY ---http://wiki.swoole.com/wiki/page/346.html
            'daemonize'       => 0,//后台守护进程 1开启守护 ---http://wiki.swoole.com/wiki/page/278.html
            //            'backlog' => '',//Listen队列长度 ---http://wiki.swoole.com/wiki/page/279.html
            //            'log_file'                 => '',//默认会打印到屏幕。---http://wiki.swoole.com/wiki/page/280.html
            //            'heartbeat_check_interval' => '',//启用心跳检测 ---http://wiki.swoole.com/wiki/page/283.html
            //            'heartbeat_idle_time'      => '',//表示连接最大允许空闲的时间 ---http://wiki.swoole.com/wiki/page/284.html
            //            'open_eof_check'          => false,//打开EOF检测 ---http://wiki.swoole.com/wiki/page/285.html
            //            'open_eof_split'          => "",//启用EOF自动分包 ---http://wiki.swoole.com/wiki/page/421.html
            //            'package_eof'             => "\r\n",//设置EOF字符串 ---http://wiki.swoole.com/wiki/page/286.html
            //            'open_length_check'       => TRUE,//打开包长检测特性 ---http://wiki.swoole.com/wiki/page/287.html
            //            'package_length_type'     => '',//长度值的类型 ---http://wiki.swoole.com/wiki/page/463.html
            //            'package_max_length'      => '',//设置最大数据包尺寸 ---http://wiki.swoole.com/wiki/page/301.html
            //            'open_cpu_affinity'       => '',//启用CPU亲和性设置 ---http://wiki.swoole.com/wiki/page/315.html
            //            'cpu_affinity_ignore'     => '',//IO密集型程序中 ---http://wiki.swoole.com/wiki/page/429.html
            //            'open_tcp_nodelay'        => '',//启用open_tcp_nodelay ---http://wiki.swoole.com/wiki/page/316.html
            //            'tcp_defer_accept'        => '',//启用tcp_defer_accept特性 ---http://wiki.swoole.com/wiki/page/317.html
            //            'ssl_cert_file'           => '',//设置SSL隧道加密 ---http://wiki.swoole.com/wiki/page/318.html
            //            'ssl_key_file'=>'',//设置SSL隧道加密
            //            'user'                    => 'apache',//设置worker/task子进程的所属用户 ---http://wiki.swoole.com/wiki/page/370.html
            //            'group'                   => '',//设置worker/task子进程的进程用户组 ---http://wiki.swoole.com/wiki/page/371.html
            //            'chroot'                  => '/data/server/',//定向Worker进程的文件系统根目录 ---http://wiki.swoole.com/wiki/page/392.html
            //            'pipe_buffer_size'        => '',// 调整管道通信的内存缓存区长度 ---http://wiki.swoole.com/wiki/page/439.html
            //            'buffer_output_size'      => '',//数据发送缓存区 ---http://wiki.swoole.com/wiki/page/440.html
            //            'enable_unsafe_event'     => TRUE,//启用onConnect/onClose事件 ---http://wiki.swoole.com/wiki/page/448.html
            //            'discard_timeout_request' => TRUE,//表示如果worker进程收到了已关闭连接的数据请求，将自动丢弃。
        );
//        var_dump($config);
        if ( empty( $config ) ) {
            $server_config = $default_server_config;
        } else {
            $server_config = array_merge( $default_server_config, $config[ 'server' ] );
        }
        $merge_config = array(
            'server' => $server_config,
        );
        $this->config = $merge_config;
//        var_dump($this->config);

        if ( !isset( $this->config[ 'server' ][ 'pid_file' ] ) ) {
            $backtrace = debug_backtrace();
            self::$start_file = $backtrace[ count( $backtrace ) - 1 ][ 'file' ];
            $this->config[ 'server' ][ 'pid_file' ] = sys_get_temp_dir() . "/WOS_" . str_replace( '/', '_', self::$start_file ) . ".pid";
        }

        $this->pid_file = $this->config[ 'server' ][ 'pid_file' ];


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
            $this->events[ $name ] = $value;
        }
    }

    function run()
    {
        // 首先处理命令
        $this->parseCommand();
        //根据配置选择并初始化worker对象
        foreach ( self::$listen as $listen ) {
            $this->initWorkers( $listen );
        }

        //设置回调
        $this->setCallbacks();


        self::$server->set( $this->config[ 'server' ] );// 仅加载针对server的配置


        //显示命令行状态
        $this->displayUI();
        self::$server->status_interval = self::$status_interval;
        self::$server->start_file = self::$start_file;


        self::$server->start();
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
        $base_name = basename( $argv[ 0 ] );

        if ( !isset( $argv[ 1 ] ) ) {
            echo Console::alert( "Usage: php " . $base_name . " {start|stop|restart|reload|status}" ) . "\n";
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
                echo Console::alert( "WorkerOnSwoole [$start_file] is already running" ) . "\n";
                exit( 0 );
//                self::log ("Workerman[$start_file] is running");
            }
        } elseif ( $command !== 'start' && $command !== 'restart' ) {
            echo Console::error( "WorkerOnSwoole [$start_file] not run" ) . "\n";
//            self::log ("Workerman[$start_file] not run");
            exit( 0 );
        }
        // 根据命令做相应处理
        switch ( $command ) {
            // 启动 workerman
            case 'start':
                if ( $command2 === '-d' ) {
                    $this->config[ 'server' ][ 'daemonize' ] = 1;
                }
                break;
            // 显示 workerman 运行状态 - 暂时没想到办法起作用
            case 'status':
                // 由定时器产生,定时写入服务器状态

                $state_file = sys_get_temp_dir() . "/WOS_status_" . str_replace( '/', '_', self::$start_file ) . ".pid";
                $data = file_get_contents( $state_file );
                $data = json_decode( $data, TRUE );
                $base_name = basename( $start_file );
                $ui = Console::table()->setSlice( '  ' )->td4( '<bg=lightBlue>' . $base_name . '</>', 'center' )->br( '-' )
                    ->td( "Swoole vension:", 'right' )->td( SWOOLE_VERSION )->td( "PHP version:", 'right' )->td( PHP_VERSION )->br()
                    ->td( "Server start at:", 'right' )->td( date( 'Y-m-d H:i:s', $data[ 'start_time' ] ), 'left' )->td( 'Connection num:', 'right' )->td( $data[ 'connection_num' ] )->br()
                    ->td( "Accept count:", 'right' )->td( $data[ 'accept_count' ], 'left' )->td( 'Close count:', 'right' )->td( $data[ 'close_count' ] )->br()
                    ->td( "Tasking num:", 'right' )->td( $data[ 'tasking_num' ], 'left' )->td( 'Request count:', 'right' )->td( $data[ 'request_count' ] )->br()
                    ->td( "Worker request count:", 'right' )->td( $data[ 'worker_request_count' ], 'left' )->td( 'Task process num:', 'right' )->td( $data[ 'task_process_num' ] )->br()
                    ->td( "Master PID:", 'right' )->td( $data[ 'master_pid' ], 'left' )->td( 'Manager PID:', 'right' )->td( $data[ 'manager_pid' ] )->br();
                exit( PHP_EOL . $ui . PHP_EOL );
            // 重启 workerman
            case 'restart':
                // 停止 workeran
            case 'stop':
                echo Console::info( "Server is shutdown now!" ) . "\n";
                posix_kill( $master_pid, SIGTERM );
                posix_kill( $master_pid + 1, SIGTERM );
                sleep( 5 );
                posix_kill( $master_pid, 9 );// 如果是不是守护进程,这里最后发送个强制停止的信号.
                if ( $command == 'stop' ) {
                    exit();
                    // 如果是restart ,那么继续执行后续逻辑,会再起一个进程
                }

                echo Console::info( "Server is restart now! " ) . "\n";
                break;
            // 平滑重启 workerman
            case 'reload':
                echo Console::info( "Server worker reload now! " ) . "\n";
                posix_kill( $master_pid, SIGUSR1 );//重启worker进程,可以测试装载功能
                exit;
            // 未知命令
            default :
                echo Console::alert( "Usage: php " . $base_name . " {start|stop|restart|reload|status}" ) . "\n";
                exit();
        }
    }

    /**
     * 根据配置初始化worker对象
     * @throws Exception
     */
    public function initWorkers( $listen )
    {

        // 获得应用层通讯协议以及监听的地址
        $url = parse_url( $listen );

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
            self::$type = strtolower( $url[ 'scheme' ] );

            switch ( strtolower( $url[ 'scheme' ] ) ) {
                case 'ws':
                case 'wss':
                    if ( self::$server ) {
                        self::$server->addListener( $url[ 'host' ], $url[ 'port' ], SWOOLE_SOCK_TCP );
                    } else {
                        self::$server = new \swoole_websocket_server( $url[ 'host' ], $url[ 'port' ], SWOOLE_PROCESS );
                    }
                    break;
                case 'http':
                case 'https':
                    if ( self::$server ) {
                        self::$server->addListener( $url[ 'host' ], $url[ 'port' ], SWOOLE_SOCK_TCP );
                    } else {
                        self::$server = new \swoole_http_server( $url[ 'host' ], $url[ 'port' ], SWOOLE_PROCESS );
//                        self::$server->setGlobal(HTTP_GLOBAL_ALL);
                    }
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
                case
                'unix':
                    $type = SWOOLE_UNIX_DGRAM;//未实现

                    break;
                default:
                    throw new \Exception( "Only support ws, wss, http, https, tcp, udp or unix scheme" );
            }
            // 统一处理socket
            if ( isset( $type ) ) {
                if ( self::$server ) {
                    self::$server->addListener( $url[ 'host' ], $url[ 'port' ], $type );
                } else {
                    self::$server = new \swoole_server( $url[ 'host' ], $url[ 'port' ], SWOOLE_PROCESS, $type );
                }
            }
        } else {
            throw new \Exception( "Can't parse this url: " . $url );
        }


    }

    /**
     * 导入用户自定义的事件处理对象
     * @param $user_event
     */
    public function setEvent( $user_event )
    {
        $methods = get_class_methods( $user_event );
        // 没办法了.既然使用 __call动态加载不行,那么暂时能想到的只能是遍历event对象中所有on开头的方法,都注册过来!
        foreach ( $methods as $event ) {
            if ( strpos( $event, 'on' ) === 0 ) {
                // 还是直接处理吧....
                $name = strtolower( ltrim( $event, 'on' ) );
//                var_dump($user_event->on);die;
//                self::$server->on( $name , array( $user_event, $event ) );
                $this->events[ $name ] = array( $user_event, $event );
            }
        }

        $this->user_event = $user_event;
    }

    // 设定回调和注入用户自定义Events
    function setCallbacks()
    {
        $version = explode( '.', SWOOLE_VERSION );


        // server基本事件
        self::$server->on( 'Start', array( $this, 'onStart' ) );
        self::$server->on( 'Shutdown', array( $this, 'onShutdown' ) );
        self::$server->on( 'ManagerStart', array( $this, 'onManagerStart' ) );
        self::$server->on( 'ManagerStop', array( $this, 'onManagerStop' ) );
        self::$server->on( 'WorkerStart', array( $this, 'onWorkerStart' ) );
        self::$server->on( 'WorkerStop', array( $this, 'onWorkerStop' ) );
//        self::$server->on( 'Timer', array( $this, 'onTimer' ) );// 已经不这么用了
        self::$server->on( 'Connect', array( $this, 'onConnect' ) );
        self::$server->on( 'Receive', array( $this, 'onReceive' ) );
//        if(self::$type == 'udp' || self::$type == 'udps'){
        //1.7.18以上版本
//            self::$server->on( 'Packet', array( $this, 'onPacket' ) );
//        }
        self::$server->on( 'Close', array( $this, 'onClose' ) );
        self::$server->on( 'Task', array( $this, 'onTask' ) );
        self::$server->on( 'Finish', array( $this, 'onFinish' ) );
        self::$server->on( 'PipeMessage', array( $this, 'onPipeMessage' ) );
        self::$server->on( 'WorkerError', array( $this, 'onWorkerError' ) );
        // WebSocketServer事件
        if ( self::$type == 'ws' || self::$type == 'wss' ) {
            // onHandShank和onOpen不能并存
//            self::$server->on( 'HandShank', array( $this, 'onHandShake' ) );
            self::$server->on( 'Open', array( $this, 'onOpen' ) );
            self::$server->on( 'Message', array( $this, 'onMessage' ) );
        }
        // HttpServer和websocket通用事件
        if ( in_array( self::$type, array( 'http', 'https', 'ws', 'wss' ) ) ) {
            self::$server->on( 'Request', array( $this, 'onRequest' ) );
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
        $base_name = basename( $argv[ 0 ] );

        $ui = Console::table()->setSlice( '  ' )->br( '-' )->td4( '<bg=lightBlue>WorkerOnSwoole</>', 'center' )->br( '-' )
            ->td( "WorkerServer version:", 'right' )->td( self::VERSION )->td( "PHP version:", 'right' )->td( PHP_VERSION )->br()
            ->td( "Swoole version:", 'right' )->td( SWOOLE_VERSION, 'left' )->td2( '' )->br();
        foreach ( $listen as $value ) {
            $ui->td( "Server listen:", 'right' )->td( $value )->td2( '' )->br();
        }
        $ui->td( "Server file:", 'right' )->td( $base_name )->td2( '' )->br( '-' )
            ->td4( "<bg=lightBlue>WORKERS</>", 'center' )->br( '-' );

        if ( isset( $this->config[ 'server' ][ 'daemonize' ] ) && $this->config[ 'server' ][ 'daemonize' ] == 1 ) {
            $start_file = $base_name;
            $ui->td4( "Input \"php $start_file status\" to get server status.  \n" )->br( ' ' );
            $ui->td4( "Input \"php $start_file stop\"   to quit. Start success.\n" )->br( ' ' );
        } else {
            $ui->td4( "Press Ctrl-C to quit." )->br( ' ' );
        }

        echo $ui;
    }

    //----------------------按照WorkerMan-GateWay包装功能,本质是将swoole转换一下方便理解使用---------------------------------
    //广播
    static function sendToAll( $send_data, $client_id_array = array() )
    {
        $clients = self::$server->connections;//这个是TCP下可用的连接迭代器对象
        if ( self::$type == 'udp' || self::$type == 'udp4' ) {
            echo 'function only for tcp' . PHP_EOL;

            return FALSE;
        }
        if ( self::$type == 'ws' || self::$type == 'wss' ) {
            foreach ( $clients as $fd ) {
                // 这里需要判断server的type,tcp udp 用send .ws用push httpServer就根本用不到这个
                self::$server->push( $fd, $send_data );//
            }
        } elseif ( method_exists( self::$server, 'send' ) ) {

            foreach ( $clients as $fd ) {
                self::$server->send( $fd, $send_data );// sw服务,请使用push像客户端发送数据,send有问题
            }
        }

        return TRUE;
    }

    // 单播
    static function sendToClient( $client_id, $send_data )
    {
        if ( self::$type == 'ws' || self::$type == 'wss' ) {
            self::$server->push( $client_id, $send_data );//
        } elseif ( method_exists( self::$server, 'send' ) ) {
            self::$server->send( $client_id, $send_data );// sw服务,请使用push像客户端发送数据,send有问题
        }

        return TRUE;
    }

    //sendToCurrentClient 未实现,似乎没啥用

    //服务器主动关闭连接
    static function closeClient( $client_id )
    {
        return self::$server->close( $client_id );
    }
    //closeCurrentClient 未实现

    // 是否在线
    static function isOnline( $client_id )
    {
        return self::$server->exist( $client_id );
    }

    //获取全部在线客户端
    static function getOnlineStatus()
    {
        return self::$server->connections;
    }

    //将client_id与uid绑定，以便通过sendToUid发送数据,每个客户端只允许执行一次,未测试
    static function bindUid( $client_id, $uid )
    {
        return self::$server->bind( $client_id, $uid );
    }

    // 向配置的uid发送消息
    static function sendToUid( $uid, $message )
    {
        if ( self::$type == 'ws' || self::$type == 'wss' ) {
            return self::$server->push( $uid, $message );//sw服务,请使用push像客户端发送数据,send有问题
        } elseif ( method_exists( self::$server, 'send' ) ) {
            return self::$server->send( $uid, $message );//
        }
    }

    //----------------------------------服务器基本事件--------------------------------------------------------------------
    /**
     * Server启动在主进程的主线程回调此函数
     * @param $server
     */
    function onStart( $server )
    {
        global $argv;
        $base_name = basename( $argv[ 0 ] );
        echo Console::success( "[{$base_name }]: master  process is running at : {$server->master_pid} " ) . "\n";
        echo Console::success( "[{$base_name }]: manager process is running at : {$server->manager_pid} " ) . "\n";

        if ( !empty( $this->config[ 'server' ][ 'pid_file' ] ) ) {
            //增加写独占锁
            file_put_contents( $this->pid_file, $server->master_pid, LOCK_EX );
        }

        // 记录服务器状态
        $server->tick( $server->status_interval, function () use ( $server ) {
            $state_file = sys_get_temp_dir() . "/WOS_status_" . str_replace( '/', '_', $server->start_file ) . ".pid";
            $status = $server->stats();
            // 增加记录服务器各进程
            $status[ 'master_pid' ] = $server->master_pid;
            $status[ 'manager_pid' ] = $server->manager_pid;
            //woker随时会变化,因此用处不大
            file_put_contents( $state_file, json_encode( $status ), LOCK_EX );
        } );

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onStart' ) ) {
            $this->user_event->onStart( $server );
        }

    }


    /**
     * 此事件在Server结束时发生
     * @param $server
     */
    function onShutdown( $server )
    {
        if ( !empty( $this->config[ 'server' ][ 'pid_file' ] ) ) {
            unlink( $this->pid_file );
        }
        global $argv;
        $base_name = basename( $argv[ 0 ] );
        echo Console::info( "[{$base_name}]: master process is stoped.. " ) . "\n";

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onShutdown' ) ) {
            $this->user_event->onShutdown( $server );
        }

    }

    /**
     * 当管理进程启动时调用它
     * @param $server
     */
    function onManagerStart( $server )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onManagerStart' ) ) {
            $this->user_event->onManagerStart( $server );
        }

    }


    /**
     * 当管理进程结束时调用它
     * @param $server
     */
    function onManagerStop( $server )
    {
        if ( !empty( $this->config[ 'server' ][ 'pid_file' ] ) ) {
            unlink( $this->pid_file );
        }
        global $argv;
        $base_name = basename( $argv[ 0 ] );
        echo Console::info( "[{$base_name}]: manager process is stoped... " ) . "\n";

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onManagerStop' ) ) {
            $this->user_event->onManagerStop( $server );
        }

    }

    /**
     * 此事件在worker进程/task进程启动时发生
     *
     * @param $server
     * @param $worker_id
     */
    function onWorkerStart( $server, $worker_id )
    {
//        var_dump($server);
        global $argv;
        $base_name = basename( $argv[ 0 ] );

        if ( $worker_id >= $server->setting[ 'worker_num' ] ) {
            Console::setProcessName( 'php ' . $base_name . ': task' );
            echo Console::success( "[{$base_name}]: task    process is running at : {$server->worker_pid} " ) . "\n";
        } else {
            Console::setProcessName( 'php ' . $base_name . ': worker' );
            echo Console::success( "[{$base_name}]: worker  process is running at : {$server->worker_pid} " ) . "\n";
        }

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onWorkerStart' ) ) {
            $this->user_event->onWorkerStart( $server, $worker_id );
        }
    }

    /**
     * 此事件在worker进程终止时发生
     *
     * @param $server
     * @param $worker_id $worker_id和进程PID没有任何关系
     */
    function onWorkerStop( $server, $worker_id )
    {
        global $argv;
        $base_name = basename( $argv[ 0 ] );
        echo Console::info( "[{$base_name}]: worker process is stoped.. " ) . "\n";

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onWorkerStop' ) ) {
            $this->user_event->onWorkerStop( $server, $worker_id );
        }
    }


    /**
     * 定时器触发
     *
     * @param     $server
     * @param     $interval
     */
//    function onTimer( $server, $interval )
//    {
//        // 如果用户也自定义了,那么接着执行用户自定义部分
//        if ( method_exists( $this->user_event, 'onTimer' ) ) {
//            $this->user_event->onTimer( $server, $interval );
//        }
//    }

    /**
     * 有新的连接进入时，在worker进程中回调
     *
     * @param $server
     * @param $fd    int   $fd是连接的文件描述符，发送数据/关闭连接时需要此参数
     * @param $from_id int
     */
    function onConnect( $server, $fd, $from_id )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onConnect' ) ) {
            $this->user_event->onConnect( $server, $fd, $from_id );
        }
    }

    /**
     * 接收到数据时回调此函数
     *
     * @param $server
     * @param $fd
     * @param $from_id
     * @param $data
     * @return mixed
     */
    function onReceive( $server, $fd, $from_id, $data )
    {

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onReceive' ) ) {
            $this->user_event->onReceive( $server, $fd, $from_id, $data );
        }
    }


    /**
     * 接收到UDP数据包时回调此函数
     *
     * @param $server
     * @param $data 收到的数据内容，可能是文本或者二进制内容
     * @param $client_info array 客户端信息包括address/port/server_socket 3项数据
     */
    function  onPacket( $server, $data, $client_info )
    {

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onPacket' ) ) {
            $this->user_event->onPacket( $server, $data, $client_info );
        }
    }

    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数。
     *
     * @param $server
     * @param $fd
     * @param $from_id
     */
    function onClose( $server, $fd, $from_id )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onClose' ) ) {
            $this->user_event->onClose( $server, $fd, $from_id );
        }
    }


    /**
     * 在task_worker进程内被调用
     *
     * @param $server
     * @param $task_id
     * @param $from_id
     * @param $data
     */
    function onTask( $server, $task_id, $from_id, $data )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onTask' ) ) {
            $this->user_event->onTask( $server, $task_id, $from_id, $data );
        }
    }


    /**
     * 当worker进程投递的任务在task_worker中完成时
     *
     * @param $server
     * @param $task_id
     * @param $data
     */
    function  onFinish( $server, $task_id, $data )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onFinish' ) ) {
            $this->user_event->onFinish( $server, $task_id, $data );
        }

    }

    /**
     * 当工作进程收到由sendMessage发送的管道消息时会触发onPipeMessage事件
     *
     * @param $server
     * @param $from_worker_id
     * @param $message
     */
    function onPipeMessage( $server, $from_worker_id, $message )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onFinish' ) ) {
            $this->user_event->onPipeMessage( $server, $from_worker_id, $message );
        }
    }

    /**
     * 当worker/task_worker进程发生异常后会在Manager进程内回调此函数
     * @param $server
     * @param $worker_id
     * @param $worker_pid
     * @param $exit_code
     */
    function onWorkerError( $server, $worker_id, $worker_pid, $exit_code )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onWorkerError' ) ) {
            $this->user_event->onWorkerError( $server, $worker_id, $worker_pid, $exit_code );
        }
    }

    //-----------------------WebSocket服务事件---------------------------------------------------------------------------
    /**
     * WebSocket建立连接后进行握手
     *
     * @param $request
     * @param $response
     */
    function onHandShake( $request, $response )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onHandShake' ) ) {
            $this->user_event->onHandShake( $request, $response );
        }
    }

    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数
     *
     * @param $server
     * @param $request
     */
    function onOpen( $server, $request )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onOpen' ) ) {
            $this->user_event->onOpen( $server, $request );
        }
    }

    /**
     * 当服务器收到来自客户端的数据帧时会回调此函数
     *
     * @param                        $server
     * @param swoole_websocket_frame $frame
     * swoole_websocket_frame 包含属性
     * $frame->fd，客户端的socket id，使用$server->push推送数据时需要用到
     * $frame->data，数据内容，可以是文本内容也可以是二进制数据，可以通过opcode的值来判断
     * $frame->opcode，WebSocket的OpCode类型，可以参考WebSocket协议标准文档
     * $frame->finish， 表示数据帧是否完整，一个WebSocket请求可能会分成多个数据帧进行发送
     *
     * @return bool
     */
    function onMessage( $server, $frame )
    {

        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onMessage' ) ) {
            $this->user_event->onMessage( $server, $frame );
        }
    }

    //--------------------------HttpServer------------------------------------------------------------------------------
    /**
     * 在收到一个完整的Http请求后，会回调此函数
     *
     * @param $request
     * @param $response
     */
    function onRequest( $request, $response )
    {
        // 如果用户也自定义了,那么接着执行用户自定义部分
        if ( method_exists( $this->user_event, 'onRequest' ) ) {
            $this->user_event->onRequest( $request, $response );
        }
    }

}