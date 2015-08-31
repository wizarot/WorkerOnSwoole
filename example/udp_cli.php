<?php
$client = new swoole_client(SWOOLE_SOCK_UDP);
if (!$client->connect('127.0.0.1', 8888, 0.5))
{
	echo 'conn';
    die("connect failed.");
}

if (!$client->send("hi!"))
{
	echo 'send';
    die("send failed.");
}

$data = $client->recv();
if (!$data)
{
    die("recv failed.");
}
var_dump($data);

$client->close();