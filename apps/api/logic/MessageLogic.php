<?php
namespace app\api\logic;

use think\Log;
use think\Model;
use think\Db;
use app\api\model\SmsSend;

class MessageLogic extends Model {

    private $codeModel = '';

    function __construct () {
        parent::__construct ();
        $this->codeModel = new SmsSend();
    }

    /**
     * 获取验证码信息
     * @param array $where
     * 查询条件
     */
    public function getCodeInfo ($where = []) {
        $data = Db::name('sms_send')->where($where)->find();
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => transArray($data),
            'count' => 0
        ];
    }

    /**
     * 删除验证码
     * @param string $phone_number
     */
    public function getCodeDel ($phone_number = '') {
        $this->codeModel->where(['phone_number' => $phone_number])->delete();
    }

    /**
     * 更新验证码
     * @param array $save_data
     * 更新数据
     * @param int $scene
     * 场景 0是添加， 1是更新
     */
    public function getCodeSave ($save_data = [], $scene = 0) {
        $is_update = false;
        if ($scene == 1) {
            $is_update = true;
        }
        $this->codeModel->isUpdate($is_update)->save($save_data);
    }

    /**
     * 验证验证码对否正确
     * @param string $phone
     * @param string $code
     */
    public function getVerificationCode ($phone = '', $code = '') {
        $data = $this->codeModel->where(['phone_number' => $phone, 'code' => $code])->find();
        $data = transArray($data);
        if (!empty($data)) {
            $min_time = strtotime("+15 minute");
            if ($data['add_time'] + $min_time < time())  {
                return [
                    'code' => 201,
                    'msg' => '验证码已过期请重新发送',
                    'data' => [],
                    'count' => 0
                ];
            } else {
                return [
                    'code' => 200,
                    'msg' => '验证成功',
                    'data' => [],
                    'count' => 0
                ];
            }
        } else {
            return [
                'code' => 201,
                'msg' => '验证码错误请重新填写',
                'data' => [],
                'count' => 0
            ];
        }
    }

    /**
     * 获取运营商短息配置
     */
    public function getSmsConfig () {
        $data = $this->smsModel
            ->where(['operator_id' => $this->operator_id, 'status' => 1])
            ->find();
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => transArray($data),
            'count' => 0
        ];
    }

    /**
     * 获取集团短信剩余条数
     * @param int $operator_id
     * 运营商ID
     * @return array|string
     */
    public function getSmsNumber ($operator_id = 0) {
        // 默认短信剩余条数为0条
        $num = 0;
        // 获取短信剩余信息
        $data  = $this->smsCountModel->where(['operator_id' => $operator_id])->find();
        $data  = transArray($data);
        // 集团剩余短信信息不为空
        if (!empty($data)) {
            $num = $data['num'];
            if ($data['num'] <= $data['warning_num'] && $data['is_warning'] == 2) {
                // 短信预警发送
                try {
                    $this->getSendMsmToaAdmin($operator_id, $data['warning_num']);
                } catch (\Exception $e) {

                }
            }
        }
        // 返回数据
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => $num,
            'count' => 0
        ];
    }

    /**
     * 减剩余短信条数
     * @param int $operator_id
     * 运营商ID
     * @param int $num
     * 减去使用条数
     */
    public function getReduceSmsNumber ($operator_id = 0, $num = 1) {
        // 查询集团短信剩余信息
        $data  = $this->smsCountModel->where(['operator_id' => $operator_id])->find();
        $data  = transArray($data);
        // 有集团短信信息
        if (!empty($data)) {
            // 剩余短信条数 默认为0条
            $surplus_num = 0;
            // 如果当前剩余短信条数大于短信发送数量
            if ($data['num'] >= $num) {
                $surplus_num = $data['num']-$num;
            }
            // 更新短信剩余条数
            $this->smsCountModel->where(['operator_id' => $operator_id])->update(['num' => $surplus_num, 'update_time' => time()]);
            // 如果剩余短信达到预警值发送短信
            if ($surplus_num <= $data['warning_num'] && $data['is_warning'] == 2) {
                $this->getSendMsmToaAdmin($operator_id, $data['warning_num']);
            }
        }
    }

    /**
     * 给管理员发送短信预警短信
     * @param int $operator_id
     * 运营商ID
     * @param int $warning_num
     * 短信预警值
     */
    public function getSendMsmToaAdmin ($operator_id = 0, $warning_num = 500) {
        // 查询运营商信息
        $data = $this->operator->where(['id' => $operator_id])->find();
        $data = transArray($data);
        // 集团信息不为空
        if (!empty($data)) {
            // 集团联系人手机号不为空
            if (!empty($data['contacts_phone_num'])) {
                // 总部配置
                $sms_config['app_key']    = $data['key'];
                $sms_config['app_secret'] = $data['secret'];
                $sms_config['sign_name']  = $data['sign_name'];
                $sms_config['tpl_code']   = 'SMS_167051809';
                // 发送短信预警提醒
                $sms_phone = $data['contacts_phone_num'];
                $sms_content = ['code' => $warning_num];
                $list = sendShortMessage($sms_phone, $sms_content, $sms_config);
                if ($list['Code'] == 0 && $list['Message'] == "OK") {
                    $this->smsCountModel->where(['operator_id' => $operator_id])->update(['is_warning' => 1, 'update_time' => time()]);
                }
            }
        }
    }

    /**
     * 短信插入记录
     * @param array $save_data
     * 插入数据
     */
    public function getAddSmsRecord ($save_data = []) {
        if (!empty($save_data)) {
            // 添加短信发送记录
            $this->smsRecordModel->saveAll($save_data);
        }
    }

    /**
     * 获取用户open_id
     * @return array
     */
    public function getUserOpenId () {
        $data = $this->userModel->where(['id' => $this->user_id])->find();
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => transArray($data),
            'count' => 0
        ];
    }
    /**
     * 获取用户form_id
     */
    public function getUserFormId () {
        $where = [
            ['user_id', 'eq', $this->user_id],
            ['operator_id', 'eq', $this->operator_id],
            ['end_time', '>=', time()],
            ['number', '>=', 1],
        ];
        $data = $this->formIdModel->where($where)->find();
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => transArray($data),
            'count' => 0
        ];
    }

    /**
     * 获取场景消息数据
     * @param string $scene
     * 场景
     * @param array $param
     * 参数
     * @return array
     */
    public function getWeChatMessageScene ($scene = '', $param = []) {
        switch ($scene) {
            case 'SuccessfulPaymentNotice':
                $data['keyword1']['value'] = $param['dining_code_letter'].$param['dining_code']; // 取餐号
                $data['keyword2']['value'] = getDeliveryType($param['delivery_type']);           // 订单类型
                $data['keyword3']['value'] = date('Y-m-d H:i:s', $param['add_time']);     // 下单时间
                $data['keyword4']['value'] = $param['order_address']['name'];                    // 取餐门店
                $data['keyword5']['value'] = $param['order_address']['address'];                 // 取餐地址
                $data['keyword6']['value'] = '请耐心等待~';                                       // 温馨提示
                break;
            case 'TakeMealsNotice':
                $data['keyword1']['value'] = $param['dining_code_letter'].$param['dining_code']; // 取餐号
                $data['keyword2']['value'] = getDeliveryType($param['delivery_type']);           // 订单类型
                $data['keyword3']['value'] = date('Y-m-d H:i:s', $param['add_time']);     // 下单时间
                $data['keyword4']['value'] = $param['order_address']['name'];                    // 取餐门店
                $data['keyword5']['value'] = $param['order_address']['address'];                 // 取餐地址
                $data['keyword6']['value'] = '';
                break;
        }
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => $data,
            'count' => 0
        ];
    }

    /**
     * 获取运营商模板消息场景配置
     * @param string $scene
     * 场景
     * @return array
     */
    public function getWeChatMessageConfig ($scene = '') {
        // 获取微信消息通知配置
        $data = $this->templateMessageModel->where(['operator_id' => $this->operator_id, 'sign' => $scene])->find();
        return [
            'code' => 200,
            'msg' => 'success',
            'data' => transArray($data),
            'count' => 0
        ];
    }

    /**
     * 发送微信小程序模板消息
     * @param array $scene
     * 发送场景
     * @return array|mixed|string
     */
    public function getSendTemplateMessages ($scene = '', $param = []) {
        try {
            // 获取运营商支付配置
            $pay_config = $this->payLogic->getWeChatConfig($this->operator_id)['data'];
            $pay_config_data = json_decode($pay_config['details'], true);
            $pay_config = [];
            if (!empty($pay_config_data)) {
                foreach ($pay_config_data as $key => $val) {
                    $pay_config[$val['parameter']] = $val['value'];
                }
            }
            if (!empty($pay_config)) {
                // 获取AccessToken
                $access_token_data = $this->loginLogic->getAccessTokenIsExpire($pay_config['app_id'], $pay_config['app_secret_id']);
                // 获取用户form_id
                $user_form_data = $this->getUserFormId();
                // 获取模板配置
                $message_config_data = $this->getWeChatMessageConfig($scene);
                // 获取消息场景
                $scene_data = $this->getWeChatMessageScene($scene, $param);
                // 获取用户open_id
                $user_data = $this->getUserOpenId();
                // 模板消息数据
                $post_data = array (
                    "touser"           => $user_data['data']['wx_openid'],
                    "template_id"      => $message_config_data['data']['template_id'],
                    "page"             => $message_config_data['data']['page'],
                    "form_id"          => $user_form_data['data']['form_id'],
                    "data"             => $scene_data['data'],
                    "emphasis_keyword" => 'keyword'.$message_config_data['data']['emphasis_keyword'].'.DATA'
                );
                // 模板消息请求地址
                $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token_data['data'];
                //return https_curl_json($url, $post_data);
                $result = https_curl_json($url, $post_data);
                if (!empty($user_form_data['data'])) {
                    $this->formIdModel->where(['id' => $user_form_data['data']['id']])->delete();
                }
                Log::record('6666消息：'.json_encode($result));
                $result = json_decode($result, true);
                if ((isset($result['errcode']) && isset($result['errmsg'])) && ($result['errcode'] == 0 && $result['errmsg'] == 'ok')) {
                    return [
                        'code' => 200,
                        'msg' => 'success',
                        'data' => $result,
                    ];
                } else {
                    return [
                        'code' => 201,
                        'msg' => 'error',
                        'data' => $result,
                    ];
                }
            } else {
                return [
                    'code' => 201,
                    'msg' => '参数有误',
                    'data' => [],
                ];
            }
        } catch (\Exception $e) {
            return [
                'code' => 201,
                'msg' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * 向 java 发送消息通知
     * @param array $param
     * 参数
     * @return array
     */
    public function getSendJavaNotifications ($param = []) {
        try {
            $url = 'baiduapi.youwuu.com/order/api/cyOrder';
            //AES加密解密
            $aes_data = json_encode($param);
            $aes = new Aes();
            //$aes->require_pkcs5();
            $send_data = $aes->encrypt($aes_data);
            $post_data = ['jdata' => $send_data];
            //p($aes->decrypt($send_data));die;

            $result = https_curl_json($url, $post_data, 'post');
            $result = json_decode($result, true);
            Log::save();
            Log::init(['type' => 'File', 'path' => '../runtime/api_java_message_Logs/']);
            Log::record('java消息3666：'.json_encode($result));
            Log::save();
            Log::init(['type' => 'File', 'path' => '../runtime/' ]);
            if (isset($result['code']) &&  $result['code'] == 200) {
                return [
                    'code' => 200,
                    'msg' => isset($result['message']) ? $result['message'] : '成功',
                ];
            } else {
                return [
                    'code' => 201,
                    'msg' => isset($result['message']) ? $result['message'] : '失败',
                ];
            }


        } catch (\Exception $e) {
            return [
                'code' => 201,
                'msg' => $e->getMessage(),
            ];
        }
    }
}