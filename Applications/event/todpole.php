<?php
/**
 * Created by PhpStorm.
 * User: wizarot
 * Date: 15/8/17
 * Time: 下午10:42
 */

namespace Applications\event;
use WorkerOnSwoole\Worker;

// 基本完全复制原代码,按照需要简单修改点方法名和事件名就能投入使用了.
class todpole
{
    /**
     * @param $server
     * @param $request
     *
     * $request->fd(client_id);就是用户id,发送数据用,由系统自动生成如果断开重连则会改变
     */
    public function onOpen($server, $request )
    {
        Worker::sendToClient($request->fd ,('{"type":"welcome","id":'.$request->fd.'}') );
    }


    /**
     * 有消息时
     * @param int $client_id
     * @param string $message
     */
    public function onMessage($server, $frame)
    {
        // 获取客户端请求
        $message_data = json_decode($frame->data, true);
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
                $data = json_encode(
                    array(
                        'type' => 'update',
                        'id' => $frame->fd,
                        'angle' => $message_data["angle"] + 0,
                        'momentum' => $message_data["momentum"] + 0,
                        'x' => $message_data["x"] + 0,
                        'y' => $message_data["y"] + 0,
                        'life' => 1,
                        'name' => isset($message_data['name']) ? $message_data['name'] : 'Guest.' . $frame->fd,
                        'authorized' => false,
                    )
                );
//                Worker::sendToAll($data);
                self::sendToAll($server,$data);

                return;
            // 聊天
            case 'message':
                // 向大家说
                $new_message = array(
                    'type'=>'message',
                    'id'=>$frame->fd,
                    'message'=>$message_data['message'],
                );
//                Worker::sendToAll(json_encode($new_message));
                self::sendToAll($server,json_encode($new_message));
                return ;
        }
    }

    /**
     * 当用户断开连接时
     * @param integer $client_id 用户id
     */
    public function onClose($server, $fd)
    {
        // 广播 xxx 退出了,worker回调方式实现
//        Worker::sendToAll(json_encode(array('type'=>'closed', 'id'=>$fd)));
        // task异步广播
        self::sendToAll($server ,json_encode(array('type'=>'closed', 'id'=>$fd)));
    }

    //另外实现一个调用task进程异步的广播
    public static function sendToAll($server ,$data)
    {
        $server->task( array('type'=>'broadcast' , 'data'=>$data) );//非阻塞调用task
    }

    /**
     * 如果配置中开启task,那么就要求实现 onTask ,onFinish两个方法
     * 广播那里,可以考虑使用task进程进行异步广播,这样可以不阻塞woker进程
     * @param \swoole_server $server
     * @param int            $task_id
     * @param int            $from_id
     * @param string         $data
     * @return bool
     */
    public function onTask(\swoole_server $server,  $task_id,  $from_id,  $data)
    {
        if($data['type'] == 'broadcast'){
            $clients = $server->connections;//这个是TCP下可用的连接迭代器对象
            $send_data = $data['data'];
            foreach ( $clients as $fd ) {
                // 这里需要判断server的type,tcp udp 用send .ws用push httpServer就根本用不到这个
                $server->push( $fd, $send_data );//
            }
            return true;
        }

        return true;
    }

    /**
     * 如果配置中开启task,那么就要求实现 onTask ,onFinish两个方法
     * @return bool
     */
    public  function onFinish(\swoole_server $server,  $task_id,  $data)
    {
        echo 'task finish!';// 暂时不用做什么
        return true;
    }



}