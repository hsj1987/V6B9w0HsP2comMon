<?php
namespace common\helper;

/**
 * 运行环境工具类
 */
class env
{

    public static function is_production()
    {
        return self::is_env('production');
    }

    public static function is_test()
    {
        return ! self::is_production();
    }

    /**
     * 判断当前运行环境
     * @param string $env_name
     * @return boolean
     */
    public static function is_env($env_name)
    {
        $curr_env_name = self::get_env_config();
        return $curr_env_name == $env_name;
    }
    
    /**
     * 判断当前运行环境是否是允许的环境
     * @param string/array $allow_env
     * @return boolean
     */
    public static function check_allow_env($allow_env)
    {
        $curr_env_name = self::get_env_config();
        return is_array($allow_env) ? in_array($curr_env_name, $allow_env) : $curr_env_name == $allow_env;
    }

    /**
     * 获取环境变量
     * @param string $key 键
     * @return array/string 环境变量
     */
    public static function get_env_config($key = 'name')
    {
        return CURR_ENV_NAME;
    }
}