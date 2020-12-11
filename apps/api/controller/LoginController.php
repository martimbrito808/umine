<?php
namespace app\api\controller;

use app\api\logic\LoginLogic;
use app\api\logic\MessageLogic;
use think\Controller;
use app\api\logic\SmsLogic;

class LoginController extends Controller
{
    private $loginLogic = '';
    private $messageLogic = '';

    function __construct () {
        parent::__construct ();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        header("Cache-control: private");
        $this->loginLogic = new LoginLogic();
        $this->messageLogic = new MessageLogic();
    }

    /**
     * 发送短信验证码
     */
    public function sendVerificationCode () {
        $param  = Input('param.');
        if(!isset($param['phone']) || strlen($param['phone'])!=11 || !is_numeric($param['phone'])){
            sendRequest(201, '请输入正确的手机号');
        }
//        sendRequest(201, '请先开通发送验证码功能');
        try {
            if($param['type'] == 1){ //注册
                //先查询此手机号码是否已经注册过了
                $res = $this->loginLogic->getIsRegister($param);
                $res = transArray($res);
                if(!empty($res)){
                    sendRequest(201, '您已经注册过了,请不要重复注册');
                }
            }
            // 获取验证码信息
            $code_where['tel'] = $param['phone'];
            $code_where['status'] = 1;
            $code_where['type'] = $param['type'];
            $code_info = $this->messageLogic->getCodeInfo($code_where);
            $code_info = transArray($code_info);
            $random_number = rand(1000,9999);
            if (!empty($code_info['data'])) {
                $random_number = $code_info['data']['code'];
            }
            $smsapi = "http://api.smsbao.com/";
            $user = "shengren510"; //短信平台帐号
            $pass = md5("xinbeijingxg4936"); //短信平台密码
            $content="【灰度超算】您的验证码为".$random_number;//要发送的短信内容
            $phone = $param['phone'];//要发送短信的手机号码
            $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=".$phone."&c=".urlencode($content);
            $result =file_get_contents($sendurl) ;
//            p($result);die;
            $result = json_decode(json_encode($result),true);
            //  $result =0;
            // $result['Message'] ="OK";
            if ($result == 0) {
                if(empty($code_info['data'])) {
                    $save_code_data = [
                        'send_time' => time(),
                        'code' => $random_number,
                        'status' => 1,
                        'type' => $param['type'],
                        'tel' => $param['phone'],
                    ];
                    $this->messageLogic->getCodeSave($save_code_data, 0);
                }
                sendRequest(200, '发送成功',$random_number);
            } else {
                sendRequest(201, '发送失败请稍后重试!',$result);
            }
        } catch (\Exception $e) {
            sendRequest(201, $e->getMessage());
        }
    }

    /**
     * 会员注册
     */
    public function register()
    {
        $param = input('param.');
        $this->assign('data',$param);
        return $this->fetch();
    }

    /**
     * 会员注册
     */
    public function userRegister()
    {
            $param = input('param.');
            // 验证参数
            if(!isset($param['phone']) || $param['phone'] == 0){
                sendRequest(201, '手机号必填');
            }
            if(!isset($param['password']) || $param['password'] == ''){
                sendRequest(201, '密码必填');
            }
            if(!isset($param['again']) || $param['again'] == ''){
                sendRequest(201, '再次输入密码必填');
            }
            if(!isset($param['code']) || $param['code'] == ''){
                sendRequest(201, '验证码必填');
            }
            /*if(!isset($param['invitation']) || $param['invitation'] == ''){
                sendRequest(201, '邀请码必填');
            }*/
            if($param['password'] != $param['again']){
                sendRequest(201, '两次密码不一致');
            }
            $openid_result = $this->loginLogic->userRegister($param);
            sendRequest($openid_result['code'], $openid_result['msg']);
    }

    /**
     * 用户登录
     */
    public function login() {
        return $this->fetch();
    }

    /**
     * 用户登录
     */
    public function signIn() {
        $param = input('param.');
        // 验证参数
        if(!isset($param['phone']) || $param['phone'] == 0){
            sendRequest(201, '手机号必填');
        }
        if(!isset($param['password']) || $param['password'] == ''){
            sendRequest(201, '密码必填');
        }
        $openid_result = $this->loginLogic->signIn($param);
        if ($openid_result['code'] == 200) {
            sendRequest(200, 'success',$openid_result['data']);
        } else {
            sendRequest(201, $openid_result['msg']);
        }
    }

    /**
     * 找回密码
     */
    public function forget_password()
    {
        return $this->fetch();
    }

    /**
     * 找回密码
     */
    public function retrievePassword()
    {
        $param = input('param.');
        // 验证参数
        if(!isset($param['phone']) || $param['phone'] == 0){
            sendRequest(201, '手机号必填');
        }
        if(!isset($param['password']) || $param['password'] == ''){
            sendRequest(201, '密码必填');
        }
        if(!isset($param['code']) || $param['code'] == ''){
            sendRequest(201, '验证码必填');
        }
        $openid_result = $this->loginLogic->retrievePassword($param);
        if ($openid_result['code'] == 200) {
            sendRequest(200, $openid_result['msg']);
        } else {
            sendRequest(201, $openid_result['msg']);
        }
    }

    /**
     * 图片上传接口
     */
    public function imageUpload()
    {
        $fileName = Input('file_name/s', 'file');
        $file = request()->file($fileName);
        // 移动到框架应用根目录/uploads/ 目录下
        $info = $file->move('static/image');
        if($info){
            // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
            sendRequest(200,'上传成功',$info->getSaveName(),0);
        }else{
            // 上传失败获取错误信息
            sendRequest(201,'上传失败',$file->getError(),0);
        }
    }
    
    /**
     * 图片上传接口
     */
    public function image()
    {
            $the_code = '224576';
            $pwd = userEncrypt($param['password']);
            import('phpqrcode.phpqrcode',VENDOR_PATH,'.php');
//                require_once __DIR__ . '/../../../vendor/phpqrcode/phpqrcode.php';
            //$domain = \think\facade\Config::get('domain');
            $url = request()->domain().'/api/login/register?code='.$the_code;
            //$value = $the_code;         //二维码内容
            $errorCorrectionLevel = 'L';  //容错级别
            $matrixPointSize = 7;      //生成图片大小
            $base_file = __DIR__.'/../../../public/static/qrcode/';
            if (!file_exists($base_file)){
                mkdir($base_file,'0777');
            }
            //生成二维码图片
            $filename = $base_file.$the_code.time().'.jpg';
            \QRcode::png($url,$filename , $errorCorrectionLevel, $matrixPointSize, 2,true);
            echo $the_code.time().'.jpg';
    
    }
    
    
    
    
}