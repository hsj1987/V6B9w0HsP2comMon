<?php
error_reporting(E_ALL ^ E_NOTICE);
define('EXECUTE_START', microtime(true));
defined('COMMON_APP_ROOT') || define('COMMON_APP_ROOT', dirname(__DIR__));

// 日志相关配置
define('LOG_LEVEL_ERROR', 3);
define('LOG_LEVEL_WARNING', 4);
define('LOG_LEVEL_NOTICE', 5);
define('LOG_LEVEL_INFO', 6);
define('LOG_LEVEL_DEBUG', 7);

// 错误码
define('ERR_INTERNAL', 100);
define('ERR_PFS', 101);
define('ERR_MISSING_PARAM', 110);
define('ERR_INVALID', 111);
define('ERR_TOKEN', 120);
define('ERR_TOKEN_NO_BIND', 121);
define('ERR_DEVICE_NO_BIND', 122);
define('ERR_OPENAPI_IDENTITY', 123);
define('ERR_NO_RIGHT', 124);
define('ERR_SHOP_IN_MAINTENANCE', 130);
define('ERR_DATA_NOT_EXISTS', 130);
define('ERR_SHOP_NO_LICENSE', 130);
define('ERR_UNDEFINDED', 201);

// 响应编码
define('STAT_RESPONSE_REDIRECT', 1001);
define('STAT_RESPONSE_RENDER', 1002);

// 身份类型
define('IDENTITY_TYPE_TOKEN', 1); // TOKEN
define('IDENTITY_TYPE_OPEN', 2); // 外部

// 其它配置
define('PFS_API_KEY', ''); // PFS API 密钥
define('PFS_LOG_DOMAIN', ''); // 平台LOG 主机
define('PFS_LOG_PORT', 5564); // 平台LOG 端口

// 服务URL
$SERVICES_URL = [

];

$VERSION_INFO = [
    'num' => '1.0',
    'make_time' => '2017-11-26'
];