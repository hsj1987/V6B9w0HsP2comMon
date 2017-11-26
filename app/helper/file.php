<?php
namespace common\helper;

/**
 * 文件操作工具类
 */
class file
{

    /**
     * 获取指定一行内容
     * @param string $file_path 文件路径
     * @param int $offset 行下标
     * @return string 行内容
     */
    public static function get_line($file_path, $offset = 0)
    {
        $result = self::get_lines($file_path, $offset, 1);
        return $result ? $result[0] : null;
    }

    /**
     * 获取指定多行内容
     * @param string $file_path 文件路径
     * @param int $offset 行下标
     * @param int $length 行数量
     * @return array 行内容
     */
    public static function get_lines($file_path, $offset = 0, $length = 1)
    {
        $result = [];
        if (! is_file($file_path)) {
            return $result;
        }
        $handle = @fopen($file, "r");
        $i = 0;
        if ($handle) {
            while (! feof($handle)) {
                $buffer = fgets($handle, $length);
                if ($i >= $offset && $i < $offset + $length)
                    $result[] = $buffer;
                $i ++;
            }
            fclose($handle);
        }
        return $result;
    }
    
    /**
     * 获取目录下的文件
     * @param string $dir 目录
     * @param boolean $recursive 是否递归获取
     * @param array $result_files
     * @return 
     */
    public static function get_dir_files($dir, $recursive = false, &$result_files = null)
    {
        $files = scandir($dir);
        if (! $files) {
            return [];
        }
        if ($result_files === null) {
            $result_files = [];
        }
        foreach ($files as $file) {
            if (in_array($file, [
                '.',
                '..'
            ])) {
                continue;
            }
            
            $file_full_path = $dir . DIRECTORY_SEPARATOR . $file;
            if(is_dir($file_full_path)) {
                $file_obj = [
                    'name' => $file,
                    'type' => 'dir'
                ];
                if($recursive) {
                    $file_obj['child'] = [];
                    self::get_dir_files($file_full_path, $recursive, $file_obj['child']);
                }
                $result_files[] = $file_obj;
            } else {
                $result_files[] = [
                    'name' => $file,
                    'type' => 'file'
                ];
            }
        }
        
        return $result_files;
    }
}