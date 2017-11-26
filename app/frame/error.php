<?php
namespace common\frame;

class error extends \Exception
{
    
    public $output;

    public function __construct($message = null, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取错误码名称
     * @return string
     */
    public function get_code_name()
    {
        switch ($this->code) {
            case E_NOTICE:
                $code_name = 'NOTICE';
                break;
            case E_WARNING:
                $code_name = 'WARNING';
                break;
            case E_ERROR:
                $code_name = 'ERROR';
                break;
            case E_ALL:
                $code_name = 'ALL';
                break;
            default:
                $code_name = 'E_ ' . $this->code;
                break;
        }
        return $code_name;
    }

    public function set_code($code)
    {
        $this->code = $code;
    }

    public function set_message($message)
    {
        $this->message = $message;
    }

    public function set_file($file)
    {
        $this->file = $file;
    }

    public function set_line($line)
    {
        $this->line = $line;
    }
}

