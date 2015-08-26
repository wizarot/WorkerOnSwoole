<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/26
 * Time: 下午1:59
 */
namespace WorkerOnSwoole\lib\IFace;

interface Server
{
    function run( $setting );

    function send( $client_id, $data );

    function close( $client_id );

    function shutdown();

    function setProtocol( $protocol );
}