<?php
namespace app\api\controller;


use app\api\logic\IndexLogic;
use app\api\logic\MessageLogic;
use think\Db;

class IndexController extends BaseController
{
    private $indexLogic = '';
    private $messageLogic = '';

    function __construct () {
        parent::__construct ();
        $this->indexLogic = new IndexLogic();
        $this->messageLogic = new MessageLogic();
    }

    /**
     * 首页
     */
    public function index()
    {
        $param = input('param.');
        if(empty($param)){
            $data['token'] = '';
            $data['code'] = 0;
        }else{
            $data['token'] = $param['token'];
            $data['code'] = 1;
        }
        //资讯
        $data['message']= $this->indexLogic->getMessage()['data'] ;
        //我的矿机数量
        $data['my_mill_num'] = Db::name('user_mill')->where(['user_id' => $this->user_id])->sum('mill_num');
        $data['suanli'] = Db::name('user')->where(['id' => $this->user_id])->value('suanli');
        //矿机总收益
        $data['totalshouyi'] = Db::name('user_mill')->where(['user_id' => $this->user_id])->sum('count_earnings');
        $data['totalshouyi_cny'] = $data['totalshouyi'] * getconfig('btc_parities');
        //昨日收益 (真·昨日收益) 'earnings_date' => date('Y-m-d',strtotime('-1day'))
        $data['yesterdayshouyi'] = Db::name('goods_mill_earnings')->where(['user_id' => $this->user_id, 'earnings_date' => date('Y-m-d',strtotime('-1day'))]) -> sum('price');
        //我的矿机列表
        // $data['mill_list'] = Db::name('user_mill')->alias('um')
        //     ->join('goods_mill gm','um.mill_id = gm.id','LEFT')
        //     ->field('um.id, um.yesterday_earnings, um.mill_num, gm.name, gm.cover')
        //     ->where(['um.user_id' => $this->user_id])
        //     ->limit(0,3)
        //     ->select();
        $list = Db::name('user_mill')->alias('um')
                ->join('goods_mill m','um.mill_id = m.id','LEFT')
                ->join('goods_mill_order mo','um.mill_id = mo.goods_mill_id','LEFT')
                ->join('days d','d.days = m.zhouqi','LEFT')
                ->field('um.*, mo.num as onum, m.ipfs_type, m.category, rebate_at, m.name, m.cover, m.oc_type, m.suanli, d.label, d.days, m.jieshu_time, mo.zhouqi, mo.buy_time')
                ->where([
                    'um.user_id' => $this->user_id,
                    'mo.user_id' => $this->user_id,
                ])
                ->order('buy_time desc')
                ->select();
        $list_sorted = [];
        foreach($list as $key => $mill)
        {
            $bExist = false;
            foreach($list_sorted as $key1 => $item)
            {
                if(date('Y-m-d', strtotime($item['buy_time'])) == date('Y-m-d', strtotime($mill['buy_time'])) && $item['mill_id'] == $mill['mill_id'])
                {
                    $list_sorted[$key1]['mill_num'] += $mill['onum'];
                    $bExist = true;
                }
            }
            if(!$bExist)
            {
                $list[$key]['mill_num2'] =  $list[$key]['mill_num'];
                $list[$key]['mill_num'] = $mill['onum'];
                $list[$key]['buy_time_t'] = $mill['buy_time'];
                $list[$key]['buy_time'] = date('Y-m-d', strtotime($mill['buy_time']));
                $list[$key]['end_date'] = date('Y-m-d', strtotime($mill['buy_time'].' +'.$mill['zhouqi'].' day'));
                $list[$key]['active'] = date('Y-m-d') <= $list[$key]['end_date']?1:0;
                $list[$key]['earning_date'] = date('Y-m-d',strtotime($mill['buy_time'].' +'.$mill['rebate_at'].' day'));
                $list_sorted[] = $list[$key];
            }
        }
        $data['list_result'] = [];
        foreach($list_sorted as $key => $mill)
        {
            if($key < 3 && $mill['active'] == 1)
                $data['list_result'][] = $mill;
        }
        return $this->fetch('', compact('data'));
    }

