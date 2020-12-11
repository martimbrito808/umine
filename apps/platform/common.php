<?php

//admin模块公共函数

/**
 * 管理员操作日志
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function addlog($content_id=''){
	$data['content_id'] = $content_id;
	$data['manager_id'] = \think\Session::get('manager_id');//管理员id
	$request = \think\Request::instance();
	$data['ip'] = $request->ip();//操作ip
	$data['create_time'] = time();//操作时间
	$data['controller'] = $request->controller();
	$data['action'] = $request->action();
	\think\Db::name('logs')->insert($data);
}

/**
 * 加密
 * @param $password 密码
 * @param $encrypt 加密
 */
function userEncrypt($password = '', $encrypt = null){
    if(empty($password)){
        return '';
    }
    if(!isset($encrypt) || $encrypt === null || strlen($encrypt) !== 6){
        $encrypt = rand_str(6);
        $arr['password'] = md5($encrypt.md5($password.$encrypt));
        $arr['encrypt'] = $encrypt;
        return $arr;
    }else{
        return md5($encrypt.md5($password.$encrypt));
    }
}

/**
 * 生成随机字符串
 * @param $length 字符串长度
 */
function rand_str($length = 6, $pattern=null){
    if($pattern==null){
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    $key = '';
    for($i=0;$i<$length;$i++){
        $key .= $pattern{mt_rand(0, (strlen($pattern)-1))}; //生成php随机数
    }
    return $key;
}



