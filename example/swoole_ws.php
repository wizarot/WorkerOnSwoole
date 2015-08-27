<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/27
 * Time: 下午2:23
 */

$serv = new swoole_websocket_server( "127.0.0.1", 9502 );

$serv->on( 'Open', function ( $server, $req ) {
    echo "connection open: " . $req->fd;
} );

$serv->on( 'Message', function ( $server, $frame ) {
    echo "message: " . $frame->data;
    $server->push( $frame->fd, json_encode( [ "hello", "world" ] ) );
} );

$serv->on( 'Close', function ( $server, $fd ) {
    echo "connection close: " . $fd;
} );


$serv->on( 'Request', function ( $request, $response ) {
    var_dump( $request->get );
    var_dump( $request->post );
    var_dump( $request->cookie );
    var_dump( $request->files );
    var_dump( $request->header );
    var_dump( $request->server );

    $response->cookie( "User", "Swoole" );
    $response->header( "X-Server", "Swoole" );
    $response->end( "<h1>Hello Swoole!</h1>" );
} );

$serv->start();