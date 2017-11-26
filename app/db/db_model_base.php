<?php
namespace common\db;

/**
 * MODEL基类
 */
class db_model_base
{

    public $db;

    public $db_type = 'main';

    public $tblname;

    public $viewname;

    public $pkcol = 'id';

    function __construct($db = null)
    {
        if ($db === null) {
            $this->db = db::get_db($this->db_type);
        } else {
            $this->db = $db;
        }
        
        $this->tblname = self::table_name();
    }

    /**
     * 实例化构造
     * @param like_medoo $db（shop库必传）
     * @return db_model
     */
    public static function instance($db = null)
    {
        static $instances;
        $class = get_called_class();
        $instance_id = $db === null ? $class : $db->db_id . ':' . self::table_name();
        if (! isset($instances[$instance_id])) {
            $instances[$instance_id] = new $class($db);
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
    
    // 搜索数据
    function select($join, $columns = '*', $where = null)
    {
        $ret = $this->db->select($this->tblname, $join, $columns, $where);
        if ($ret === false)
            return array();
        return $ret;
    }
    
    // 新增数据
    function insert($datas)
    {
        if (empty($datas))
            return;
        return $this->db->insert($this->tblname, $datas);
    }
    
    // 修改数据
    function update($data, $where = null)
    {
        return $this->db->update($this->tblname, $data, $where);
    }
    
    // 删除数据
    function delete($where)
    {
        return $this->db->delete($this->tblname, $where);
    }
    
    // 替换数据
    function replace($columns, $search = null, $replace = null, $where = null)
    {
        return $this->db->replace($this->tblname, $columns, $search, $replace, $where);
    }
    
    // 获取单条数据
    function get($columns = '*', $where = null)
    {
        return $this->db->get($this->tblname, $columns, $where);
    }
    
    // 是否存在数据
    function has($join, $where = null)
    {
        return $this->db->has($this->tblname, $join, $where);
    }
    
    // 获取数量
    function count($join = null, $column = null, $where = null)
    {
        return $this->db->count($this->tblname, $join, $column, $where);
    }
    
    // 获取最大值
    function max($join, $column = null, $where = null)
    {
        return $this->db->max($this->tblname, $join, $column, $where);
    }
    
    // 获取最小值
    function min($join, $column = null, $where = null)
    {
        return $this->db->min($this->tblname, $join, $column, $where);
    }
    
    // 获取平均值
    function avg($join, $column = null, $where = null)
    {
        return $this->db->avg($this->tblname, $join, $column, $where);
    }
    
    // 获取和值
    function sum($join, $column = null, $where = null)
    {
        return $this->db->sum($this->tblname, $join, $column, $where);
    }

    /* 以下都是增加的自定义函数 */
    
    // 根据主键获取单条数据
    function get_one($pkval, $columns = '*')
    {
        return $this->db->get($this->tblname, $columns, array(
            $this->pkcol => $pkval
        ));
    }
    
    // 修改单条记录
    function update_one($pkval, $data)
    {
        return $this->db->update($this->tblname, $data, array(
            $this->pkcol => $pkval
        ));
    }
    
    // 删除单条记录
    function delete_one($pkval)
    {
        return $this->db->delete($this->tblname, array(
            $this->pkcol => $pkval
        ));
    }
    
    // 设置记录删除状态
    function set_deleted($where)
    {
        return $this->db->update($this->tblname, array(
            'deleted' => 1
        ), $where);
    }
    
    // 设置单条记录删除
    function set_one_deleted($pkval)
    {
        return $this->db->update($this->tblname, array(
            'deleted' => 1
        ), array(
            $this->pkcol => $pkval
        ));
    }
    
    // 获取所有数据
    function getall($filter_deleted = true)
    {
        $where = $filter_deleted ? array(
            'deleted[!]' => 1
        ) : null;
        return $this->db->select($this->tblname, '*', $where);
    }
    
    // 分页获取数据
    function getpaged($columns = '*', $page_no = 1, $page_size = 20, $where = null, $sort = null)
    {
        return $this->db->getpaged($this->tblname, $columns, $page_no, $page_size, $where, $sort);
    }
    
    // 根据主键获取单个字段的值
    function getval($pkval, $col)
    {
        return $this->db->get($this->tblname, $col, array(
            $this->pkcol => $pkval
        ));
    }

    /**
     * 锁定行
     * @param int $pkval 主键值
     * @param string/array $columns 为空表示只锁定不获取数据，否则表示锁定并获取数据
     */
    function lockrow($pkval, $columns = null)
    {
        $sql = 'select %s from %s where %s=%s for update';
        if ($columns === null) {
            $columns = 1;
        } else if (is_array($columns)) {
            $columns = implode(',', $columns);
        }
        $sql = sprintf($sql, $columns, $this->tblname, $this->pkcol, $pkval);
        $rs = $this->db->query($sql);
        if ($columns === 1) {
            return $rs->fetchColumn() == $columns;
        } else {
            $data = $rs->fetchAll();
            return isset($data[0]) ? $data[0] : $data;
        }
    }

    /**
     * 获取数据库当前时间
     */
    function get_db_date($format = null)
    {
        $now = $this->db->query('select NOW() as time from dual')->fetchColumn();
        if ($format === null)
            return $now;
        if ($format == 'time')
            return strtotime($now);
        return date($format, strtotime($now));
    }
}