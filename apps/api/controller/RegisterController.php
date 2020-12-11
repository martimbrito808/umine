<?php
/**
 * Author: Orzly
 * Date: 2020-08-17
 */
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Cache;

class RegisterController extends BaseController
{
    protected $noNeedLogin = ['*'];
    
    /*
     * 注册 提交
     * */
    public function index()
    {
        $param = input('param.');
        $validate = new \think\Validate([
            ['username', 'require'],
            ['password', 'require|length:6,16', '登录密码不能为空|密码长度为6-16个字符之间'],
            ['tel', 'require|length:11', '手机号不能为空|手机号格式不正确'],
            ['code', 'require', '验证码不能为空'],
        ]);
        //验证部分数据合法性
        if (!$validate->check($param)){
            return callbackJson(0,$validate->getError());
        }
        if(!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{3,16}$/u', $param['username'])) {
            return callbackJson(0,'用户名由3-16位数字或字母、汉字、下划线组成！');
        }
        $chePhone = Db::name('user')->where(['tel'=>$param['tel']])->count();
        if($chePhone){
            return callbackJson(0,'该手机号码已注册');
        }
        $cheCode = Db::name('sms_send')->where(['status' => 1,'tel' => $param['tel'], 'code' => $param['code'],'type' => 1])->count();
        if(!$cheCode){
            return callbackJson(0,'验证码不正确，请重新输入');
        }

        $insertData = [
            'tel'       => $param['tel'],
            'username'  => $param['username'],
            'password'  => password($param['password']),
            'head_img'  => '/public/static/home/image/defhead.jpg',
            'create_time'  => time(),
            'update_time'  => time(),
        ];

        Db::startTrans();
        $res = Db::name('user')->insert($insertData);
        $res2 = Db::name('sms_send')->where(['tel'=>$param['tel'], 'type' => 1,'code' => $param['code']])->setField('status',2);

        if($res && $res2){
            Db::commit();
            return callbackJson(1,'注册成功');
        }else{
            // 回滚事务
            Db::rollback();
            return callbackJson(0,'注册失败');
        }
    }

    /*
     * 找回密码 提交
     * */
    public function forgotPassword()
    {
        $param = input('param.');
        $validate = new \think\Validate([
            ['tel', 'require|length:11', '手机号不能为空|手机号格式不正确'],
            ['code', 'require', '验证码不能为空'],
            ['password', 'require|length:6,16|confirm', '登录密码不能为空|密码长度为6-16个字符之间|两次输入密码不一致'],
        ]);
        //验证部分数据合法性
        if (!$validate->check($param)){
            return callbackJson(0,$validate->getError());
        }

        $userId = Db::name('user')->where(['tel'=>$param['tel']])->value('id');
        if(!$userId){
            return callbackJson(0,'该手机号码未注册');
        }

        $cheCode = Db::name('sms_send')->where(['status' => 1,'tel' => $param['tel'], 'code' => $param['code'],'type' => 2])->count();
        if(!$cheCode){
            return callbackJson(0,'验证码不正确，请重新输入');
        }

        $updateData = [
            'password'  => password($param['password']),
            'update_time'  => time(),
        ];

        Db::startTrans();
        $res = Db::name('user')->where('id',$userId)->update($updateData);
        $res2 = Db::name('sms_send')->where(['tel'=>$param['tel'], 'type' => 2,'code' => $param['code']])->setField('status',2);

        if($res && $res2){
            Db::commit();
            return callbackJson(1,'修改成功');
        }else{
            // 回滚事务
            Db::rollback();
            return callbackJson(0,'修改失败');
        }
    }
}