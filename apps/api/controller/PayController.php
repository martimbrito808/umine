<?php
/**
 * 支付管理
 * Author: Orzly
 * Date: 2020-08-07
 */
namespace app\api\controller;

use app\common\model\Order;
use think\Db;
use think\Model;

class PayController extends BaseController{

    protected $noNeedLogin = ['alipayNotify','wechatNotify'];

    /**
     * 支付
     * @return \think\response\Json|\think\response\Jsonp
     * @throws \think\exception\DbException
     */
    public function payment()
    {
        $param = input('param.');
        $validate = new \think\Validate([
            ['orderno', 'require', '订单号不能为空'],
            ['payway', 'require', '支付方式不能为空'],
        ]);
        //验证部分数据合法性
        if (!$validate->check($param)) {
            return callbackJson(0,$validate->getError());
        }
        //查询订单信息
        $order = model('order')->get(['orderno' => $param['orderno'],'user_id' => $this->uid]);
        if(empty($order)){
            return callbackJson(0,'查询不到订单信息');
        }
        if($order['status'] != 1 ){
            return callbackJson(0,'订单状态异常_1');
        }

        $subject = $order['orderno'];
        $orderno = $order['orderno'];
        $total_amount = $order['order_price'];
        switch($param['payway']){
            case 'alipay':
                return $this -> toalipay($subject, $orderno, $total_amount);
                break;
            case 'wechat':
                return $this -> towechatpay($subject, $orderno, $total_amount);
                break;
            default:
                return callbackJson(0,'非法支付方式');
        }
    }
    /**
     * 支付宝支付
     * @param string $subject 标题
     * @param string $orderno 订单号
     * @param int $total_amount 价格
     * @return \think\response\Json
     */
	public function toalipay($subject = '', $orderno = '', $total_amount = 0){
		if(empty($subject)){
		    return callbackJson(0,'缺少标题参数');
		}
		if(empty($orderno)){
		    return callbackJson(0,'缺少订单编号');
		}
		if(empty($total_amount)){
		    return callbackJson(0,'缺少价格');
		}
        vendor('alipay.AopSdk');// 加载类库
        $config = config('alipay');
        $config['notify'] = getConfig('weburl').$config['notify'];

		$biz_content = [
            'subject' => $subject,
            'out_trade_no' => $orderno,
            'total_amount' => showprice($total_amount),
        ];
        $aop = new \AopClient ();
        $aop->gatewayUrl          = 'https://openapi.alipay.com/gateway.do';
        $aop->appId               = $config['appid'];
        $aop->rsaPrivateKey       = $config['private_key'];
        $aop->alipayrsaPublicKey  = $config['public_key'];
        $aop->apiVersion          = '1.0';
        $aop->signType            = $config['sign_type'];
        $aop->postCharset         = 'UTF-8';
        $aop->format              = 'json';
        $request = new \AlipayTradeAppPayRequest ();
        $request->setNotifyUrl($config['notify']);
        $request->setBizContent(json_encode($biz_content));
        $result = $aop->sdkExecute ($request);
        return callbackJson(1,'调起成功', $result);
	}

    /**
     * 支付宝回调
     * @return mixed
     */
	public function alipayNotify(){

		$data = input();
		$data = str_replace("&quot;", "\"", $data);
        $config = config('alipay');

        vendor('alipay.AopSdk');
        $aop = new \AopClient;
		$aop->alipayrsaPublicKey = $config['public_key'];
		$flag = $aop->rsaCheckV1($data, null, $config['sign_type']);

		file_put_contents("./logs/alilogs.txt", $flag."\n", FILE_APPEND);
		if(!$flag){
            file_put_contents("./logs/alilogs.txt", "error sign\n", FILE_APPEND);
            echo 'error sign'; die;
		}
        file_put_contents("./logs/alilogs.txt", json_encode($data)."\n", FILE_APPEND);
        if($data['trade_status'] != 'TRADE_SUCCESS'){
            file_put_contents("./logs/alilogs.txt", "error trade\n", FILE_APPEND);
            echo 'error trade';die;
        }
        if($data['app_id'] != $config['appid']){
            file_put_contents("./logs/alilogs.txt", "error appid\n", FILE_APPEND);
            echo 'error appid';die;
        }

        $orderno  = $data['out_trade_no'];  //商户网站唯一订单号
        $payprice = $data['total_amount'];  //该笔订单的资金总额，单位为RMB-Yuan。取值范围为[0.01，100000000.00]，精确到小数点后两位。
        $payno    = $data['trade_no'];  //该交易在支付宝系统中的交易流水号。

        return model('order')->notify([
            'payway'   => 'alipay',
            'orderno'  => $orderno,
            'payno'    => $payno,
            'payprice' => $payprice
        ]);
        echo "success";
	}

    /**
     * 微信支付
     * @param string $subject
     * @param string $orderno
     * @param int $total_amount
     * @return \think\response\Json
     */
	public function towechatpay($subject = '', $orderno = '', $total_amount = 0){
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $onoce_str = randChar(32);
        $config = config('wechatpay');
        $config['notify'] = getConfig('weburl').$config['notify'];


        $data["appid"] = $config["appid"];
        $data["body"] = $subject;
        $data["mch_id"] = $config['mch_id'];
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $config['notify'];
        $data["out_trade_no"] = $orderno;
        $data["spbill_create_ip"] = $this->get_client_ip();
        $data["total_fee"] = $total_amount;
        $data["trade_type"] = "APP";
        $data["sign"] = $this->getSign($data, false);

        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
		$xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);//XML转数组		
		$result = (array)$xml;

