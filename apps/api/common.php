<?php
/**
 * Author: Orzly
 * Date: 2020-07-16
 * Api/common.php
 */

/**
 * 获取文件完整路径
 * @param $filename
 * @return string
 */
function getFullPath($filename)
{
    return \think\Request::instance()->domain() . $filename;
}
function getMillEfee($mill, $days, $rebate = 0)
{
    $fee = ($mill['gonghaobi'] / 1000.00)*$mill['dianfei'] * $days;
    $fee -= $fee * $rebate / 100.00;

    return $fee;
}
/**
 * 富文本编辑器替换图片路径添加域名
 * @param $content
 * @return mixed
 */
function editorGetFullImage($content) {
    $url = "http://".$_SERVER['SERVER_NAME'];
    $pregRule = "/<[img|IMG].*?src=[\'|\"](.*?(?:[\.jpg|\.jpeg|\.png|\.gif|\.bmp]))[\'|\"].*?[\/]?>/";
    $content = preg_replace($pregRule, '<img src="'.$url.'${1}" style="max-width:100%">', $content);
    return $content;
}

/**
 * 获取用户信息
 * @param integer $uid
 * @return array|boolean
 * */
function getUserInfo($uid)
{
    if (!empty($uid)) {
        return \think\Db::name('user')->where(['id' => $uid])->find();
    } else {
        return 0;
    }
}

/**
 * base64 上传图片 base64
 * @param string $base64_img 图片base64编码
 * @param string $path 根目录
 * @param string $savepath 保存位置
 * @return array
 * */
function baseimg_upload($base64_img){
    //$base64_img ='data:image/jpg;base64,'.str_replace('', '+', $base64_img);

    $base64_img = str_replace('', '+', $base64_img);
    $up_dir = 'public' . DS . 'uploads'. DS .date('Ymd',time());

    if(!file_exists($up_dir)){
        mkdir($up_dir,0777,true);
    }

    if(!preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
        return ['code' => -3, 'msg' => "文件错误"];
    }
    $type = $result[2];
    if(!in_array($type, array('pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'))){
        return ['code' => -2, 'msg' => "文件类型错误"];
    }

    $randStr = str_shuffle('1234567890'); //随机字符串
    $rand = substr($randStr,0,4); // 返回前四位
    $file_path = $up_dir.'/'.date('YmdHis').$rand.'.'.$type;

    $new_file = ROOT_PATH.$file_path;  //完整路径 + 图片名
    if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
        return ['code' => 1, 'msg' => "图片上传成功", 'data' => '/'.$file_path];
    }

    return ['code' => -1, 'msg' => "图片上传失败"];
}

/**
 * [array_group_by ph]
 * @param  [type] $arr [二维数组]
 * @param  [type] $key [键名]
 * @return [type]      [新的二维数组]
 */
function array_group_by($arr, $key){
    $grouped = array();
    foreach ($arr as $value) {
        $grouped[$value[$key]][] = $value;
    }
    if (func_num_args() > 2) {
        $args = func_get_args();
        foreach ($grouped as $key => $value) {
            $parms = array_merge($value, array_slice($args, 2, func_num_args()));
            $grouped[$key] = call_user_func_array('array_group_by', $parms);
        }
    }
    return $grouped;
}

/**
 * 获取视频总时长
 * @param string $ffmpeg_path  [ffmpeg.exe的绝对路径]
 * @param string $video_path  [video的绝对路径]
 */
function getVideoTime($video_path) {
//    $ffmpeg_path = '/usr/local/ffmpeg/bin/ffmpeg -i "%s" 2>&1';
//    $ffmpeg_path = '/usr/local/ffmpeg/bin/ffmpeg';
    $ffmpeg_path = 'ffmpeg';

    if (!file_exists($video_path))  return '视频文件不存在';
    $times = false;
    $commond = "{$ffmpeg_path} -i {$video_path} 2>&1";
    exec($commond, $str_res, $str_r);
    if (is_array($str_res)){
        foreach($str_res as $v){
            if (strpos($v, 'Duration') !== false){
                $times = substr($v, stripos($v , '.') - 8, 8);//'  Duration: 00:24:28.14, start: 0.000000, bitrate: 486 kb/s'
                break;
            }
        }
    }
    return $times ? $times : '';
}

/**
 *接口返回
 * @param integer $code 状态  1为正确  0为通用错误码 其他为错误码任意但不能为1
 * @param string  $msg  返回信息的详细说明
 * @param mixed   $data 选填 json数据格式信息
 * @return \think\response\Jsonp;
 */
