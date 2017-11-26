<?php
namespace common\log;

use common\helper\utils;
use common\helper\arr;

class log_request extends log_base
{
    /**
     * 记录执行开始
     * @param string $params 自定义参数
     */
    public function execute_start($params = null)
    {
        try {
            
            $post_str = file_get_contents('php://input');
            $post = (strlen($post_str) && empty($_POST)) ? $post_str : $_POST;
            $content['execute_step'] = 'start';
            $content = arr::merge_by_keys($content, $params, [
                'version_num',
                'version_hash',
                'version_make_time'
            ]);
            $content['server'] = [
                'server_addr' => $_SERVER['SERVER_ADDR'],
                'remote_addr' => $_SERVER['REMOTE_ADDR']
            ];
            $content['get'] = $_GET;
            $content['post'] = $post;
            $params['log_id'] = $this->log_id;
            $params['execute_step'] = 'start';
            
            $this->log_content('info', $content, true, $params);
            $this->log_execute_start = true;
            return $this->log_id;
        } catch (\Exception $e) {
            return false;
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
        try {
            $content['execute_step'] = 'end';
            $content['execute_time'] = $execute_time;
            if (isset($params['step'])) {
                $content['child_step'] = $params['step'];
            }
            $content['output'] = $output;
            
            $params['log_id'] = $this->log_id;
            $params['execute_step'] = 'end';
            $params['stat'] = isset($output['stat']) ? $output['stat'] : 0;
            
            $this->log_content('info', $content, true, $params);
            return $this->log_id;
        } catch (\Exception $e) {
            return false;
        }
    }
}
