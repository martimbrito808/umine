<?php
namespace app\api\controller;

use think\Model;
use think\Db;

class SmsController extends BaseController{
    protected $noNeedLogin = ['*'];
	
	protected function _initialize(){
		parent::_initialize();
	}
	
	/**
	 * 发送注册验证码
	 */
	public function send(){
        $tel = input('tel/s','');
        $type = input('type', 1);

        if(!is_mobile($tel)){
            return callbackJson(0,'手机号码格式不正确');
        }
        if($type == 1 && Db::name('user')->where(['tel'=>$tel])->count()){
            return callbackJson(0,'该手机号码已注册，请直接登录');
        }
        if($type == 2 && !Db::name('user')->where(['tel'=>$tel])->count()){
            return callbackJson(0,'该手机号码未注册');
        }

        $res = Db::name('sms_send')->field('send_time,tel')->where(['tel'=>$tel])->order('id desc')->find();
        if($res && ($res['send_time'] + 60 > time())){
            return callbackJson(0,'1分钟内只能发送一条短信');
        }

        $code = rand(100000,999999);
        $result = $this->smsbao($tel, $code);
        if($result['code'] != 1){
           return callbackJson(0,$result['msg']);
        }

        Db::name('sms_send')->insert (['tel'=>$tel, 'code'=>$code, 'send_time'=>time(),'type'=>$type]);
        return callbackJson(1,$result['msg']);
	}

	/**
	 * 短信宝发送短信
     * @param string $tel 要发送的手机号
     * @param string $code 验证码
	 * */
    public function smsbao( $tel, $code)
    {
        $statusStr = array(
            "0" => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词"
        );
        $smsapi = "http://api.smsbao.com/";
        $user = config('smsbao.user');
        $pass = config('smsbao.pass') ;
        $pass = md5($pass);   //短信平台密码
        $content = "【秒光APP】您的验证码为{$code}，验证码5分钟内有效。";
        $phone = $tel;
        $sendurl = $smsapi . "sms?u=" . $user . "&p=" . $pass . "&m=" . $phone . "&c=" . urlencode($content);
        $result = file_get_contents($sendurl);

        if ($result == '0') {
            return ['code' => 1, 'msg' => "短信发送成功"];
        }
        //错误
        return ['code' => 0,  'msg' => $statusStr[$result]];
    }
}
