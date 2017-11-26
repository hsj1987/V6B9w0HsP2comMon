<?php
namespace common\frame;

class app
{
    public static $instance;
    
    /**
     * 获取配置值
     * @param string $component
     * @param string $key
     */
    public static function get_config_value($component, $key = null)
    {
        return self::$instance->get_config_value($component, $key);
    }
}