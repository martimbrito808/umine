<?php
// 应用公共文件

// 异常错误报错级别,
error_reporting(E_ERROR | E_PARSE);

/**
 * 计算数组笛卡尔积
 * [[1,2,2,3],[3,3,2,2]]
 */
function dikaer($arr, $break = '')
{
    $arr1 = array();
    $result = array_shift($arr);
    while ($arr2 = array_shift($arr)) {
        $arr1 = $result;
        $result = array();
        foreach ($arr1 as $v) {
            foreach ($arr2 as $v2) {
                if (empty($break)) {
                    $result[] = $v . $v2;
                } else {
                    $result[] = $v . $break . $v2;
                }
            }
        }
    }
    return $result;
}


/**
 * 私钥加密
 */
function encryption($data = '')
{
    if (empty($data)) {
        return false;
    }
    $key = randChar(16);
    $aes = new \app\api\controller\AesController();
    $rsa_en = $aes->privEncrypt($key);
    if (is_array($data)) {
        $data = $aes->aes_encrypt(json_encode($data), $key);
    } else {
        $data = $aes->aes_encrypt($data, $key);
    }
    return ['key' => $rsa_en, 'data' => $data];
}

/**
 * 解密
 */
function decryption($key = '', $data = '')
{
    if (empty($key) || empty($data)) {
        return false;
    }

    $aes = new \app\api\controller\AesController();
    $key = $aes->privDecrypt($key);
    $data = $aes->aes_decrypt($data, $key);
    return $data;
}

/**
 * 发送短信
 */
function sendSms($phone, $content)
{
    $user = '994188465'; //短信平台帐号
    $pass = md5('zhangjiaxuan'); //短信平台密码
    $sendurl = '"http://api.smsbao.com/"';    //短信接口地址

    $sendurl = $sendurl . "sms?u=" . $user . "&p=" . $pass . "&m=" . $phone . "&c=" . urlencode($content);
    $result = file_get_contents($sendurl);
    return $result;
}

/**
 * 获取订单状态
 */
function getStatus($status)
{
    $arr = array(
        1 => '待付款',
        2 => '待发货',
        3 => '待收货',
        4 => '已完成',
        5 => '售后',
    );
    return $arr[$status];
}

/**
 * 获取地区信息
 */
function getArea($area_id, $field = 'name')
{
    if (!empty($area_id)) {
        return \think\Db::name('chinaarea')->where(['id' => $area_id])->value($field);
    } else {
        return 0;
    }
}

/**
 * 根据用户ID 获取用户字段信息
 */
function getUserField($user_id, $field = 'username')
{
    if (!empty($user_id)) {
        return \think\Db::name('user')->where(['id' => $user_id])->value($field);
    } else {
        return 0;
    }
}

/**
 * 获取订单支付方式
 */
function getPayway($payway)
{
    $arr = array(
        'wechat' => '微信支付',
        'alipay' => '支付宝支付'
    );
    return $arr[$payway];
}


/**
 * 格式化金额
 */
 function formatprice($money, $weishu='8') {
     return number_format(floatval($money), $weishu, '.','');
 }

/**
 * 格式化金额
 */
function showprice($money,$weishu='8')
{
    return number_format(floatval($money) / 10000, $weishu, '.', '');
    // return floatval(number_format(floatval($money) / 10000, 6, '.', ''));
}

/**
 * 存储金额
 */
function toprice($money)
{
    return number_format(floatval($money) * 10000, 8, '.', '');
}


/**
 * 获取字段
 */
function getField($table, $id, $field = 'title')
{
    if ($table && $id) {
        $result = \think\Db::name($table)->where(['id' => $id])->value($field);
        if ($result) {
            return $result;
        }else{
            return '';
        }
    }
}

/**
 * 获取图片路径
 */
function getFile($id, $filed = 'filepath')
{
    if ($id) {
        $result = \think\Db::name('attachment')->where(array('id' => $id))->value($filed);
        if (!empty($result)) {
            return \think\Request::instance()->domain() . $result;
        }
    }
}

