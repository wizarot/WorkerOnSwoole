<?php
/**
 * @author will <wizarot@gmail.com>
 * @link http://wizarot.me/
 *
 * Date: 15/9/15
 * Time: 上午11:13
 */

namespace Applications\event;


class webIm
{

    /**
     * Class SwooleImService
     * swoole 对话功能的逻辑处理
     * @package Wiz\WechatPluginBundle\Service'
     */

    protected $store;
    protected $info;// 记录信息 client_id => info数组
    protected $users;// 咨询用户 open_id => client_id 关系
    protected $servers;//服务人员 cid => client_id 关系


    function __construct( $config = array() )
    {
        //检测日志目录是否存在
        $log_dir = dirname( $config[ 'swoole' ][ 'log_file' ] );

        if ( !empty( $config[ 'swoole' ][ 'log_file' ] ) ) {
            if ( !is_dir( $log_dir ) ) {
                mkdir( $log_dir, 0777, TRUE );
            }
            $logger = new \Swoole\Log\FileLog( $config[ 'webim' ][ 'log_file' ] );
        } else {
            $conf[ 'display' ] = FALSE;
            $logger = new \Swoole\Log\EchoLog( $conf[ 'display' ] );
        }
        $this->setLogger( $logger );   //Logger

        /**
         * 使用mysql 存数据
         */
        // $this->setStore(new \WebIM\Store\File($config['webim']['data_dir']));
        // $this->setStore(new \WebIM\Store\Redis());
        $this->setStore( new \Swoole\Database( $config[ 'db' ] ) );
        // $this->origin = $config['server']['origin'];
        parent::__construct( $config );
    }





    function setStore( $store )
    {
        $this->store = $store;
        $store->connect();
    }

    //自定义一些用户关系信息记录
    // 记录 client_id 对应的客服信息,建立 $this->servers表
    function setServer( $client_id, $info )
    {
        $this->info[ $client_id ] = $info;
        $this->servers[ $info[ 'cid' ] ] = $client_id;//cid代表客服系统id
        return TRUE;
    }

    // 记录client_id 对应微信客户端信息
    function setUser( $client_id, $info )
    {
        $this->info[ $client_id ] = $info;
        $this->users[ $info[ 'open_id' ] ] = $client_id;

        return TRUE;
    }