		$info["appid"]     = $config["appid"];
		$info["noncestr"]  = $onoce_str;
		$info["package"]   = "Sign=WXPay";
		$info["partnerid"] = $config['mch_id'];
		$info["prepayid"]  = $result['prepay_id'];
		$info["timestamp"] = time();
		$s = $this -> getSign($info, false);
		$info["sign"] = $s;
		
		return ajaxSuccess('调起成功', $info);
	}

    /**
     * 微信回调
     * @return mixed
     */
	public function wechatNotify(){
		if (file_get_contents("php://input")) {
            $post = file_get_contents("php://input");
        }
     
        if ($post == null) {
            $post = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        }
     
        if (empty($post) || $post == null || $post == '') {
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';  
            echo $str;
            exit('Notify 非法回调');
        }
		
		file_put_contents("./logs/wxlogs.txt", $post."\n", FILE_APPEND);
	 
		/*****************微信回调返回数据样例*******************
		$post = '<xml>
			<return_code><![CDATA[SUCCESS]]></return_code>
			<return_msg><![CDATA[OK]]></return_msg>
			<appid><![CDATA[wx2421b1c4370ec43b]]></appid>
			<mch_id><![CDATA[10000100]]></mch_id>
			<nonce_str><![CDATA[IITRi8Iabbblz1Jc]]></nonce_str>
			<sign><![CDATA[7921E432F65EB8ED0CE9755F0E86D72F]]></sign>
			<result_code><![CDATA[SUCCESS]]></result_code>
			<prepay_id><![CDATA[wx201411101639507cbf6ffd8b0779950874]]></prepay_id>
			<trade_type><![CDATA[APP]]></trade_type>
			</xml>';
		 *************************微信回调返回*****************/
	 
		libxml_disable_entity_loader(true); //禁止引用外部xml实体
		$xml = simplexml_load_string($post, 'SimpleXMLElement', LIBXML_NOCDATA); //XML转数组
		$post_data = (array)$xml;	
		
		/** 解析出来的数组
			*Array
			* (
			* [appid] => wx1c870c0145984d30
			* [bank_type] => CFT
			* [cash_fee] => 100
			* [fee_type] => CNY
			* [is_subscribe] => N
			* [mch_id] => 1297210301
			* [nonce_str] => gkq1x5fxejqo5lz5eua50gg4c4la18vy
			* [openid] => olSGW5BBvfep9UhlU40VFIQlcvZ0
			* [out_trade_no] => fangchan_588796
			* [result_code] => SUCCESS
			* [return_code] => SUCCESS
			* [sign] => F6890323B0A6A3765510D152D9420EAC
			* [time_end] => 20180626170839
			* [total_fee] => 100
			* [trade_type] => JSAPI
			* [transaction_id] => 4200000134201806265483331660
			* )
		**/

		//接收到的签名
		$post_sign = $post_data['sign'];
		unset($post_data['sign']);
 
		//重新生成签名
		$newSign = $this->getSign($post_data);
 
		//签名统一，则更新数据库
		if($post_sign == $newSign){
			file_put_contents("./logs/wxlogs.txt", "success\n", FILE_APPEND);
			$orderno  = $post_data['out_trade_no'];
			$payprice = $post_data['total_fee'];
			$payno    = $post_data['transaction_id'];
			
			return model('order')->notify(['payway'=>'wechat', 'orderno'=>$orderno, 'payprice'=>$payprice]);

			$str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';  
			echo $str;
		}else{
			file_put_contents("./logs/wxlogs.txt", "post_sign:".$post_sign . "\nnewSign:" . $newSign. "\n", FILE_APPEND);
		}
	}

    /**
     * 生成签名
     * @param $Obj
     * @return string
     */
    function getSign($Obj){
        foreach ($Obj as $k => $v){
            $Parameters[strtolower($k)] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo "【string】 =".$String."</br>";
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".$this->wxconfig['api_key'];
		//echo "<textarea style='width: 50%; height: 150px;'>$String</textarea> <br />";
        //签名步骤三：MD5加密
        $result_ = strtoupper(md5($String));
        return $result_;
    }

    /**
     * 数组转xml
     * @param $arr
     * @return string
     */
    function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val))
            {
                $xml.="<".$key.">".$val."</".$key.">";

            }
            else
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * post https请求，CURLOPT_POSTFIELDS xml格式
     * @param $xml
     * @param $url
     * @param int $second
     * @return bool|string
     */
    function postXmlCurl($xml,$url,$second=30){
        //初始化curl
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        }else{
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error"."<br>";
            echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
            curl_close($ch);
            return false;
        }
    }

    /**
     * 获取当前服务器的IP
     * @return array|false|mixed|string
     */
    function get_client_ip(){
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    //将数组转成uri字符串

    /**
     * 数组按转为uri字符串
     * @param $paraMap
     * @param $urlencode
     * @return false|string
     */
    function formatBizQueryParaMap($paraMap, $urlencode){
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v){
            if($urlencode){
                $v = urlencode($v);
            }
            $buff .= strtolower($k) . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0){
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }
	
}
