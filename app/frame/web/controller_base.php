<?php
namespace common\frame\web;

use common\helper\validate;
use common\helper\output;
use common\helper\utils;
use common\frame\app;
use common\frame\request;
use common\kv\kv;

abstract class controller_base
{
    // smarty实例
    protected $smarty;
    
    // 页面模板
    protected $page_view;
    
    // 是否分配URL参数到smarty
    protected $assign_url_params = false;
    
    // 是否分配版本到smarty
    protected $assign_version = false;

    // 是否分配页面route到smarty
    protected $assign_page_route = false;
    
    // 当前action
    protected $action;
    
    // action类型
    protected $action_type;
 // action、render
                            
    // 是否是API controller，如果为true则run_action而不会render
    protected $is_api = false;
    
    // 记录LOG
    public $need_log = true;

    public function __construct($action)
    {
        $this->action = $action;
        $this->action_type = $this->action == app::get_config_value('action', 'view_name') && ! $this->is_api ? 'render' : 'action';
    }

    /**
     * 运行
     * @param request $request
     */
    public function run($request)
    {
        // 初始化session
        $session_config = app::get_config_value('session');
        if ($session_config && $session_config['enabled']) {
            $this->_init_session($session_config);
        }
        
        // 初始化smarty
        $smarty_config = app::get_config_value('smarty');
        if ($this->action_type == 'render' && $smarty_config && $smarty_config['enabled']) {
            $this->smarty = $this->_init_smarty($smarty_config);
            
            if ($this->assign_url_params) {
                $params = $_GET;
                $this->assign('url_params', $params);
            }
            
            if ($this->assign_version) {
                $this->assign('version', app::$instance->version_info['hash']);
            }

            if ($this->assign_page_route) {
                $this->assign('page_route', app::$instance->controller_route);
            }
        }
        
        // 初始化事件
        $res = $this->on_init($this->action_type);
        if ($res['stat'] !== 0) {
            return $res;
        }
        
        // 运行action
        return $this->run_action($this->action);
    }

    /**
     * 初始化事件
     * @param string $action_type action/view
     */
    public function on_init($action_type)
    {
        return output::ok();
    }
    
    // 运行action
    public function run_action($action)
    {
        $action_method = app::get_config_value('action', 'prefix') . $action;
        if (is_callable([
            $this,
            $action_method
        ])) {
            $res = $this->$action_method();
        } else if ($this->action !== app::get_config_value('action', 'view_name')) {
            return output::err_undefinded('action');
        }

        if ($this->action_type == 'render' && $this->smarty && ($res === null || $res === true)) { // render action把null和true输出缓存render输出
            $view = $this->_get_page_view();
            $res = output::render($view);
        }
        return $res;  
    }
    
    // 呈现
    public function render($view = null)
    {
        $view = $this->_get_page_view($view);
        $this->smarty->display($view);
    }

    /**
     * 输出响应
     * @param array/string $content
     * @param string $step 执行步骤
     */
    public function output($content)
    {
        if (is_array($content)) {
            $smarty_config = app::get_config_value('smarty');
            if ($content['stat'] == STAT_RESPONSE_REDIRECT) {
                utils::redirect($content['data']['url']);
            } else if ($content['stat'] == STAT_RESPONSE_RENDER) {
                $this->render($content['data']['view']);
            } else if ($content['stat'] !== 0 && $this->action_type == 'render' && $this->smarty && $smarty_config['error_view']) {
                $this->assign('result', $content);
                $this->render($smarty_config['error_view']);
            } else {
                output::write($content);
            }
        } else {
            output::write($content);
        }
    }

    /**
     * 获取页面视图
     * @param string $view
     * @return string
     */
    private function _get_page_view($view = null)
    {
        if ($view === null) {
            $view = $this->page_view === null ? app::$instance->controller_route . '.html' : $this->page_view;
        }
        return $view;
    }
    
    // 初始化session
    private function _init_session($config)
    {
        static $is_session_start;
        if (! $is_session_start) {
            if($config['handler'] == 'redis') {
                $client = kv::get_kv($config['kv_type'], $config['kv_prefix']);
                $handler = new \Predis\Session\Handler($client);
                $handler->register();
            }
            session_start();
            $is_session_start = true;
        }
    }
    
    // 初始化smarty
    private function _init_smarty($config)
    {
        static $smarty;
        if (empty($smarty)) {
            require_once COMMON_APP_ROOT . '/lib/smarty/Smarty.class.php';
            $smarty = new \Smarty();
            // $smarty->error_reporting = 0;
            $smarty->setTemplateDir($config['template_dir']);
            $smarty->setCompileDir($config['compile_dir']);
            $smarty->addPluginsDir($config['plugins_dir']);
            $smarty->caching = false;
        }
        return $smarty;
    }
    
    // 保存变量
    protected function assign($tpl_var, $value = null, $nocache = false)
    {
        if ($value === null)
            $value = '';
        $this->smarty->assign($tpl_var, $value, $nocache);
    }
}