    // 用户下线,清除数据
    function delClient( $client_id )
    {
        $info = $this->info[ $client_id ];
        if ( isset( $info ) ) {
            if ( isset( $info[ 'open_id' ] ) ) {
                unset( $this->info[ $client_id ] );
                unset( $this->users[ $info[ 'open_id' ] ] );
            } elseif ( isset( $info[ 'cid' ] ) ) {
                unset( $this->info[ $client_id ] );
                unset( $this->servers[ $info[ 'cid' ] ] );
            }

            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * 下线时，清理部分数据,保留对话记录
     */
    function onExit( $client_id )
    {
        $info = $this->info[ $client_id ];
        if ( isset( $info[ 'open_id' ] ) ) {
            // 通知客服
            $to_server = $info[ 'server_id' ];
            // 根据 server_id 反向查找客服的 client_id
            $to_id = $this->servers[ $to_server ];
            $resMsg = array(
                'cmd'  => 'offline',
                'fd'   => $client_id,
                'from' => 0,
                // 'channal' => 0,
                'data' => '下线了',
            );
            $this->sendJson( $to_id, $resMsg );
        }

        $this->delClient( $client_id );
    }

    function onTask( $serv, $task_id, $from_id, $data )
    {
        $req = unserialize( $data );
        if ( $req ) {
            switch ( $req[ 'cmd' ] ) {
                case 'getHistory':
                    // 查询数据,并发送服务端
                    $where = " openid = '{$req['open_id']}' ";
                    if ( $req[ 'offset' ] > 0 ) {
                        $where .= " AND id < {$req['offset']} ";
                    }
                    $sql = "SELECT * FROM wiz_wechat_im_log WHERE {$where} ORDER BY id DESC LIMIT {$req['limit']} ";
                    $result = $this->store->query( $sql )->fetchall();
                    $to_id = $req[ 'to_id' ];

                    $resMsg = array(
                        'cmd'  => 'history',
                        'fd'   => $req[ 'fd' ],// 记录针对的用户client_id
                        'data' => $result,
                    );
                    $this->sendJson( $to_id, $resMsg );// 发送记录到客服那边

                    break;
                case 'addHistory':
                    if ( empty( $req[ 'msg' ] ) ) {
                        $req[ 'msg' ] = '';
                    }
                    $insert_data = $req;
                    unset( $insert_data[ 'cmd' ] );
                    unset( $insert_data[ 'fd' ] );
                    unset( $insert_data[ 'msg' ] );
                    $this->store->insert( $insert_data, 'wiz_wechat_im_log' );
                    break;
                default:
                    break;
            }
        }
    }

    function onFinish( $serv, $task_id, $data )
    {
        $this->send( substr( $data, 0, 32 ), substr( $data, 32 ) );
    }

    /**
     * 获取在线列表
     */
    function cmd_getOnline( $client_id, $msg )
    {
        $resMsg = array(
            'cmd' => 'getOnline',
        );
        // $users = $this->store->getOnlineUsers();
        // $info = $this->store->getUsers(array_slice($users, 0, 100));
        // 只有客服端需要获取自己相关客户
        $info = $this->info[ $client_id ];
        $users = array();
        foreach ( $this->info as $cli => $value ) {
            if ( isset( $value[ 'server_id' ] ) && ( $value[ 'server_id' ] == $info[ 'cid' ] ) ) {
                $users[] = $value;
            }
        }

        $resMsg[ 'users' ] = $users;
        // $resMsg['list'] = $info;
        $this->sendJson( $client_id, $resMsg );
    }

    /**
     * 获取历史聊天记录
     */
    function cmd_getHistory( $client_id, $msg )
    {
        $task[ 'cmd' ] = 'getHistory';
        // 发起查询,针对客户的聊天记录
        $user_id = $this->users[ $msg[ 'open_id' ] ];
        $task[ 'fd' ] = $user_id;// 方便前端取用
        $info = $this->info[ $user_id ];
        $server_id = $this->servers[ $info[ 'server_id' ] ];
        $task[ 'to_id' ] = $server_id;
        // $msg 中需要加客户的open_id来发送聊天记录
        $task[ 'open_id' ] = $msg[ 'open_id' ];
        $task[ 'offset' ] = isset( $msg[ 'offset' ] ) ? $msg[ 'offset' ] : 0;//客户端回传最小id,首次查询第一页传0
        $task[ 'limit' ] = 10;//默认每次查10条
        //在task worker中会直接发送给客户端
        $this->getSwooleServer()->task( serialize( $task ), self::WORKER_HISTORY_ID );
    }

    /**
     * 记录历史聊天记录
     */
    function addHistory( $client_id, $msg )
    {
        $task = $msg;
        $task[ 'fd' ] = $client_id;
        $task[ 'cmd' ] = 'addHistory';

        //在task worker中会直接发送给客户端
        $this->getSwooleServer()->task( serialize( $task ), self::WORKER_HISTORY_ID );
    }


    /**
     * 咨询微信登录
     * @param $client_id
     * @param $msg
     */
    function cmd_login( $client_id, $msg )
    {
        // 可能需要做个验证
        $info[ 'name' ] = $msg[ 'name' ];
        $info[ 'avatar' ] = $msg[ 'avatar' ];
        $info[ 'fd' ] = $client_id;
        $info[ 'open_id' ] = $msg[ 'open_id' ];// 有openid人为是客户 - 根据openid 识别客户唯一性
        $info[ 'server_id' ] = 1;//由服务器分配
        // $info['server_id'] = $msg['server_id'];//由服务器分配

        //处理特殊情况,用户使用中短线,重连造成的fd更新
        if ( isset( $this->users[ $info[ 'open_id' ] ] ) ) {
            // 将对应的client_id用户下线
            $this->getSwooleServer()->close( $this->users[ $info[ 'open_id' ] ] );
        }

        //把会话存起来,记录用户信息
        $this->setUser( $client_id, $info );

        $resMsg = $info;
        $resMsg[ 'cmd' ] = 'login';

        $this->sendJson( $client_id, $resMsg );//回复服务器正确接受用户上线


        // 通知服务人员新用户上线
        $resMsg[ 'cmd' ] = 'newUser';
        if ( isset( $this->servers[ $info[ 'server_id' ] ] ) ) {
            $server_cli_id = $this->servers[ $info[ 'server_id' ] ];
            $this->sendJson( $server_cli_id, $resMsg );
            $this->cmd_getHistory( $client_id, $info );
        } else {
            // 处理客服人员没在线的情况...

        }


    }

    /**
     * 后台客服人员登录
     * @param $client_id
     * @param $msg
     */
    function cmd_login_s( $client_id, $msg )
    {
        // 可能需要做个验证
        $info[ 'name' ] = $msg[ 'name' ];
        $info[ 'avatar' ] = $msg[ 'avatar' ];
        $info[ 'cid' ] = $msg[ 'cid' ];
        $info[ 'fd' ] = $client_id;

        $this->setServer( $client_id, $info );

        $resMsg = $info;
        // 登陆成功
        $resMsg[ 'cmd' ] = 'login_s';

        $this->sendJson( $client_id, $resMsg );//回复服务器正确接受用户上线
    }

    /**
     * 发送信息请求 - 来自微信客户
     */
    function cmd_message( $client_id, $msg )
    {
        // $msg 也记录下客服id
        $resMsg = $msg;
        $resMsg[ 'from' ] = $client_id;
        $resMsg[ 'cmd' ] = 'fromMsg';

        if ( strlen( $msg[ 'data' ] ) > self::MESSAGE_MAX_LEN ) {
            $this->sendErrorMessage( $client_id, 102, 'message max length is ' . self::MESSAGE_MAX_LEN );

            return;
        }

        // 来的消息
        $from_user = $this->info[ $client_id ];
        // 客户给客服
        $to_server = $from_user[ 'server_id' ];

        // 根据 server_id 反向查找客服的 client_id
// 需要考虑客服掉线的情况.
        if ( !isset( $this->servers[ $to_server ] ) ) {
            $to_id = current( $this->servers );//给第一个客服Id
        } else {
            $to_id = $this->servers[ $to_server ];

        }

        $msg[ 'type' ] = 'from_user';

        // redis 增加聊天记录 - 可以改成数据库的
        // 增加聊天记录
        $data = array(
            'msgType'  => 0,
            'sendType' => 1,//1向客服发送提问
            'content'  => $msg[ 'data' ],
            'openid'   => $from_user[ 'open_id' ],
            'server'   => $from_user[ 'server_id' ],
            'sendAt'   => date( 'Y-m-d H:i:s' ),
        );
        // var_dump($data);
        // task 异步记录到mysql
        $this->addHistory( $client_id, $data );


        $this->sendJson( $to_id, $resMsg );

    }

    /**
     * 发送信息请求 - 来自客服
     */
    function cmd_message_s( $client_id, $msg )
    {
        $resMsg = $msg;
        $resMsg[ 'from' ] = $client_id;
        $resMsg[ 'cmd' ] = 'fromMsg';

        if ( strlen( $msg[ 'data' ] ) > self::MESSAGE_MAX_LEN ) {
            $this->sendErrorMessage( $client_id, 102, 'message max length is ' . self::MESSAGE_MAX_LEN );

            return;
        }

        // 来的消息
        $from_user = $this->info[ $client_id ];
        $resMsg[ 'from_name' ] = $from_user[ 'name' ];
        // 客服回复消息
        $to_id = $msg[ 'to' ];//client_id
        $msg[ 'type' ] = 'to_user';

        // 防止客服发送的空内容影响业务
        if ( !isset( $this->info[ $to_id ] ) ) {
            return FALSE;
        }
        $to_user = $this->info[ $to_id ];

        // 增加聊天记录
        $data = array(
            'msgType'  => 0,
            'sendType' => 0,//0向用户发送
            'content'  => $msg[ 'data' ],
            'openid'   => $to_user[ 'open_id' ],
            'server'   => $from_user[ 'cid' ],
            'sendAt'   => date( 'Y-m-d H:i:s' ),
        );
        // var_dump($data);
        // task 异步记录到mysql
        $this->addHistory( $client_id, $data );


        $this->sendJson( $to_id, $resMsg );

    }

    /**
     * 接收到消息时
     * @see WSProtocol::onMessage()
     */
    function onMessage( $client_id, $ws )
    {
        $this->log( "onMessage #$client_id: " . $ws[ 'message' ] );
        $msg = json_decode( $ws[ 'message' ], TRUE );
        if ( empty( $msg[ 'cmd' ] ) ) {
            $this->sendErrorMessage( $client_id, 101, "invalid command" );

            return;
        }
        $func = 'cmd_' . $msg[ 'cmd' ];
        if ( method_exists( $this, $func ) ) {
            $this->$func( $client_id, $msg );
        } else {
            $this->sendErrorMessage( $client_id, 102, "command $func no support." );

            return;
        }
    }

    /**
     * 发送错误信息
     * @param $client_id
     * @param $code
     * @param $msg
     */
    function sendErrorMessage( $client_id, $code, $msg )
    {
        $this->sendJson( $client_id, array( 'cmd' => 'error', 'code' => $code, 'msg' => $msg ) );
    }

    /**
     * 发送JSON数据
     * @param $client_id
     * @param $array
     */
    function sendJson( $client_id, $array )
    {
        $msg = json_encode( $array );
        $this->send( $client_id, $msg );
    }


}