<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午6:04
 */


namespace Applications\event;

use \Swoole;

/**
 * Class httpEvent
 * 简单的web服务器,可以处理静态请求和简单的php. 超全局变量的获取和设置等问题没有处理,依然使用swoole的.
 * @package Applications\event
 * 示范一下,事件处理类的命名是随意的,没有限制,只要在生成worker时,new 对象并用setEvent()方法载入即可
 */
class httpEvent
{
    public $document_root;
    public $static_dir;
    public $static_ext;

    // 简单实现一个http服务器,处理静态和php请求
    function onRequest( $request, $response )
    {
        global $php;
        // 处理路由
        $host = $request->header[ 'host' ];
        if ( !isset( $php[ 'web_root' ][ $host ] ) ) {
            //404~
            $response->status( 404 );
            return $response->end( 'host not found' );
        } else {
            // 暂时都放在一起
            $this->static_dir = $php[ 'web_root' ][ $host ];
            $mimes = include __DIR__ . '/../data/mimes.php';
            $this->static_ext = array_flip( $mimes );
            $this->document_root = $php[ 'web_root' ][ $host ];
        }


        $this->currentResponse = $response;

        //请求路径
        if ( $request->server[ 'request_uri' ][ strlen( $request->server[ 'request_uri' ] ) - 1 ] == '/' ) {
            $request->server[ 'request_uri' ] .= 'index.php';//暂时先默认Index.php
        }


        if ( $this->doStaticRequest( $request, $response ) ) {
            //pass
        } /* 动态脚本 暂时扩展名只支持 .php */
        elseif ( ( $request->ext_name == 'php' ) or empty( $ext_name ) ) {
            $response = $this->processDynamic( $request, $response );
        } else {
            $response->status( 404 );
            $response->end( 'host not found' );
        }

        return $response;
    }


    /**
     * 过滤请求，阻止静止访问的目录，处理静态文件
     */
    function doStaticRequest( $request, $response )
    {
        $path = explode( '/', trim( $request->server[ 'request_uri' ], '/' ) );
        //扩展名
        $request->ext_name = $ext_name = strtolower( trim( substr( strrchr( $request->server[ 'request_uri' ], '.' ), 1 ) ) );
        /* 是否静态目录 */
        if ( isset( $this->static_ext[ $ext_name ] ) ) {
            return $this->processStatic( $request, $response );
        }

        return FALSE;
    }

    /**
     * 静态请求
     * @param $request
     * @param $response
     * @return unknown_type
     */
    function processStatic( $request, $response )
    {
        $path = $this->document_root . '/' . $request->server[ 'request_uri' ];
        if ( is_file( $path ) ) {
            $read_file = TRUE;
//
//            $this->expire = true;//先这样
//            if ($this->expire)
//            {
//                $expire = intval(30);
//                $fstat = stat($path);
//                //过期控制信息
//                if (isset($request->head['If-Modified-Since']))
//                {
//                    $lastModifiedSince = strtotime($request->head['If-Modified-Since']);
//                    if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince)
//                    {
//                        //不需要读文件了
//                        $read_file = false;
//                        $response->setHttpStatus(304);
//                    }
//                }
//                else
//                {
//                    $response->head['Cache-Control'] = "max-age={$expire}";
//                    $response->head['Pragma'] = "max-age={$expire}";
//                    $response->head['Last-Modified'] = date(self::DATE_FORMAT_HTTP, $fstat['mtime']);
//                    $response->head['Expires'] = "max-age={$expire}";
//                }
//            }

            //暂不处理静态文件缓存
            $ext_name = strtolower( trim( substr( strrchr( $request->server[ 'request_uri' ], '.' ), 1 ) ) );
            if ( $read_file ) {
                $response->header( 'Content-Type', $this->static_ext[ $ext_name ] );
                $response->end( file_get_contents( $path ) );
            }

            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * 动态请求
     * @param $request
     * @param $response
     * @return unknown_type
     */
    function processDynamic( $request, $response )
    {
        $path = $this->document_root . '/' . $request->server[ 'request_uri' ];
        if ( is_file( $path ) ) {
            $this->setGlobal( $request );
            $response->header( 'Content-Type', 'text/html' );

            ob_start();
            try {
                global $php;
                $php['response'] = $response;
                include $path;
                // 走一圈,处理cookie
                $response = $php['response'];
                $response->end( ob_get_contents() );
                ob_clean();
            } catch ( \Exception $e ) {
                $response->status( 500 );
                $response->end( $e->getMessage() . '!<br /><h1>Worker On Swoole</h1>' );
            }
            ob_end_clean();
            // 完事就赶快清理掉
            $this->unsetGlobal();
        } else {
            $response->status( 404 );
            $response->end( "页面不存在({$request->server['request_uri']})！" );
        }

        return $response;
    }

    /**
     * 将原始请求信息转换到PHP超全局变量中
     * 有点怀疑这样会有问题
     */
    function setGlobal( $request )
    {
        if ( isset( $request->get ) ) {
            $_GET = $request->get;
        } else {
            $request->get = array();
        }
        if ( isset( $request->post ) ) {
            $_POST = $request->post;
        }else{
            $request->post = array();
        }
        if ( isset( $request->file ) ) $_FILES = $request->file;
        if ( isset( $request->cookie ) ) {
            foreach($request->cookie as $k => $v){
                $_COOKIE[$k] = urldecode($v);
            }
        }else{
            $request->cookie = array();
        }
        $_SERVER = $request->server;
        $_REQUEST = array_merge( $request->get, $request->post, $request->cookie );

        $_SERVER[ 'REQUEST_URI' ] = $request->server[ 'request_uri' ];
        /**
         * 将HTTP头信息赋值给$_SERVER超全局变量
         */
        foreach ( $request->header as $key => $value ) {
            $_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $key ) );
            $_SERVER[ $_key ] = $value;
        }
        $_SERVER[ 'REMOTE_ADDR' ] = $request->server[ 'remote_addr' ];
    }

    function unsetGlobal()
    {
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = array();
    }


}