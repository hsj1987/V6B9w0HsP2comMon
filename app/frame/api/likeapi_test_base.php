<?php
namespace common\frame\api;

use common\helper\output;
use common\helper\utils;
use common\helper\http;
use common\pfs\token;
use common\kv\kv;

class likeapi_test_base extends api_test_base
{
    public $cid = 'pos:15060000077';
    
    public function __construct($request)
    {
        parent::__construct($request);
        
        $this->common_params = [
            '_cid' => $this->cid,
            '_t' => $this->get_token()
        ];
    }
    
    /**
     * 获取token
     */
    protected function get_token()
    {
        $token = $this->token;
    
        if (utils::is_empty($token)) {
            $kv = kv::osa_kv('cache_test');
            $token = $kv->get_cache('token');
            $token_valid = true;
            $res = token::verify($token, $this->cid);
            if ($res['stat'] !== 0) {
                $token_valid = false;
            }
        }
    
        if (utils::is_empty($token) || ! $token_valid) {
            $params = [
                '_cid' => $this->cid,
                'time' => time(),
                '_ppdv' => 'android:merchant:pad:1.0.0',
                'nonce' => utils::uuid(64),
                'signed' => utils::uuid(16),
                'signkey' => utils::uuid(16)
            ];
            $res = http::api('pos.login', $params);
            $token = $res['data']['token'];
            if (! $kv)
               $kv = kv::osa_kv('cache_test');
            $kv->set_cache('token', $token, 6 * 24 * 3600);
            $this->token = $token;
        }
        return $token;
    }
}