function callbackJson($code = 0, $msg = '',  $data = []){
    return json_encode(compact('code','msg', 'data'));
}

/**
 * 手机号正则
 * @param $tel
 * @return bool
 */
function is_mobile($tel){
    if(preg_match("/^1[3456789]{1}\d{9}$/",$tel)){
        return true;
    }else{
        return false;
    }
}

/**
 * 向前端返回json数据
 */
function sendRequest($code=0,$msg,$list=[],$count=0,$json_option=0)
{
    $data=[
        'code'=>$code,
        'msg'=>$msg,
        'data'=>$list,
        'count'=>$count
    ];
    header('Content-Type:application/json; charset=utf-8');
    exit(json_encode($data, $json_option));
}

/**
 * 转换成数组
 */
function transArray($arr = []){
    return  json_decode(json_encode($arr),true);
}

/**
 * 发送验证码
 * @param string $phone
 * 手机号
 * @param string $text
 * 内容
 * @param array $sms_config
 * 短息配置
 * @return mixed
 */
function sendShortMessage($phone = '', $text = '', $sms_config = []) {
    require_once '../extend/dysms/vendor/autoload.php';
    \Aliyun\Core\Config::load();
    // AccessKeyId
    $accessKeyId = $sms_config['app_key'];
    // AccessKeySecret
    $accessKeySecret = $sms_config['app_secret'];
    // 模版变量替换
    $templateParam = $text;
    // 必填，设置签名名称，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
    $signName = $sms_config['sign_name'];
    // 必填，设置模板CODE，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
    $templateCode = $sms_config['tpl_code'];
    //产品名称:云通信流量服务API产品,开发者无需替换
    $product = "Dysmsapi";
    //产品域名,开发者无需替换
    $domain = "dysmsapi.aliyuncs.com";
    // 暂时不支持多Region
    $region = "cn-hangzhou";
    // 服务结点
    $endPointName = "cn-hangzhou";
    // fixme 可选: 设置发送短信流水号
    $params['OutId'] = "12345";
    // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
    $params['SmsUpExtendCode'] = "1234567";
    $profile = \Aliyun\Core\Profile\DefaultProfile::getProfile($region,$accessKeyId,$accessKeySecret);
    \Aliyun\Core\Profile\DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);
    $acsClient = new \Aliyun\Core\DefaultAcsClient($profile);
    $request = new \Aliyun\Api\Sms\Request\V20170525\SendSmsRequest();
    $request->setPhoneNumbers($phone);
    $request->setSignName($signName);
    $request->setTemplateCode($templateCode);
    if ($templateParam){
        $request->setTemplateParam(json_encode($templateParam));
    }
    $acsResponse = $acsClient->getAcsResponse($request);
    $result = json_decode(json_encode($acsResponse),true);
    return $result;
}

/**
 * $arr：加密数组
 * @param array $arr
 * 加密数组
 * @return string
 */
function  zencrypt($arr = []){
    import('Encrypt.Aes',VENDOR_PATH,'.php');
    $crypt = new \Aes();
    $encryptArr = json_encode($arr).'#'.time();
    //$encryptArr = $crypt->des3Encrypt($encryptArr);
    $encryptArr = $crypt->encrypt($encryptArr);
    return $encryptArr;
}

/**
 * $arr：解密数组
 * @param string $arr
 * 解密数组
 * @return array|string
 */
function  zdcrypt($arr = ''){
    import('Encrypt.Aes',VENDOR_PATH,'.php');
    $crypt = new \Aes();
    //$dcryptArr = $crypt->des3Decrypt($arr);
    $dcryptArr = $crypt->decrypt($arr);
    $dcryptArr = explode('#',$dcryptArr);
    $dcryptArr = (array)json_decode($dcryptArr[0]);
    return $dcryptArr;
}

//打印测试
function p($data){
    echo '<pre>';
    print_r(json_decode(json_encode($data)));
    echo '<pre>';
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

/**
 * 访问
 * @param $url
 * @param $data
 * @param string $type
 * @return mixed
 */
function https_curl($url = '', $data = '', $type = 'json') {
    if ($type == 'json') {
        $headers = array("Content-type: application/json;charset=UTF-8","Accept: application/json","Cache-Control: no-cache", "Pragma: no-cache");
        $data =json_encode($data);
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (!empty($data)){
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
    $output = curl_exec($curl);
    if (curl_errno($curl)) {
        echo 'Errno'.curl_error($curl);//捕抓异常
    }
    curl_close($curl);
    return $output;
}

