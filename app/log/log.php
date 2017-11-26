<?php
namespace common\log;

class log
{
    // 使用common的项目必须给$instance赋值一个log对象（log_request或log_script）
    public static $instance;

    public static function is_log_sql()
    {
        return self::$instance && self::$instance->is_log_sql;
    }

    public static function is_log_kv()
    {
        return self::$instance && self::$instance->is_log_kv;
    }

    public static function is_log_http()
    {
        return self::$instance && self::$instance->is_log_http;
    }
    
    // 记录SQL LOG
    public static function sql($content)
    {
        if (self::$instance) {
            return self::$instance->sql($content);
        }
    }
    
    // 记录redis LOG
    public static function kv($content)
    {
        if (self::$instance) {
            return self::$instance->kv($content);
        }
    }
    
    // 记录HTTP LOG
    public static function http($content)
    {
        if (self::$instance) {
            return self::$instance->http($content);
        }
    }
    
    // 记录INFO LOG
    public static function info($content, $key = null)
    {
        if (self::$instance) {
            return self::$instance->info($content, $key);
        }
    }
    
    // 记录ERROR LOG
    public static function error($content, $exception = null)
    {
        if (self::$instance) {
            return self::$instance->error($content, $exception);
        }
    }

    /**
     * 记录执行开始
     * @param string $params 自定义参数
     */
    public static function execute_start($params = null)
    {
        if (self::$instance) {
            return self::$instance->execute_start($params);
        }
    }

    /**
     * 记录执行结束
     * @param string/array $output 输出内容
     * @param int $execute_time 执行时间
     * @param array $params 自定义参数
     */
    public static function execute_end($output, $execute_time, $params = null)
    {
        if (self::$instance) {
            return self::$instance->execute_end($output, $execute_time, $params);
        }
    }
    
    public static function get_output($type)
    {
        if (self::$instance) {
            return self::$instance->outputs[$type];
        }
    }
    
    public static function get_log_id()
    {
        if (self::$instance) {
            return self::$instance->log_id;
        }
    }
}