<?php
// APP_CONFIG
return [
    'error_notify' => true,
    'controller' => [
        'default_name' => 'index',// 默认controller名称
        'prefix' => 'controller_', // controller前缀，controller文件和类名需要以此前缀定义，route中不需要带此前缀
        'dir' => 'controller', // controller目录，项目APP_ROOT下的目录
        'uri' => '/' // controller对应uri，默认对应web根目录uri
    ],
    'action' => [
        'view_name' => 'index', // 视图Action名称
        'prefix' => 'action_' // 视图Action名称
    ],
    'session' => [
        'enabled' => true,
        'handler' => 'default', // default、redis
        'kv_type' => 'main',
        'kv_prefix' => 'SESSION:'
    ],
    'smarty' => [
        'enabled' => true,
        'template_dir' => APP_ROOT . '/view',
        'compile_dir' => dirname(APP_ROOT) . '/tmp/templates_c',
        'plugins_dir' => APP_ROOT . '/web/plugins',
        'error_view' => 'error.html'
    ]
];