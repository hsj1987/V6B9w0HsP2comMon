<?php
namespace common\helper;

/**
 * 数据验证工具类
 */
class validate
{
    // 验证消息
    public static function validate_msg($method)
    {
        $msgs = [
            'required' => '“%1$s”不能为空',
            'str' => '“%1$s”只能包括数字和字母',
            'str1' => '“%1$s”只能包括数字、字母、减号‘-’ 、下划线 ‘_’',
            'str2' => '“%1$s”只能包括数字、字母、空格和特殊字符：+ - * / % $ . _',
            'float' => '“%1$s”必须为整数或浮点数',
            'float1' => '“%1$s”必须为非负整数或非负浮点数',
            'float2' => '“%1$s”必须为正整数或正浮点数',
            'int' => '“%1$s”必须为整数',
            'int1' => '“%1$s”必须为非负整数',
            'int2' => '“%1$s”必须为正整数',
            'eq_length' => '“%1$s”长度必须为%2$s个字符',
            'eq_bytes' => '“%1$s”长度必须为%2$s个字节',
            'min_length' => '“%1$s”长度不能小于%2$s个字符',
            'min_bytes' => '“%1$s”长度不能小于%2$s个字节',
            'max_length' => '“%1$s”长度不能大于%2$s个字符',
            'max_bytes' => '“%1$s”长度不能大于%2$s个字节',
            'range_length' => '“%1$s”长度必须为%2$s-%3$s个字符',
            'range_bytes' => '“%1$s”长度必须为%2$s-%3$s个字节',
            'min' => '“%1$s”最小值为%2$s',
            'max' => '“%1$s”最大值为%2$s',
            'range' => '“%1$s”的范围必须为%2$s-%3$s',
            'mobile' => '“%1$s”必须为有效的11位手机号码',
            'tel' => '“%1$s”必须为有效的电话号码',
            'tel_or_mobile' => '“%1$s”必须为有效的手机或电话号码',
            'email' => '“%1$s”必须为有效的E-mail格式',
            'url' => '“%1$s”必须为有效的URL',
            'zipcode' => '“%1$s”必须为有效的邮编号码',
            'date' => '“%1$s”必须为有效的日期',
            'card_no' => '“%1$s”必须为有效的身份证号',
            'str_channel_id' => '“%1$s”无效',
            'str_secret' => '“%1$s”无效',
            'str_version' => '“%1$s”无效',
            'str_cid' => '“%1$s”无效',
            'enum' => '“%1$s”无效'
        ];
        return $msgs[$method];
    }

