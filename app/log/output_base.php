<?php
namespace common\log;

/**
 * 日志输出基类
 */
abstract class output_base
{
    // 写模式：1-直接写，2-追加后再写
    public $write_mode = 1;
    
    // 日志内容，适用于write_mode=2
    public $contents;
    
    // log实例
    public $log;
    
    // 配置
    public $config;

    function __construct($log_instance, $config)
    {
        $this->log = $log_instance;
        $this->config = $config;
    }
    
    /**
     * 判断是否输出某类型的log
     * @param string $type
     * @return boolean
     */
    public function is_output_type($type)
    {
        return in_array($type, ['info', 'error']) || ! isset($this->config['logs']) || in_array($type, $this->config['logs']);
    }

    /**
     * 格式化单个日志内容
     * @param string $type 日志类型
     * @param string/array $content 日志内容
     * @return string 格式化后的日志内容
     */
    public function format_content_item($type, $content)
    {
        $content = is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $content;
        $mtime = microtime(true);
        list (, $ms) = explode('.', $mtime);
        $ms = str_pad($ms, 4, 0, STR_PAD_RIGHT);
        $time = date('His', $mtime) . '.' . $ms;
        return strtoupper($type) . '_LOG ' . $time . ':' . $content;
    }

    /**
     * 获取日志级别名称
     * @return string 日志级别名称
     */
    public function get_level_name($level)
    {
        $levels = [
            LOG_LEVEL_DEBUG => 'debug',
            LOG_LEVEL_INFO => 'info',
            LOG_LEVEL_NOTICE => 'notice',
            LOG_LEVEL_WARNING => 'warning',
            LOG_LEVEL_ERROR => 'error'
        ];
        return $levels[$level];
    }

    /**
     * 获取输出配置里的值
     * @param string $key 配置键
     */
    public function get_config_value($key, $params = null)
    {
        if (is_callable($this->config[$key])) {
            return $this->config[$key]($params);
        } else {
            return $this->config[$key];
        }
    }

    /**
     * 追加LOG
     * @param string $content 日志内容
     * @param string $params 自定义参数
     */
    public function append($content, $params = null)
    {
        $this->contents .= $content . "\n";
    }
    
    /**
     * 清除LOG内容
     */
    public function clear()
    {
        $this->contents = null;
    }
    
    /**
     * 写入日志
     * @param string $content 日志内容
     * @param string $params 自定义参数
     */
    public abstract function write($content, $params = null);
}