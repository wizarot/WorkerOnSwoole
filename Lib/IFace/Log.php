<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/26
 * Time: 下午3:41
 */

namespace WorkerOnSwoole\lib\IFace;

use \Swoole;


interface Log
{

    /**
     * 写入日志
     *
     * @param $msg   string 内容
     * @param $type  int 类型
     */
    function put( $msg, $type = Swoole\Log::INFO );

}