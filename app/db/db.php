<?php
namespace common\db;

class db
{

    /**
     * 根据DSN信息创建DB实例
     * @param array $dsn DNS配置
     * @param string $db_id 数据库ID
     * @return like_medoo
     */
    public static function get_db_by_dsn($dsn, $db_id)
    {
        $options = [
            'database_type' => 'mysql',
            'database_name' => $dsn['db_name'],
            'server' => $dsn['db_host'],
            'port' => $dsn['db_port'],
            'username' => $dsn['db_user'],
            'password' => $dsn['db_pass'],
            'charset' => 'utf8',
            'option' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
            ]
        ];
        return new medoo($options, $db_id);
    }

    /**
     * 获取db实例
     * @param string $type db类型：main
     */
    public static function get_db($type)
    {
        switch ($type) {
            case 'main':
                return self::main_db();
        }
    }

    /**
     * 获取main库实例
     * @return like_medoo
     */
    public static function main_db()
    {
        static $main_db;
        if (! isset($main_db)) {
            global $MAIN_DB_DSN;
            $main_db = self::get_db_by_dsn($MAIN_DB_DSN, 'main');
        }
        return $main_db;
    }
}