<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午6:04
 */

namespace Applications\event;


class http1Event
{
    function onRequest($request, $response) {
        $response->end ("<h1>Hello Swoole. #" . rand (1000, 9999) . "</h1>");
    }

}