<?php
/** 从workerman项目复制过来的
 */
namespace WorkerOnSwoole\lib;

/**
 * 数据库类
 */
class Db
{
    /**
     * 实例数组
     * @var array
     */
    protected static $instance = array();

    /**
     * 获取实例
     * @param string $config_name
     * @throws \Exception
     */
    public static function instance( $config_name )
    {
        global $php;

        if ( !isset( $php['config']['db'][$config_name] ) ) {
            echo '$php[\'config\'][\'db\'][\''.$config_name.'\'] not set';
            throw new \Exception( '$php[\'config\'][\'db\'][\''.$config_name.'\'] not set' );
        }

        if ( empty( self::$instance[ $config_name ] ) ) {
            $config = $php['config']['db'][$config_name];
            self::$instance[ $config_name ] = new DbConnection( $config[ 'host' ], $config[ 'port' ], $config[ 'user' ], $config[ 'password' ], $config[ 'dbname' ] );
        }

        return self::$instance[ $config_name ];
    }

    /**
     * 关闭数据库实例
     * @param string $config_name
     */
    public static function close( $config_name )
    {
        if ( isset( self::$instance[ $config_name ] ) ) {
            self::$instance[ $config_name ]->closeConnection();
            self::$instance[ $config_name ] = NULL;
        }
    }

    /**
     * 关闭所有数据库实例
     */
    public static function closeAll()
    {
        foreach ( self::$instance as $connection ) {
            $connection->closeConnection();
        }
        self::$instance = array();
    }
}
