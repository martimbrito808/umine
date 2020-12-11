<?php


namespace app\api\controller;

use think\Db;

class MoneyController extends BaseController
{

    function __construct () {
        parent::__construct ();
    }
    
    /**
     * 财富
     */
    public function money()
    {
        $param = input('param.');
        $data['token'] = $param['token'];
        $money = Db::name('goods_wealth')
            ->where('type',2)
            ->where('status', 1)
            ->order('create_time desc')
            ->select();
        $money = transArray($money);
        $zhouqi = ['1' => 'apr_0','30'=>'apr_3','60'=>'apr_6','90'=>'apr_9','120'=>'apr_12'];
        foreach($money as $k=>$v){
            $money[$k]['apr'] = $v[$zhouqi[$v['zhouqi']]];
        }
        $data['money'] = $money;
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * USDT生币宝定期理财详情
     */
    public function money_management()
    {
    
        $param = input('param.');
        $data['token'] = $param['token'];
        
        $money = Db::name('goods_wealth')
            ->where('id',$param['id'])
            ->find();
            
        $dateNow = date('Y-m-d H:i:s');
    
        if( $money['rengou_begin'] < $dateNow && $money['rengou_end'] > $dateNow) {
            $money['buy'] = true;
        }else{
            $money['buy'] = false;
        }

        $rengou_end = strtotime($money['rengou_end'].' + 1 day');
        $money['day'] = date('Y',$rengou_end).'年'.date('m',$rengou_end).'月'.date('d',$rengou_end).'日起';
        $money['rengou_begin'] = date('m-d H:i',strtotime($money['rengou_begin']));
        $money['get_time'] = date('m-d H:i',strtotime($money['rengou_end']) + $money['zhouqi']*60*60*24);
        $money['rengou_end'] = date('m-d H:i',strtotime($money['rengou_end']));
        
        if($money['type'] == 1) {
            $money['get_time_3'] = date('m-d H:i', $rengou_end + 30*60*60*24);
            $money['get_time_6'] = date('m-d H:i', $rengou_end + 60*60*60*24);
            $money['get_time_9'] = date('m-d H:i', $rengou_end + 90*60*60*24);
            $money['get_time_12'] = date('m-d H:i', $rengou_end + 120*60*60*24);
            $money['zhouqi'] = '30';
            $money['apr'] = $money['apr_3'];
            return $this->fetch('money_money_management_1', compact('money','data'));
        }else{
            $money = transArray($money);
            $zhouqi = ['1' => 'apr_0','30'=>'apr_3','60'=>'apr_6','90'=>'apr_9','120'=>'apr_12'];
            $money['apr'] = $money[$zhouqi[$money['zhouqi']]];
            $this->assign('data',$data);
            $this->assign('money',$money);
            return $this->fetch();
        }
    }
    
    /**
     * 我的资产
     */
    public function property()
    {
        $info = Db::name('user')->where(['id' => $this->user_id])->find();
        $info['btc_cny'] = showprice($info['btc'] * getconfig('btc_parities'), 2);
    
        if(empty($info)) {
             sendRequest(201, '获取数据失败，请稍后再试');
        }
        
        return $this->fetch('', compact('info'));
    }

    /**
     * 充值
     */
    public function recharge()
    {
        $type = input('param.type', '');
        if(empty($type)) {
            sendRequest(201, '非法请求');
        }
        
        if($type == 'btc') {
            $variable = 'yollon_btc';
            $class_num = 1;
        }elseif( $type == 'usdt') {
            $variable = 'yollon_usdt';
            $class_num = 2;
        }elseif( $type == 'eth') {
            $variable = 'yollon_eth';
            $class_num = 3;
        }elseif( $type == 'ethu') {
            $variable = 'yollon_ethu';
            $class_num = 4;
        }elseif( $type == 'euf' ) {
            $variable = 'yollon_euf';
            $class_num = 5;
        }elseif( $type == 'er' ) {
            $variable = 'yollon_er';
            $class_num = 6;
        }
        
        $info = Db::name('config')->where('variable', $variable)->find();

        if(empty($info)) {
             sendRequest(201, '获取数据失败，请稍后再试');
        }
        $info['value'] = getfile($info['value']);
        $info['class'] = strtoupper($type); 
        $info['class_num'] = $class_num;
        $info['min_price'] = getconfig("minimum_$type");
        
        return $this->fetch('', compact('info'));
    }
    
    /**
     * 上传充值凭证
     */
    public function recharge_up()
    {
        $param = input('param.');
        $validate = new \think\Validate([
            ['money', 'require', '请输入充值金额'],
            ['voucher','require', '请上传支付凭证'],
            ['type','require', '非法请求']
        ]);
        if (!$validate->check($param)){
            sendRequest(201, $validate->getError());
        }
        
        $min_price = getconfig("minimum_{$param['class_name']}");
        if($param['money'] < $min_price) {
             sendRequest(201, "最小充值金额不能小于{$min_price}");
        }
        
        $info = Db::name('recharge')->insert([
            'user_id' => $this->user_id,
            'type' => $param['type'],
            'money' => toprice($param['money']),
            'voucher' => $param['voucher'],
            'create_time' => time()            
        ]);
        $smsapi = "http://api.smsbao.com/";
        $user = "shengren510"; //短信平台帐号
        $pass = md5("xinbeijingxg4936"); //短信平台密码
        $content="【云端办公平台】有充值订单啦";//要发送的短信内容
        $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=18610382626&c=".urlencode($content);
        $result =file_get_contents($sendurl);
        if(true == $info) {
            sendRequest(200, '提交成功',$result);
        }else{
            sendRequest(201, '提交失败');
        }
    }
    
    /**
     * 提现
     */
    public function withdraw_deposit()
    {
        $type = input('param.type', '');
        if(empty($type)) {
            sendRequest(201, '非法请求');
        }
        
        $money['selfmoney'] = Db::name("user")->where(['id' => $this->user_id])->value($type);
        $money['selfmoney'] = showprice($money['selfmoney']);
        $money["mincash"] = getconfig("mincash_{$type}");  //最低提现
        
        $superNode = Db::name('node')->where(['user_id' => $this->user_id, 'type' => 2])->find();
        if($superNode) {
            $money['withdrawal_fee'] = 0;
        }else{
            $money["withdrawal_fee"] = getconfig("withdrawal_fee_{$type}");  //手续费    
        }

        return $this->fetch('', compact('money','type'));
    }
    
    /**
     * 提现提交
     */
    public function withdraw_deposit_up()
    {
        if(!request()->isPost()) {
            sendRequest(201, '非法请求');
        }

        $param = input('param.');
        $type = $param['type'];
        $validate = new \think\Validate([
            ['money', 'require', '请输入充值金额'],
            ['address','require', '请输入账户地址'],
            ['type','require', '非法请求']
        ]);
        if (!$validate->check($param)){
            sendRequest(201, $validate->getError());
        }  
        $money['selfmoney'] = Db::name("user")->where(['id' => $this->user_id])->value($type);
        $money['selfmoney'] = showprice($money['selfmoney']);
        $money["mincash"] = getconfig("mincash_{$type}");  //最低提现
        
        $superNode = Db::name('node')->where(['user_id' => $this->user_id, 'type' => 2])->find();
        if($superNode) {
            $money['withdrawal_fee'] = 0;
        }else{
            $money["withdrawal_fee"] = getconfig("withdrawal_fee_{$type}");
        }
        
        if($param['money'] < $money["mincash"]) {
            sendRequest(201, "最低提现金额不能小于{$money['mincash']} {$type}");
        };
        if($money['selfmoney'] < $param['money']) {
            sendRequest(201, "余额不足");
        };
        
        if($type == 'btc') {
            $type_num = 1;
        }elseif( $type == 'usdt') {
            $type_num = 2;
        }elseif( $type == 'eth') {
            $type_num = 3;
        }elseif( $type == 'ethu') {
            $type_num = 4;
        }elseif( $type == 'euf' ) {
            $type_num = 5;
        }elseif( $type == 'er' ) {
            $type_num = 6;
        }
        
        Db::startTrans();
        try{
            $smsapi = "http://api.smsbao.com/";
            $user = "shengren510"; //短信平台帐号
            $pass = md5("xinbeijingxg4936"); //短信平台密码
            $content="【云端办公平台】有提现订单啦";//要发送的短信内容
            $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=18610382626&c=".urlencode($content);
            $result =file_get_contents($sendurl);
            //创建提现记录
            Db::name('withdraw')->insert([
                'user_id'      => $this->user_id,
                'type'         => $type_num,
                'address'      => $param['address'],
                'money'        => toprice($param['money']),
                'rate_num'     => toprice($money["withdrawal_fee"]),
                'pay_num'      => toprice($param['money'] - $money['withdrawal_fee']),
                'create_time'  => time()            
            ]);
            //减少用户金额
            Db::name('user')->where(['id' => $this->user_id])->setDec($type, toprice($param['money']));
            Db::commit();    
            sendRequest(200, '提交成功',$result);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            sendRequest(201, '提交失败');
        }
    }
    
    /**
     * 理财购买
     */
     public function purchaseFinancing(){
         $param = input('param.');
         //减少用户金额,添加变动记录,添加购买记录
         $usdt = Db::name('user')->where('id',$this->user_id)->value('usdt');
         //p($usdt);die;
         $usdt = showprice($usdt);
         if($usdt<$param['num']){
             sendRequest(201, 'usdt余额不足');
         }
         $is_have = Db::name('goods_wealth_order')->where('user_id',$this->user_id)->where('goods_wealth_id',$param['id'])->find();
         $is_have = transArray($is_have);
         if(!empty($is_have)){
              sendRequest(201, '只能购买一次');
         }
         $goods = Db::name('goods_wealth')->where('id',$param['id'])->find();
         $min_num = showprice($goods['rengouedu']);
         if($param['num'] < $min_num){
              sendRequest(201, '最少购买'.$min_num);
         }
         //p($goods['rengou_end']);die;
         if(time()< strtotime($goods['rengou_begin']) || time()>strtotime($goods['rengou_end'])){
             sendRequest(201, '请在认购期内购买');
         }
         $zhouqi = ['30'=>'apr_3','60'=>'apr_6','90'=>'apr_9','120'=>'apr_12'];
         $order_arr = [
             'type'=>2,
             'user_id'=>$this->user_id,
             'goods_wealth_id'=>$goods['id'],
             'orderno'=>time().rand(1000,9999),
             'price'=>toprice($param['num']),
             'duration'=>$goods['zhouqi'],
             'rengou_end'=>$goods['rengou_end'],
             'fanxihuobi'=>$goods['fanxihuobi'],
             'apr'=>$goods[$zhouqi[$goods['zhouqi']]],
             'create_time'=>time(),
        ];
        $user_arr = [
            'usdt'=>toprice($usdt-$param['num'])
        ];
        $arr = [
            'type' =>8,
            'money_type' =>'usdt',
            'mold' =>'out',
            'user_id' =>$this->user_id,
            'money' =>toprice($param['num']),
            'create_time' =>time()
        ];
        Db::startTrans();
        try{
            Db::name('user')->where('id',$this->user_id)->update($user_arr);
            Db::name('goods_wealth_order')->insert($order_arr);
            Db::name('finance')->insert($arr);
            Db::name('goods_wealth')->where('id',$goods['id'])->update(['sales_sum'=>$goods['sales_sum']+1]);
            Db::commit();
            sendRequest(200, '购买成功');
        }catch(\Exception $e){
            Db::rollback();
             sendRequest(201, '购买失败',$e->getMessage());
        }
         
     }
     
     /**
     * 智能合约购买
     */
     public function contractPurchase(){
         $param = input('param.');
         //减少用户金额,添加变动记录,添加购买记录
         $usdt = Db::name('user')->where('id',$this->user_id)->value('usdt');
         //p($usdt);die;
         $usdt = showprice($usdt);
         if($usdt<$param['num']){
             sendRequest(201, 'usdt余额不足');
         }
         $is_have = Db::name('goods_wealth_order')->where('user_id',$this->user_id)->where('goods_wealth_id',$param['id'])->find();
         $is_have = transArray($is_have);
         if(!empty($is_have)){
              sendRequest(201, '只能购买一次');
         }
         $goods = Db::name('goods_wealth')->where('id',$param['id'])->find();
         $min_num = showprice($goods['rengouedu']);
         if($param['num'] < $min_num){
              sendRequest(201, '最少购买'.$min_num);
         }
         //p($goods['rengou_end']);die;
         if(time()< strtotime($goods['rengou_begin']) || time()>strtotime($goods['rengou_end'])){
             sendRequest(201, '请在认购期内购买');
         }
         $zhouqi = ['30'=>'apr_3','60'=>'apr_6','90'=>'apr_9','120'=>'apr_12'];
         $order_arr = [
             'type'=>1,
             'user_id'=>$this->user_id,
             'goods_wealth_id'=>$goods['id'],
             'orderno'=>time().rand(1000,9999),
             'price'=>toprice($param['num']),
             'duration'=>$param['lang'],
             'rengou_end'=>$goods['rengou_end'],
             'apr'=>$goods[$zhouqi[$param['lang']]],
             'fanxihuobi'=>$goods['fanxihuobi'],
             'create_time'=>time(),
        ];
        $user_arr = [
            'usdt'=>toprice($usdt-$param['num'])
        ];
        $arr = [
            'type' =>8,
            'money_type' =>'usdt',
            'mold' =>'out',
            'user_id' =>$this->user_id,
            'money' =>toprice($param['num']),
            'create_time' =>time()
        ];
        Db::startTrans();
        try{
            Db::name('user')->where('id',$this->user_id)->update($user_arr);
            Db::name('goods_wealth_order')->insert($order_arr);
            Db::name('finance')->insert($arr);
            Db::name('goods_wealth')->where('id',$goods['id'])->update(['sales_sum'=>$goods['sales_sum']+1]);
            Db::commit();
            sendRequest(200, '购买成功');
        }catch(\Exception $e){
            Db::rollback();
             sendRequest(201, '购买失败',$e->getMessage());
        }
         
     }    
     
     
     
     
     
     
     
     
     
     
     
     
     
     
     
     

}