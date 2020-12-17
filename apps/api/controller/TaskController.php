<?php
/**
 * 定时任务
 */
namespace app\api\controller;

use think\Model;
use think\Controller;
use think\Db;

class TaskController extends Controller{

	protected function _initialize(){

    }

    /**
     * 不需要验证Token的方法
     * @var string[]
     */
    protected $noNeedLogin = ['*'];
	
	/**
	 * 公共执行文件
	 */
	public function index(){
	    $time = date('Y-m-d',strtotime('-1day'));
		$this -> closeOrder($time); //合约定时返usdt
	}

    /**
     * 合约定时返usdt
     */
	public function closeOrder($time){
		$list = Db::name('goods_wealth_order')->whereTime('rengou_end','<=',time())->select();
		foreach($list as $k=>$v){
		    $end_time = strtotime($v['rengou_end']) + $v['duration']*60*60*24;
		    $return_time = $end_time + 3*60*60*24;
		    if($v['type'] == 1){
		        $type = 7;
		    }else{
		        $type = 8;
		    }
		    if(strtotime($time)<$end_time){//发放金额
		        $money = $v['price'] * $v['apr']/100/365;
		        $arr[] = [
                    'type' =>$type,
                    'money_type' =>strtolower($v['fanxihuobi']),
                    'mold' =>'in',
                    'user_id' =>$v['user_id'],
                    'money' =>$money,
                    'create_time' =>time()
                ];
                $log_arr = [
                    'type'=>$v['type'],
                    'user_id'=>$v['user_id'],
                    'goods_wealth_id'=>$v['goods_wealth_id'],
                    'price'=>$money,
                    'earnings_date'=>$time,
                    'create_time'=>time(),
                    'order_id'=>$v['id']
                ];
                Db::name('user')->where('id',$v['user_id'])->setInc(strtolower($v['fanxihuobi']), $money);
                if($type == 7){
                    $er = $money * 10;
                    $er = number_format($er, 8, '.', '');//返还10倍ER币;
                    Db::name('user')->where('id',$v['user_id'])->setInc('er', $er);
                    $arr[] = [
                        'type' =>$type,
                        'money_type' =>er,
                        'mold' =>'in',
                        'user_id' =>$v['user_id'],
                        'money' =>$er,
                        'create_time' =>time()
                    ];
                }
                Db::name('finance')->insertAll($arr);
                Db::name('goods_wealth_earnings')->insert($log_arr);
		    }elseif(strtotime($time)>$end_time && $return_time == strtotime($time)){//返还本金
		        $money = $v['price'];
		        $arr = [
                    'type' =>$type,
                    'money_type' =>'usdt',
                    'mold' =>'in',
                    'user_id' =>$v['user_id'],
                    'money' =>$money,
                    'create_time' =>time()
                ];
                $log_arr = [
                    'type'=>$v['type'],
                    'user_id'=>$v['user_id'],
                    'goods_wealth_id'=>$v['goods_wealth_id'],
                    'price'=>$money,
                    'earnings_date'=>$time,
                    'create_time'=>time(),
                    'order_id'=>$v['id']
                ];
                Db::name('user')->where('id',$v['user_id'])->setInc('usdt',$money);
                Db::name('finance')->insert($arr);
                Db::name('goods_wealth_earnings')->insert($log_arr);
		    }
		}
	    return true;
	}
    
