<?php
namespace common\kv;

use common\log\log;
use common\helper\utils;

/**
 * MODEL基类
 */
class kv_model_base
{
    
    // kv实例
    public $kv;
    
    // kv type
    public $kv_type = 'main';
    
    // 表名
    public $tblname;

    /*
     * 索引分割列，指明此列后：
     * 1. where条件和插入数据必须包括此列的值，不能为空，否则报错；
     * 2. 修改数据时忽略此字段的修改；
     * 3. idx_str_cols和idx_num_cols不允许包括此列
     */
    public $idx_partition_col = null;
    
    // 字符串索引列
    public $idx_str_cols = [];
    
    // 数值索引列
    public $idx_num_cols = [];
    
    // 插入时默认值
    public $insert_defaults = [];
    
    // 修改时默认值
    public $update_defaults = [];

    function __construct($kv = null)
    {
        if ($kv === null) {
            $this->kv = kv::get_kv($this->kv_type);
        } else {
            $this->kv = $kv;
        }
        
        $this->tblname = self::table_name();
        
        // 去除索引中包括索引分割列
        if ($this->idx_partition_col) {
            if (in_array($this->idx_partition_col, $this->idx_str_cols)) {
                unset($this->idx_str_cols[$this->idx_partition_col]);
            }
            if (in_array($this->idx_partition_col, $this->idx_num_cols)) {
                unset($this->idx_num_cols[$this->idx_partition_col]);
            }
        }
    }

    /**
     * 实例化构造
     * @param like_redis $kv
     * @return kv_model
     */
    public static function instance($kv = null)
    {
        static $instances;
        $class = get_called_class();
        $instance_id = $kv === null ? $class : $kv->kv_id . ':' . self::table_name();
        if (! isset($instances[$instance_id])) {
            $instances[$instance_id] = new $class($kv);
        }
        return $instances[$instance_id];
    }
    
    // 获取当前模型对应表名
    public static function table_name()
    {
        $class = get_called_class();
        $class = explode('\\', $class);
        $class = array_pop($class);
        return $class;
    }

