<?php
/**
 * Created by PhpStorm.
 * User: wizarot
 * Date: 15/8/17
 * Time: 下午10:42
 */

namespace Applications\event;


class todpole
{
    /**
     * 当客户端连上时触发
     * @param int $client_id
     */
    public function onConnect($server, $client_id)
    {
        var_dump($client_id);
        $server->send($client_id ,('{"type":"welcome","id":'.$client_id.'}'));
    }

    /**
     * 有消息时
     * @param int $client_id
     * @param string $message
     */
    public function onMessage($server, $client_id,$from_id , $message)
    {
        // 获取客户端请求
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }

        switch($message_data['type'])
        {
            case 'login':
                break;
            // 更新用户
            case 'update':
                // 转播给所有用户
                $this->sendToAll($server,json_encode(
                    array(
                        'type' => 'update',
                        'id' => $client_id,
                        'angle' => $message_data["angle"] + 0,
                        'momentum' => $message_data["momentum"] + 0,
                        'x' => $message_data["x"] + 0,
                        'y' => $message_data["y"] + 0,
                        'life' => 1,
                        'name' => isset($message_data['name']) ? $message_data['name'] : 'Guest.' . $client_id,
                        'authorized' => false,
                    )
                ));
                return;
            // 聊天
            case 'message':
                // 向大家说
                $new_message = array(
                    'type'=>'message',
                    'id'=>$client_id,
                    'message'=>$message_data['message'],
                );
                $this->sendToAll($server,json_encode($new_message));
                return ;
        }
    }

    /**
     * 当用户断开连接时
     * @param integer $client_id 用户id
     */
    public function onClose($server , $client_id)
    {
        // 广播 xxx 退出了

        $this->sendToAll($server, json_encode(array('type'=>'closed', 'id'=>$client_id)));


    }

    public function sendToAll($server,$data){
        foreach($server->connections as $fd)
        {
            $server->send($fd, $data);
        }
    }
}