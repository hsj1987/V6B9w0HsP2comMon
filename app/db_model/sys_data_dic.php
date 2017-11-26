<?php
namespace common\db_model;

use common\db\db_model_base;

class sys_data_dic extends db_model_base
{
    public $db_type = 'main';
    
    /**
     * 获取配置数据
     * @param int $src 数据源
     * @param string $ns 命名空间
     * @param string $key 键
     */
    public function get_data_value($src, $ns, $key)
    {
        return $this->get('data_value', [
            'AND' => [
                'data_src' => $src,
                'data_ns' => $ns,
                'data_key' => $key
            ]
        ]);
    } 
}