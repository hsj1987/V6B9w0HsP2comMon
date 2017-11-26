<?php
namespace common\frame\api;

use common\frame\app;
use common\helper\output;
use common\helper\utils;
use common\helper\http;

class api_test_base extends api_base
{
    
    // 测试用例参数
    protected $test_cases;
    
    // 公共参数
    protected $common_params;

    public function prepare($request)
    {
        $this->test_cases = [];
    }
    
    public function run($request)
    {
        $pass = array();
        $faild = array();
        
        $url = utils::get_entry_url(app::$instance->app_id, 2) . '/' . $this->get_api_name();
        foreach ($this->test_cases as $key => $val) {
            $params = $val[0];
            $assert_result = $val[1];
            if (is_array($this->common_params)) {
                $params = array_merge($this->common_params, $params);
            }
            $result = http::curl(app::$instance->app_id, $url, $params);
            $res = json_decode($result, true);
            $result_code = is_array($res) ? $res['stat'] : $result;
            $is_pass = $result_code === $assert_result;
            $item = [
                'params' => $params,
                'assert_result' => $assert_result,
                'api_result' => is_array($res) ? $res : $result,
                'api_url' => $this->get_full_url($url, $params)
            ];
            if ($is_pass == true) {
                $pass[] = $item;
            } else {
                $faild[] = $item;
            }
        }
        $data = [
            'pass' => $pass,
            'faild' => $faild,
            'pass_count' => count($pass),
            'faild_count' => count($faild)
        ];
        return $data;
    }

    /**
     * 获取API请求全路径
     * @param string $url url
     * @param array/string $params 参数
     * @return string
     */
    protected function get_full_url($url, $params)
    {
        return $url . '?' . (is_array($params) ? http_build_query($params) : $params);
    }

    /**
     * 获取API名称
     */
    protected function get_api_name()
    {
        return substr(app::$instance->api_info['api_name'], 5);
    }
}