/**
 * 解析链接打开方式
 */
function getTarget($target)
{
    if ($target == 1) {
        return '_blank';
    } else {
        return '_self';
    }
}

/**
 * 获取系统配置信息
 **/
function getConfig($variable)
{
    if ($variable) {
        $result = \think\Db::name('config')->where("variable='" . $variable . "'")->value('value');
        return $result;
    }
}

/**
 * 输出多城市名称
 */
function getCitysName($citys)
{
    if ($citys) {
        $city_arr = explode(',', $citys);
        $arr = array();
        foreach ($city_arr as $k => $v) {
            $arr[$k] = \think\Db::name('chinaarea')->where("id='" . $v . "'")->value('name');
        }
        $str = implode(',', $arr);
        return $str;
    }
}

/**
 * 微信用户名去emoji
 * @param $str
 * @return mixed|string
 */
function jsonNickName($str)
{
    $str = preg_replace_callback('/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);

    return $str;
}

/**
 * 截取字符串
 */
function subtext($text, $length)
{
    if (mb_strlen($text, 'utf8') > $length) {
        return mb_substr($text, 0, $length, 'utf8') . ' …';
        return $text;
    } else {
        return $text;
    }
}

/**
 * 密码加密方式
 * @param $password  密码
 * @param $salt 密码额外加密字符
 * @return string
 */
function password($password, $salt = 'kdoilaol785a62ss')
{
    return md5(md5($password) . md5($salt));
}

/**
 * 文件大小转化
 */
function getSize($filesize)
{
    if ($filesize >= 1073741824) {
        $filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
    } elseif ($filesize >= 1048576) {
        $filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
    } elseif ($filesize >= 1024) {
        $filesize = round($filesize / 1024 * 100) / 100 . ' KB';
    } else {
        $filesize = $filesize . ' 字节';
    }
    return $filesize;
}

/**
 * 获取随机位数数字，用于生成短信验证码
 * @param integer $len 长度
 * @return string
 */
function randNum($len = 6)
{
    $chars = str_repeat('0123456789', $len);
    $chars = str_shuffle($chars);
    $str = substr($chars, 0, $len);
    return $str;
}

/**
 * 获取随机位数字符串
 * @param integer $len 长度
 * @return string
 */
function randChar($len = 8)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $len; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * 替换手机号码中间四位数字
 * @param  [type] $str [description]
 */
function hidePhone($str)
{
    $resstr = substr_replace($str, '****', 3, 4);
    return $resstr;
}

/**
 * [SendMail 邮件发送]
 * @param [type] $address  [description]
 * @param [type] $title    [description]
 * @param [type] $message  [description]
 * @param [type] $from     [description]
 * @param [type] $fromname [description]
 * @param [type] $smtp     [description]
 * @param [type] $username [description]
 * @param [type] $password [description]
 */
function sendMail($address)
{
    vendor('phpmailer.PHPMailerAutoload');
    //vendor('PHPMailer.class#PHPMailer');
    $mail = new \PHPMailer();
    // 设置PHPMailer使用SMTP服务器发送Email
    $mail->IsSMTP();
    // 设置邮件的字符编码，若不指定，则为'UTF-8'
    $mail->CharSet = 'UTF-8';
    // 添加收件人地址，可以多次使用来添加多个收件人
    $mail->AddAddress($address);

    $data = \think\Db::name('emailconfig')->where('email', 'email')->find();
    $title = $data['title'];
    $message = $data['content'];
    $from = $data['from_email'];
    $fromname = $data['from_name'];
    $smtp = $data['smtp'];
    $username = $data['username'];
    $password = $data['password'];
    // 设置邮件正文
    $mail->Body = $message;
    // 设置邮件头的From字段。
    $mail->From = $from;
    // 设置发件人名字
    $mail->FromName = $fromname;
    // 设置邮件标题
    $mail->Subject = $title;
    // 设置SMTP服务器。
    $mail->Host = $smtp;
    // 设置为"需要验证" ThinkPHP 的config方法读取配置文件
    $mail->SMTPAuth = true;
    //设置html发送格式
    $mail->isHTML(true);
    // 设置用户名和密码。
    $mail->Username = $username;
    $mail->Password = $password;
    // 发送邮件。
    return ($mail->Send());
}

/**
 * 获取客户端IP
 */
function get_client_ip()
{
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
}

/**
 * 返回正确的AJAX数据
 * @param $msg
 * @param array $data
 * @param int $error
 */
function ajaxSuccess($msg, $data = array(), $encryption = 1){
    if(empty($data)){
        $returnData = array(
            'error' => 0,
            'msg' => $msg
        );
    }else{
        if($encryption == 1){
            $data = encryption($data);
            $returnData = array(
                'error' => 0,
                'msg' => $msg,
                'key' => $data['key'],
                'data' => $data['data'],
            );
        }else{
            $returnData = array(
                'error' => 0,
                'msg' => $msg,
                'data' => $data,
            );
        }

    }
    return json($returnData);
}

/**
 * 返回错误的AJAX信息
 * @param $msg
 * @param array $data
 * @param int $error
 */
function ajaxError($msg, $error = 403){
    $returnData = array(
        'error' => $error,
        'msg' => $msg
    );
    return json($returnData);
}

/**
 * 返回带数量JSON数据
 * @param $msg
 * @param array $data
 * @param int $error
 */
function ajaxData($msg, $data = array(), $count = 0)
{
    $returnData = array(
        'error' => 0,
        'msg' => $msg,
        'count' => $count,
        'data' => $data
    );
    return json($returnData);
}

/**
 * 返回带layui需要的JSON数据
 * @param $msg
 * @param array $data
 * @param int $error
 */
function layData($msg, $data = array(), $count = 0)
{
    $returnData = array(
        'code' => 0,
        'msg' => $msg,
        'count' => $count,
        'data' => $data
    );
    return json($returnData);
}

/**
 * 返回array数据
 * @param $msg
 * @param array $data
 * @param int $error
 */
function arrData($msg, $error = 1, $data = array())
{
    $returnData = array(
        'error' => $error,
        'msg' => $msg,
        'data' => $data
    );
    return $returnData;
}
function getExchangeRate($mode = 1)
{
    $res = json_decode(httpGet('https://www.okex.com/api/index/v3/BTC-USD/constituents'));
    
    $rate = 0;
    if($res->error_code == 0)
    {
        try {
            if($mode == 1)
                $rate = 1.00 / $res->data->last;
            if($mode == 2)
                $rate = $res->data->last;
        } catch (Exception $e) {
            $rate = 0;
        }
    }
    else
    {
        return json_encode($res);
    }
    
    return $rate;
}
/**
 * 发起一个post请求到指定接口
 *
 * @param string $api 请求的接口
 * @param array $params post参数
 * @param int $timeout 超时时间
 * @return string 请求结果
 */
function postURL($api, array $params = array(), $timeout = 30)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api);
    // 以返回的形式接收信息
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // 设置为POST方式
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    // 不验证https证书
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        'Accept: application/json',
    ));
    // 发送数据
    $response = curl_exec($ch);
    // 不要忘记释放资源
    curl_close($ch);
    return $response;
}

