<?php
namespace common\frame;

class request
{

    public $map = [];
    
    public $raw = [];

    public function __construct($raw = null)
    {
        $this->raw = $raw === null ? $_REQUEST : $raw;
        foreach ($this->raw as $k => $v)
            $this->map[$k] = $v;
    }

    public function __get($key)
    {
        return isset($this->map[$key]) ? $this->map[$key] : null;
    }
}

