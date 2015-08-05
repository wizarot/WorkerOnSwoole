<?php
/**
 * Created by PhpStorm.
 * User: will
 * Date: 15/8/4
 * Time: 下午6:13
 */

// 如果ini没设置时区，则设置一个默认的
if(!ini_get('date.timezone') )
{
    date_default_timezone_set('Asia/Shanghai');
}
// 显示错误到终端
ini_set('display_errors', 'on');



register_shutdown_function('handleFatal');
function handleFatal()
{
    $error = error_get_last();
    if (isset($error['type']))
    {
        switch ($error['type'])
        {
            case E_ERROR :
            case E_PARSE :
            case E_DEPRECATED:
            case E_CORE_ERROR :
            case E_COMPILE_ERROR :
                $message = $error['message'];
                $file = $error['file'];
                $line = $error['line'];
                $log = "$message ($file:$line)\nStack trace:\n";
                $trace = debug_backtrace();
                foreach ($trace as $i => $t)
                {
                    if (!isset($t['file']))
                    {
                        $t['file'] = 'unknown';
                    }
                    if (!isset($t['line']))
                    {
                        $t['line'] = 0;
                    }
                    if (!isset($t['function']))
                    {
                        $t['function'] = 'unknown';
                    }
                    $log .= "#$i {$t['file']}({$t['line']}): ";
                    if (isset($t['object']) && is_object($t['object']))
                    {
                        $log .= get_class($t['object']) . '->';
                    }
                    $log .= "{$t['function']}()\n";
                }
                if (isset($_SERVER['REQUEST_URI']))
                {
                    $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
                }
                error_log($log);
                $serv->send($this->currentFd, $log);
        }
    }
}