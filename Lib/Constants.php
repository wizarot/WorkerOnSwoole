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


