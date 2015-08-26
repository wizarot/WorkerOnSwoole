<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/26
 * Time: 下午2:16
 */

namespace WorkerOnSwoole\lib\IFace;


interface Protocol
{
    function onStart( $server );

    function onConnect( $server, $client_id, $from_id );

    function onReceive( $server, $client_id, $from_id, $data );

    function onClose( $server, $client_id, $from_id );

    function onShutdown( $server );
}