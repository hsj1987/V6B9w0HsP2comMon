<?php
namespace common\frame\api;

use common\helper\validate;
use common\helper\output;
use common\helper\utils;
use common\frame\request;
use common\pfs\token;
use common\db\db;

abstract class api_base
{
    // token 信息
    protected $token_info;
    
    // 设备信息
    protected $device_info;
    
    // 店铺ID
    protected $shop_id;
    
    // 记录LOG
    public $need_log = true;
    
    // 身份类型：like-来客；open-外部 
    public $identity_type = IDENTITY_TYPE_LIKE;
    
    // API默认版本号，如果不传入version参数，则以此参数为准
    public $version = '1.0';
    
    // request对象
    public $request;
    
    public $config;

    public function __construct($request, $config = null)
    {
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * 定义验证规则
     * @return array 验证规则，格式如：
     *         [
     *         ['validate_likeapi_token', 'need_bind_shop' => true],
     *         ['ident','required']
     *         ]
     */
    public function validate_rules()
    {
        return [];
    }

    /**
     * 验证函数配置
     */
    public function get_validate_fn_config($request)
    {
        $config = [
            'validate_identity' => [
                'base_rules' => [
                    [
                        'identity_type',
                        'enum',
                        'args' => [
                            IDENTITY_TYPE_LIKE,
                            IDENTITY_TYPE_OPEN
                        ]
                    ]
                ],
            ],
            'validate_likeapi_token' => [
                'base_rules' => [
                    [
                        '_t',
                        'required'
                    ],
                    [
                        '_cid',
                        'required'
                    ],
                    [
                        '_cid',
                        'str_cid'
                    ]
                ],
            ],
            'validate_device_bind' => [
                'base_rules' => [
                    [
                        '_cid',
                        'required'
                    ],
                    [
                        '_cid',
                        'str_cid'
                    ]
                ],
            ],
            'validate_openapi_identity' => [
                'base_rules' => [
                    [
                        'channel_id',
                        'required'
                    ],
                    [
                        'channel_id',
                        'str_channel_id'
                    ]
                ]
            ],
            'validate_page_params' => [
                'base_rules' => [
                    [
                        'page_size',
                        'int2'
                    ],
                    [
                        'curr_page',
                        'int2'
                    ]
                ]
            ],
            'validate_from_internal' => [],
        ];
        
        $version = utils::get($rule, 'version', $this->version);
        if ($version == '1.0') {
            $config['validate_openapi_identity']['base_rules'][] = [
                'secret',
                'required'
            ];
            $config['validate_openapi_identity']['base_rules'][] = [
                'secret',
                'str_secret'
            ];
        } else {
            $config['validate_openapi_identity']['base_rules'][] = [
                'sign',
                'required'
            ];
        }
        
        $append_identity_config = $this->identity_type == IDENTITY_TYPE_LIKE ? $config['validate_likeapi_token'] : $config['validate_openapi_identity'];
        $config['validate_identity'] = array_merge($config['validate_identity'], $append_identity_config);
        
        return $config;
    }
    
    /**
     * 验证
     * @param request $request 请求
     * @return res
     */
    public function validate($request)
    {
        $rules = $this->validate_rules();
        if (! is_array($rules) || count($rules) === 0) {
            return output::ok();
        }
        
        // 设置身份验验证类型
        $this->identity_type = $request->identity_type && in_array($request->identity_type, [IDENTITY_TYPE_LIKE, IDENTITY_TYPE_OPEN]) ? $request->identity_type : (!utils::is_empty($request->_t) ? IDENTITY_TYPE_LIKE : IDENTITY_TYPE_OPEN);
        
        // 获取预定义验证中的基础验证规则
        $validate_fn_config = $this->get_validate_fn_config($request);
        $validate_fns = [];
        for ($k = 0; $k < count($rules); $k ++) {
            $rule = $rules[$k];
            $method = $rule[0];
            if (array_key_exists($method, $validate_fn_config)) {
                $base_rules = $validate_fn_config[$method]['base_rules'];
                array_splice($rules, $k, 1, $base_rules);
                if ($base_rules) {
                    $k = $k - 1 + count($base_rules);
                }
                $validate_fns[] = $rule;
            }
        }
        
        // 基础验证
        $res = validate::valid_multi($request->map, $rules);
        if ($res['stat'] !== 0) {
            return $res;
        }
        
        // 函数验证
        foreach ($validate_fns as $rule) {
            $method = $rule[0];
            if (is_callable([
                $this,
                $method
            ])) {
                $res = call_user_func([
                    $this,
                    $method
                ], $request, $rule);
                
                $callback_method = $validate_fn_config[$method]['callback_fn'];
                if (! utils::is_empty($callback_method)) {
                    call_user_func([
                        $this,
                        $callback_method
                    ], $res);
                }
                if ($res['stat'] !== 0) {
                    return $res;
                }
            }
        }
        return output::ok();
    }

    /**
     * API 预处理
     * @param request $request
     */
    public function prepare($request)
    {
        return true;
    }

    /**
     * API 运行
     * @param request $request
     */
    public abstract function run($request);

    /**
     * 输出响应
     * @param array/string $content
     * @param string $step 执行步骤
     */
    public function output($content)
    {
        output::write($content);
    }
    
    /**
     * 验证身份（用于同时支持来客和外部调用的API）
     * @param request $request
     * @param array $rule
     * @return unknown
     */
    public function validate_identity($request, $rule)
    {
        if($this->identity_type == IDENTITY_TYPE_LIKE) {
            return $this->validate_likeapi_token($request, $rule);
        } else {
            return $this->validate_openapi_identity($request, $rule);
        }
    }
    
    /**
     * 验证likeapi token
     * @param request $request
     * @param array $rule 验证规则配置
     */
    public function validate_likeapi_token($request, $rule)
    {
        $need_bind_shop = utils::get($rule, 'need_bind_shop', true);
        
        // token验证
        $res = token::verify($request->_t, $request->_cid);
        if ($res['stat'] === 1 || $res['stat'] === 2) {
            return output::err_token();
        } else if ($need_bind_shop && $res['stat'] === 10) {
            return output::err_token_no_bind();
        } else if ($res['stat'] === 11) {
            return output::err_shop_in_maintenance();
        } else if ($res['stat'] !== 0 && $res['stat'] !== 10) {
            return output::err_pfs($res);
        }
        
        $this->token_info = $res['data'];
        $this->shop_id = $this->token_info['shop'];
        return output::ok($res['data'], [
            'sub_stat' => $res['stat']
        ]);
    }
    
    /**
     * 验证设备是否绑定商户
     * @param string $ident 设备ID
     * @param string $device_type 设备类型 pos、pc
     */
    public function validate_device_bind($request)
    {
        $device = $this->parse_cid($request->_cid);
        $shop_id = cred::get_bind_shop_id($device['type'], $device['ident']);
        if ($shop_id === null)
            return output::err_device_no_bind($device['type']);
        
        $this->device_info = $device;
        $this->shop_id = $shop_id;
        return output::ok([
            'device_info' => $device,
            'shop_id' => $shop_id
        ]);
    }
    
    /**
     * 验证请求是否来自内部
     * @param request $request
     */
    public function validate_from_internal($request)
    {
        return utils::is_internal() ? output::ok() : output::err_no_right();
    }
    
    /**
     * 解析CID
     * @param string $cid cid，格式：<type>:<ident>
     * @return string 或 array [type:'类型',ident:'ID']
     */
    public function parse_cid($cid, $field = null)
    {
        $cid_arr = explode(':', $cid);
        $cid_data = [
            'type' => $cid_arr[0],
            'ident' => $cid_arr[1]
        ];
        return $field !== null ? $cid_data[$field] : $cid_data;
    }
    
    /**
     * 验证openapi 身份
     * @param request $request
     * @param array $rule 验证规则配置
     * @return res
     */
    public function validate_openapi_identity($request, $rule)
    {
        $version = utils::get($rule, 'version', $this->version);
        $db_type = utils::get($rule, 'db_type', 'main');
        $db = db::get_db($db_type);
    
        // 验证身份
        if ($version == '1.0') {
            $count = $db->count('sys_partner_channel', [
                'AND' => [
                    'id' => $request->channel_id,
                    'secret' => $request->secret,
                    'deleted' => 0
                ]
            ]);
            if ($count === 0) {
                return output::err_openapi_identity();
            }
        } else {
            $sign = $this->get_sign_str($request->channel_id, $request->raw);
            if ($sign != $request->sign) {
                return output::err_openapi_identity('签名验证失败');
            }
        }
        
        // 验证商户权限
        $validate_right = utils::get($rule, 'validate_right', true);
            if ($validate_right) {
            $count = $db->count('sys_partner_right', [
                'AND' => [
                    'channel_id' => $request->channel_id,
                    'shop_id' => $request->shop_id
                ]
            ]);
            if ($count === 0) {
                return output::err_no_right();
            }
        }
        
        $this->shop_id = $request->shop_id;
        return output::ok();
    }
    
    /**
     * 获取OPENAPI签名字符串
     * @param string $channel_id 合作方通道ID
     * @param array $params 参数
     * @param bool $get_params_str 是否获取参数字符串
     * @return string
     */
    public function get_sign_str($channel_id, $params, $get_params_str = false)
    {
        $db = db::main_db();
        $secret = $db->get('sys_partner_channel', 'secret', [
            'AND' => [
                'id' => $channel_id,
                'deleted' => 0
            ]
        ]);
        
        unset($params['sign']);
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $value) {
            $params_str .= '&' . $key . '=' . (is_array($value) ? urlencode(json_encode($value)) : urlencode($value));
        }
        $params_str = substr($params_str, 1);
        $sign = md5(strtolower($params_str . '&secret=' . $secret));
        $params_str = $params_str . '&sign=' . $sign;
        return $get_params_str ? $params_str : $sign;
    }
}