/*
 * 通过HTTP获取信息
 */
function httpGet($url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);
    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
}



/**
 * 发送友盟推送消息 |单独推送
 * @param  integer $utype  用户类型 user / master
 * @param  integer $uid    用户id
 * @param  integer $type   1/系统消息  2/交易消息
 * @param  string  $title  推送的标题
 * @return boolear         是否成功
 */
function umeng_push($utype = 'user', $uid, $content_id, $title='', $content='')
{
    // 获取token
    $device_tokens = model("$utype")->where('id',$uid)->value('upush_device_token');
    // 如果没有token说明移动端没有登录；则不发送通知
    if (empty($device_tokens)) {
        return false;
    }
    $timestamp = strval(time());
//  苹果
    if (strlen($device_tokens) == 64) {
        $config = config('umeng.ios');
        import('umeng.ios.IOSUnicast', VENDOR_PATH );
        $unicast = new IOSUnicast();
        $unicast->setAppMasterSecret();
        $unicast->setPredefinedKeyValue("appkey",           $config['app_key']);
        $unicast->setPredefinedKeyValue("timestamp",        $timestamp);
        $unicast->setPredefinedKeyValue("device_tokens",    $device_tokens);
        $unicast->setPredefinedKeyValue("alert", "交易消息");
        $unicast->setPredefinedKeyValue("badge", 0);
        $unicast->setPredefinedKeyValue("sound", "chime");
        $unicast->setPredefinedKeyValue("production_mode", "false");
        // Set customized fields
        $unicast->setCustomizedField("type", 2);
        $unicast->setCustomizedField("id", $content_id);
        $unicast->send();
        return true;
 //     安卓
    } else {
        $config = config('umeng.android');
        import('umeng.android.AndroidUnicast', VENDOR_PATH );
        $unicast = new \AndroidUnicast();
        $unicast->setAppMasterSecret($config['app_master_secret']);
        $unicast->setPredefinedKeyValue("appkey",           $config['app_key']);
        $unicast->setPredefinedKeyValue("timestamp",        $timestamp);
        $unicast->setPredefinedKeyValue("device_tokens",    $device_tokens);
        $unicast->setPredefinedKeyValue("ticker",           "交易消息");  //通知栏提示文字
        $unicast->setPredefinedKeyValue("title",            "$title");   //通知标题
        $unicast->setPredefinedKeyValue("text",             "$content"); //通知文字描述
        $unicast->setPredefinedKeyValue("after_open",       "go_app");
        // 如果是测试设备，将“生产模式”设置为“false”。
        // 有关如何注册测试设备，请参阅开发人员文档。 TODO::上线以后修改 true
        $unicast->setPredefinedKeyValue("production_mode", "false");
        // 额外字段
        $unicast->setExtraField("type", 2);
        $unicast->setExtraField("id", $content_id);
        $unicast->send();
        return true;
    }
}

