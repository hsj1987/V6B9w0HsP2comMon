<?php
namespace common\helper;

use common\log\log;

/**
 * http请求工具类
 */
class http
{

    /**
     * 请求外部API
     * @param string $url 地址
     * @param array/string $params 参数
     * @param array $config 配置，详情见curl函数
     * @return 输出
     */
    public static function ext_api($url, $params = null, $config = null)
    {
        $api_type = utils::default_value($config['api_type'], 'extapi');
        return self::curl($api_type, $url, $params, $config);
    }

    /**
     * 请求内部API
     * @param string $api_type 请求类型，pfsapi、fileapi、likeapi、openapi
     * @param string $api API名称
     * @param array/string $params 参数
     * @param array $config 配置，详情见curl函数
     * @return array 输出
     */
    public static function inner_api($api_type, $api, $params = null, $config = null)
    {
        global $LIKE_SERVICES_URL;
        if (! array_key_exists($api_type, $LIKE_SERVICES_URL)) {
            return false;
        }
        
        if (! isset($config['timeout'])) {
            $config['timeout'] = 3;
        }
        
        $url = $LIKE_SERVICES_URL[$api_type] . '/' . $api;
        if (! $config['log_params_filter_fn']) {
            $config['log_params_filter_fn'] = [
                'self::',
                'log_params_filter'
            ];
        }
        if (! $config['log_result_filter_fn']) {
            $config['log_result_filter_fn'] = [
                'self::',
                'log_result_filter'
            ];
        }
        
        $result = self::curl($api_type, $url, $params, $config);
        
        // 把code转成stat，message转成msg
        $res = json_decode($result, true);
        if ($res) {
            if (isset($res['code']) && ! isset($res['stat'])) {
                $res['stat'] = $res['code'];
                unset($res['code']);
            }
            if (isset($res['message'])) {
                $res['msg'] = $res['message'];
                unset($res['message']);
            }
        } else {
            $res = output::err_internal('API result json_decode fail!', [
                'api_result' => $result
            ]);
        }
        
        return $res;
    }

    /**
     * curl请求
     * @param string $api_type API类型
     * @param string $url 地址
     * @param array/string $params 参数
     * @param array $config [
     *        'method' => 'POST', // 请求方式
     *        'timeout' => 60, // 超时时长（秒）
     *        'header' => null, // 请求头部
     *        'is_json' => false, // 参数是否为json格式，默认否
     *        'log_params_filter_fn' => null, // 日志参数过滤函数
     *        'log_result_filter_fn' => null // 日志输出过滤函数
     *        ]
     * @return 输出
     */
    public static function curl($api_type, $url, $params = null, $config = null)
    {
        $method = utils::default_value($config['method'], 'POST');
        $timeout = utils::default_value($config['timeout'], 30);
        $header = $config['header'];
        $is_json = $config['is_json'];
        $log_params_filter_fn = $config['log_params_filter_fn'];
        $log_result_filter_fn = $config['log_result_filter_fn'];
        $opts = [
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => 1,
        ];
        if ($header !== null) {
            $opts[CURLOPT_HTTPHEADER] = $header;
        }
        
        // 根据请求类型设置特定参数
        switch (strtoupper($method)) {
            case 'GET':
                $full_url = $params ? ($url . '?' . (is_array($params) ? http_build_query($params) : $params)) : $url;
                $opts[CURLOPT_URL] = $full_url;
                break;
            case 'POST':
                // 判断是否传输文件
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = true;
                if ($params) {
                    $opts[CURLOPT_POSTFIELDS] = $is_json === true ? json_encode($params) : http_build_query($params);
                }
                break;
        }
        
        // 记录请求开始LOG
        if (log::is_log_http()) {
            $log_start = self::log_start($api_type, $url, $params, $method, $header, $timeout, $log_params_filter_fn);
        }
        
        // 初始化并执行curl请求
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        // 错误信息
        if (! empty($error)) {
            $info = curl_getinfo($ch);
            log::http(['http_info' => $info]);
            
            $res = output::err_internal('HTTP_REQUEST_ERROR: ' . $errno . '-' . $error, [
                'errno' => $errno
            ]);
            $result = json_encode($res);
        }
        
        curl_close($ch);
        
        // 记录请求结束LOG
        if (log::is_log_http()) {
            self::log_end($api_type, $log_start['request_id'], $log_start['starttime'], $result, $log_result_filter_fn);
        }
        
        return $result;
    }

    /**
     * 记录HTTP请求LOG开始
     * @param string $api_type API类型
     * @param string $url 地址
     * @param array $params 参数
     * @param string $method 请求方式
     * @param array $header 请求头部
     * @param int $timeout 超时时长（秒）
     * @param function $log_params_filter_fn 日志参数过滤函数
     * @return array
     */
    public static function log_start($api_type, $url = null, $params = null, $method = null, $header = null, $timeout = null, $log_params_filter_fn = null)
    {
        $request_id = utils::uuid();
        $starttime = microtime(true);
        $log_params = is_callable($log_params_filter_fn) ? $log_params_filter_fn($api_type, $params) : $params;
        $log_content = [
            'api_type' => $api_type,
            'request_id' => $request_id,
            'request_step' => 'start'
        ];
        arr::append_not_null_value($log_content, 'url', $url);
        arr::append_not_null_value($log_content, 'params', $log_params);
        arr::append_not_null_value($log_content, 'method', $method);
        arr::append_not_null_value($log_content, 'header', $header);
        arr::append_not_null_value($log_content, 'timeout', $timeout);
        log::http($log_content);
        return [
            'request_id' => $request_id,
            'starttime' => $starttime
        ];
    }

    /**
     * API类型
     * @param string $api_type API类型
     * @param string $request_id 请求ID
     * @param int $starttime 请求开始时间
     * @param string $result 请求结果
     * @param function $log_result_filter_fn 日志输出过滤函数
     * @return boolean
     */
    public static function log_end($api_type, $request_id, $starttime, $result, $log_result_filter_fn = null)
    {
        $endtime = microtime(true);
        $exectime = round(($endtime - $starttime) * 1000);
        $log_result = is_callable($log_result_filter_fn) ? $log_result_filter_fn($api_type, $result) : $result;
        $log_content = [
            'api_type' => $api_type,
            'request_id' => $request_id,
            'request_step' => 'end',
            'exectime' => $exectime,
            'result' => $log_result
        ];
        log::http($log_content);
        return true;
    }

    public static function log_params_filter($api_type, $params)
    {
        if ($api_type == 'pfs' && isset($params['key'])) {
            $params['key'] = '***';
        }
        return $params;
    }

    public static function log_result_filter($api_type, $result)
    {
        if (env::is_production() && $api_type == 'pfs') {
            $log_result = json_decode($result, true);
            if (isset($log_result['data'])) {
                $log_result['data'] = '***';
            }
            return json_encode($log_result, JSON_UNESCAPED_UNICODE);
        } else {
            $log_result = strlen($result) > 500 ? substr($result, 0, 500) . '...（Log tip: too much ouput!）' : $result;
        }
        return $log_result;
    }
}