    /**
     * 验证数据集合
     * @param array $data 数据，格式为：{"key":"value",... ...}
     * @param array $rules 验证规则集合，格式为：
     *        [
     *        [
     *        "<field>", // 字段名，必填，stirng或数组
     *        "<method>", // 函数名，必填，string或function类型
     *        "stat":100, // 状态码，可选，int类型
     *        "msg":"验证失败消息", // 消息，可选，string类型
     *        "args":[1, 10], // 参数，可选，类型格式与method对应
     *        ]
     *        ... ...
     *        ]
     * @param string $key_prefix 键前缀，用于输出错误信息
     */
    public static function valid_multi($data, $rules, $key_prefix = null)
    {
        if (! is_array($data)) {
            return output::err_missing_param('参数缺失', true);
        }
        foreach ($rules as $rule) {
            $rule['method'] = $rule[1];
            if ($key_prefix !== null)
                $rule['key_prefix'] = $key_prefix;
            $keys = $rule[0];
            unset($rule[0]);
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    $value = $data[$key];
                    $res = self::valid_item($key, $value, $rule);
                    if ($res['stat'] !== 0)
                        return $res;
                }
            } else {
                $value = $data[$keys];
                $res = self::valid_item($keys, $value, $rule);
                if ($res['stat'] !== 0)
                    return $res;
            }
        }
        
        return output::ok();
    }

    /**
     * 验证一项
     * @param string $key 键
     * @param string $value 值
     * @param array $options 选项，格式为：
     *        [
     *        "method":"required", // 函数名，必填，string或function类型
     *        "stat":100, //状态码，可选，int类型
     *        "msg":"验证失败消息", // 消息，可选，string类型
     *        "args":[1, 10], // 参数，可选，类型格式与method对应
     *        "key_prefix": "table.",// 键前缀，用于输出错误信息，key_prefix不为空且label为空则显示为key_prefix<key>
     *        "label": "table.name"// 标签，用于输出错误信息，有标签则忽略key_prefix和key
     *        ]
     */
    public static function valid_item($key, $value, $options)
    {
        if (is_string($options)) {
            $method = $options;
            $options = [];
        } else {
            $method = $options['method'];
        }
        
        $valid_result = false;
        $is_empty_value = utils::is_empty($value);
        $error = '';
        if (is_string($method)) {
            $allow_empty = $method != 'required';
            $valid_result = ($allow_empty && $is_empty_value) || call_user_func('self::' . $method, $value, $options['args']);
            if (! $valid_result) {
                $label = ! utils::is_empty($options['label']) ? $options['label'] : (utils::is_empty($options['key_prefix']) ? $key : $options['key_prefix'] . $key);
                $error = isset($options['msg']) ? $options['msg'] : self::get_error($method, $label, $options['args']);
            }
        } else if (is_callable($method)) {
            $args = isset($options['args']) ? $options['args'] : $value;
            $valid_result = $is_empty_value || call_user_func($method, $value, $args);
            if (! $valid_result)
                $error = $options['msg'];
        }
        
        if ($valid_result) {
            return output::ok();
        } else {
            $stat = isset($options['stat']) ? $options['stat'] : ($method == 'required' ? 110 : 111);
            return output::err($stat, $error);
        }
    }

    /**
     * 获取验证函数对应错误信息
     * @param $method 验证方法
     * @param $label 标签
     * @param $param 验证参数
     */
    public static function get_error($method, $key, $args = null)
    {
        $msg = self::validate_msg($method);
        if (is_array($args) && count($args) > 0) {
            $param_count = count($args);
            switch ($param_count) {
                case 1:
                    return sprintf($msg, $key, $args[0]);
                case 2:
                    return sprintf($msg, $key, $args[0], $args[1]);
                default:
                    return sprintf($msg, $key, $args[0], $args[1], $args[2]);
            }
        } else if ($args != null) {
            return sprintf($msg, $key, $args);
        } else {
            return sprintf($msg, $key);
        }
    }
    
    // 不为空
    public static function required($val)
    {
        return ! utils::is_empty($val);
    }
    
    // str
    public static function str($val)
    {
        return preg_match("/^([0-9A-Za-z]+)$/i", $val) == 1;
    }
    
    // str1
    public static function str1($val)
    {
        return preg_match("/^([\-\w]+)$/i", $val) == 1;
    }
    
    // str2
    public static function str2($val)
    {
        return preg_match("/^([A-Z0-9\+\-\*\/\%\$\.\_\s]+)$/", $val) == 1;
    }
    
    // float
    public static function float($val, $precisior = 2)
    {
        $precisior = "{0," . $precisior . "}";
        return preg_match("/^(\-?(0|[1-9]\d*)(\.\d" . $precisior . ")?)$/i", $val) == 1;
    }
    
    // 非负float
    public static function float1($val, $precisior = 2)
    {
        return self::float($val, $precisior) && $val >= 0;
    }
    
    // 正float
    public static function float2($val, $precisior = 2)
    {
        return self::float($val, $precisior) && $val > 0;
    }
    
    // int
    public static function int($val)
    {
        return preg_match("/^(\-?(0|[1-9]\d*))$/i", $val) == 1;
    }
    
    // 非负int
    public static function int1($val)
    {
        return self::int($val) && $val >= 0;
    }
    
    // 正int
    public static function int2($val)
    {
        return self::int($val) && $val > 0;
    }
    
    // 字符长度
    public static function eq_length($val, $length_limit)
    {
        return mb_strlen($val) == $length_limit;
    }
    
    // 字节长度
    public static function eq_bytes($val, $length_limit)
    {
        return mb_strlen($val, true) == $length_limit;
    }
    
    // 最小字符长度
    public static function min_length($val, $length_limit)
    {
        return mb_strlen($val) >= $length_limit;
    }
    
    // 最小字节长度
    public static function min_bytes($val, $lengthLimit)
    {
        return mb_strlen($val, true) >= $lengthLimit;
    }
    
    // 最大字符长度
    public static function max_length($val, $lengthLimit)
    {
        return mb_strlen($val) <= $lengthLimit;
    }
    
    // 最大字节长度
    public static function max_bytes($val, $lengthLimit)
    {
        return mb_strlen($val, true) <= $lengthLimit;
    }
    
    // 字符长度区间
    public static function range_length($val, $args)
    {
        $length = mb_strlen($val);
        return $length >= $args[0] && $length <= $args[1];
    }
    
    // 字节长度区间
    public static function range_bytes($val, $args)
    {
        $length = mb_strlen($val, true);
        return $length >= $args[0] && $length <= $args[1];
    }
    
    // 最大值
    public static function max($val, $args)
    {
        return $val <= $args;
    }
    
    // 最小值
    public static function min($val, $args)
    {
        return $val >= $args;
    }
    
    // 取值范围
    public static function range($val, $args)
    {
        return $val >= $args[0] && $val <= $args[1];
    }
    
    // 手机
    public static function mobile($val)
    {
        return preg_match("/^(1[34578]\d{9})$/i", $val) == 1;
    }
    
    // 电话号码
    public static function tel($val)
    {
        return preg_match("/^((\d{3,4}\-?)?(\d\-?){6,7}\d?(\-?\d{1,4})?)$/i", $val) == 1;
    }
    
    // 电话或手机
    public static function tel_or_mobile($val)
    {
        return self::tel($val) || self::mobile($val);
    }
    
    // URL
    public static function url($val)
    {
        return preg_match(
            "/^((ht|f)tps?):\/\/[\w\-]+(\.[\w\-]+)+([\w\-\.,?^=%&:\/~\+#]*[\w\-\?^=%&\/~\+#])?$/i", 
            $val) == 1;
    }
    
    // email
    public static function email($val)
    {
        return preg_match("/^(\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*)$/i", $val) == 1;
    }
    
    // 邮编
    public static function zipcode($val)
    {
        return preg_match("/^([1-9]\d{5})$/i", $val) == 1;
    }
    
    // 日期（格式为：yyyy-mm-dd或yyyy/mm/dd）
    public static function date($val, $has_separator = true)
    {
        $separator = $has_separator ? "[\/\-]" : "";
        return preg_match("/^\d{4}$separator((0[1-9])|(1[0-2]))$separator((0[1-9])|([1-2][0-9])|(3[0-1]))$/i", $val) == 1;
    }
    
    // 身份证号验证：15位数字、18位数字、17位数字+X
    public static function card_no($val)
    {
        $Y = '([1-2]\d{3})';
        $M = '((0[1-9])|(1[0-2]))';
        $D = '((0[1-9])|([1-2]\d)|(3[0-1]))';
        $MD = $M . $D;
        $YMD = $Y . $MD;
        return preg_match("/^[1-9]((\d{7}" . $MD . "\d{3})|(\d{5}" . $YMD . "\d{4})|(\d{5}" . $YMD . "\d{3})(\d|X|x))$/i", $val) == 1;
    }
    
    // 通道号格式验证
    public static function str_channel_id($val)
    {
        return preg_match('/^[0-9a-zA-Z]{6,16}$/', $val) == 1;
    }
    
    // 密钥格式验证
    public static function str_secret($val)
    {
        return preg_match('/^[0-9a-zA-Z]{128}$/', $val) == 1;
    }
    
    // 版本格式验证
    public static function str_version($val)
    {
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $val) == 1;
    }
    
    // CID格式验证
    public static function str_cid($val, $type = null)
    {
        switch ($type) {
            case 'pos':
                return preg_match('/^pos:[0-9a-zA-Z]{10,11}$/', $val) == 1;
            case 'pc':
                return preg_match('/^pc:[0-9a-zA-Z]{8,32}$/', $val) == 1;
            default:
                return preg_match('/^[0-9a-zA-Z]{1,16}:[0-9a-zA-Z]{8,32}$/', $val) == 1;
        }
    }

    public static function enum($val, $args)
    {
        return in_array($val, $args) == 1;
    }
    
    // 16位id检查
    public static function id_format($str)
    {
        if ($str) {
            if (preg_match('/^[0-9a-zA-Z]{16}$/i', $str)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
