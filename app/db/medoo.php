<?php
namespace common\db;

require_once COMMON_APP_ROOT . '/lib/medoo/medoo.php';
use common\log\log;

/**
 * 数据库操作类
 */
class medoo extends \medoo
{
    // 数据库ID
    public $db_id;

    public function __construct($options = null, $db_id)
    {
        parent::__construct($options);
        $this->db_id = $db_id;
    }
    
    // 返回多条结果集
    function all($sql)
    {
        return $this->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // 返回一条结果集
    function one($sql)
    {
        return $this->query($sql)->fetch(\PDO::FETCH_ASSOC);
    }
    
    // 返回结果集行数
    function get_count($sql)
    {
        return $this->query('SELECT count(1) AS count FROM (' . $sql . ') tb')->fetch(\PDO::FETCH_COLUMN);
    }
    
    // 替换（不存在则插入，存在则更新）
    function replace_into($table, $datas, $batch_replace = true)
    {
        // Check indexed or associative array
        if (!isset($datas[0])) {
            $datas = array($datas);
        }

        foreach ($datas as $data) {
            $values = array();
            $columns = array();

            foreach ($data as $key => $value) {
                array_push($columns, $this->column_quote($key));

                switch (gettype($value)) {
                    case 'NULL':
                        $values[] = 'NULL';
                        break;

                    case 'array':
                        preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);

                        $values[] = isset($column_match[0]) ?
                            $this->quote(json_encode($value)) :
                            $this->quote(serialize($value));
                        break;

                    case 'boolean':
                        $values[] = ($value ? '1' : '0');
                        break;

                    case 'integer':
                    case 'double':
                    case 'string':
                        $values[] = $this->fn_quote($key, $value);
                        break;
                }
            }

            if(!$batch_replace) {
                $this->exec('REPLACE INTO "' . $this->prefix . $table . '" (' . implode(', ', $columns) . ') VALUES (' . implode($values, ', ') . ')');
                $lastId[] = $this->pdo->lastInsertId();
            } else {
                $values_sql .= '(' . implode($values, ', ') . '),';
            }
        }
        
        if($batch_replace) {
            $sql = 'REPLACE INTO "' . $this->prefix . $table . '" (' . implode(', ', $columns) . ') VALUES ' . substr($values_sql, 0, strlen($values_sql)-1);
            $this->exec($sql);
        }

        return true;
    }

    /**
     * 执行SQL
     * @param string $type exec或query
     * @param string $sql SQL
     */
    private function _exec_sql($type, $query)
    {
        try {
            $starttime = microtime(true);
            $res = $type == 'query' ? $this->pdo->query($query) : $this->pdo->exec($query);
            $result = $type == 'query' ? 'success' : $res;
        } catch (\Exception $e) {
            $res = false;
            $result = 'fail';
            throw new \Exception($e);
        } finally {
            // 输出SQL
            if (log::is_log_sql()) {
                $endtime = microtime(true);
                $exectime = round(($endtime - $starttime) * 1000);
                $log_content = [
                    'db' => $this->database_name,
                    'type' => $type,
                    'result' => $result,
                    'exectime' => $exectime,
                    'sql' => $query
                ];
                log::sql($log_content);
            }
        }
        return $res;
    }

    public function query($query)
    {
        return $this->_exec_sql('query', $query);
    }

    public function exec($query)
    {
        return $this->_exec_sql('exec', $query);
    }
    
    // 开始事务
    public function begintran()
    {
        return $this->pdo->beginTransaction();
    }
    
    // 提交事务
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    // 回滚事务
    public function rollback()
    {
        return $this->pdo->rollBack();
    }
    
    // 锁住数据行
    public function lock_rows($table, $join = null, $columns = null, $where = null)
    {
        $query = $this->query($this->select_context($table, $join, $columns, $where) . ' FOR UPDATE');
        return $query ? $query->fetchAll((is_string($columns) && $columns != '*') ? \PDO::FETCH_COLUMN : \PDO::FETCH_ASSOC) : false;
    }
    
    // 分页获取数据
    function get_paged($tblname, $columns = '*', $page_no = 1, $page_size = 10, $where = null, $sort = null)
    {
        if ($where == null)
            $where = array();
        $where['LIMIT'] = [
            ($page_no - 1) * $page_size,
            $page_size
        ];
        
        if (! empty($sort)) {
            $where['ORDER'] = $sort;
        }
        return $this->select($tblname, $columns, $where);
    }

    // 分页获取数据（需要关联查询）
    function get_join_paged($tblname, $join, $columns = '*', $page_no = 1, $page_size = 10, $where = null, $sort = null)
    {
        if ($where == null)
            $where = array();
        $where['LIMIT'] = [
            ($page_no - 1) * $page_size,
            $page_size
        ];
        
        if (! empty($sort)) {
            $where['ORDER'] = $sort;
        }
        return $this->select($tblname, $join, $columns, $where);
    }
    
