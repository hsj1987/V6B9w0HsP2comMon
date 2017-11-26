<?php
namespace common\helper;

use common\log\log;

/**
 * 输出工具类
 */
class output
{

    /**
     * 写输出并退出
     */
    public static function write($content)
    {
        if (is_string($content)) {
            echo $content;
        } elseif (is_array($content)) {
            echo json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        exit();
    }
    
    /**
     * 构造跳转返回结构
     * @param string $url
     * @return array
     */
    public static function redirect($url)
    {
        return self::res(STAT_RESPONSE_REDIRECT, '页面跳转', [
            'url' => $url
        ], [
            'response_type' => 'redirect'
        ]);
    }
    
    /**
     * 构造呈现返回结构
     * @param string $url
     * @return array
     */
    public static function render($view)
    {
        return self::res(STAT_RESPONSE_RENDER, '页面呈现', [
            'view' => $view
        ], [
            'response_type' => 'render'
        ]);
    }

    /**
     * 构造返回结构
     * @param int $stat 0:正常，1：无权限
     * @param string $msg 错误信息/数据
     * @param array $data 数据
     * @param array $params 扩展参数
     * @return 返回json结构
     */
    public static function res($stat = 0, $msg = null, $data = null, $params = null)
    {
        $res['stat'] = $stat;
        if ($msg !== null)
            $res['msg'] = $msg;
        if ($data !== null)
            $res['data'] = $data;
        if (is_array($params))
            $res = array_merge($res, $params);
        return $res;
    }

    /**
     * 执行成功
     * @param array $data 输出数据
     * @param array $params 扩展参数
     * @return res
     */
    public static function ok($data = null, $params = null)
    {
        return self::res(0, null, $data, $params);
    }

    /**
     * 根据异常获取API输出结构
     * @param Exception $exception
     * @return res
     */
    public static function exception($exception)
    {
        $error_info = utils::get_exception_info($exception);
        $log_error = env::is_test() ? ['error' => $error_info] : null;
        $res = $exception->output !== null ? $exception->output : output::err_internal(null, $log_error);
        log::error('服务端出现异常', $exception);
        return $res;
    }

    /**
     * 执行失败
     * @param int $stat 错误码
     * @param string $msg 错误消息
     * @return res
     */
    public static function err($stat, $msg = null)
    {
        return self::res($stat, $msg);
    }

    /**
     * 服务端出现异常
     * @param string $msg 错误消息
     * @param array $params 扩展参数
     * @return res
     */
    public static function err_internal($msg = null, $params = null)
    {
        if ($msg) {
            return self::res(ERR_INTERNAL, '服务端出现异常: ' . $msg, null, $params);
        } else {
            return self::res(ERR_INTERNAL, '服务端出现异常', null, $params);
        }
    }

    /**
     * PFS错误
     * @param array $pfs_res PFS输出
     * @return res
     */
    public static function err_pfs($pfs_res)
    {
        $msg = 'PFS报错';
        return self::res(ERR_PFS, $msg, [
            'pfs_result' => $pfs_res
        ]);
    }

    /**
     * 缺失参数
     * @param string $k 参数
     * @return res
     */
    public static function err_missing_param($key_or_msg, $param1_is_msg = false)
    {
        $msg = $param1_is_msg ? $key_or_msg : '“' . $key_or_msg . '”不能为空';
        return self::res(ERR_MISSING_PARAM, $msg);
    }

    /**
     * 参数无效
     * @param string $k
     * @return res
     */
    public static function err_invalid($key_or_msg = null, $param1_is_msg = false)
    {
        $msg = $param1_is_msg ? $key_or_msg : '“' . $key_or_msg . '”无效';
        return self::res(ERR_INVALID, $msg);
    }

    /**
     * token无效
     * @return res
     */
    public static function err_token($msg = 'token 无效')
    {
        return self::res(ERR_TOKEN, $msg);
    }

    /**
     * token没有绑定商户
     * @return res
     */
    public static function err_token_no_bind($msg = 'token 没有绑定商户')
    {
        return self::res(ERR_TOKEN_NO_BIND, $msg);
    }

    /**
     * 设备没有绑定商户
     * @param string $device_type_or_msg 设备类型：pos、pc，或错误消息
     * @return res
     */
    public static function err_device_no_bind($device_type_or_msg = 'pos')
    {
        $msg = in_array(strtolower($device_type_or_msg), [
            'pos',
            'pc'
        ]) ? strtoupper($device_type_or_msg) . '没有绑定商户' : $device_type_or_msg;
        return self::res(ERR_DEVICE_NO_BIND, $msg);
    }

    /**
     * channel_id 或 secret 无效（OPENAPI专用）
     * @return res
     */
    public static function err_openapi_identity($msg = '“channel_id”或“secret”无效')
    {
        return self::res(ERR_OPENAPI_IDENTITY, $msg);
    }

    /**
     * 没有权限
     * @return res
     */
    public static function err_no_right($msg = '没有权限')
    {
        return self::res(ERR_NO_RIGHT, $msg);
    }

    /**
     * 商户处于维护状态
     * @return res
     */
    public static function err_shop_in_maintenance($msg = '商户处于维护状态')
    {
        return self::res(ERR_SHOP_IN_MAINTENANCE, $msg);
    }

    /**
     * 数据不存在或已被删除或无权访问
     * @return res
     */
    public static function err_data_not_exists($msg = '数据不存在或已被删除或无权访问')
    {
        return self::res(ERR_DATA_NOT_EXISTS, $msg);
    }

    /**
     * 用户没绑定liscen
     * @return \返回json结构
     */
    public static function err_shop_no_license($msg = '用户没绑定license')
    {
        return self::res(ERR_SHOP_NO_LICENSE, $msg);
    }

    /**
     * 未定义
     * @param string $type 类型：controller、api、action
     */
    public static function err_undefinded($type = 'controller')
    {
        $msg = $type . '未定义';
        return self::res(ERR_UNDEFINDED, $msg);
    }
}
