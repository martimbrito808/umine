<?php
namespace app\api\controller;

use think\Controller;
use think\Db;


class BaseController extends Controller {

    protected $user_id;
    protected $user_name;
    protected $user_phone;

    /**
     * 析构函数
     */
    function __construct () {
        header("Cache-control: private");
        parent::__construct();
        /*$this->user_id = 1;
        $this->user_name = 1;
        $this->user_phone = 1;*/
        $param = Input('param.');
        // 升级维护提示，code未3000
        // sendResponseStr(3000, '系统升级维护中!');
        // die;
        // 微信支付回调不验证方法
        if (in_array(strtolower(request()->controller().'/'.request()->action()),$this->notVerification())) {
            if(isset($param['token']) && $param['token'] != '' && $param['token'] != null && !empty($param['token']) && $param['token'] != 'null '){
                // 验证
//                p($param['token']);die;
                $this->verification();
            }
        }else{
            // 验证
            $this->verification();
        }
    }

    /**
     * 验证
     */
    protected function verification () {
        // 获取参数
        $param = Input('param.');
        // 判断token
        if (isset($param['token']) && !empty($param['token'])) {
            // 解码token，ASCII码转正常字符
            $param['token']  = str_replace('%3D', '=', $param['token']);
            $param['token']  = str_replace('%23', '#', $param['token']);
            $param['token']  = str_replace('%3F', '?', $param['token']);
            $param['token']  = str_replace('%2F', '/', $param['token']);
            $param['token']  = str_replace('%20', ' ', $param['token']);
            $token = str_replace('%2B', '+', $param['token']);
            $token = urldecode($token);
            $token = str_replace(' ', '+', $token);
            if (!empty($token)) {
                // 解密token
                $decrypt_token = zdcrypt($token);
                if (!empty($decrypt_token)){
                    $this->user_id = $decrypt_token['id'];
                    /*$user = Db::table('by_user')
                        ->where('phone', $decrypt_token['phone'])
                        ->find();
                    if (!empty($user)) {
                        $this->user_id = $user['id'];
                        $this->user_name = $user['name'];
                        $this->user_phone = $user['phone'];
                    }else{
                        sendRequest(300,'token错误!');
                        exit;
                    }*/
                }else{
                    sendRequest(300,'token错误!');
                    exit;
                }
            }
        }else{
            sendRequest(300,'token错误!');
            exit;
        }
    }


    /*
     * 初始化操作
     */
    public function _initialize() {

    }

    /**
     * 不做验证的类和方法
     * @return array
     */
    function notVerification(){
        return [
            'mill/testjiesuan',
            'index/index',
            'index/my_mill_all',
            'mill/mill',
            'money/money',
            'index/consult',
            'public/baseimg_upload',
            'mill/income_introduced',
            'money/money_management',
            'my/message',
            'my/message_details',
            'node/index',
            'index/money_record',
        ];
    }

}
