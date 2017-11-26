<?php
namespace common\helper;

/**
 * 字符串工具类
 */
class str
{
    /**
     * 编码转换
     * @param unknown $str
     * @param string $to_encoding
     * @return unknown|string
     */
    public static function convert_encoding($str, $to_encoding = 'UTF-8')
    {
        if(empty($str))
            return $str;
        
        $from_encoding = self::get_charset($str);
        $str = mb_convert_encoding($str, $to_encoding, $from_encoding);
        return $str;
    }

    /**
     * 获取字符串编码格式
     * @param str $string
     */
    public static function get_charset($str)
    {
        if (chr(239) . chr(187) . chr(191) == substr($str, 0, 3))
            return 'utf-8 bom';
        if ($str === iconv('UTF-8', 'UTF-8', iconv('UTF-8', 'UTF-8', $str)))
            return 'utf-8';
        if ($str === iconv('UTF-8', 'ASCII', iconv('ASCII', 'UTF-8', $str)))
            return 'ascii';
        if ($str === iconv('UTF-8', 'GBK', iconv('GBK', 'UTF-8', $str)))
            return 'gbk';
        if ($str === iconv('UTF-8', 'GB2312', iconv('GB2312', 'UTF-8', $str)))
            return 'gb2312';
        return strtolower(mb_detect_encoding($str, 'UTF-8,ASCII,GBK,GB2312', true));
    }

    /**
     * 获取字符串字符长度
     * @param string $str 字符串
     */
    public static function get_len($str, $get_byte_len = false, $charset = 'GBK')
    {
        if (empty($str))
            return 0;
        
        $str_charset = self::get_charset($str);
        if ($get_byte_len) {
            if ($str_charset != $charset) {
                $str = iconv($str_charset, $charset, $str);
            }
            return strlen($str);
        } else {
            return mb_strlen($str, $str_charset);
        }
    }
}