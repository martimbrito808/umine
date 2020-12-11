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
        //昨日收益 (真·昨日收益)
        $data['yesterdayshouyi'] = Db::name('goods_mill_earnings')->where(['user_id' => $this->user_id, 'earnings_date' => date('Y-m-d',strtotime('-1day'))]) -> sum('price');
        //我的矿机列表
        $data['mill_list'] = Db::name('user_mill')->alias('um')
            ->join('goods_mill gm','um.mill_id = gm.id','LEFT')
            ->field('um.id, um.yesterday_earnings, um.mill_num, gm.name, gm.cover')
            ->where(['um.user_id' => $this->user_id])
            ->limit(0,3)
            ->select();
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
        
        $list = Db::name('finance')->where($map)->order("id desc")->select();
        foreach($list as $k => &$v) {
            $v['money_type'] = strtoupper($v['money_type']);
            $v['money'] = showprice($v['money']);
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
        ->field('um.*, m.name, m.cover')
        ->where(['um.user_id' => $this->user_id])
        ->select();
    
        return $this->fetch('',compact('list','token'));
    }
    
    
    /**
     * 我的矿机详情
     */
    public function my_mill()
    {
        $token = input('param.token', '');
        $user_mill_id = input('id','');
        if(empty($user_mill_id)) {
            sendRequest(201, '非法请求');
        }
        
        $info = Db::name('user_mill')->alias('um')
            ->join('goods_mill m','um.mill_id = m.id','LEFT')
            ->field('um.*, m.name, m.cover, m.location, m.dianfei, m.baoxianfei, m.guanlifei, m.id = mill_id')
            ->where(['um.user_id' => $this->user_id,'um.id' => $user_mill_id])
            ->find();
        
        if(empty($user_mill_id)) {
            sendRequest(201, '未查询到次矿机信息，请稍后再试');
        }
        $info['count_earnings_cny'] = $info['count_earnings'] * getconfig('btc_parities');
        $info['yesterday_earnings_cny'] = $info['yesterday_earnings'] * getconfig('btc_parities');
        $info['suanli'] = Db::name('user')->where(['id' => $this->user_id])->value('suanli');
    
    
        //矿机预计今日收益  
        $today = date('Y-m-d',strtotime());
        $before18pm = date('Y-m-d H:i:s', strtotime(date("Y-m-d")) - (6 * 60 * 60));
        
        //当前矿机18点之前购买的数量
        $settleMillNum = Db::name('goods_mill_order')
            ->where([
                'user_id' => $info['user_id'] , 
                'goods_mill_id' => $info['mill_id'], 
                'buy_time' => ['lt', $before18pm]])
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
            $mill['guanlifei'] = $mill['richanchu_cny'] * ( $millInfo['guanlifei'] / 100 ) * $settleMillNum;  //管理费(￥) = 挖矿产出的*%
            
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
        
        $info['shouyi_format'] = toprice($mill['shouyi_cny'] / getconfig('btc_parities')); //收益 比特币 | 格式化
    
        $shouyilist = Db::name('goods_mill_earnings')
            ->where(['will_id' => $info['mill_id'], 'user_id' => $this->user_id])
            ->order('id desc')
            ->select();
        
        return $this->fetch('',compact('info','token','shouyilist'));
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