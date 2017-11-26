<?php
namespace common\kv;

require_once COMMON_APP_ROOT . '/lib/predis/autoload.php';

/**
 * redis 客户端
 */
class like_redis extends \Predis\Client
{
    public $kv_id;
    
    function __construct($kv_id, $parameters = null, $options = null) {
        parent::__construct($parameters, $options);
        $this->kv_id = $kv_id;
    } 
    
    
    /**
     * 设置缓存
     * 
     * @param string $name 缓存名称
     * @param unknown $value 缓存值           
     * @param int $expired 过期时间（秒）           
     */
    public function set_cache($name, $value, $expired)
    {
        if(is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $this->setex($name, $expired, $value);
        return true;
    }

    /**
     * 获取缓存
     * 
     * @param string $name 缓存名称            
     * @return string
     */
    public function get_cache($name)
    {
        if ($data = $this->get($name)) {
            return $data;
        }
        return null;
    }

    /**
     * 移除缓存
     * 
     * @param string $name 缓存名称           
     * @return boolean
     */
    public function remove_cache($name)
    {
        $this->del($name);
        return true;
    }
}