    // 向where追加分页
    function append_where_paged($where, $page_no = 1, $page_size = 10)
    {
        if ($where == null)
            $where = [];
        $where['LIMIT'] = [
            ($page_no - 1) * $page_size,
            $page_size
        ];
        return $where;
    }
    
    // 向where追加排序
    function append_where_sort($where, $sort = null)
    {
        if ($where == null)
            $where = [];
        if ($sort !== null) {
            $where['ORDER'] = $sort;
        }
        return $where;
    }
    
    // 获取select SQL
    public function get_select_sql($table, $join, $columns = null, $where = null)
    {
        return $this->query($this->select_context($table, $join, $columns, $where))->queryString;
    }
    
    // 单层查询
    protected function select_context($table, $join, &$columns = null, $where = null, $column_fn = null)
    {
        $table = '"' . $this->prefix . $table . '"';
        $join_key = is_array($join) ? array_keys($join) : null;
        
        if (isset($join_key[0]) && strpos($join_key[0], '[') === 0) {
            $table_join = array();
            
            $join_array = array(
                '>' => 'LEFT',
                '<' => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER'
            );
            
            foreach ($join as $sub_table => $relation) {
                preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);
                
                if ($match[2] != '' && $match[3] != '') {
                    if (is_string($relation)) {
                        $relation = 'USING ("' . $relation . '")';
                    }
                    
                    if (is_array($relation)) {
                        // For ['column1', 'column2']
                        if (isset($relation[0])) {
                            $relation = 'USING ("' . implode($relation, '", "') . '")';
                        } else {
                            $joins = array();
                            
                            foreach ($relation as $key => $value) {
                                $joins[] = $this->prefix . (strpos($key, '.') > 0 ? 
                                // For ['tableB.column' => 'column']
                                '"' . str_replace('.', '"."', $key) . '"' : 
                                
                                // For ['column1' => 'column2']
                                $table . '."' . $key . '"') . ' = ' . '"' . (isset($match[5]) ? $match[5] : $match[3]) . '"."' . $value . '"';
                            }
                            
                            $relation = 'ON ' . implode($joins, ' AND ');
                        }
                    }
                    
                    $table_join[] = $join_array[$match[2]] . ' JOIN "' . $this->prefix . $match[3] . '" ' . (isset($match[5]) ? 'AS "' . $match[5] . '" ' : '') . $relation;
                }
            }
            
            $table .= ' ' . implode($table_join, ' ');
        } else {
            if (is_null($columns)) {
                if (is_null($where)) {
                    if (is_array($join) && isset($column_fn)) {
                        $where = $join;
                        $columns = null;
                    } else {
                        $where = null;
                        $columns = $join;
                    }
                } else {
                    $where = $join;
                    $columns = null;
                }
            } else {
                $where = $columns;
                $columns = $join;
            }
        }
        
        if (isset($column_fn)) {
            if ($column_fn == 1) {
                $column = '1';
                
                if (is_null($where)) {
                    $where = $columns;
                }
            } else {
                if (empty($columns)) {
                    $columns = '*';
                    $where = $join;
                }
                
                $column = $column_fn . '(' . $this->column_pushs($columns) . ')';
            }
        } else {
            $column = $this->column_pushs($columns);
        }
        
