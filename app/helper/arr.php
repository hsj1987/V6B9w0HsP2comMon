<?php
namespace common\helper;

/**
 * 数组工具类
 */
class arr
{

    /**
     * 把不为NULL的value设置得json里
     * @param array $arr json
     * @param string $key 键
     * @param string $value 值
     */
    public static function append_not_null_value(&$arr, $key, $value)
    {
        if ($value !== null) {
            $arr[$key] = $value;
        }
    }

    /**
     * 数组合并部分key（把arr2中的部分key合并到arr1中）
     * @param array $arr1
     * @param array $arr2
     * @param array $keys 需要合并的key
     * @return unknown
     */
    public static function merge_by_keys($arr1, $arr2, $keys)
    {
        foreach ($keys as $key) {
            if (isset($arr2[$key])) {
                $arr1[$key] = $arr2[$key];
            }
        }
        return $arr1;
    }

    /**
     * 格式化数组里的值
     * @param array $datas 数据
     * @param array $keys 需要格式化的键
     * @param string $function 格式化函数，intval、doubleval、strval
     * @return boolean
     */
    public static function parse_value(&$datas, $keys, $function)
    {
        if (! is_array($datas) || ! is_array($keys)) {
            return $datas;
        }
        // 是否为一维数组
        if (count($datas) == count($datas, 1)) {
            foreach ($datas as $key => $value) {
                if (in_array($key, $keys)) {
                    $datas[$key] = call_user_func($function, $value);
                }
            }
        } else {
            foreach ($datas as $i => $data) {
                foreach ($data as $key => $value) {
                    if (in_array($key, $keys)) {
                        $datas[$i][$key] = call_user_func($function, $value);
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * 数据排序
     * @param array $data_list 数据列表
     * @param string $sort 排序方式，格式为“字段名:排序方式”，排序方式：0-升序，1-降序
     */
    public static function data_sort(&$data_list, $sort)
    {
        list ($column, $sort_type) = explode(':', $sort);
        $sort_values = array_column($data_list, $column);
        array_multisort($sort_values, $sort_type == 0 ? SORT_ASC : SORT_DESC, $data_list);
    }
    
    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     * @param array $a array to be merged to
     * @param array $b array to be merged from. You can specify additional
     * arrays via third argument, fourth argument etc.
     * @return array the merged array (the original arrays are not changed.)
     */
    public static function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            $next = array_shift($args);
            foreach ($next as $k => $v) {
                if (is_int($k)) {
                    if (isset($res[$k])) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }
    
        return $res;
    }
}