<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午6:04
 */


namespace Applications\event;

/**
 * Class httpEvent
 * @package Applications\event
 * 示范一下,事件处理类的命名是随意的,没有限制,只要在生成worker时,new 对象并用setEvent()方法载入即可
 */
class httpEvent
{
    // 简单实现一个http服务器,处理静态和php请求
    function onRequest( $request, $response )
    {
//        var_dump($request->get);
//        var_dump($request->cookie);
//        var_dump($request->server);
//        var_dump($_SERVER);
//        $response->cookie("User", "Swoole");
        $response->end( "<h1>Hello Swoole. #" . rand( 1000, 9999 ) . "</h1>" );
    }

}