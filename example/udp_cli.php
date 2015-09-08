<?php
//异步客户端
$client = new swoole_client(SWOOLE_SOCK_UDP, SWOOLE_SOCK_ASYNC);
$client->on("connect", function(swoole_client $cli) {
    echo "connect";
    $cli->send("GET / HTTP/1.1\r\n\r\n");
});
$client->on("receive", function(swoole_client $cli, $data){
    echo "Receive: $data";
    $cli->send(str_repeat('A', 100)."\n");
    sleep(1);
});
$client->on("error", function(swoole_client $cli){
    echo "error\n";
});
$client->on("close", function(swoole_client $cli){
    echo "Connection close\n";
});
$client->connect('127.0.0.1', 8888);