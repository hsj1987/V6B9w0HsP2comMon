<?php
namespace common\log;

class log_script extends log_base
{

    /**
     * 输出执行进度
     * @param int $total 总执行数量
     * @param int $finish_count 已完成执行数量
     */
    public function progress($total, $finish_count)
    {
        printf("进度: %d/%d\r", $finish_count, $total);
    }
}
