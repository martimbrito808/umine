<?php
/**
 * Author: Orzly
 * Date: 2020-07-15
 * 用户中心
 */
namespace app\api\controller;

use think\Db;
use think\Image;
use app\api\model\GoodsWealthOrder;

class MyController extends BaseController
{
    function __construct () {
        parent::__construct ();
        
    }

    /**
     * 财富
     */
    public function my()
    {
        $param = input('param.');
        $data['token'] = $param['token'];
        $userInfo = Db::name('user')->where(['id' => $this->user_id])->find();
        if(empty($userInfo)) {
            sendRequest(201, '非法请求');
        }
        $userInfo['formatTel'] =  substr_replace($userInfo['tel'], '****',"3", '4');
        if($userInfo['username'] == ''){
            $userInfo['username'] = $userInfo['tel'];
        }
        
        $userInfo['cny'] = showPrice($userInfo['btc'] * getconfig('btc_parities'));
        
        $this->assign('data',$data);
        return $this->fetch('', compact('userInfo'));
    }

    /**
     * 关于灰度超算
     */
    public function contact_us()
    {
        $param = input('param.');
        $data['token'] = $param['token'];
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 灰度超算服务条款
     */
    public function agreement()
    {
        $data = getConfig('service_agreement');
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 更新日志
     */
    public function update_log()
    {
        $data = getConfig('update_log');
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 意见反馈
     */
    public function feedback()
    {
        if(request()->isAjax()){
            $param = input('param.');
            if($param['text'] == '') {
                sendRequest(201, '请输入反馈内容');
            }
            $arr = [
                'user_id' =>$this->user_id,
                'content' =>$param['text'],
                'create_time' =>time(),
            ];
            Db::name('feedback')->insert($arr);
            sendRequest(200,'提交成功!');
        }
        return $this->fetch();
    }

    /**
     * 费用抵扣方式
     */
    public function cost()
    {
        if(request()->isAjax()){
            $param = input('param.');
            Db::name('user')->where('id',$this->user_id)->update(['deduction'=>$param['deduction']]);
            sendRequest(200,'提交成功!');
        }
        $user = Db::name('user')->where('id',$this->user_id)->value('deduction');
        $list = getConfig('offset_instructions');
        $this->assign('user',$user);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 买卖USDT(法币交易)
     */
    public function deal()
    {
        $param = input('param.');
        if(request()->isAjax()){
            
        }
        $data['token'] = $param['token'];
        $fiat_deal_in_rule = getConfig('fiat_deal_in_rule');//买入
        $fiat_deal_out_rule = getConfig('fiat_deal_out_rule');//卖出
        $fiat_deal_in = getConfig('fiat_deal_in');//买入汇率
        $fiat_deal_out = getConfig('fiat_deal_out');//卖出汇率
        $usdt = Db::name('user')->where('id',$this->user_id)->value('usdt');
        $usdt = showPrice($usdt);
        $this->assign('data',$data);
        $this->assign('fiat_deal_in_rule',$fiat_deal_in_rule);
        $this->assign('fiat_deal_out_rule',$fiat_deal_out_rule);
        $this->assign('fiat_deal_in',$fiat_deal_in);
        $this->assign('fiat_deal_out',$fiat_deal_out);
        $this->assign('usdt',$usdt);
        return $this->fetch();
    }

        
    /**
     * 购买usdt
     */
     public function buyNow(){
        $param = input('param.');
        $order_sn = time().rand(1000,9999);
        $buy_max= getConfig('paper_buy_max');
        $buy_min = getConfig('paper_buy_min');
        if($buy_max<$param['num']){
            sendRequest(201,'不能超过最大购买数量'.$buy_max);
        }
        if($buy_min>$param['num']){
            sendRequest(201,'不能低于最低购买数量'.$buy_min);
        }
        $arr = [
            'order_sn'=>$order_sn,
            'price' =>$param['usdt'],
            'num' =>$param['num'],
            'pay_price' =>$param['pay_price'],
            'create_time' =>time(),
            'status' =>1,
            'u_id' =>$this->user_id,
            'type' =>1,
        ];
        $id = Db::name('transaction_order')->insertGetId($arr);
        sendRequest(200,'',$id);
     }
     
    /**
     * 出售usdt
     */
     public function sellNow(){
        $param = input('param.');
        $order_sn = time().rand(1000,9999);
        $sell_max= getConfig('paper_sell_max');
        $sell_min = getConfig('paper_sell_min');
        if($sell_max<$param['num']){
            sendRequest(201,'不能超过最大售卖数量'.$buy_max);
        }
        if($sell_min>$param['num']){
            sendRequest(201,'不能低于最低售卖数量'.$buy_min);
        }
        $user = Db::name('user')->where('id',$this->user_id)->find();
        if($param['num']>showPrice($user['usdt'])){
            sendRequest(201,'USDT余额不足');
        }
        $arr = [
            'order_sn'=>$order_sn,
            'price' =>$param['usdt'],
            'num' =>$param['num'],
            'pay_price' =>$param['pay_price'],
            'create_time' =>time(),
            'status' =>1,
            'u_id' =>$this->user_id,
            'type' =>2,
        ];
        $id = Db::name('transaction_order')->insertGetId($arr);
        sendRequest(200,'',$id);
     }

    /**
     * 交易记录
     */
    public function trading_record()
    {
          $param = input('param.');
          $buy = Db::name('transaction_order')->where('u_id',$this->user_id)->where('type',1)->order('create_time desc')->select();
          $status_name = [1=>'未支付',2=>'已支付',3=>'已完成',4=>'超时已取消订单',5=>'商家取消订单',6=>'用户取消订单'];
          foreach($buy as $k=>$v){
              $buy[$k]['create_time'] = date('m月d日 H:i',$v['create_time']);
              $buy[$k]['status_name'] = $status_name[$v['status']];
          }
          $sell = Db::name('transaction_order')->where('u_id',$this->user_id)->where('type',2)->order('create_time desc')->select();
          $status_name = [1=>'出售待确认',2=>'出售已确认',3=>'出售完成',4=>'超时已取消订单',5=>'商家取消订单',6=>'用户取消订单'];
          foreach($sell as $k=>$v){
            $sell[$k]['create_time'] = date('m月d日 H:i',$v['create_time']);
            $sell[$k]['status_name'] = $status_name[$v['status']];
          }
          $this->assign('buy',$buy);
          $this->assign('sell',$sell);
          return $this->fetch();
    }
    
    
    /**
     * 购买详情
     */
    public function buy_details()
    {
          $param = input('param.');
          $data = Db::name('transaction_order')->where('id',$param['id'])->find();
          $over_time = getConfig('expiration_time')*60;
          $data['over_time'] = date('Y年m月d日 H:i',$data['create_time']+$over_time);
          $data['create_time'] = date('Y年m月d日 H:i',$data['create_time']);
          $collection_bank = getConfig('collection_bank');
          $collection_name = getConfig('collection_name');
          $collection_num = getConfig('collection_num');
          $data['collection_bank'] = $collection_bank;
          $data['collection_name'] = $collection_name;
          $data['collection_num'] = $collection_num;
          $this->assign('data',$data);
          return $this->fetch();
    }
    
    
    /**
     * 购买取消
     */
    public function buy_cancel()
    {
          $param = input('param.');
          $data = Db::name('transaction_order')->where('id',$param['id'])->find();
          if($data['status'] != 1){
              sendRequest(201,'请稍后再试');
          }
          Db::name('transaction_order')->where('id',$param['id'])->update(['status'=>6]);
          sendRequest(200,'取消成功!');
    }
    
    /**
     * 已付款
     */
    public function buy_paid()
    {
          $param = input('param.');
          $data = Db::name('transaction_order')->where('id',$param['id'])->find();
          if($data['status'] != 1){
              sendRequest(201,'请稍后再试');
          }
          $smsapi = "http://api.smsbao.com/";
          $user = "shengren510"; //短信平台帐号
          $pass = md5("xinbeijingxg4936"); //短信平台密码
          $content="【云端办公平台】有商城订单啦";//要发送的短信内容
          $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=18610382626&c=".urlencode($content);
          $result =file_get_contents($sendurl);
          Db::name('transaction_order')->where('id',$param['id'])->update(['status'=>2,'pay_time'=>time()]);
          sendRequest(200,'付款成功!',$result);
    }
    
    /**
     * 售卖详情
     */
    public function sell_details()
    {
          $param = input('param.');
          $data = Db::name('transaction_order')->where('id',$param['id'])->find();
          $data['create_time'] = date('Y年m月d日 H:i',$data['create_time']);
          $this->assign('data',$data);
          return $this->fetch();
    }
    
    
    /**
     * 售卖取消
     */
    public function sell_cancel()
    {
          $param = input('param.');
          $data = Db::name('transaction_order')->where('id',$param['id'])->find();
          if($data['status'] != 1){
              sendRequest(201,'请稍后再试');
          }
          Db::name('transaction_order')->where('id',$param['id'])->update(['status'=>6]);
          sendRequest(200,'取消成功!');
    }
    
    
    /**
     * 出售
     */
    public function sell_paid()
    {
          $param = input('param.');
          $pay_price = Db::name('transaction_order')->where('id',$param['id'])->find();
          if($pay_price['status'] != 1){
              sendRequest(201,'请稍后再试');
          }
          $user = Db::name('user')->where('id',$this->user_id)->find();
          if($user['bindname'] == ''){
             sendRequest(201,'请先绑定银行卡');
          }
          if($pay_price['num']>showPrice($user['usdt'])){
             sendRequest(201,'USDT余额不足');
          }
          $arr = [
                'type' =>5,
                'money_type' =>'usdt',
                'mold' =>'out',
                'user_id' =>$this->user_id,
                'money' =>toprice($pay_price['num']),
                'create_time' =>time()
          ];
          Db::startTrans();
          try{
              $smsapi = "http://api.smsbao.com/";
              $user = "shengren510"; //短信平台帐号
              $pass = md5("xinbeijingxg4936"); //短信平台密码
              $content="【云端办公平台】有商城订单啦";//要发送的短信内容
              $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=18610382626&c=".urlencode($content);
              $result =file_get_contents($sendurl);
              Db::name('transaction_order')->where('id',$param['id'])->update(['status'=>2,'pay_time'=>time()]);
              $update_price = $user['usdt']-toprice($pay_price['num']);
              Db::name('user')->where('id',$this->user_id)->update(['usdt'=>$update_price]);
              Db::commit();
              sendRequest(200,'出售成功!',$result);
          }catch(\Exception $e){
              Db::rollback();
              sendRequest(201,'出售失败!');
          }
    }

    /**
     * 优惠券
     */
    public function discount_coupon()
    {
        if(request()->isAjax()){
            $param = input('param.');
            $coupon = Db::name('coupon_card')->where('number',$param['coupon'])->where('user_id',0)->find();
            $coupon = transArray($coupon);
            if(empty($coupon)){
                sendRequest(201,'已经被领取过了!');
            }else{
                Db::name('coupon_card')->where('number',$param['coupon'])->update(['get_time'=>time(),'user_id'=>$this->user_id,'status'=>2]);
                sendRequest(200,'领取成功!');
            }
        }
        return $this->fetch();
    }
    
    /**
     * 安全中心
     */
    public function safety_center()
    {
        $param = input('param.');
        $data['token'] = $param['token'];
        $user = Db::name('user')->where('id',$this->user_id)->find();
        $user = transArray($user);
        $user['tel_start'] = substr($user['tel'],0,3);
        $user['tel_end'] = substr($user['tel'],-2,2);
        $this->assign('user',$user);
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 修改密码
     */
    public function change_password()
    {
        if(request()->isAjax()){
            $param = input('param.');
            $user = Db::name('user')->where('id',$this->user_id)->find();
            $pwd = userEncrypt($param['old_pwd'],$user['encrypt']);
            if($param['new_pwd'] == ''){
                sendRequest(201,'新密码不能为空!');
            }
            if($param['again_pwd'] == ''){
                sendRequest(201,'确认密码不能为空!');
            }
            if($pwd == $user['password']){
                if($param['new_pwd'] == $param['again_pwd']){
                    $pwd = userEncrypt($param['new_pwd']);
                    Db::name('user')->where('id',$this->user_id)->update(['password'=>$pwd['password'],'encrypt'=>$pwd['encrypt']]);
                    sendRequest(200,'修改密码成功!');
                }else{
                    sendRequest(201,'新密码与确认密码不一致!');
                }
            }else{
                sendRequest(201,'旧密码错误!');
            }
        }
        return $this->fetch();
    }

    /**
     * 绑定邮箱
     */
    public function binding_email()
    {
        if(request()->isAjax()){
            $param = input('param.');
            Db::name('user')->where('id',$this->user_id)->update(['email'=>$param['email']]);
            sendRequest(200,'绑定成功!');
        }
        return $this->fetch();
    }

    
    /**
     * 绑定银行卡
     */
    public function binding_bank()
    {
        
        if(Request()->isPost()) {
            
            $data = input('param.');
            $validate = new \think\Validate([
                ['bankname', 'require', '请输入银行名称'],
                ['banknum','require', '请输入银行卡号'],
                ['bindname','require', '请输入用户名称']
            ]);
            if (!$validate->check($data)){
                sendRequest(201, $validate->getError());
            }
            
            Db::name('user')
                ->where(['id' => $this->user_id]) 
                ->update([
                    'bankname' => $data['bankname'], 
                    'banknum' => $data['banknum'], 
                    'bindname' => $data['bindname']
                ]);
            sendRequest(200, '操作成功');
        }
        
        $info = Db::name('user')->field('bankname,banknum, bindname')->where(['id' => $this->user_id])->find();
        return $this->fetch('' ,compact('info'));
    }
    
    /**
     * 谷歌验证
     */
    public function binding()
    {
        return $this->fetch();
    }

    /**
     * 兑换(闪兑)
     */
    public function exchange()
    {
        $param = input('param.');
        
        $superNode = Db::name('node')->where(['user_id' => $this->user_id, 'type' => 2])->find();
        if($superNode) {
            $convert_service_charge = 0;
        }else{
            $convert_service_charge = getConfig('convert_service_charge'); //手续费
        }
        $convert_min_price = getConfig('convert_min_price');
        $convert_max_price = getConfig('convert_max_price');
        $convert_exchange_rate = getConfig('convert_exchange_rate');
        
        if(request()->isAjax()){
            if($param['num'] <= 0){
                sendRequest(201,'请输入兑换数量!');
            }
            $user = Db::name('user')->where('id',$this->user_id)->field('usdt,btc')->find();
            if(toprice($param['num']) > $user['btc']){
                sendRequest(201,'BTC余额不足!');
            }
            if($param['num'] > $convert_max_price){
                sendRequest(201,'兑换金额不能超过最高限额!');
            }
            if($param['num'] < $convert_min_price){
                sendRequest(201,'兑换金额不能低于最低限额!');
            }
            $usdt = ($param['num']-$param['num']*$convert_service_charge/100)*$convert_exchange_rate;
            $arr = [
                [
                    'type' =>5,
                    'money_type' =>'BTC',
                    'mold' =>'out',
                    'user_id' =>$this->user_id,
                    'money' =>toprice($param['num']),
                    'create_time' =>time(),
                    'usdt_money'=>toprice($usdt),
                    'service_charge'=>toprice($param['num']*$convert_service_charge/100*$convert_exchange_rate)
                ],
                [
                    'type' =>5,
                    'money_type' =>'USDT',
                    'mold' =>'in',
                    'user_id' =>$this->user_id,
                    'money' =>toprice($usdt),
                    'create_time' =>time(),
                    'usdt_money'=>0,
                    'service_charge'=>0
                ],
            ];
            $user_arr = [
                'btc'=>$user['btc'] - toprice($param['num']),
                'usdt'=>$user['usdt'] + toprice($usdt)
            ];
            try{
                Db::startTrans();
                Db::name('user')->where('id',$this->user_id)->update($user_arr);
                Db::name('finance')->insertAll($arr);
                Db::commit();
                sendRequest(200,'兑换成功!');
            }catch(\Exception $e){
                Db::rollback();
                sendRequest(201,$e->getMessage());
            }
        }
        $data['token'] = $param['token'];
        $btc = Db::name('user')->where('id',$this->user_id)->value('btc');
        $btc = showprice($btc);
        $this->assign('data',$data);
        $this->assign('convert_service_charge',$convert_service_charge);
        $this->assign('convert_min_price',$convert_min_price);
        $this->assign('btc',$btc);
        $this->assign('convert_max_price',$convert_max_price);
        $this->assign('convert_exchange_rate',$convert_exchange_rate);
        return $this->fetch();
    }
    
    /**
     * 兑换记录
     */
    public function exchange_record()
    {
        $list = Db::name('finance')->where('user_id',$this->user_id)->where('type',5)->where('money_type','btc')->where('mold','out')->order('create_time desc')->select();
        foreach($list as $k=>$v){
            $list[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            $list[$k]['money'] = showPrice($v['money']);
            $list[$k]['usdt_money'] = showPrice($v['usdt_money']);
            $list[$k]['service_charge'] = showPrice($v['service_charge']);
        }
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 邀请好友
     */
    public function invite_friends()
    {
        $param = input('param.');
        $data['token'] = $param['token'];
        $code = Db::name('user')->where('id',$this->user_id)->field('code,qr_code')->find();
        $link = request()->domain().'/api/login/register?code='.$code['code'];
        $money = Db::name('returning_servant')->where('return_u_id',$this->user_id)->sum('money');
        $money = showprice($money);
        $user = Db::name('user')->where('parent_1',$this->user_id)->field('username,create_time,tel,id')->select();
        $user = transArray($user);
        $id = array_column($user,'id');
        $count = count($user);
        $num = Db::name('goods_mill_order')->whereIn('user_id',$id)->group('user_id')->field('count(*) num,user_id')->select();
        $num = transArray($num);
        $num = array_column($num,NULL,'user_id');
        foreach($user as $k=>$v){
            $user[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            // $user[$k]['tel'] = substr_replace($v['tel'], '****',"3", '6');
            $user[$k]['num'] = isset($num[$v['id']]['num'])?$num[$v['id']]['num']:0;
        }
        $list = [
            'link'=>$link,
            'code'=>$code['code'],
            'qr_code'=>'../../public/static/qrcode/'.$code['qr_code'],
            'money'=>$money,
            'count'=>$count,
            'user'=>$user
        ];
        $content = getConfig('invite_commission');
        $this->assign('data',$data);
        $this->assign('content',$content);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 生成海报
     */
    public function business_card()
    {
        $param = input('param.');
        $this->assign('data',$param);
        return $this->fetch();
    }
    
     /**
     * 昵称修改
     */
    public function modify_name()
    {
        if(request()->isAjax()){
            $param = input('param.');
            Db::name('user')->where('id',$this->user_id)->update(['username'=>$param['name']]);
            sendRequest(200,'修改成功');
        }
        return $this->fetch();
    }
    
    /**
     * 消息
     */
    public function message()
    {
        $message = Db::name('notice')->order('create_time desc')->select();
        $this->assign('message',$message);
        return $this->fetch();
    }
    
     /**
     * 消息详情
     */
    public function message_details()
    {
        $param = input('param.');
        $message = Db::name('notice')->where('id',$param['id'])->find();
        $this->assign('message',$message);
        return $this->fetch();
    }
    
     /**
     * 我的租赁
     */
    public function reason_wealth()
    {
        $param = input('param.');
        $model = new GoodsWealthOrder();
        $wealth = $model->with('goodsWealth')->where('user_id',$this->user_id)->field('id,goods_wealth_id,duration,apr')->order('create_time desc')->select();
        $wealth = transArray($wealth);
        foreach($wealth as $k=>$v){
            if(strtotime($v['goods_wealth']['rengou_end'])>time()){
                $wealth[$k]['status'] = '未开始';
            }elseif(strtotime($v['goods_wealth']['rengou_end'])<time() && strtotime($v['goods_wealth']['rengou_end'])+$v['duration']*24*60*60>=time()){
                 $wealth[$k]['status'] = '挖矿中';
            }elseif(strtotime($v['goods_wealth']['rengou_end'])+$v['duration']*24*60*60<time()){
                 $wealth[$k]['status'] = '已结束';
            }else{
                $wealth[$k]['status'] = '已结束';
            }
        }
        $this->assign('wealth',$wealth);
        return $this->fetch();
    }
   
   
     /**
     * 我的租赁详情
     */
    public function reason_wealth_details()
    {
        $param = input('param.');
        $model = new GoodsWealthOrder();
        $wealth = $model->with(['goodsWealth','goodsWealthEarnings'=>function($query){
            $query->order('create_time desc');
            }])->where('id',$param['id'])->field('id,goods_wealth_id,duration,apr,price')->find();
        $history_price = 0;
        $wealth['status'] = $param['status'];
        $wealth = transArray($wealth);
        if(!empty($wealth['goods_wealth_earnings'])){
            foreach($wealth['goods_wealth_earnings'] as $k=>$v){
                $history_price = $history_price + $v['price'];
            }
            $wealth['history_price'] = showprice($history_price);
            $wealth['today_price'] = showprice($today_price = $wealth['price'] * $wealth['apr'] / 100 / 365);
            $wealth['surplus_price'] = showprice($today_price*$wealth['duration']-$history_price);
        }else{
            if(strtotime($wealth['goods_wealth']['rengou_end'])<time()){
                $wealth['history_price'] =0;
                $wealth['today_price'] = showprice($wealth['price'] * $wealth['apr']/100/365);
                $wealth['surplus_price'] = showprice($wealth['price'] * $wealth['apr']/100/365*$wealth['duration']-$history_price);
            }else{
                $wealth['history_price'] = 0;
                $wealth['today_price']   = 0;
                $wealth['surplus_price'] = showprice($wealth['price'] * $wealth['apr']/100/365*$wealth['duration']-$history_price);
            }
        }
        $this->assign('wealth',$wealth);
        return $this->fetch();
    } 
    
    
    
    
    
    

}