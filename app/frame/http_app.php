<?php
namespace common\frame;

use common\log\log;
use common\frame\error;
use common\helper\output;

class http_app extends app_base
{

    public $controller_route;

    public $action_name;

    /**
     * 执行
     */
    public function execute()
    {
        // 初始化控制器
        $controller = $this->_init_controller();
        return $controller->run();
    }
    
    /**
     * 输出事件
     * @param array/string $content 输出内容
     * @param array $params 自定义参数
     */
    public function on_output($content, $params = null)
    {
        if (! is_array($content) || $content['stat'] != ERR_UNDEFINDED) { // 非controller未定义才记录日志
            log::execute_start();
    
            $execute_time = round((EXECUTE_END - EXECUTE_START) * 1000);
            $log_id = log::execute_end($content, $execute_time);
    
            if (is_array($content)) {
                $content['log_id'] = $log_id;
            }
        }
    
        if ($params['redirect_url']) {
            header('Location: ' . $params['redirect_url']);
            exit();
        } else {
            output::write($content);
        }
    }
    
    /**
     * 初始化控制器
     */
    private function _init_controller()
    {
        // 获取controller信息
        $res = $this->_get_controller_info();
        if ($res['stat'] !== 0) {
            $err = new error();
            $err->output = $res;
            throw $err;
        }
        
        $controller_info = $res['data'];
        $this->controller_route = $controller_info['controller_route'];
        $this->action_name = isset($_REQUEST['action']) ? $_REQUEST['action'] : $this->get_config_value('action', 'view_name');
        
        // 记录LOG头部
        log::execute_start();
        
        // 实例化控制器
        require_once $controller_info['controller_path'];
        $controller = new $controller_info['controller_class']();
        return $controller;
    }

    /**
     * 获取controller信息
     */
    private function _get_controller_info()
    {
        // 获取访问路由
        $route = isset($_REQUEST['r']) ? $_REQUEST['r'] : $_SERVER['SCRIPT_NAME'];
        $route = strtolower(trim($route, '/'));
        if ($route == 'http.php') {
            $route = 'index';
        }
        if (strpos($route, '.php') !== false) {
            $route = substr($route, 0, strlen($route) - 4);
        }
        
        $controller_path = APP_ROOT . '/app/controllers/' . $route . '.php';
        
        // 判断路径是否有效
        if (! is_file($controller_path)) {
            return output::err_undefinded('controller');
        }
        
        // 获取class路径
        $class_path = str_replace('/', '\\', $route);
        $class_path = 'controllers\\' . $class_path;
        return output::ok([
            'controller_route' => $route,
            'controller_path' => $controller_path,
            'controller_class' => $class_path
        ]);
    }
}