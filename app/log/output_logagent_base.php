<?php
namespace common\log;

use common\helper\utils;

/**
 * 日志输出到LOGAGENT
 */
abstract class output_logagent_base extends output_base
{

    public $write_mode = 2;

    abstract function get_from($params = null);
    
    abstract function get_sender($params = null);
    
    abstract function get_subject($params = null);
    
    public function get_version($params = null)
    {
        return '0.0';
    }
    
    /**
     * 写入日志
     * @param string $content 日志内容
     * @param string $params 自定义参数
     */
    public function write($content, $params = null)
    {
        try {
            $level = utils::get($params, 'log_level', LOG_LEVEL_INFO);
            $level_name = $this->get_level_name($level);
            $params['level_name'] = $level_name;
            
            $header = '';
            $header .= 'Date:' . date(DATE_ISO8601, time()) . "\n";
            $header .= 'From:' . $this->get_from($params) . "\n";
            $header .= 'Sender:' . $this->get_sender($params) . "\n";
            $header .= 'Message-ID:' . $params['log_id'] . "\n";
            $header .= 'Subject:' . $this->get_subject($params) . "\n";
            $header .= 'X-Version: 1.0' . $this->get_version($params) . "\n";
            $header .= 'Priority:' . $level . "\n";
            
            if (isset($params['ref_log_id'])) {
                $header .= 'References:' . $params['ref_log_id'] . "\n";
            }
            if ($params['execute_step'] == 'end') {
                $head .= 'X-Metrics:stat=' . $params['stat'] . "\n";
            }
            
            $msg = $header . "\n" . $this->contents;
            if (function_exists('socket_create')) {
                $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                socket_connect($sock, PFS_LOG_DOMAIN, PFS_LOG_PORT);
                socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
                socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 3, 'usec' => 0]);
                socket_write($sock, $msg, strlen($msg));
                socket_close($sock);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}