/**
 * 发送友盟推送消息
 * @param  string  $title  推送的标题
 * @param  string $content  消息内容
 * @return boolear         是否成功
 */
function umeng_pushall($title, $content)
{
    $timestamp = strval(time());
    $iosConfig = config('umeng.ios');
    $androidConfig = config('umeng.android');
    import('umeng.ios.IOSBroadcast', VENDOR_PATH);
    import('umeng.android.AndroidBroadcast', VENDOR_PATH);
    $brocastAndroid = new \AndroidBroadcast();

//            $brocastIOS = new IOSBroadcast();
//            TODO:://没有开发者账号
//            $brocastIOS->setAppMasterSecret($iosConfig['app_master_secret']);
//            $brocastIOS->setPredefinedKeyValue("appkey", $iosConfig['app_key']);
//            $brocastIOS->setPredefinedKeyValue("timestamp", $timestamp);
//            $brocastIOS->setPredefinedKeyValue("alert", "$title");
//            $brocastIOS->setPredefinedKeyValue("badge", 0);
//            $brocastIOS->setPredefinedKeyValue("sound", "chime");
//            $brocastIOS->setPredefinedKeyValue("production_mode", "false");
//            $brocastIOS->setCustomizedField("type", 1);
//            $brocastIOS->send();

    $brocastAndroid->setAppMasterSecret($androidConfig['app_master_secret']);
    $brocastAndroid->setPredefinedKeyValue("appkey", $androidConfig['app_key']);
    $brocastAndroid->setPredefinedKeyValue("timestamp", $timestamp);
    $brocastAndroid->setPredefinedKeyValue("ticker", "系统通知");
    $brocastAndroid->setPredefinedKeyValue("title", "$title");
    $brocastAndroid->setPredefinedKeyValue("text", "$content");
    $brocastAndroid->setPredefinedKeyValue("after_open", "go_app");
    $brocastAndroid->setPredefinedKeyValue("production_mode", "false"); //开发模式
    $brocastAndroid->setExtraField("type", 1);
    $brocastAndroid->send();
    return true;
}