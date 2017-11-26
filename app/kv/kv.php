<?php
namespace common\kv;

use common\pfs\dsn;
use common\kv\like_redis;
use common\helper\utils;

class kv
{

    /**
     * 根据DSN信息创建redis客户端实例
     * @param array $dsn DNS配置
     * @param string $kv_id KV ID
     * @param string $prefix redis前缀
     */
    public static function get_kv_by_dsn($dsn, $kv_id, $prefix = null)
    {
        $parameters = [
            'scheme' => 'tcp',
            'host' => $dsn['db_host'],
            'port' => $dsn['db_port']
        ];
        
        $options = null;
        if ($prefix !== null) {
            $options = [
                'prefix' => $prefix
            ];
        }
        $client = new like_redis($kv_id, $parameters, $options);
        return $client;
    }

    /**
     * 获取db实例
     * @param string $type kv类型：main、shop、osa、wx
     * @param string $prefix 前缀
     * @param string $shop_id 商户ID
     */
    public static function get_kv($type, $prefix = null, $shop_id = null)
    {
        switch ($type) {
            case 'main':
                return self::main_kv($prefix);
        }
    }

    /**
     * 获取main redis实例
     * @param string $prefix
     * @return 消息redis实例
     */
    public static function main_kv($prefix = null)
    {
        $id = $prefix === null ? '' : $prefix;
        static $main_kvs;
        if (! isset($main_kvs[$id])) {
            global $MAIN_KV_DSN;
            $kv = self::get_kv_by_dsn($MAIN_KV_DSN, 'main', $prefix);
            $main_kvs[$id] = $kv;
        }
        return $main_kvs[$id];
    }
}