        return 'SELECT ' . $column . ' FROM ' . $table . $this->where_clause($where);
    }
    
    // 获取嵌套查询的sql
    protected function select_sql_context($sql, $table, $join, &$columns = null, $where = null, $column_fn = null)
    {
        $table = '"' . $this->prefix . $table . '"';
        $join_key = is_array($join) ? array_keys($join) : null;
        
        if (isset($join_key[0]) && strpos($join_key[0], '[') === 0) {
            $table_join = array();
            
            $join_array = array(
                '>' => 'LEFT',
                '<' => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER'
            );
            
            foreach ($join as $sub_table => $relation) {
                preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);
                
                if ($match[2] != '' && $match[3] != '') {
                    if (is_string($relation)) {
                        $relation = 'USING ("' . $relation . '")';
                    }
                    
                    if (is_array($relation)) {
                        // For ['column1', 'column2']
                        if (isset($relation[0])) {
                            $relation = 'USING ("' . implode($relation, '", "') . '")';
                        } else {
                            $joins = array();
                            
                            foreach ($relation as $key => $value) {
                                $joins[] = $this->prefix . (strpos($key, '.') > 0 ? 
                                // For ['tableB.column' => 'column']
                                '"' . str_replace('.', '"."', $key) . '"' : 
                                
                                // For ['column1' => 'column2']
                                $table . '."' . $key . '"') . ' = ' . '"' . (isset($match[5]) ? $match[5] : $match[3]) . '"."' . $value . '"';
                            }
                            
                            $relation = 'ON ' . implode($joins, ' AND ');
                        }
                    }
                    
                    $table_join[] = $join_array[$match[2]] . ' JOIN "' . $this->prefix . $match[3] . '" ' . (isset($match[5]) ? 'AS "' . $match[5] . '" ' : '') . $relation;
                }
            }
            
            $table .= ' ' . implode($table_join, ' ');
        } else {
            if (is_null($columns)) {
                if (is_null($where)) {
                    if (is_array($join) && isset($column_fn)) {
                        $where = $join;
                        $columns = null;
                    } else {
                        $where = null;
                        $columns = $join;
                    }
                } else {
                    $where = $join;
                    $columns = null;
                }
            } else {
                $where = $columns;
                $columns = $join;
            }
        }
        
        if (isset($column_fn)) {
            if ($column_fn == 1) {
                $column = '1';
                
                if (is_null($where)) {
                    $where = $columns;
                }
            } else {
                if (empty($columns)) {
                    $columns = '*';
                    $where = $join;
                }
                
                $column = $column_fn . '(' . $this->column_pushs($columns) . ')';
            }
        } else {
            $column = $this->column_pushs($columns);
        }
        
        return 'SELECT ' . $column . ' FROM (' . $sql . ') ' . $table . $this->where_clause($where);
    }
    
    // 最内层嵌套查询方法（每个结果只能调用一次） 返回sql字符串
    public function select_sql($table, $join, $columns = null, $where = null)
    {
        $query = $this->select_context($table, $join, $columns, $where);
        return $query;
    }

    /**
     * 配合select_sql，外层嵌套查询方法（可多次嵌套调用）如：select_push(select_push()),注意返回的是sql字符串，还是结果集
     * @param s `*` string $sql select_sql方法返回的sql或本方法上一次调用返回的sql
     * @param s `*` string $table sql结果集的表别名
     * @param s int $state 0:sql过程调用，返回sql字符串,1:sql结果调用，返回结果集数组
     * @return string/array
     */
    public function select_push($sql, $table, $state = 0, $join, $columns = null, $where = null)
    {
        if ($state == 1)
            $query = $this->query($this->select_sql_context($sql, $table, $join, $columns, $where));
        else {
            // 如果state不为0，此时默认返回sql，后面的参数向前一位
            if ($state != 0) {
                $where = $columns;
                $columns = $join;
                $join = $state;
            }
            $query = $this->select_sql_context($sql, $table, $join, $columns, $where);
        }
        return $state == 1 ? $query->fetchAll((is_string($columns) && $columns != '*') ? \PDO::FETCH_COLUMN : \PDO::FETCH_ASSOC) : $query;
    }

    public function column_fn_quote($string)
    {
        $rule = '/^[A-Za-z0-9\_\.\-\/\(\)]*\)$/';
        $case_rule = '/^[A-Za-z0-9\_\.\=\ \-\/\(\)]*\)$/';
        if (strcasecmp(substr($string, 1, 4), 'CASE') == 0)
            return preg_match($case_rule, $string) ? $string : $this->quote($string);
        else
            return preg_match($rule, $string) ? $string : $this->quote($string);
    }

    protected function column_pushs($columns)
    {
        if ($columns == '*') {
            return $columns;
        }
        
        if (is_string($columns)) {
            $columns = array(
                $columns
            );
        }
        
        $stack = array();
        
        foreach ($columns as $key => $value) {
            if (substr_count($value, '(') >= 2 && substr_count($value, ')') >= 2) {
                preg_match('/([a-zA-Z0-9_\-\=\ \.\(\)]*)\s*\(([a-zA-Z0-9_\-]*)\)/i', $value, $match);
                if (isset($match[1], $match[2])) {
                    array_push($stack, $this->column_fn_quote($match[1]) . ' AS ' . $this->column_quote($match[2]));
                } else {
                    array_push($stack, $this->column_fn_quote($value));
                }
            } else {
                preg_match('/([a-zA-Z0-9_\-\.]*)\s*\(([a-zA-Z0-9_\-]*)\)/i', $value, $match);
                if (isset($match[1], $match[2])) {
                    array_push($stack, $this->column_quote($match[1]) . ' AS ' . $this->column_quote($match[2]));
                } else {
                    array_push($stack, $this->column_quote($value));
                }
            }
        }
        
        return implode($stack, ',');
    }
    
    // 验证是否为新增判断当前id是否存在，如果存在是否在数据库中
    public function is_create($id, $table, $shop_id = NULL)
    {
        if ($id == '' || $id == null) {
            return true;
        }
        if ($shop_id) {
            $db = shop_db($shop_id);
            $res = $db->has($table, 'id', [
                'AND' => [
                    'id' => $id
                ]
            ]);
            if ($res) {
                return false;
            } else {
                return true;
            }
        } else {
            $db = main_db();
            $res = $db->has($table, 'id', [
                'AND' => [
                    'id' => $id
                ]
            ]);
            if ($res) {
                return false;
            } else {
                return true;
            }
        }
    }
}