    /**
     * 搜索数据
     * @param array $columns 需要返回的列
     * @param array $where 过滤条件，每个条件为且的关系，
     *        索引分割列只支持=（如果有索引分割列且where条件中包含字符串索引或数值索引列，则where条件一定要包含索引分割列），
     *        字符串索引只支持：=、IN，
     *        数值索引和非索引列支持：=、>、<、>=、<=、<>、IN、><、!
     *        格式为：[
     *        "<column>[<operator>]": <filter>,
     *        ... ...
     *        ]
     *        注意：
     *        1. <>表示BETWEEN，><表示NOT BETWEEN
     *        2. operator为IN、<>、><时，filter必须为数组
     *        3. 如果不指定operator且filter为数组，则默认operator为IN
     *        4. column为id时，只支持=和IN
     * @param string $sort 排序方式，格式为“字段名:排序方式”，排序方式：0-升序，1-降序
     * @param int $page_no 页号
     * @param int $page_size 每页行数
     * @return array 如果需要分页（page_no或page_size不为NULL），返回格式为：["total": 100, "data": 数据列表]，否则直接返回：数据列表
     */
    function select($columns = ['id'], $where = null, $sort = null, $page_no = null, $page_size = null)
    {
        $starttime = microtime(true);
        try {
            $res = $this->_select($columns, $where, $sort, $page_no, $page_size);
            $need_page = $page_no || $page_size;
            $result = count($need_page ? $res['data'] : $res);
            return $res;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 搜索数据
     */
    private function _select($columns = ['id'], $where = null, $sort = null, $page_no = null, $page_size = null)
    {
        // 获取where中索引列过滤后的id
        $ids = $this->_get_where_key_ids($where);
        
        // 获取详情数据
        $datas = [];
        $other_wheres = [];
        if ($ids) {
            // 过滤掉有索引但是无数据的id
            $pipe = $this->kv->pipeline();
            foreach ($ids as $id) {
                $pipe->exists($this->tblname . ':' . $id);
            }
            $exists_list = $pipe->execute();
            $ids2 = [];
            foreach ($exists_list as $key => $exists) {
                if ($exists) {
                    $ids2[] = $ids[$key];
                }
            }
            $ids = $ids2;
            
            $idx_cols = array_merge($this->idx_str_cols, $this->idx_num_cols, [
                'id'
            ]);
            $other_where_cols = $this->_get_other_where_cols($where, $idx_cols);
            $other_wheres = $this->_get_other_wheres($where, $idx_cols);
            $mget_columns = $this->_get_mget_columns($other_where_cols, $columns);
            $noget_columns = $this->_get_noget_columns($other_where_cols, $columns);
            
            if ($mget_columns) { // 通过id查询数据
                $pipe = $this->kv->pipeline();
                foreach ($ids as $id) {
                    $pipe->hmget($this->tblname . ':' . $id, $mget_columns);
                }
                $values_list = $pipe->execute();
                $datas = [];
                foreach ($ids as $key => $id) {
                    $values = $values_list[$key];
                    $data = array_combine($mget_columns, $values);
                    $data['id'] = $id;
                    $datas[] = $data;
                }
            } else { // 直接把id转成目标格式数据
                foreach ($ids as $id) {
                    $data['id'] = $id;
                    $datas[] = $data;
                }
            }
        }
        
        // 再根据其它条件过滤
        if ($other_wheres) {
            $datas2 = [];
            foreach ($datas as $data) {
                $data_flag = true;
                foreach ($other_wheres as $col => $value) {
                    list ($col, $operator) = $this->_get_where_col_operator($col, $value);
                    $flag = false;
                    switch ($operator) {
                        case 'IN':
                            $flag = in_array($data[$col], $value);
                            break;
                        case '=':
                            $flag = $data[$col] == $value;
                            break;
                        case '>':
                            $flag = $data[$col] > $value;
                            break;
                        case '<':
                            $flag = $data[$col] < $value;
                            break;
                        case '>=':
                            $flag = $data[$col] >= $value;
                            break;
                        case '<=':
                            $flag = $data[$col] <= $value;
                            break;
                        case '<>': // BETWEEN，value为array
                            $flag = $data[$col] >= $value[0] && $data[$col] <= $value[1];
                            break;
                        case '><': // NOT BETWEEN，value为array
                            $flag = $data[$col] < $value[0] || $data[$col] > $value[1];
                            break;
                        case '!': // !=
                            $flag = $data[$col] != $value;
                            break;
                    }
                    if (! $flag) {
                        $data_flag = false;
                        break;
                    }
                }
                if ($data_flag && ! isset($data2[$data['id']])) {
                    $datas2[$data['id']] = $data;
                }
            }
            $datas = array_values($datas2);
        }
        
        $total = 0;
        $need_page = $page_no || $page_size;
        
        if ($datas) {
            // 数据排序
            if ($sort) {
                data_sort($datas, $sort);
            }
            
            // 数据分页
            $total = count($datas);
            if ($need_page) {
                if ($page_no === null) {
                    $page_no = 1;
                }
                $start = ($page_no - 1) * $page_size;
                $datas = array_slice($datas, $start, $page_size);
            }
            
            // 去除columns没有的列
            if ($noget_columns) {
                foreach ($datas as $key => $data) {
                    foreach ($noget_columns as $col) {
                        unset($datas[$key][$col]);
                    }
                }
            }
        }
        
        if ($need_page) {
            $res = [
                'total' => $total,
                'data' => $datas
            ];
        } else {
            $res = $datas;
        }
        return $res;
    }

    /**
     * 插入数据
     * @param array $datas
     * @return int 新增的数据条数
     */
    function insert($datas)
    {
        $starttime = microtime(true);
        try {
            $result = $this->_set($datas);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 修改数据
     * @param array $data 单条数据
     * @param array $where 修改条件（如果有索引分割列且where条件中包含字符串索引或数值索引列，则where条件一定要包含索引分割列）
     * @return int 修改的数据条数
     */
    function update($data, $where)
    {
        $starttime = microtime(true);
        try {
            $datas = $this->_select([
                'id'
            ], $where);
            if (! $datas)
                return 0;
            foreach ($datas as $key => $id) {
                $data['id'] = $id['id'];
                $datas[$key] = $data;
            }
            $result = $this->_set($datas);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 新增或根据id修改数据
     * @param array $datas 需要插入或修改的数据，传入单条或多条数据，如果是修改则需要传入数据id
     * @return int 新增或修改的数据条数
     */
    function set($datas)
    {
        $starttime = microtime(true);
        try {
            $result = $this->_set($datas);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally {
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 新增或根据id修改数据
     * @param array $datas 需要插入或修改的数据，传入单条或多条数据，如果是修改则需要传入数据id
     * @return int 新增或修改的数据条数
     */
    private function _set($datas)
    {
        if (empty($datas))
            return 0;
        
        if (! isset($datas[0])) {
            $datas = [
                $datas
            ];
        }
        
        $add_idxs = [
            'str' => [],
            'num' => []
        ];
        $del_idxs = [
            'str' => [],
            'num' => []
        ];
        $idx_cols_data = [
            'str' => $this->idx_str_cols,
            'num' => $this->idx_num_cols
        ];
        $pk_ids = [];
        foreach ($datas as $data) {
            // 判断是新增或修改
            $is_update = true;
            if (! isset($data['id']) || utils::is_empty($data['id'])) {
                $data['id'] = utils::uuid();
                $is_update = false;
            } else if (! $this->_exists($data['id'])) {
                $is_update = false;
            }
            
            // 补充默认值列
            $defaults = $is_update ? $this->update_defaults : $this->insert_defaults;
            if ($defaults) {
                $need_defaults = array_diff_key($defaults, $data);
                foreach ($need_defaults as $col => $value) {
                    $data[$col] = strpos($value, 'fn_') === 0 ? $this->_get_default_value($value) : $value;
                }
            }
            
            $id = $data['id'];
            if (! $is_update) {
                $pk_ids[] = $id;
            } else if ($this->idx_partition_col) {
                unset($data[$this->idx_partition_col]);
            }
            
            // 处理需要新增或删除的索引
            $idx_cols = array_merge($this->idx_str_cols, $this->idx_num_cols);
            $set_columns = array_keys($data);
            $idx_cols = array_intersect($idx_cols, $set_columns);
            $add_idxs = [];
            $del_idxs = [];
            if ($idx_cols) {
                $get_columns = $idx_cols;
                if ($this->idx_partition_col) {
                    $get_columns[] = $this->idx_partition_col;
                    $get_columns = array_unique($get_columns);
                }
                $old_data = $is_update ? $this->_get_by_id($get_columns, $id) : [];
                // 索引
                foreach ($idx_cols_data as $idx_type => $idx_data) {
                    foreach ($idx_data as $col) {
                        if ($data[$col] !== $old_data[$col]) {
                            if (isset($data[$col]) && ! utils::is_empty($data[$col])) {
                                if ($idx_type == 'str') {
                                    $add_idxs[$idx_type][$col][$data[$col]][] = $id;
                                } else if ($idx_type == 'num') {
                                    $add_idxs[$idx_type][$col][$id] = $data[$col];
                                }
                            }
                            
                            if ($is_update && isset($old_data[$col]) && ! utils::is_empty($old_data[$col])) {
                                if ($idx_type == 'str') {
                                    $del_idxs[$idx_type][$col][$old_data[$col]][] = $id;
                                } else if ($idx_type == 'num') {
                                    $del_idxs[$idx_type][$col][] = $id;
                                }
                            }
                        }
                    }
                }
            } else if ($is_update && $this->idx_partition_col) {
                $old_data = $this->_get_by_id([
                    $this->idx_partition_col
                ], $id);
            }
            
            $pipe = $this->kv->pipeline();
            
            // 保存数据
            unset($data['id']);
            $pipe->hmset($this->tblname . ':' . $id, $data);
            
            // 添加主键
            $partition_value = ! $is_update ? $data[$this->idx_partition_col] : $old_data[$this->idx_partition_col];
            if ($pk_ids) {
                $pk_key = $this->_get_idx_key('id', $partition_value);
                $pipe->sadd($pk_key, $pk_ids);
            }
            
            // 先删除索引
            if ($del_idxs) {
                foreach ($del_idxs as $idx_type => $del_idx_cols) {
                    if ($del_idx_cols) {
                        foreach ($del_idx_cols as $col => $del_idx_data) {
                            if ($idx_type == 'str') {
                                foreach ($del_idx_data as $val => $ids) {
                                    $idx_key = $this->_get_idx_key($idx_type, $partition_value, $col, $val);
                                    foreach ($ids as $id) {
                                        $pipe->srem($idx_key, $id);
                                    }
                                }
                            } else if ($idx_type == 'num') {
                                $idx_key = $this->_get_idx_key($idx_type, $partition_value, $col);
                                foreach ($del_idx_data as $id) {
                                    $pipe->zrem($idx_key, $id);
                                }
                            }
                        }
                    }
                }
            }
            
            // 再添加索引（如果是zset，需要先删除索引再添加索引，不然相同score（id）下不能重复添加）
            if ($add_idxs) {
                foreach ($add_idxs as $idx_type => $add_idx_cols) {
                    if ($add_idx_cols) {
                        foreach ($add_idx_cols as $col => $add_idx_data) {
                            if ($idx_type == 'str') {
                                foreach ($add_idx_data as $val => $ids) {
                                    $idx_key = $this->_get_idx_key($idx_type, $partition_value, $col, $val);
                                    $pipe->sadd($idx_key, $ids);
                                }
                            } else if ($idx_type == 'num') {
                                $idx_key = $this->_get_idx_key($idx_type, $partition_value, $col);
                                $pipe->zadd($idx_key, $add_idx_data);
                            }
                        }
                    }
                }
            }
            
            $pipe->execute();
        }
        
        $result = count($datas);
        return $result;
    }

    /**
     * 删除数据
     * @param array $where 删除数据的条件（如果有索引分割列且where条件中包含字符串索引或数值索引列，则where条件一定要包含索引分割列）
     * @return int 新增或修改的数据条数
     */
    function delete($where)
    {
        $starttime = microtime(true);
        try {
            $result = $this->_delete($where);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 删除数据
     * @param array $where 删除数据的条件
     * @return int 新增或修改的数据条数
     */
    private function _delete($where)
    {
        $columns = $this->idx_str_cols;
        $columns[] = 'id';
        if ($this->idx_partition_col && utils::is_empty($where[$this->idx_partition_col])) {
            $columns[] = $this->idx_partition_col;
            $partition_from_data = true;
        }
        $datas = $this->_select($columns, $where);
        if (! $datas) {
            return 0;
        }
        
        $pipe = $this->kv->pipeline();
        // 删除数据和主键
        foreach ($datas as $data) {
            $data_key = $this->tblname . ':' . $data['id'];
            $pipe->del($data_key);
            $partition_value = $partition_from_data ? $data[$this->idx_partition_col] : $where[$this->idx_partition_col];
            $pk_key = $this->_get_idx_key('id', $partition_value);
            $pipe->srem($pk_key, $data['id']);
        }
        
        // 删除字符串索引
        if ($this->idx_str_cols) {
            foreach ($this->idx_str_cols as $col) {
                foreach ($datas as $data) {
                    $partition_value = $partition_from_data ? $data[$this->idx_partition_col] : $where[$this->idx_partition_col];
                    $idx_key = $this->_get_idx_key('str', $partition_value, $col, $data[$col]);
                    $pipe->srem($idx_key, $data['id']);
                }
            }
        }
        
        // 删除数值索引
        if ($this->idx_num_cols) {
            foreach ($this->idx_num_cols as $col) {
                foreach ($datas as $data) {
                    $partition_value = $partition_from_data ? $data[$this->idx_partition_col] : $where[$this->idx_partition_col];
                    $idx_key = $this->_get_idx_key('num', $partition_value, $col);
                    $pipe->zrem($idx_key, $data['id']);
                }
            }
        }
        $pipe->execute();
        return count($datas);
    }

    /**
     * 获取单条数据
     * @param array/string $columns 需要获取的列
     * @param string $where 筛选条件
     * @return array/string 如果$columns为string类型，则返回单列的值
     */
    function get($columns, $where = null)
    {
        $starttime = microtime(true);
        try {
            $data = $this->_get($columns, $where);
            $result = $data ? 1 : 0;
            return $data;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 获取单条数据
     * @param array/string $columns 需要获取的列
     * @param string $where 筛选条件
     * @return array/string 如果$columns为string类型，则返回单列的值
     */
    private function _get($columns, $where = null)
    {
        $get_columns = is_string($columns) ? [
            $columns
        ] : $columns;
        $datas = $this->_select($get_columns, $where);
        $data = isset($datas[0]) ? $datas[0] : null;
        $result = $data ? 1 : 0;
        return is_string($columns) ? $data[$columns] : $data;
    }

    /**
     * 根据ID获取数据
     * @param array/string $columns 需要获取的列
     * @param string $id 数据ID
     * @return array/string 如果$columns为string类型，则返回单列的值
     */
    function get_by_id($columns, $id)
    {
        $starttime = microtime(true);
        try {
            $data = $this->_get_by_id($columns, $id);
            $result = $data ? 1 : 0;
            return $data;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 根据ID获取数据
     * @param array/string $columns 需要获取的列
     * @param string $id 数据ID
     * @return array/string 如果$columns为string类型，则返回单列的值
     */
    private function _get_by_id($columns, $id)
    {
        $data = null;
        if ($this->_exists($id)) {
            $get_columns = is_string($columns) ? [
                $columns
            ] : $columns;
            $key = $this->tblname . ':' . $id;
            $values = $this->kv->hmget($key, $get_columns);
            $data = array_combine($get_columns, $values);
        }
        return $data && is_string($columns) ? $data[$columns] : $data;
    }

    /**
     * 获取数量
     * @param string $where
     * @return number
     */
    function count($where = null)
    {
        $starttime = microtime(true);
        try {
            $result = $this->_count($where);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally {
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }

    /**
     * 获取数量
     * @param string $where
     * @return number
     */
    function _count($where = null)
    {
        $datas = $this->_select([
            'id'
        ], $where);
        return count($datas);
    }
    
    // 判断ID是否存在
    function exists($id)
    {
        $starttime = microtime(true);
        try {
            $result = $this->_exists($id);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }
    
    // 判断ID是否存在
    private function _exists($id)
    {
        return $this->kv->exists($this->tblname . ':' . $id) ? true : false;
    }
    
    // 判断数据是否存在
    function has($where)
    {
        $starttime = microtime(true);
        try {
            $result = $this->_has($where);
            return $result;
        } catch (\Exception $e) {
            $result = 'fail';
            throw new \Exception($e);
        } finally{
            $this->_log($starttime, __METHOD__, func_get_args(), $result);
        }
    }
    
    // 判断数据是否存在
    private function _has($where)
    {
        $datas = $this->_select([
            'id'
        ], $where);
        return $datas ? true : false;
    }
    
    public function time()
    {
        list($time, $ms) = $this->kv->time();
        return doubleval($time . '.'  . $ms);
    }

    /**
     * 获取默认值
     * @param string 函数名
     * @return mixed
     */
    private function _get_default_value($function_name)
    {
        switch ($function_name) {
            case 'fn_microtime':
                return $this->time();
        }
    }
    
    // 获取where中列的操作符
    private function _get_where_col_operator($col, $value = null)
    {
        if (preg_match("/^([\w\.\-]+)(\[(IN|in|\>|\>\=|\<|\<\=|\!|\<\>|\>\<)\])?$/i", $col, $matches)) {
            if (isset($matches[3])) {
                $operator = strtoupper($matches[3]);
                $col = $matches[1];
            } else if (is_array($value)) {
                $operator = 'IN';
            } else {
                $operator = '=';
            }
        }
        return [
            $col,
            $operator
        ];
    }
    
    // 获取where中索引列用于过滤的keys，最后只返回有key的类型
    private function _get_where_idx_keys($where)
    {
        // 先根据字符串索引过滤
        $sinter_keys = [];
        $sunion_keys = [];
        $range_keys = [];
        $not_range_keys = [];
        $partition_value = $where[$this->idx_partition_col];
        foreach ($where as $col => $value) {
            list ($col, $operator) = $this->_get_where_col_operator($col, $value);
            if (in_array($col, $this->idx_str_cols)) { // 字符串索引处理
                if ($operator == 'IN') {
                    foreach ($value as $val) {
                        $key = $this->_get_idx_key('str', $partition_value, $col, $val);
                        $sunion_keys[] = $key;
                    }
                } else {
                    $key = $this->_get_idx_key('str', $partition_value, $col, $value);
                    $sinter_keys[] = $key;
                }
            } else if (in_array($col, $this->idx_num_cols)) { // 数值索引处理
                $key = $this->_get_idx_key('num', $partition_value, $col);
                switch ($operator) {
                    case 'IN':
                        foreach ($value as $val) {
                            $range_keys[$key][$operator][] = [
                                $val,
                                $val
                            ];
                        }
                        break;
                    case '=':
                        $range_keys[$key][] = [
                            $value,
                            $value
                        ];
                        break;
                    case '>':
                        $range_keys[$key][] = [
                            '(' . $value,
                            '+inf'
                        ];
                        break;
                    case '<':
                        $range_keys[$key][] = [
                            '-inf',
                            '(' . $value
                        ];
                        break;
                    case '>=':
                        $range_keys[$key][] = [
                            $value,
                            '+inf'
                        ];
                        break;
                    case '<=':
                        $range_keys[$key][] = [
                            '-inf',
                            $value
                        ];
                        break;
                    case '<>': // BETWEEN，value为array
                        $range_keys[$key][] = [
                            $value[0],
                            $value[1]
                        ];
                        break;
                    case '><': // NOT BETWEEN，value为array
                        $range_keys[$key][$operator][] = [
                            '-inf',
                            '(' . $value[0]
                        ];
                        $range_keys[$key][$operator][] = [
                            '(' . $value[1],
                            '+inf'
                        ];
                        break;
                    case '!': // !=
                        $range_keys[$key][$operator][] = [
                            '-inf',
                            '(' . $value
                        ];
                        $range_keys[$key][$operator][] = [
                            '(' . $value,
                            '+inf'
                        ];
                        break;
                }
            }
        }
        
        $keys = [];
        if ($sinter_keys) {
            $keys['sinter'] = $sinter_keys;
        }
        if ($sunion_keys) {
            $keys['sunion'] = $sunion_keys;
        }
        if ($range_keys) {
            $keys['range'] = $range_keys;
        }
        if (isset($where['id'])) {
            $keys['id'] = is_array($where['id']) ? $where['id'] : [
                $where['id']
            ];
        }
        return $keys;
    }

    /**
     * 生成临时sunion
     * @param array $keys 键集合
     * @return array [tmp_key, count]
     */
    private function _make_tmp_sunion($keys)
    {
        $tmp_key = '~' . $this->tblname . ':sunion_' . utils::uuid();
        $cnt = $this->kv->sunionstore($tmp_key, $keys);
        return [
            $tmp_key,
            $cnt
        ];
    }

    /**
     * 生成临时zrange
     * @param string $key 键
     * @param array $score_range 数值索引值范围
     * @return array [tmp_key, count]
     */
    private function _make_tmp_zrange($key, $score_range)
    {
        $tmp_key = '~' . $this->tblname . ':zrange_' . utils::uuid();
        $pipe = $this->kv->pipeline();
        
        // 通过zunionstore复制z
        $pipe->zunionstore($tmp_key, [
            $key
        ]);
        
        // 删除访问外的内容
        $remove_ranges = $this->_get_zrange_other($score_range[0], $score_range[1]);
        if ($remove_ranges) {
            foreach ($remove_ranges as $remove_range) {
                $pipe->zremrangebyscore($tmp_key, $remove_range[0], $remove_range[1]);
            }
        }
        
        $results = $pipe->execute();
        $cnt = $results[0];
        unset($results[0]);
        foreach ($results as $remove_cnt) {
            $cnt -= $remove_cnt;
        }
        return [
            $tmp_key,
            $cnt
        ];
    }

    /**
     * 生成临时zunion
     * @param string $key 键
     * @param arrry $score_ranges 多组数值索引值范围
     */
    private function _make_tmp_zunion($key, $score_ranges)
    {
        $zunion_keys = [];
        $tmp_zrange_keys = [];
        foreach ($score_ranges as $score_range) {
            list ($tmp_key, $cnt) = $this->_make_tmp_zrange($key, $score_range);
            $tmp_zrange_keys[] = $tmp_key;
            if ($cnt) {
                $zunion_keys[] = $tmp_key;
            }
        }
        
        if ($zunion_keys) {
            $tmp_key = '~' . $this->tblname . ':zunion_' . utils::uuid();
            $pipe = $this->kv->pipeline();
            $pipe->zunionstore($tmp_key, $tmp_zrange_keys);
        } else {
            $tmp_key = null;
        }
        
        // 删除临时zrange
        $pipe->del($tmp_zrange_keys);
        
        $results = $pipe->execute();
        $cnt = $zunion_keys ? $results[0] : 0;
        return [
            $tmp_key,
            $cnt
        ];
    }

    /**
     * 获取制定范围之外的范围
     * @param string $range_start 范围开始
     * @param string $range_end 范围结束
     */
    private function _get_zrange_other($range_start, $range_end)
    {
        $ranges = [];
        if (strtolower($range_start) != '-inf') {
            $ranges[] = [
                '-inf',
                (strpos($range_start, '(') === 0 ? $range_start : '(' . $range_start)
            ];
        }
        if (strtolower($range_end) != '+inf') {
            $ranges[] = [
                (strpos($range_end, '(') === 0 ? $range_end : '(' . $range_end),
                '+inf'
            ];
        }
        return $ranges;
    }
    
    // 获取where中索引列过滤后的id，如果没有索引列过滤则获取所有id
    private function _get_where_key_ids($where)
    {
        $idx_keys = $this->_get_where_idx_keys($where);
        $ids = null;
        if ($idx_keys) {
            // id索引值
            if ($idx_keys['id']) {
                $ids = $idx_keys['id'];
                unset($idx_keys['id']);
            }
            
            $set_keys = []; // 字符串索引的key
            $first_zset_ranges = []; // 第1个数值索引列的ranges
            $first_zset_tmp_keys = []; // 第1个数值索引列的临时key
            $other_zset_tmp_keys = []; // 其它数值索引列的临时key
            $tmp_keys = []; // 生成的临时key
            foreach ($idx_keys as $idx_type => $keys) {
                if ($idx_type == 'sinter') {
                    $set_keys = array_merge($set_keys, $keys);
                } else if ($idx_type == 'sunion') {
                    list ($tmp_key, $tmp_cnt) = $this->_make_tmp_sunion($keys);
                    $tmp_keys[] = $tmp_key;
                    if ($tmp_cnt == 0) {
                        $no_result = true;
                        goto for_lev1_end;
                    } else {
                        $set_keys[] = $tmp_key;
                    }
                } else if ($idx_type == 'range') {
                    // 第1个数值索引
                    $first_zset_key = array_keys($keys)[0];
                    $first_zset_range_arr = $keys[$first_zset_key];
                    unset($keys[$first_zset_key]);
                    foreach ($first_zset_range_arr as $index => $key_range) {
                        if ($index === '><' || $index === 'IN' || $index === '!') {
                            list ($tmp_key, $tmp_cnt) = $this->_make_tmp_zunion($first_zset_key, $key_range);
                        } else {
                            $tmp_key = null;
                            $first_zset_ranges[] = $key_range;
                        }
                        if ($tmp_key) {
                            $tmp_keys[] = $tmp_key;
                            if ($tmp_cnt == 0) {
                                $no_result = true;
                                goto for_lev1_end;
                            } else {
                                $first_zset_tmp_keys[] = $tmp_key;
                            }
                        }
                    }
                    // 其它数值索引
                    foreach ($keys as $key => $key_range_arr) {
                        foreach ($key_range_arr as $index => $key_range) {
                            if ($index === '><' || $index === 'IN' || $index === '!') {
                                list ($tmp_key, $tmp_cnt) = $this->_make_tmp_zunion($key, $key_range);
                            } else {
                                list ($tmp_key, $tmp_cnt) = $this->_make_tmp_zrange($key, $key_range);
                            }
                            $tmp_keys[] = $tmp_key;
                            if ($tmp_cnt == 0) {
                                $no_result = true;
                                goto for_lev1_end;
                            } else {
                                $other_zset_tmp_keys[] = $tmp_key;
                            }
                        }
                    }
                }
            }
            for_lev1_end:
            
            if ($no_result) { // 取交集单项无数据，则最终无数据
                $ids = [];
            } else if ($set_keys || $first_zset_ranges || $first_zset_tmp_keys || $other_zset_tmp_keys) { // 需要取交集
                if (! $first_zset_ranges && ! $first_zset_tmp_keys && ! $other_zset_tmp_keys) { // 只需要sinter
                    $tmp_ids = $this->kv->sinter($set_keys);
                } else if (! $set_keys && $first_zset_ranges && ! $first_zset_tmp_keys && ! $other_zset_tmp_keys) { // 第1个数值索引无临时过滤key和其它数值索引，直接zrangebyscore
                    $tmp_ids = null;
                    foreach ($first_zset_ranges as $key_range) {
                        $tmp_ids2 = $this->kv->zrangebyscore($first_zset_key, $key_range[0], $key_range[1]);
                        $tmp_ids = $tmp_ids === null ? $tmp_ids2 : array_intersect($tmp_ids, $tmp_ids2);
                    }
                } else if (! $set_keys && ! $first_zset_ranges && count($first_zset_tmp_keys) == 1 && ! $other_zset_tmp_keys) { // 第1个数值索引只有1个临时过滤key
                    $tmp_ids = $this->kv->zrange($first_zset_tmp_keys[0], 0, - 1);
                } else { // 需要zinterstore
                    $result_tmp_key = $tmp_key = '~' . $this->tblname . ':zinter_' . utils::uuid();
                    $tmp_keys[] = $result_tmp_key;
                    
                    $merge_zinter_keys = [];
                    $zinterstore_weights = [];
                    if ($set_keys) { // set的weight为0
                        $merge_zinter_keys = array_merge($merge_zinter_keys, $set_keys);
                        $zinterstore_weights = array_pad($zinterstore_weights, count($set_keys), 0);
                    }
                    if ($first_zset_tmp_keys) { // zset的weight为1
                        $merge_zinter_keys = array_merge($merge_zinter_keys, $first_zset_tmp_keys);
                        $zinterstore_weights = array_pad($zinterstore_weights, count($zinterstore_weights) + count($first_zset_tmp_keys), 1);
                    }
                    if ($first_zset_ranges) { // zset的weight为1
                        $merge_zinter_keys[] = $first_zset_key;
                        $zinterstore_weights[] = 1;
                    }
                    
                    $cnt = $this->kv->zinterstore($result_tmp_key, $merge_zinter_keys, [
                        'WEIGHTS' => $zinterstore_weights,
                        'AGGREGATE' => 'MAX'
                    ]);
                    $tmp_ids = null;
                    if ($cnt) {
                        // 过滤第1个数值索引
                        if ($first_zset_ranges) {
                            foreach ($first_zset_ranges as $key_range) {
                                $tmp_ids2 = $this->kv->zrangebyscore($result_tmp_key, $key_range[0], $key_range[1]);
                                $tmp_ids = $tmp_ids === null ? $tmp_ids2 : array_intersect($tmp_ids, $tmp_ids2);
                            }
                        } else { // 不需要再过滤第1个数值索引
                            $tmp_ids = $this->kv->zrange($result_tmp_key, 0, - 1);
                        }
                        
                        // 过滤其它数值索引
                        if ($other_zset_tmp_keys) {
                            $result_tmp_key = $tmp_key = '~' . $this->tblname . ':zinter_' . utils::uuid();
                            $tmp_keys[] = $result_tmp_key;
                            $cnt = $this->kv->zinterstore($result_tmp_key, $other_zset_tmp_keys);
                            if ($cnt) {
                                $tmp_ids2 = $this->kv->zrange($result_tmp_key, 0, - 1);
                                $tmp_ids = array_intersect($tmp_ids, $tmp_ids2);
                            } else {
                                $tmp_ids = [];
                            }
                        }
                    } else {
                        $tmp_ids = [];
                    }
                }
                if ($tmp_ids) {
                    $ids = $ids ? array_intersect($ids, $tmp_ids) : $tmp_ids;
                } else if ($ids === null) {
                    $ids = [];
                }
            } else if ($ids === null) { // 不需要取交集
                $ids = [];
            }
            // 删除临时key
            if ($tmp_keys) {
                $this->kv->del($tmp_keys);
            }
        } else {
            $partition_value = $where[$this->idx_partition_col];
            $key = $this->_get_idx_key('id', $partition_value);
            $ids = $this->kv->smembers($key);
        }
        return array_values(array_unique($ids));
    }

    /**
     * 获取除了索引和id列外的WEHRE条件
     * @param array $where where条件
     * @param array $idx_cols 索引列和id列
     */
    private function _get_other_wheres($where, $idx_cols)
    {
        foreach ($where as $col_key => $value) {
            list ($col, ) = $this->_get_where_col_operator($col_key);
            if (in_array($col, $idx_cols)) {
                unset($where[$col_key]);
            }
        }
        return $where;
    }

    /**
     * 获取除了索引和id列外的WEHRE 列
     * @param array $where where条件
     * @param array $idx_cols 索引列和id列
     */
    private function _get_other_where_cols($where, $idx_cols)
    {
        $other_where_cols = [];
        foreach ($where as $col_key => $value) {
            list ($col, ) = $this->_get_where_col_operator($col_key);
            if (! in_array($col, $idx_cols)) {
                $other_where_cols[] = $col;
            }
        }
        return $other_where_cols;
    }

    /**
     * 获取mget需要的列
     * @param array $other_where_cols 其它where 中的列
     * @param array $columns 要获取的列
     */
    private function _get_mget_columns($other_where_cols, $columns)
    {
        $get_columns = array_unique(array_merge($columns, $other_where_cols));
        
        // 去除id列
        if ($col_id = array_keys($get_columns, 'id')) {
            unset($get_columns[$col_id[0]]);
        }
        return $get_columns;
    }

    /**
     * 获取不需要获取的列
     * @param array $other_where_cols 其它where 中的列
     * @param array $columns 要获取的列
     */
    private function _get_noget_columns($other_where_cols, $columns)
    {
        $noget_columns = array_diff($other_where_cols, $columns);
        if (! in_array('id', $columns)) {
            $noget_columns[] = 'id';
        }
        return $noget_columns;
    }

    /**
     * 获取索引key
     * @param string $idx_type 索引类型：id,str,num
     * @param string $partition_value 索引分割值
     * @param string $column 索引列名
     * @param string $value 索引列值
     * @return string 索引key
     */
    private function _get_idx_key($idx_type, $partition_value, $column = null, $value = null)
    {
        if ($this->idx_partition_col) {
            if ($partition_value === null || $partition_value === '') {
                throw new \Exception('索引分割裂"' . $this->idx_partition_col . '"的值不能为空。');
            } else if (! is_numeric($partition_value) && ! is_string($partition_value)) {
                throw new \Exception('索引分割裂"' . $this->idx_partition_col . '"的值必须是数值或字符串。');
            }
        }
        
        if (! in_array($idx_type, [
            'id',
            'str',
            'num'
        ])) {
            throw new \Exception('kv_model_base->_get_idx_key中的$idx_type参数值无效');
        }
        
        $idx_partition = $this->idx_partition_col ? '_' . $partition_value : '';
        switch ($idx_type) {
            case 'id':
                return $this->tblname . $idx_partition . '$id';
            case 'str':
                return $this->tblname . $idx_partition . '$' . $column . '=' . $value;
            case 'num':
                return $this->tblname . $idx_partition . '#' . $column;
        }
    }

    /**
     * 日志记录结束
     */
    private function _log($starttime, $method, $args, $result)
    {
        if (log::is_log_kv()) {
            $endtime = microtime(true);
            $exectime = round(($endtime - $starttime) * 1000);
            list (, $method) = explode('::', $method, 2);
            $log_content = [
                'kv' => $this->kv->kv_id,
                'tbl' => $this->tblname,
                'method' => $method,
                'exectime' => $exectime,
                'args' => $args,
                'result' => $result
            ];
            log::kv($log_content);
        }
    }
}