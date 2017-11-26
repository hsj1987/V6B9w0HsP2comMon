<?php
namespace common\log;

/**
 * 日志输出到命令行
 *
 */
class output_print extends output_base
{
    /**
     * 写入日志
     * @param string $content 日志内容
     * @param string $params 自定义参数
     */
    public function write($content, $params = null)
    {
        echo $content . "\n";
    }
}