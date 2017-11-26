<?php
namespace common\frame\api;

use common\log\log;
use common\frame\error;
use common\frame\request;
use common\helper\output;
use common\helper\env;
use common\helper\file;
use common\helper\utils;
use common\frame\app_base;

class api_app extends app_base
{
    public $api;

    public $api_info;
    
    /**
     * 执行
     */
    public function execute()
    {
        // 初始化API
        $api = $this->_init_api();
        if (is_array($api) && $api['stat'] !== 0) {
            return [
                'res' => $api,
                'params' => [
                    'step' => 'init_api'
                ]
            ];
        }
        
        $this->api = $api;
        
        // 记录请求开始LOG
        $this->log_execute_start();
        
        $result = self::run_api($api, $api->request);
        return [
            'res' => $result['res'],
            'params' => [
                'step' => $result['step']
            ]
        ];
    }

    /**
     * 运行API
     * @param object $api API实例
     * @param array $request 请求实例
     * @return array 输出结果，结构为：['step' => res]
     */
    public static function run_api($api, $request)
    {
        // API验证
        $res = $api->validate($request);
        if ($res['stat'] !== 0) {
            return [
                'step' => 'validate',
                'res' => $res
            ];
        }
        
        // API预处理
        $res = $api->prepare($request);
        if ($res !== null && $res !== true) {
            return [
                'step' => 'prepare',
                'res' => $res
            ];
        }
        
        // API运行
        $res = $api->run($request);
        return [
            'step' => 'run',
            'res' => $res
        ];
    }

    /**
     * 输出事件
     * @param array/string $content 输出内容
     * @param array $params 自定义参数
     */
    public function on_output($content, $params = null)
    {
        if (! is_array($content) || $content['stat'] != ERR_UNDEFINDED) { // 非未定义才记录日志
            if (log::$instance && ! log::$instance->log_id)
                $this->log_execute_start();
            
            $execute_time = round((EXECUTE_END - EXECUTE_START) * 1000);
            
            $log_id = $this->log_execute_end($content, $execute_time, $params);
            
            if (is_array($content) && $log_id) {
                $content['log_id'] = $log_id;
            }
        }
        
        if (is_array($content)) {
            if ($content['stat'] == STAT_RESPONSE_REDIRECT) {
                utils::redirect($content['data']['url']);
            } else if ($this->api) {
                $this->api->output($content);
            } else {
                output::write($content);
            }
        } else {
            output::write($content);
        }
    }

    /**
     * 初始化API
     * @return api
     */
    public function _init_api()
    {
        // 获取版本信息
        $version_info = $this->_get_version_info();
        $this->version_info = $version_info;
        
        // 获取API信息
        $res = $this->_get_api_info();
        if ($res['stat'] !== 0) {
            return $res;
        }
        
        $api_info = $res['data'];
        $this->api_info = $api_info;
        
        $request = new request();
        
        // 创建API实例
        require $api_info['file_path'];
        $api = new $api_info['class_name']($request);
        return $api;
    }
    
    public function log_execute_start()
    {
        if ($this->api->need_log || in_array($params['step'], ['exception', 'shutdown'])) {
            log::execute_start();
        }
    }
    
    public function log_execute_end($content, $execute_time, $params)
    {
        if ($this->api->need_log || in_array($params['step'], ['exception', 'shutdown'])) {
            return log::execute_end($content, $execute_time, $params);
        }
    }

    /**
     * 获取版本信息
     * @return array
     */
    private function _get_version_info()
    {
        $version_lines = file::get_line(APP_ROOT . '/rev', 0, 2);
        if (! $version_lines) {
            return [];
        }
        
        list ($version, $make_date) = $version_lines;
        $version = trim($version);
        $version_arr = explode('.', $version, 3); // 版本管理
        return [
            'num' => $version_arr[0] . '.' . $version_arr[1],
            'hash' => $version_arr[2],
            'make_time' => $make_date
        ];
    }

    /**
     * 获取API信息
     */
    private function _get_api_info()
    {
        $uri = substr($_SERVER['SCRIPT_NAME'], 1);
        if (empty($uri)) {
            return output::err_undefinded('api');
        }
        
        list ($uri) = explode('/', $uri);
        $uri = explode('.', $uri, 2);
        if (count($uri) != 2) {
            return output::err_undefinded('api');
        }
        $api_name = $uri[0] . '.' . $uri[1];
        $is_test = $uri[0] == 'test';
        
        // 判断是否开启测试模式
        if (! env::is_test() && $is_test) {
            return output::err_undefinded('api');
        }
        
        // 获取api路径和类名
        if ($is_test) {
            $test_uri = explode('.', $uri[1], 2);
            $api_path = $uri[0] . '/' . $test_uri[0];
            $namespace_part = $uri[0] . '\\' . $test_uri[0];
            $file_name = $test_uri[1];
        } else {
            $api_path = $uri[0];
            $namespace_part = $uri[0];
            $file_name = $uri[1];
        }
        $file_name = str_replace(array(
            '.',
            '-'
        ), array(
            '_',
            '_'
        ), $file_name);
        $path = APP_ROOT . '/api/' . $api_path . '/' . $file_name . '.php';

        // 判断路径是否有效
        if (! is_file($path)) {
            return output::err_undefinded('api');
        }
        
        $class_name = 'app\\api\\' . $namespace_part . '\\' . $file_name;
        return output::ok([
            'api_name' => $api_name,
            'is_test' => $is_test,
            'file_path' => $path,
            'class_name' => $class_name
        ]);
    }
}