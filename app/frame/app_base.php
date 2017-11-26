<?php
namespace common\frame;

use common\helper\output;
use common\frame\error;
use common\frame\app;

abstract class app_base
{

    public $version_info = [
        'num' => '0.0',
        'hash' =>'000000',
        'make_time' => '1970-01-01'
    ];
    
    public $app_id;
    
    public $config;

    public function __construct($app_id, $config = null)
    {
        $this->app_id = $app_id;
        $this->config = $config;
    }

    /**
     * APP运行
     */
    public function run()
    {
        // 初始化APP
        $this->init();
        
        $result = $this->execute();
        
        $this->output($result['res'], $result['params']);
    }

    /**
     * APP初始化
     */
    public function init()
    {
        // 初始化异常、错误、关闭处理
        $this->_init_exception_handler();
        $this->_init_error_handler();
        $this->_init_shutdown_handler();
        
        $this->on_init();
    }

    /**
     * 初始化事件
     * @param unknown $content
     * @return boolean
     */
    public function on_init()
    {
        return true;
    }

    /**
     * 执行
     */
    public abstract function execute();

    /**
     * 输出
     * @param array/string $content 输出内容
     * @param array $params 自定义参数
     */
    public function output($content, $params = null)
    {
        if (! defined('EXECUTE_END'))
            define('EXECUTE_END', microtime(true));
        
        $this->on_output($content, $params);
    }

    /**
     * 输出事件
     * @param array/string $content 输出内容
     * @param array $params 自定义参数
     */
    public function on_output($content, $params = null)
    {
        return true;
    }
    
    /**
     * 获取配置值
     * @param string $component
     * @param string $key
     */
    public function get_config_value($component, $key = null)
    {
        return $key === null ? $this->config[$component] : $this->config[$component][$key];
    }

    /**
     * 初始化异常处理
     */
    private function _init_exception_handler()
    {
        // 定义异常处理
        $exception_handler = function ($err) {
            $res = output::exception($err);
            app::$instance->output($res, [
                'step' => 'exception',
                'log_level' => LOG_LEVEL_ERROR
            ]);
        };
        
        set_exception_handler($exception_handler);
    }

    /**
     * 初始化错误处理（error_handler里throw exception会被exception_handler处理）
     * @throws \common\frame\error
     */
    private function _init_error_handler()
    {
        // 定义错误处理
        $error_handler = function ($err_code, $err_msg, $err_file, $err_line) {
            if (error_reporting() === 0 || $err_code === E_NOTICE) {
                return;
            }
            
            $err = new error($err_msg, $err_code);
            $err->set_file($err_file);
            $err->set_line($err_line);
            throw $err;
        };
        set_error_handler($error_handler);
    }

    /**
     * 初始化关闭处理（shutdown_handler里throw exception不会被exception_handler处理）
     * @throws \common\frame\error
     */
    private function _init_shutdown_handler()
    {
        $shutdown_handler = function () {
            $status = connection_status();
            $status_msg = [
                CONNECTION_ABORTED => 'PHP连接中断',
                CONNECTION_TIMEOUT => 'PHP连接超时'
            ];
            if (array_key_exists($status, $status_msg)) {
                $msg = $status_msg[$status];
                $res = output::err_internal($msg);
                app::$instance->output($res, [
                    'step' => 'shutdown'
                ]);
            } else {
                $err = error_get_last();
                if ($err && $err['type'] !== E_NOTICE) {
                    $error = new error($err['message'], $err['type']);
                    $error->set_file($err['file']);
                    $error->set_line($err['line']);
                    $res = output::exception($error);
                    app::$instance->output($res, [
                        'step' => 'shutdown'
                    ]);
                }
            }
        };
        
        register_shutdown_function($shutdown_handler);
    }
}