    public function colsemileorder()
    {
        $orders = Db::name('goods_mill_order')->where('status', 1)->select();
        foreach($orders as $order)
        {
            $mill = Db::name('goods_mill')->where('id',$order['goods_mill_id'])->find();
            if($mill != null)
            {
                $limit_day = date('Y-m-d',strtotime($order['buy_time'].' +'.$order['zhouqi'].' day'));
                
                if($limit_day < date('Y-m-d'))
                {
                    Db::startTrans();
                    try
                    {
                        Db::name('goods_mill_order')->where('id', $order['id'])->update(['status'=>0]);
                        if($order['method'] == 2)
                        {   
                            Db::name('finance')
                            ->insert([
                                'type'          => 13,
                                'money_type'    => 'usdt',
                                'mold'          => 'in',
                                'user_id'       => $order['user_id'],
                                'money'         => $order['order_price'],
                                'create_time'   => time(),
                            ]);
                            Db::name('user_mill')
                            ->where(['user_id' => $order['user_id'], 'mill_id' => $order['goods_mill_id']])
                            ->setDec('mill_num',$order['num']);
                            Db::name('user')
                            ->where(['id' => $order['user_id']])
                            ->setInc('usdt', $order['order_price']);
                        }
                        Db::commit();   
                    }
                    catch(\Exception $e)
                    {
                        Db::rollback();
                    }
                    
                }
            }
        }
    }
    public function checkEfeeLimit()
    {
        $dateToday = date('Y-m-d');
        $date5DayPlus = date('Y-m-d', strtotime('+5 day'));
        
        // $limitedOrders = Db::name('goods_mill_order')
        //         ->where([ 
        //             'efee_limit' => ['lt', $dateToday],
        //             'status' => 1])
        //         ->update(['status' => 5]);
        
        $limitOrdersAfter5Day = Db::name('goods_mill_order')->alias('g')
                                ->join('user u','g.user_id = u.id','LEFT')
                                ->field('g.*, u.username, u.tel')
                                ->where([ 
                                    'efee_limit' => $date5DayPlus,
                                    'g.status' => 1])
                                ->select();
        foreach($limitOrdersAfter5Day as $order)
        {
            $tel = $order['tel'];
            sendSms($tel, getconfig('efee_limited_sms'));
        }
    }
    /**
     * 实体矿机收益结算 | 单独执行定时任务
     * 执行时间 24:00  
     * 执行任务 结算前一天的收益
     */
    public function milljiesuan() {
        $yesterday = date('Y-m-d',strtotime('-1day'));
        $daybeforeyesterday = date('Y-m-d',strtotime('-2day'));
        $yesterday_18pm = date('Y-m-d H:i:s', strtotime(date("Y-m-d")) - (6 * 60 * 60));
        
        $info = Db::name('user_mill')->alias('um')
            ->join('user u','um.user_id = u.id','LEFT')
            ->field('um.*, u.username, u.tel')
            ->where('um.status = 1 AND u.status = 1 AND ( um.last_earnings_date< :yesterday OR um.last_earnings_date IS NULL )',['yesterday' => strtotime($yesterday)])
            ->select();
            
        foreach($info as $k => $v) {
            //当前矿机昨天18点之前购买的数量
            $settleMillNum = Db::name('goods_mill_order')
                ->where([
                    'user_id' => $v['user_id'] , 
                    'goods_mill_id' => $v['mill_id'], 
                    'efee_limit' => ['gt', date('Y-m-d')],
                    'buy_time' => ['lt', $yesterday_18pm],
                    'status' => 1])
                ->sum('num');
            $settleMillNumRent = Db::name('goods_mill_order')
                ->where([
                    'user_id' => $v['user_id'] , 
                    'goods_mill_id' => $v['mill_id'], 
                    'efee_limit' => ['gt', date('Y-m-d')],
                    'buy_time' => ['lt', $yesterday_18pm],
                    'method'    => 2,
                    'status' => 1])
                ->sum('num');
            $presellMillCheck = Db::name("goods_mill")
                ->field('id, shangjia_time, type')
                ->where(['id' => $v['mill_id']])
                ->find();
    
            if($settleMillNum == 0) {
                continue;
            }
            if($presellMillCheck['type'] == 2 && $presellMillCheck['shangjia_time'] > date('Y-m-d') ) {
                continue;
            }
            
            $userInfo = Db::name('user')->where(['id' => $v['user_id']])->find(); //用户信息
            $millInfo = Db::name('goods_mill')->where(['id' => $v['mill_id']])->find(); //矿机信息
            //计算日产出收益  每日挖矿产出-电费-保险费-管理费
            $v['richanchu_cny'] = showprice($millInfo['richanchu'] * getconfig('btc_parities')) * $settleMillNum; //日产出(￥) = 日产出 * BTC汇率 * 矿机数量
            $v['baoxianfei'] = showprice($millInfo['baoxianfei'] * $settleMillNum); //保险费(￥) = 保险费 * 矿机数量
            $v['dianfei'] = ( $millInfo['gonghaobi'] / 100 ) * showprice($millInfo['dianfei']) * 24 * $settleMillNum - ($userInfo['suanli']) * $settleMillNum ; //电力(￥) = 当日持有算力*（功耗比/1000）* 电价 * 24 * 矿机数量
            if($v['dianfei'] < 0) {
                $v['dianfei'] = 0;
            }
            $v['guanlifei'] = $v['richanchu_cny'] * ( $millInfo['guanlifei'] / 100 ) * $settleMillNum;  //管理费(￥) = 挖矿产出的*%
            
            Db::startTrans();
            try{
                //扣除手续费
                if($userInfo['deduction'] == 1) {
                    $kouchu_usdt = toprice((($v['dianfei'] + $v['baoxianfei']) / getconfig('btc_parities')) * getconfig('convert_exchange_rate')); //手续费 人民币 -> btc -> usdt -> 格式化
                    if($userInfo['usdt'] > $kouchu_usdt) {
                        $v['shouyi_cny'] = $v['richanchu_cny'];
                        Db::name('user')->where(['id' => $v['user_id']])->setDec('usdt', $kouchu_usdt);
                        Db::name('finance')->insert([
                            'type'        => 4,
                            'money_type'  => 'usdt',
                            'mold'        => 'out',
                            'user_id'     => $v['user_id'],
                            'money'       => $kouchu_usdt,
                            'create_time' => time(),
                        ]);
                    }else{
                        $v['shouyi_cny'] = $v['richanchu_cny'] - $v['dianfei'] - $v['baoxianfei'] - $v['guanlifei'];
                    }
                }else{
                    $v['shouyi_cny'] = $v['richanchu_cny'] - $v['dianfei'] - $v['baoxianfei'] - $v['guanlifei'];
                }
                $v['shouyi_format'] = toprice($v['shouyi_cny'] / getconfig('btc_parities')); //收益 比特币 | 格式化
                
                /**
                 * Pay Rebate
                 * parent 1,2,3
                 * rate r1,r2,r3
                 */
                $shouyi_format_bought = $v['shouyi_format'] * ($settleMillNum / $settleMillNumRent);
                if(!empty($userInfo['parent_1'])) {
                    $rebate = $shouyi_format_bought * $millInfo['r1'] * getRebateMultiple($userInfo['parent_1']);
                    Db::name('user')->where(['id' => $userInfo['parent_1']])->setInc('btc', $rebate); 
                    Db::name('finance')->insert([
                        'type'        => 3,
                        'money_type'  => 'btc',
                        'mold'        => 'in',
                        'user_id'     => $userInfo['parent_1'],
                        'money'       => $rebate,
                        'create_time' => time(),
                    ]);
                    Db::name('returning_servant')->insert([
                        'u_id' => $v['user_id'],
                        'return_u_id' => $userInfo['parent_1'],
                        'money' => $rebate,
                        'created_time' => time()
                    ]);
                }
                if(!empty($userInfo['parent_2'])) {
                    $rebate = $shouyi_format_bought * $millInfo['r2'] * getRebateMultiple($userInfo['parent_2']);
                    Db::name('user')->where(['id' => $userInfo['parent_2']])->setInc('btc', $rebate); 
                    Db::name('finance')->insert([
                        'type'        => 3,
                        'money_type'  => 'btc',
                        'mold'        => 'in',
                        'user_id'     => $userInfo['parent_2'],
                        'money'       => $rebate,
                        'create_time' => time(),
                    ]);
                    
                    Db::name('returning_servant')->insert([
                        'u_id' => $v['user_id'],
                        'return_u_id' => $userInfo['parent_2'],
                        'money' => $rebate,
                        'created_time' => time()
                    ]);
                }
                if(!empty($userInfo['parent_3'])) {
                    $rebate = $shouyi_format_bought * $millInfo['r3'] * getRebateMultiple($userInfo['parent_3']);
                    Db::name('user')->where(['id' => $userInfo['parent_3']])->setInc('btc', $rebate); 
                    Db::name('finance')->insert([
                        'type'        => 3,
                        'money_type'  => 'btc',
                        'mold'        => 'in',
                        'user_id'     => $userInfo['parent_3'],
                        'money'       => $rebate,
                        'create_time' => time(),
                    ]);
                    
                    Db::name('returning_servant')->insert([
                        'u_id' => $v['user_id'],
                        'return_u_id' => $userInfo['parent_3'],
                        'money' => $rebate,
                        'created_time' => time()
                    ]);
                }
                // print_r('日产出 = '. $v['richanchu_cny']. "<br/>");
                // print_r('电费 = '. $v['dianfei']. "<br/>");
                // print_r('保险费 = '. $v['baoxianfei']. "<br/>");
                // print_r('管理费= '. $v['guanlifei']. "<br/>");
                // print_r('最终收益(人民币) = 日产出 - (电费 + 管理费 + 保险费) = ' . $v['shouyi_cny'] . '<br/>');
                // print_r('最终收益(BTC) = '.showprice($v['shouyi_format']));
                // die;
                
                //2020-11-27  节点收益结算
                $userPollInfo = Db::name('user_poll')->alias('up')
                    ->join('node n','n.id = up.node_id','LEFT')
                    ->field('up.*, n.user_id as node_user_id, n.type')
                    ->where(['up.user_id' => $v['user_id']])
                    ->select();
                $userPollCount = Db::name('user_poll')->alias('up')
                    ->join('node n','n.id = up.node_id','LEFT')
                    ->where(['up.user_id' => $v['user_id']])
                    ->count();
                    
                    //1. 当前矿机的用户 = 8 
                    //2. 查询出用户8 投票的节点
                    //3. 查询出用户8 投票的节点数量
                    //4. 给这几个节点的创建者, 分这个矿机收益的3/5%;
                    
                    //2.1 当前矿机用户 = 8
                    //2.2 查询出用户8 投票的节点
                    //2.3 查询出用户8 投票的节点数量
                    //2.4 给这几个几点的创建者, 分这个矿机收益的3/5%;
                    // 8 - 1
                    // 8 - 2
                    // 8 - 6
                foreach($userPollInfo as $vv) {
                    if($vv['type'] == 1) {
                        $vv['shouyilv'] = 0.03 / $userPollCount;
                    }else{
                        $vv['shouyilv'] = 0.05 / $userPollCount;
                    }
                    $vv['jiesuanshouyi'] = toprice(showprice($v['shouyi_format'] * $vv['shouyilv']));
                    Db::name('user')->where(['id' => $vv['node_user_id']])->setInc('btc',$vv['jiesuanshouyi']);
                    Db::name('finance')->insert([
                    	'type'        => 10,
                        'money_type'  => 'btc',
                        'mold'        => 'in',
                        'user_id'     => $vv['node_user_id'],
                        'money'       => $vv['jiesuanshouyi'],
                        'create_time' => time(),
                    ]);
                }
                
                //前天的收益，用来和昨天的收益进行比较
                $dayBeforYesterDayEarnings = Db::name('goods_mill_earnings')
                    ->where([
                        'earnings_date' => $daybeforeyesterday,
                        'will_id' => $v['mill_id'], 
                        'user_id' => $v['user_id']])
                    ->value('price')?: 0;
        
                if(isset($dayBeforYesterDayEarnings) && $dayBeforYesterDayEarnings != 0 ) {
                    $earningsUp = number_format(($v['shouyi_format'] - $dayBeforYesterDayEarnings) / $dayBeforYesterDayEarnings, 2);
                }else{
                    $earningsUp = 0.00;
                }
                //当前矿机总收益
                $insertCount_earnings = number_format($v['count_earnings'] + $v['shouyi_format'], 8, '.', '');
            	//写入到用户余额
            	Db::name('user')->where('id',$v['user_id'])->setInc('btc', $v['shouyi_format']);
                //写入到收益表
                Db::name("goods_mill_earnings")->insert([
                    'user_id'           => $v['user_id'],
                    'will_id'           => $v['mill_id'],
                    'price'             => $v['shouyi_format'],
                    'earnings_up'       => $earningsUp,
                    'earnings_date'     => $yesterday,
                    'yesterday_earnings'=> $dayBeforYesterDayEarnings,
                    'create_time'       => time(),
                ]);
                //写入到我的矿机表
                Db::name('user_mill')
                    ->where('id', $v['id'])
                    ->update([
                        'yesterday_earnings' => $v['shouyi_format'], //昨天的收益
                        'last_earnings_date' => strtotime($yesterday),  //最后结算日期,昨天
                        'count_earnings'     => $insertCount_earnings, //总收益
                    ]);
                //写入到明细表
                Db::name('finance')->insert([
                	'type'        => 4,
                    'money_type'  => 'btc',
                    'mold'        => 'in',
                    'user_id'     => $v['user_id'],
                    'money'       => $v['shouyi_format'],
                    'create_time' => time(),
                ]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $date = date('Y-m-d H:i:s');
                file_put_contents("./logs/commission.txt", "{$date}: {$e->getMessage()}\n", FILE_APPEND);
            }
        }
    }
     
     
 	/**
	 * 法币交易, 超时取消订单
	 */
	 public function fiatDealCancelOrder(){
	     $orderList = Db::name('transaction_order')->where(['status' => 1])->select();
	     $cancelTime = getconfig('expiration_time');
	     $cancelTime = $cancelTime ? $cancelTime * 60 : 86400;  //默认24小时
	     foreach($orderList as $v) {
	         if($v['create_time'] + $cancelTime < time()){
	             Db::name('transaction_order') ->where(['id' => $v['id']])->setField('status', 4);
	         }
	     }
	 }
	 
	  /**
	  * 添加er币记录(只用一遍)
	  */
	  public function addEr(){
	      $list = Db::name('goods_wealth_earnings')->where('type',1)->select();
	      foreach($list as $k=>$v){
	          $arr[] = [
	                'type' =>7,
                    'money_type' =>'er',
                    'mold' =>'in',
                    'user_id' =>$v['user_id'],
                    'money' =>$v['price']*10,
                    'create_time' =>strtotime($v['earnings_date'])
	              ];
	      }
	      if(isset($arr)){
	          Db::name('finance')->insertAll($arr);
	      }
	      echo 'success';
	  }
	 
	 
}
