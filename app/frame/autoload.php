<?php
// 注册自动加载
$autoload = function ($class) {
    $tmp = explode('\\', $class);
    $root = dirname(__DIR__);
    if ($tmp && $tmp[0] == 'common' && count($tmp) > 1) {
        unset($tmp[0]);
        require $root . '/' . implode('/', $tmp) . '.php';
    }
};
spl_autoload_register($autoload, true, true);