    /**
     * 资金记录
     */
    public function money_record()
    {
        //全部资金记录
        $param = input('param.');
        $auth['token'] = $param['token'];
        $map['user_id'] = $this->user_id;
        
        if(isset($param['type'])) {
            $map['type'] = $param['type'];
        }
        if(isset($param['class'])) {
            $class = $param['class'];
            $map['money_type'] = $param['class'];
        }
        
        $list = Db::name('finance')->alias('f')
                ->join('financeTypes ft','f.type = ft.id','LEFT')
                ->field('ft.label as flabel, f.*')
                ->where($map)->order("id desc")->select();
        foreach($list as $k => &$v) {
            $v['money'] = $v['money_type'] == 'btc'?showprice($v['money']):showprice($v['money'],2);
            $v['money_type'] = strtoupper($v['money_type']);
            if($v['mold'] == 'in') {
                $v['money'] = '+' .$v['money'] . $v['money_type'];
            }else{
                $v['money'] = '-' .$v['money'] . $v['money_type'];
            }
        }
        return $this->fetch('', compact('list', 'auth', 'class'));
    }

    /**
     * 我的矿机
     */
    public function my_mill_all()
    {
        $token = input('param.token', '');
        
        $list = Db::name('user_mill')->alias('um')
        ->join('goods_mill m','um.mill_id = m.id','LEFT')
        ->join('goods_mill_order mo','um.mill_id = mo.goods_mill_id','LEFT')
        ->join('days d','d.days = m.zhouqi','LEFT')
        ->field('um.*, mo.num as onum, m.ipfs_type, m.rebate_at, m.category, rebate_at, m.name, m.cover, m.oc_type, m.suanli, d.label, d.days, m.jieshu_time, mo.zhouqi, mo.buy_time')
        ->where([
            'um.user_id' => $this->user_id,
            'mo.user_id' => $this->user_id,
        ])
        ->order('buy_time desc')
        ->select();
        $list_sorted = [];
        foreach($list as $key => $mill)
        {
            $bExist = false;
            foreach($list_sorted as $key1 => $item)
            {
                if(date('Y-m-d', strtotime($item['buy_time'])) == date('Y-m-d', strtotime($mill['buy_time'])) && $item['mill_id'] == $mill['mill_id'])
                {
                    $list_sorted[$key1]['mill_num'] += $mill['onum'];
                    $bExist = true;
                }
            }
            if(!$bExist)
            {
                $list[$key]['mill_num2'] =  $list[$key]['mill_num'];
                $list[$key]['mill_num'] = $mill['onum'];
                $list[$key]['buy_time_t'] = $mill['buy_time'];
                $list[$key]['buy_time'] = date('Y-m-d', strtotime($mill['buy_time']));
                $list[$key]['end_date'] = date('Y-m-d', strtotime($mill['buy_time'].' +'.$mill['zhouqi'].' day'));
                $list[$key]['active'] = date('Y-m-d') <= $list[$key]['end_date']?1:0;
                $list[$key]['earning_date'] = date('Y-m-d',strtotime($mill['buy_time'].' +'.$mill['rebate_at'].' day'));
                $list_sorted[] = $list[$key];
            }
        }
        foreach($list_sorted as $key => $mill)
        {
            $yesterday_18pm = date('Y-m-d H:i:s', strtotime(date("Y-m-d 00:00:00")) - (6 * 60 * 60));
            $rebate_day = date('Y-m-d', strtotime($mill['buy_time_t'].' +'.$mill['rebate_at'].' day'));
            if($mill['buy_time_t'] < $yesterday_18pm)
                $list_sorted[$key]['yesterday_earnings'] = $mill['yesterday_earnings'] * $mill['mill_num'] / $mill['mill_num2'];
            else
            $list_sorted[$key]['yesterday_earnings'] = 0;
        }
        $oc_types = Db::name('oc_types')
        ->order('uid asc')
        ->select();
        
        $category2_btc = [
            [
                'id' => 1,
                'name' => '实体矿机购买'
            ],
            [
                'id' => 2,
                'name' => '云算力购买'
            ],
            [
                'id' => 3,
                'name' => '云算力租赁'
            ],
        ];
        $ipfs_types = Db::name('ipfs_types')
        ->order('uid asc')
        ->select();
        $list = $list_sorted;
        return $this->fetch('',compact('list','token','oc_types', 'category2_btc', 'ipfs_types'));
    }
    
    
    /**
     * 我的矿机详情
     */
    public function my_mill()
    {
        $token = input('param.token', '');
        $user_mill_id = input('id','');
        $buy_time = input('buy_time', '');
        if(empty($user_mill_id)) {
            sendRequest(201, '非法请求');
        }
        
        $info = Db::name('user_mill')->alias('um')
            ->join('goods_mill m','um.mill_id = m.id','LEFT')
            ->join('days d','d.days = m.zhouqi','LEFT')
            ->field('um.*, m.name, m.cover, m.rebate_at, m.jieshu_time, d.label, d.days, m.location, m.dianfei, m.baoxianfei, m.guanlifei, m.rebate_at, m.id = mill_id')
            ->where([
                'um.user_id' => $this->user_id,
                'um.id' => $user_mill_id,
            ])->find();
        
        if(empty($user_mill_id)) {
            sendRequest(201, '未查询到次矿机信息，请稍后再试');
        }

        $yesterday_18pm = date('Y-m-d H:i:s', strtotime(date("Y-m-d 0:0:0")) - (6 * 60 * 60));
        if($buy_time < $yesterday_18pm)
            $info['yesterday_earnings'] = $info['yesterday_earnings'];
        else
        {
            $info['yesterday_earnings'] = 0;
            $info['count_earnings'] = 0;
        }


        $info['buy_time'] = date('Y-m-d', strtotime($buy_time));
        //get num
        $machineNum = Db::name('goods_mill_order')
                        ->where(['user_id' => $info['user_id'] , 'goods_mill_id' => $info['mill_id']])
                        ->whereRaw('Date(buy_time)="'.$info['buy_time'].'"')
                        ->sum('num');

        $rate = $machineNum / $info['mill_num'];
        $info['count_earnings'] *= $rate;
        
        $info['yesterday_earnings'] *= $rate;
        $info['count_earnings_cny'] = $info['count_earnings'] * getconfig('btc_parities');
        $info['yesterday_earnings_cny'] = $info['yesterday_earnings'] * getconfig('btc_parities');
        $info['suanli'] = Db::name('user')->where(['id' => $this->user_id])->value('suanli');

        $latestOrder = Db::name('goods_mill_order')
                        ->where([
                            'user_id' => $info['user_id'] , 
                            'goods_mill_id' => $info['mill_id']])
                        ->order('buy_time desc')
                        ->limit(0,1)
                        ->select();
        
        $info['end_date'] = date('Y-m-d', strtotime($latestOrder[0]['buy_time'].' +'.$latestOrder[0]['zhouqi'].' day'));
        $info['earning_date'] = date('Y-m-d', strtotime($latestOrder[0]['buy_time'].' +'.$info['rebate_at'].' day'));

        //
        $diff = floor( abs(strtotime($latestOrder[0]['efee_limit']) - strtotime(date('Y-m-d'))) / (60*60*24) );
        $info['efee_date_balance'] = $diff.'天';
        //矿机预计今日收益  
        $today = date('Y-m-d',strtotime());
        $before18pm = date('Y-m-d H:i:s', strtotime(date("Y-m-d").' +1 day') - (6 * 60 * 60));
        
        //当前矿机18点之前购买的数量
        $settleMillNum = Db::name('goods_mill_order')
            ->where([
                'user_id' => $info['user_id'] , 
                'goods_mill_id' => $info['mill_id'], 
                'buy_time' => ['lt', $before18pm],
                'status' => 1])
            ->sum('num');
        
        $presellMillCheck = Db::name("goods_mill")
            ->field('id, shangjia_time, type')
            ->where(['id' => $info['mill_id']])
            ->find();

        if($settleMillNum == 0) {
            $mill['shouyi_cny'] = 0.00000000;
        }elseif($presellMillCheck['type'] == 2 && $presellMillCheck['shangjia_time'] > date('Y-m-d') ) {
            $mill['shouyi_cny'] = 0.00000000;
        }else{
            $userInfo = Db::name('user')->where(['id' => $info['user_id']])->find(); //用户信息
            $millInfo = Db::name('goods_mill')->where(['id' => $info['mill_id']])->find(); //矿机信息
            //计算日产出收益  每日挖矿产出-电费-保险费-管理费
            $mill['richanchu_cny'] = showprice($millInfo['richanchu'] * getconfig('btc_parities')) * $settleMillNum; //日产出(￥) = 日产出 * BTC汇率 * 矿机数量
            $mill['baoxianfei'] = showprice($millInfo['baoxianfei'] * $settleMillNum); //保险费(￥) = 保险费 * 矿机数量
            $mill['dianfei'] = ( $millInfo['gonghaobi'] / 100 ) * showprice($millInfo['dianfei']) * 24 * $settleMillNum - ($userInfo['suanli']) * $settleMillNum ; //电力(￥) = 当日持有算力*（功耗比/1000）* 电价 * 24 * 矿机数量
            if($mill['dianfei'] < 0) {
                $mill['dianfei'] = 0;
            }
            $mill['guanlifei'] = $mill['richanchu_cny'] * ( $millInfo['guanlifei'] / 100 );  //管理费(￥) = 挖矿产出的*%
            
            //扣除手续费
            if($userInfo['deduction'] == 1) {
                $kouchu_usdt = toprice((($v['dianfei'] + $mill['baoxianfei']) / getconfig('btc_parities')) * getconfig('convert_exchange_rate')); //手续费 人民币 -> btc -> usdt -> 格式化
                if($userInfo['usdt'] > $kouchu_usdt) {
                    $mill['shouyi_cny'] = $mill['richanchu_cny'];
                }else{
                    $mill['shouyi_cny'] = $mill['richanchu_cny'] - $mill['dianfei'] - $mill['baoxianfei'] - $mill['guanlifei'];
                }
            }else{
                $mill['shouyi_cny'] = $mill['richanchu_cny'] - $mill['dianfei'] - $mill['baoxianfei'] - $mill['guanlifei'];
            }
        }
        
        $info['shouyi_format'] = toprice($mill['shouyi_cny'] / getconfig('btc_parities')) * $rate; //收益 比特币 | 格式化
    
        $shouyilist = Db::name('goods_mill_earnings')
            ->where(['will_id' => $info['mill_id'], 'user_id' => $this->user_id])
            ->order('id desc')
            ->select();
        $efees = Db::name('efee_rebate')->select();
        $mill = Db::name('goods_mill')->find($info['mill_id']);
        foreach($efees as $key => $efee)
        {
            //*x_2*(gonghaobi/1000)*dianfei $info['x_2']*
            $efees[$key]['amount'] = showprice(getMillEfee($this->user_id, $mill, $efee['days'], $efee['rebate'], $machineNum), 2);
            $efees[$key]['rebate_label'] = $efee['rebate'] * 0.1;
        }
        $selfUsdt = Db::name('user')->where('id',$this->user_id)->value('usdt');
        $user = Db::name('user')->find($this->user_id);
        //load balances
        $balances = [];

        return $this->fetch('',compact('info','token','shouyilist', 'efees', 'machineNum', 'buy_time','selfUsdt'));
    }
    public function buy_efee()
    {
        $mill_id = input('mill_id');
        $efee_id = input('efee_id');
        $buy_time = date('Y-m-d', strtotime(input('buy_time')));
        $user_id = $this->user_id;
        $machineNum = $orders = Db::name('goods_mill_order')
                    ->where([
                        'user_id' => $user_id, 
                        'goods_mill_id' => $mill_id
                    ])
                    ->whereRaw('Date(buy_time)="'.$buy_time.'"')
                    ->sum('num');
        $mill = Db::name('goods_mill')->find($mill_id);
        $efee = Db::name('efee_rebate')->find($efee_id);
        $efee_amount = getMillEfee($user_id, $mill, $efee['days'], $efee['rebate'], $machineNum);
        
        $order = Db::name('goods_mill_order')
                ->where([
                    'user_id' => $user_id, 
                    'goods_mill_id' => $mill_id
                ])
                ->whereRaw('Date(buy_time)="'.$buy_time.'"')
                ->select()[0];
        $efee_limit = date('Y-m-d', strtotime($order['efee_limit'].' +'.$efee['days'].' day'));
        Db::name('goods_mill_order')
        ->where([
            'user_id' => $user_id, 
            'goods_mill_id' => $mill_id
        ])
        ->whereRaw('Date(buy_time)="'.$buy_time.'"')
        ->update(['efee_limit'=>$efee_limit]);
        Db::name('user')
        ->where(['id' => $user_id])
        ->dec('usdt', $efee_amount)
        ->update();
        $response = [
            'mill' => $mill,
            'efee' => $efee,
            'amount' => $efee_amount,
            'machineNum' => $machineNum,
            'buy_time' => $buy_time,
            'user_id' => $user_id,
        ];
        sendRequest(200, '购买成功');
        return $response;
    }
    /**
     * 矿机上下架
     */
    public function millChange() {
        $type = input('type',0);
        $id = input('id', '');
        
        if(empty($id)) {
            sendRequest(201, '非法请求');
        }
        
        $info = Db::name('user_mill')
            ->where(['id' => $id, 'user_id' => $this->user_id])
            ->setField('status',$type);
            
        if($info) {
            sendRequest(200, '操作成功');
        }else{
            sendRequest(201, '操作失败，请稍后再试');
        }
    }
    
    /**
     * 资讯详情
     */
    public function consult()
    {
        $param = input('param.');
        $data = $this->indexLogic->getDetails($param['id']);
        $this->assign('data',$data['data']);
        return $this->fetch();
    }
}