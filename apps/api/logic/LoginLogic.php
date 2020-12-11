<?php

namespace app\api\logic;

use think\Db;
use think\Model;
use app\api\model\User;

class LoginLogic extends Model {
    
    private $userModel = '';

    function __construct () {
        parent::__construct ();
        $this->userModel = new User();
    }

    /**
     * 判断是否注册过了会员注册
     */
    public function getIsRegister($param)
    {
        $list = Db::name('user')
            ->where('tel',$param['phone'])
            ->find();
        return $list;
    }

    /**
     * 会员注册
     */
    public function userRegister($param)
    {
        set_time_limit(0);
        //先查询是否已经注册过了
        $is_have = $this->getIsRegister($param);
        $is_have = transArray($is_have);
        if(!empty($is_have)){
            return ['code'=>201,'msg'=>'您已经注册过了,请不要重复注册'];
        }
        $time = time();
        //验证验证码是否正确后删除验证码
        $code = Db::name('sms_send')->where('tel',$param['phone'])->value('code');
        $code = transArray($code);
        if(!empty($code)){
            if($code==$param['code']){
                //插入新用户数据
                $the_code = $this->generateCode();
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
                $filename = $base_file.$the_code.$time.'.jpg';
                \QRcode::png($url,$filename , $errorCorrectionLevel, $matrixPointSize, 2,true);
                $user_arr = [
                    'tel' =>$param['phone'],
                    'create_time' =>time(),
                    'password'=>$pwd['password'],
                    'encrypt'=>$pwd['encrypt'],
                    'code' =>$the_code,
                    'qr_code' =>$the_code.$time.'.jpg',
                ];
                if(isset($param['invitation']) && $param['invitation']!=''){
                    $invitees_id = $this->userModel
                        ->where('code',$param['invitation'])
                        ->find();
                    if(!empty($invitees_id)){
                        $user_arr['parent_1'] = $invitees_id['id'];
                        $user_arr['parent_2'] = $invitees_id['parent_1'];
                        $user_arr['parent_3'] = $invitees_id['parent_2'];
                        $suanli = getConfig('give_hashrate');
                        $last_user_arr = [
                            'id'=>$invitees_id['id'],
                            'suanli'=>$suanli+$invitees_id['suanli']
                        ];
                    }else{
                        return ['code'=>201,'msg'=>'邀请码错误'];
                    }
                }
                Db::startTrans();
                try{
                    $this->userModel->isUpdate(false)->data($user_arr)->save();
                    $this->userModel->isUpdate(true)->save($last_user_arr);
                    Db::commit();
                    return ['code'=>200,'msg'=>'注册成功,请登录'];
                }catch (\Exception $e){
                    Db::rollback();
                    return ['code'=>201,'msg'=>$e->getMessage()];
                }
            }else{
                return ['code'=>201,'msg'=>'验证码输入错误,请重新输入'];
            }
        }else{
            return ['code'=>201,'msg'=>'请先获取验证码!'];
        }
    }

    /**
     * 登录获取token
     */
    public function signIn($param = []) {
        $list = $this->userModel
            ->where('tel',$param['phone'])
            ->order('id desc')
            ->find();
        $list = transArray($list);
        if(!empty($list)){
            if($list['status'] != 1){
                return ['code'=>201,'msg'=>'您的账号已被封禁!'];
            }
            $pwd = userEncrypt($param['password'],$list['encrypt']);
            if($pwd == $list['password']){
                Db::name('sms_send')->where('tel',$param['phone'])->where('type',1)->where('status',1)->update(['status'=>2]);
                $token = zencrypt(['id'=>$list['id']]);
                $data['token'] = $token;
                return ['code'=>200,'msg'=>'登录成功','data'=>$data];
            }else{
                return ['code'=>201,'msg'=>'密码错误'];
            }
        }else{
            return ['code'=>201,'msg'=>'您还没有注册过,请先注册后再登录'];
        }
    }

    /**
     * 找回密码
     */
    public function retrievePassword($param)
    {
        $is_have = $this->getIsRegister($param);
        $is_have = transArray($is_have);
        if(!empty($is_have)){
            //验证验证码是否正确后删除验证码
            $code = Db::name('sms_send')->where('tel',$param['phone'])->where('type',2)->where('status',1)->order('id desc')->value('code');
            $code = transArray($code);
            
            if(!empty($code)){
                if($code==$param['code']){
                    $pwd = userEncrypt($param['password']);
                    $user_arr = [
                        'id'=>$is_have['id'],
                        'update_time' =>time(),
                        'password'=>$pwd['password'],
                        'encrypt'=>$pwd['encrypt'],
                    ];
                }else{
                    return ['code'=>201,'msg'=>'验证码不正确!'];
                }
                try{
                    Db::startTrans();
                    $this->userModel
                        ->isUpdate(true)
                        ->save($user_arr);
                    Db::name('sms_send')->where('tel',$param['phone'])->where('type',2)->where('status',1)->update(['status'=>2]);
                    Db::commit();
                    return ['code'=>200,'msg'=>'成功找回密码'];
                }catch (\Exception $e){
                    Db::rollback();
                    return ['code'=>201,'msg'=>'请稍后再试!'];
                }
            }else{
                return ['code'=>201,'msg'=>'请先获取验证码!'];
            }
        }else{
            return ['code'=>201,'msg'=>'您还没注册过,请先注册'];
        }
    }
    
    /**
     * 生成邀请码
     */
    public function generateCode()
    {
        $the_code = '224571';
        $existence = Db::name('user')
            ->order('code desc')
            ->value('code');
        $existence = transArray($existence);
        if(!empty($existence)){
            $the_code = $existence+1;
        }
        return $the_code;
    }

}