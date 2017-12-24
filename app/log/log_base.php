<?php
namespace common\log;

use common\helper\utils;

class log_base
{
    
    // 是否记录SQL LOG
    public $is_log_sql = false;
    
    // 是否记录KV LOG
    public $is_log_kv = false;
    
    // 是否记录HTTP API请求 LOG
    public $is_log_http = false;

    // 是否记录执行 LOG
    public $is_log_execute = false;
    
    // LOG ID
    public $log_id;
    
    // 日志输出方式：支持logagent、file多选，config中value支持function，总体格式如下：
    public $outputs = [];
    
    // 配置数据
    private $config;
    
    private $log_execute_start = false;

    public function __construct($config)
    {
        $this->log_id = date('His', time()) . utils::uuid(10);
        $this->config = config;
        $this->_init($config);
    }
    
    private function _init($config)
    {
        $logs = ['sql', 'kv', 'http', 'execute'];
        
        // 初始化LOG实例
        if ($config['outputs']) {
            foreach ($config['outputs'] as $type => $output_config) {
                $class_name = isset($output_config['class']) ? $output_config['class'] : 'common\log\output_' . $type;
                $output = new $class_name($this, $output_config);
                $this->outputs[$type] = $output;
                foreach ($logs as $log_type) {
                    if ($output->is_output_type($log_type)) {
                        $this->{'is_log_' . $log_type} = true;
                    }
                }
            }
        }
    }
    
    // 关闭log记录
    public function close()
    {
        $this->is_log_sql = false;
        $this->is_log_kv = false;
        $this->is_log_http = false;
        $this->is_log_execute = false;
        $this->outputs = [];
    }
    
    // 打开log记录
    public function open()
    {
        $this->_init($this->config);
    }
    
    // 记录SQL LOG
    public function sql($content)
    {
        if ($this->is_log_sql) {
            $this->log_content('sql', $content);
        }
    }
    
    // 记录redis LOG
    public function kv($content)
    {
        if ($this->is_log_kv) {
            $this->log_content('kv', $content);
        }
    }
    
    // 记录HTTP LOG
    public function http($content)
    {
        if ($this->is_log_http) {
            $this->log_content('http', $content);
        }
    }
    
    // 记录INFO LOG
    public function info($content, $key = null)
    {
        if ($key !== null) {
            $content = [
                $key => $content
            ];
        }
        $this->log_content('info', $content);
    }
    
    // 记录ERROR LOG
    public function error($content, $exception = null)
    {
        if ($exception) {
            $exception_content = [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'debug_trace' => $exception->getTraceAsString()
            ];
            $content .= "\n" . json_encode($exception_content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $this->log_content('error', $content);
    }

    /**
     * 记录执行开始
     * @param string $params 自定义参数
     */
    public function execute_start($params = null)
    {
        if ($this->is_log_execute) {
            $content = [
                'execute_step' => 'start'
            ];
            $this->log_content('info', $content, true, $params);
            $this->log_execute_start = true;
        }
    }

    /**
     * 记录执行结束
     * @param string/array $output 输出内容
     * @param int $execute_time 执行时间
     * @param array $params 自定义参数
     */
    public function execute_end($output, $execute_time, $params = null)
    {
        if ($this->is_log_execute) {
            $content = [
                'execute_step' => 'end',
                'execute_time' => $execute_time,
                'output' => $output
            ];
            $this->log_content('info', $content, true, $params);
        }
    }

    /**
     * 记录LOG内容
     * @param string $type LOG类型
     * @param string/array $content LOG内容
     * @param boolean $is_append_write 是否追加且写
     * @param array $params 其它参数
     * @return boolean
     */
    protected function log_content($type, $content, $is_append_write = false, $params = null)
    {
        try {
            foreach ($this->outputs as $k => $output) {
                if (! $output->is_output_type($type)) {
                    continue;
                }
                
                $output_content = $output->format_content_item($type, $content);
                if ($output->write_mode == 2) {
                    $output->append($output_content, $params);
                }
                
                if ($output->write_mode == 1 || ($output->write_mode == 2 && $is_append_write)) {
                    $output->write($output_content, $params);
                    $output->clear();
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
