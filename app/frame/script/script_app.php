<?php
namespace common\frame\script;

use common\log\log;
use common\frame\error;
use common\frame\app_base;
use common\helper\output;
use common\helper\utils;

class script_app extends app_base
{

    public function __construct($app_id, $config)
    {
        if (! is_array($config) || ! $config['argv']) {
            throw new error('$config[\'argv\'] not allow be empty');
        }
        parent::__construct($app_id, $config);
    }

    /**
     * 执行
     */
    public function execute()
    {
        // 初始化控制器
        $scripts = $this->_get_scripts();
        if (! $scripts) {
            echo "请输入待运行脚本\n";
            return;
        }
        
        $script_count = count($scripts);
        if ($script_count > 1) {
            log::info(str_repeat('=', 100));
            log::info('运行开始：有' . $script_count . '个脚本需要执行，log_id=' . log::get_log_id());
        }
        
        foreach ($scripts as $script) {
            $script_id = $script['script_id'];
            $params = $script['params'];
            
            $log_prefix = '脚本' . $script_id;
            
            try {
                log::info(str_repeat('=', 50));
                
                $script_file = APP_ROOT . '/script/' . $script_id . '.php';
                if (! file_exists($script_file)) {
                    log::info($log_prefix . '：对应脚本文件不存在。');
                    continue;
                }
                
                require_once $script_file;
                $script_class = 'app\\script\\' . $script_id;
                $script = new $script_class($params);
                $script->log_prefix = $log_prefix;
                $GLOBALS['SCRIPT_ID'] = $script_id;
                
                if ($params) {
                    log::info($log_prefix . '：输入参数：' . json_encode($params));
                }
                if (! $script->valid_env()) {
                    log::info($log_prefix . '：此脚本只能在' . (is_string($this->allow_env) ? $this->allow_env : implode(',', $this->allow_env)) . '环境上执行');
                } else {
                    log::info($log_prefix . '：执行开始！');
                    $script->run();
                    log::info($log_prefix . '：执行完成！');
                }
            } catch (Exception $e) {
                log::error($log_prefix . "：运行报错，跳过此脚本。", $e);
            }
        }
        log::info(str_repeat('=', 50));
        if ($script_count > 1) {
            log::info(str_repeat('=', 100));
        }
    }

    /**
     * 获取controller信息
     */
    private function _get_scripts()
    {
        $scripts = $this->config['argv'];
        array_shift($scripts);
        if (! $scripts) {
            return [];
        }
        
        $script_result = [];
        foreach ($scripts as $script) {
            if (strpos($script, '?') === false) {
                $script_result[] = [
                    'script_id' => $script,
                    'params' => []
                ];
            } else {
                $arr = explode('?', $script, 2);
                $script_id = $arr[0];
                $params_str = $arr[1];
                $params_arr = [];
                if ($params_str) {
                    $params = explode('&', $params_str);
                    foreach ($params as $param) {
                        $kvs = explode('=', trim($param), 2);
                        if (count($kvs) == 2) {
                            $params_arr[$kvs[0]] = $kvs[1];
                        }
                    }
                }
                $script_result[] = [
                    'script_id' => $script_id,
                    'params' => $params_arr
                ];
            }
        }
        return $script_result;
    }
}