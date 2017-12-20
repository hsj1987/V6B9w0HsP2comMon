<?php
namespace common\helper;

/**
 * 其它工具类
 */
class utils
{

    /**
     * 生成UUID
     * @param $len 长度
     */
    public static function uuid($len = 16)
    {
        static $map, $map_len, $rand_max;
        if (! $map) {
            $map = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $map_len = strlen($map);
            $rand_max = 256 - 256 % $map_len - 1;
        }
        
        if (self::is_win_os()) {
            $seed[0] = mt_rand(0, 15);
            for ($i = 1; $i < $len; $i ++) {
                $seed[] = mt_rand(0, 61);
            }
            $uuid = str_repeat(' ', $len);
            foreach ($seed as $i => $v) {
                $uuid[$i] = $map[$v];
            }
            return $uuid;
        } else {
            $read_len = ceil($len * 256 / ($rand_max + 1)) + 4;
            $ret = str_repeat('0', $len);
            $i = 0;
            $f = fopen('/dev/urandom', 'r');
            while (true) {
                $cand = fread($f, $read_len);
                for ($j = 0; $j < $read_len; ++ $j) {
                    $ord = ord($cand[$j]);
                    if ($ord > $rand_max) {
                        continue;
                    }
                    $ret[$i ++] = $map[$ord % $map_len];
                    if ($i >= $len) {
                        fclose($f);
                        return $ret;
                    }
                }
                $read_len = 4;
            }
        }
    }

    /**
     * 获取array中的值
     * @param array $data 数组
     * @param string $key 键
     * @param string $default 默认值
     * @return
     *
     */
    public static function get($data, $key, $default = null)
    {
        return is_array($data) && isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * 获取默认值
     * @param unknow $value 值
     * @param unknow $default 默认值
     */
    public static function default_value($value, $default = '')
    {
        return self::is_empty($value) ? $default : $value;
    }

    /**
     * 是否为空值（null、''、[]）
     */
    public static function is_empty($val)
    {
        return $val === null || (is_string($val) && trim($val) === '') || (is_array($val) && count($val) == 0);
    }

    /**
     * 判断是否是内网请求
     * @param string $ip
     * @return boolean
     */
    public static function is_internal($ip = null)
    {
        if ($ip === null)
            $ip = $_SERVER['REMOTE_ADDR'];
        $l = ip2long($ip);
        return ($l >= 167772160 && $l <= 184549375) || // 10.0.0.0/8
($l >= 3232235520 && $l <= 3232301055) || // 192.168.0.0/16
($l >= 2130706432 && $l <= 2147483647) || // 127.0.0.0/8
($l >= 2851995648 && $l <= 2852061183) || // 169.254.0.0/16
($l >= 2886729728 && $l <= 2887778303); // 172.16.0.0/12
    }

    /**
     * 获取客户端IP
     */
    public static function get_cip()
    {
        return (isset($_SERVER['HTTP_X_REAL_IP'])) && self::is_internal($_SERVER['REMOTE_ADDR']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
    }

    /**
     * 数字格式化
     * @param number $num 数字
     * @param int $fixed 小数位数
     * @param boolean $enforce 是否强制保留小数位数（如果是false，则表示最大保留fixed小数位）
     */
    public static function num_fixed($num, $fixed = 2, $enforce = true)
    {
        if (! is_numeric($num)) {
            return empty($num) ? self::num_fixed(0, $fixed, $enforce) : $num;
        }
        
        $num = number_format($num, $fixed);
        $num = str_replace(',', '', $num);
        if ($enforce)
            return $num;
        
        if (strpos($num, '.') >= 0) {
            $num = preg_replace('/\.?0+$/i', '', $num);
        }
        
        return $num;
    }
    
    /**
     * 跳转
     * @param string $url
     */
    public static function redirect($url)
    {
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * 获取异常信息
     * @param \Exception $exception
     * @return array
     */
    public static function get_exception_info($exception)
    {
        $error_info = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'debug_trace' => $exception->getTraceAsString()
        ];
        return $error_info;
    }
    
    /**
     * 是否为WINDOWS系统
     * @return boolean
     */
    public static function is_win_os()
    {
        return substr(PHP_OS, 0, 3) == 'WIN';
    }
    
    /**
     * 获取当前请求URL
     * @return string
     */
    public static function get_curr_url()
    {
        return self::get_curr_server_uri() . $_SERVER['REQUEST_URI'];
    }
    
    /**
     * 获取当前站点地址
     */
    public static function get_curr_server_uri()
    {
        $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $uri = $protocol . $_SERVER['HTTP_HOST'];
        return $uri;
    }

    /**
     * url参数转json
     */
    public static function url_params_to_json($params_str)
    {
        $result = [];
        $a = explode("&",$params_str);
        foreach($a as $v) {
            $b = explode("=", $v);
            $key = urldecode($b[0]);
            $value = urldecode($b[1]);
            $result[$key] = $value;			
        }
        return $result;
    }
}