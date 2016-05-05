<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/17
 * Time: 下午6:04
 */


namespace Applications\event;

use WorkerOnSwoole\lib\Encryptor;

/**
 * Class shadowSocket
 *
 */
class shadowSocket
{
    function onConnect($server, $fd, $from_id){

    }

    /**
     * 解析shadowsocks客户端发来的socket5头部数据
     * @param $buffer
     * @return array|bool
     */
    function parse_socket5_header($buffer)
    {
        $addr_type = ord($buffer[0]);
        switch($addr_type)
        {
            case ADDRTYPE_IPV4:
                $dest_addr = ord($buffer[1]).'.'.ord($buffer[2]).'.'.ord($buffer[3]).'.'.ord($buffer[4]);
                $port_data = unpack('n', substr($buffer, 5, 2));
                $dest_port = $port_data[1];
                $header_length = 7;
                break;
            case ADDRTYPE_HOST:
                $addrlen = ord($buffer[1]);
                $dest_addr = substr($buffer, 2, $addrlen);
                $port_data = unpack('n', substr($buffer, 2 + $addrlen, 2));
                $dest_port = $port_data[1];
                $header_length = $addrlen + 4;
                break;
            case ADDRTYPE_IPV6:
                echo "todo ipv6 not support yet\n";
                return false;
            default:
                echo "unsupported addrtype $addr_type\n";
                return false;
        }
        return array($addr_type, $dest_addr, $dest_port, $header_length);
    }
}