<?php
namespace common\frame\web;

use common\log\log;
use common\frame\error;
use common\frame\request;
use common\helper\output;
use common\helper\file;
use common\helper\utils;
use common\frame\app_base;
use common\helper\env;

class web_app extends app_base
{

    public $controller;

    public $controller_route;

    /**
     * 执行
     */
    public function execute()
    {
        // 初始化controller
        $controller = $this->_init_controller();
        if (is_array($controller) && $controller['stat'] !== 0) {
            return [
                'res' => $controller,
                'params' => [
                    'step' => 'init'
                ]
            ];
        }
        
        $this->controller = $controller;
        
        // 记录请求开始LOG
        $this->log_execute_start([
            'step' => 'init'
        ]);
        
        $request = new request();
        
        $result = self::run_controller($controller, $request);
        return [
            'res' => $result['res'],
            'params' => [
                'step' => $result['step']
            ]
        ];
    }

    /**
     * 运行controller
     * @param object $controller controller实例
     * @param array $request 请求实例
     * @return array 输出结果，结构为：['step' => res]
     */
    public static function run_controller($controller, $request)
    {
        $res = $controller->run($request);
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
            if (log::$instance && ! log::$instance->log_execute_start) {
                $this->log_execute_start($params);
            }
            
            $execute_time = round((EXECUTE_END - EXECUTE_START) * 1000);
            
            $log_id = $this->log_execute_end($content, $execute_time, $params);
            
            if (is_array($content) && $log_id) {
                $content['log_id'] = $log_id;
            }
        }
        
        if ($this->controller) {
            $this->controller->output($content);
        } else {
            output::write($content);
        }
    }

    /**
     * 初始化controller
     * @return controller
     */
    public function _init_controller()
    {
        // 获取版本信息
        $version_info = $this->_get_version_info();
        $this->version_info = $version_info;
        
        // 获取controller信息
        $res = $this->_get_controller_info();
        if ($res['stat'] !== 0) {
            return $res;
        }
        $controller_info = $res['data'];
        $this->controller_route = $controller_info['controller_route'];
        
        // 实例化控制器
        require_once $controller_info['controller_path'];
        $controller = new $controller_info['controller_class']($controller_info['action']);
        return $controller;
    }

    public function log_execute_start($params)
    {
        if ($this->controller->need_log || in_array($params['step'], [
            'exception',
            'shutdown'
        ])) {
            log::execute_start();
        }
    }

    public function log_execute_end($content, $execute_time, $params)
    {
        if ($this->controller->need_log || in_array($params['step'], [
            'exception',
            'shutdown'
        ])) {
            return log::execute_end($content, $execute_time, $params);
        }
    }

    /**
     * 获取版本信息
     * @return array
     */
    private function _get_version_info()
    {
        global $VERSION_INFO;
        if (env::is_test()) {
            $VERSION_INFO = [
                'num' => time(),
                'make_time' => date('Y-m-d', time())
            ];
        }
        return $VERSION_INFO;
    }

    /**
     * 获取controller信息
     */
    private function _get_controller_info()
    {
        // 获取访问路由
        if (! utils::is_empty($_REQUEST['r'])) {
            $route = $_REQUEST['r'];
        } else {
            $route = $_SERVER['REDIRECT_URL'];
            if ($route == NULL) {
                $route = 'http.php';
            }
        }
        $route = strtolower(trim($route, '/'));
        if(substr($route, -5) == '.html') {
            $route = substr($route, 0, strlen($route) - 5) . '/index';
        }
        $left_uri = $this->get_config_value('controller', 'uri');
        if ($left_uri == '/') {
            $left_uri = '';
        }
        $route = substr($route, strlen($left_uri));
        if ($route == 'http.php') {
            $controller_route = $this->get_config_value('controller', 'default_name');
            $controller_path = $this->get_config_value('controller', 'prefix') . $controller_route;
            $action = $this->get_config_value('action', 'view_name');
        } else if (strpos($route, '.php') !== false) {
            $route = substr($route, 0, strlen($route) - 4);
        }
        if (! $controller_route) {
            $arr = explode('/', $route);
            $arr_cnt = count($arr);
            if ($arr_cnt == 1) {
                $controller_route = $route;
                $controller_path = $this->get_config_value('controller', 'prefix') . $controller_route;
                $action = $this->get_config_value('action', 'view_name');
            } else {
                $action = array_pop($arr);
                $controller_route = implode('/', $arr);
                $arr_cnt = count($arr);
                $arr[$arr_cnt - 1] = $this->get_config_value('controller', 'prefix') . $arr[$arr_cnt - 1];
                $controller_path = implode('/', $arr);
            }
        }
        $controller_full_path = APP_ROOT . '/' . $this->get_config_value('controller', 'dir') . '/' . $controller_path . '.php';
        // 判断路径是否有效
        if (! is_file($controller_full_path)) {
            return output::err_undefinded();
        }
        
        // 获取class路径
        $class_path = str_replace('/', '\\', $controller_path);
        $class_path = 'app\\controller\\' . $class_path;
        return output::ok([
            'controller_route' => $controller_route,
            'controller_path' => $controller_full_path,
            'controller_class' => $class_path,
            'action' => $action
        ]);
    }
}
