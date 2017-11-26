<?php
namespace common\log;

/**
 * 日志输出到文件
 */
class output_file extends output_base
{

    /**
     * 写入日志
     * @param string $content 日志内容
     * @param string $params 自定义参数
     */
    public function write($content, $params = null)
    {
        $file_path = $this->get_file_dir($params) . '/' . $this->get_file_name($params);
        file_put_contents($file_path, $content . "\n", FILE_APPEND);
    }

    /**
     * 获取LOG文件目录
     * @param array $params
     * @return string
     */
    public function get_file_dir($params = null)
    {
        $file_dir = $this->get_config_value('file_dir');
        if (! $file_dir) {
            $file_dir = dirname(APP_ROOT) . '/logs/';
        }
        if (! file_exists($file_dir)) {
            mkdir($file_dir, 0755, true);
        }
        return $file_dir;
    }

    public function get_file_name($params = null)
    {
        $file_name = $this->get_config_value('file_name');
        if (! $file_name) {
            $file_name = date('Ymd', time()) . '.log';
        }
        return $file_name;
    }
}