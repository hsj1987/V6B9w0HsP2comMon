<?php
namespace common\frame\script;

use common\helper\env;
use common\helper\utils;

abstract class script_base
{
    // 输入参数
    public $params;
    
    // 允许执行的环境，*表示所有环境，其它字符串表示允许单个环境执行，数组表示具体允许执行的环境
    public $allow_env = '*';

    public $log_prefix = '';

    public function __construct($params)
    {
        $this->params = $params;
        
        if (isset($params['env']) && $params['env'] != '') {
            $envs = explode(',', $params['env']);
            $this->allow_env = $envs;
        }
    }

    /**
     * 验证脚本运行环境
     * @return boolean
     */
    public function valid_env()
    {
        return $this->allow_env == '*' || env::check_allow_env($this->allow_env);
    }
    
    /**
     * 获取参数
     * @param string $param_name 参数名
     * @param string $default_value 默认值
     * @return 
     */
    public function get_param($param_name, $default_value = null)
    {
        return utils::get($this->params, $param_name, $default_value);
    }

    /**
     * 脚本 运行
     */
    public abstract function run();
}

