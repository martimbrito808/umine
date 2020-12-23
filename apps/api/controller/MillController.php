<?php
namespace app\api\controller;

use think\Db;

class MillController extends BaseController
{

    function __construct () {
        parent::__construct ();
    }

    /**
     * 矿机
     */
    public function mill()
    {
        $param = input('param.');
        $auth['token'] = $param['token'];
        //实体矿机列表
        $mill_list = Db::name('goodsMill')->alias('gm')
            ->join('ocTypes o','gm.oc_type = o.id','LEFT')
            ->field('o.label as olabel, gm.*')
            ->where(['status' => 1,'stock' => ['gt','0'],'jieshu_time' => ['>=', date('Y-m-d')]])
            ->order('sort asc, id desc')
            ->select();
        $mill_list_outimed = [];
        $nowTime = date('H:i:s');
        foreach($mill_list as $key => $mill)
        {

            if( ( $nowTime < $mill['rengou_begin'] || $nowTime > $mill['rengou_end'] ) && ( $nowTime < $mill['rengou_begin_day'] || $nowTime > $mill['rengou_end_day']) )
            {
                $mill_list_outimed[] = $mill;
                unset($mill_list[$key]);
            }
        }
        $jieshu_times = [];
        $today = strtotime(date('Y-m-d H:i:s'));
        $days = Db::name('days')
                ->order('order asc')
                ->select();
        $days_sorted = [];
        foreach($days as $day)
        {
            $days_sorted[$day['days']] = $day['label'];
        }
        foreach($mill_list as $key => $mill) 
        {
            $mill_list[$key]['rengou_begin'] = date('H:i',strtotime($mill['rengou_begin']));
            $mill_list[$key]['rengou_begin_day'] = date('H:i',strtotime($mill['rengou_begin_day']));
            $mill_list[$key]['zhouqi'] = $days_sorted[$mill['zhouqi']];
            $jieshu_times[] = ['id' => $mill['id'], 'time' => strtotime($mill['jieshu_time'].' 23:59:59') - $today];

        }
        $mill_list_outdated = Db::name('goodsMill')->alias('gm')
            ->join('ocTypes o','gm.oc_type = o.id','LEFT')
            ->field('o.label as olabel, gm.*')
            ->where(['status' => 1,'stock' => ['gt','0'],'jieshu_time' => ['<', date('Y-m-d')]])
            ->order('sort asc, id desc')
            ->select();
        $mill_disabled_list = Db::name('goods_mill')->alias('gm')
            ->join('ocTypes o','gm.oc_type = o.id','LEFT')
            ->field('o.label as olabel, gm.*')
            ->where(['status' => 1, 'stock' => 0,])
            ->order('sort asc, id desc')
            ->select();
        $mill_disabled_list = array_merge($mill_disabled_list,$mill_list_outdated);
        $mill_disabled_list = array_merge($mill_disabled_list,$mill_list_outimed);
        foreach($mill_disabled_list as $key => $mill) 
        {
            $mill_disabled_list[$key]['zhouqi'] = $days_sorted[$mill['zhouqi']];
        }
        //租赁矿机
        $wealth_list = Db::name('goods_wealth')->alias('gm')
        ->join('ocTypes o','gm.oc_type = o.id','LEFT')
        ->field('o.label as olabel, gm.*')
        ->where(['type' => 1,'status' => 1])
        ->order('sort asc, id desc')
        ->select();
        foreach($wealth_list as $key => $wealth)
        {
            $zhouqi = ['1' => 'apr_0','30'=>'apr_3','60'=>'apr_6','90'=>'apr_9','120'=>'apr_12'];
            $wealth_list[$key]['apr'] = $wealth[$zhouqi[$wealth['zhouqi']]];
        }
        $oc_types = Db::name('oc_types')
        ->order('uid asc')
        ->select();
        
        $ipfs_types = Db::name('ipfs_types')
        ->order('uid asc')
        ->select();

        return $this->fetch('', compact('auth', 'mill_list','mill_disabled_list','wealth_list', 'oc_types', 'ipfs_types', 'jieshu_times'));
    }
    
    
    /**
     * 矿机详情
     */
    public function income_introduced()
    {
        $id = input('param.id','');
        $auth['token'] = input('param.token');
        
        $selfUsdt = Db::name('user')->where('id',$this->user_id)->value('usdt');
        $info = Db::name('goods_mill')->alias('gm')
                ->join('ocTypes o','gm.oc_type = o.id','LEFT')
                ->field('o.label as olabel, gm.*')
                ->where('gm.id',$id)->find();
        $days = Db::name('days')
        ->order('order asc')
        ->select();
        $days_sorted = [];
        foreach($days as $day)
        {
            $days_sorted[$day['days']] = $day['label'];
        }
        $info['zhouqi_label'] = $days_sorted[$info['zhouqi']];
        $info['category_label'] = '';
        switch($info['category'])
        {
            case 1:
                $info['category_label'] = '实体矿机';
                break;
            case 2:
                $info['category_label'] = '云算力';
                break;
            case 3:
                $info['category_label'] = '租赁矿机';
                break;
        }
        $info['category_tab_label'] = '';
        switch($info['category'])
        {
            case 1:
                $info['category_tab_label'] = '矿机介绍';
                break;
            case 2:
                $info['category_tab_label'] = '运算力介绍';
                break;
            case 3:
                $info['category_tab_label'] = '租赁介绍';
                break;
        }
        $info['buy'] = false;
        $info['start_day'] = date('Y年m月d日起',strtotime('+'.($info['zhouqi'] + 1).' day'));
        if( $info['stock'] > 0) {
            if( ($info['type'] == 2 && $info['jieshu_time'] > date('Y-m-d H:i:s'))) {
                $info['buy'] = true;
            }elseif($info['type'] == 1) {
                $info['buy'] = true;
            }
            $timeNow = date('H:i:s');
            if($info['rengou_begin'] < $timeNow && $timeNow <= $info['rengou_end'])
            {
                $info['buy'] = true;
            }
            else
            if($info['rengou_begin_day'] < $timeNow && $timeNow <= $info['rengou_end_day'])
            {
                $info['buy'] = true;
            }
            else
            {
                $info['buy'] = false;
            }
        }else{
            $info['buy'] = false;
        }
        $info['rengou_begin'] = date('H : i', strtotime($info['rengou_begin']));
        $info['rengou_end'] = date('H : i', strtotime($info['rengou_end']));
        $info['rengou_begin_day'] = date('H : i', strtotime($info['rengou_begin_day']));
        $info['rengou_end_day'] = date('H : i', strtotime($info['rengou_end_day']));
        //calc efee
        $efees = Db::name('efee_rebate')->select();
        foreach($efees as $key => $efee)
        {
            //*x_2*(gonghaobi/1000)*dianfei $info['x_2']*
            $efees[$key]['amount'] = showprice(getMillEfee($this->user_id, $info, $efee['days'], $efee['rebate']), 2);
            $efees[$key]['rebate_label'] = $efee['rebate'] * 0.1;
        }
        return $this->fetch('',compact('info', 'auth','selfUsdt','efees'));
    }
    
    /**
     * 购买矿机
     */
    public function buy_mill() {

        $param = input('param.');
        $validate = new \think\Validate([
            ['mill_id', 'require', '查询不到此矿机，请稍后再试'],
            ['num', 'require|number', '请选择购买数量'],
        ]);
        if (!$validate->check($param)) {
             sendRequest(201, $validate->getError());
        }
        $millInfo = Db::name('goods_mill')->where(['id' => $param['mill_id'],'status' => 1])->find();
        if(empty($millInfo)) {
            sendRequest(201, '查询不到此矿机，请稍后再试');
        }
        
        if($millInfo['type'] == 2 && $millInfo['jieshu_time'] < date('Y-m-d')) {
            sendRequest(201, '此矿机不能购买，请稍后再试');
        }
        if($millInfo['stock'] - $param['num'] < 0 ) {
            sendRequest(201, '购买失败，库存不足');
        }
        //upfront efee
        $efee = Db::name('efee_rebate')->find($param['efee_id']);
        $efee_amount = getMillEfee($this->user_id, $millInfo, $efee['days'], $efee['rebate'], $param['num']);
        $efee_limit = date('Y-m-d', strtotime('+'.$efee['days'].' day'));
        if($millInfo['dianfei'] <= 0)
        {
            $efee_limit = date('Y-m-d', strtotime('+3000 day'));
        }

        $userInfo = Db::name('user')->where(['id' => $this->user_id])->find();
        $totalMoney = $millInfo['price'] * $param['num'] + $efee_amount;
        if($userInfo['usdt'] < ($totalMoney)) {
             sendRequest(201, '余额不足，请先充值');
        }
        
        $method = 1;
        if($millInfo['category'] == 3)
        {
            $method = 2;
        }
        // 1.减少用户余额
        // 2.减少矿机表库存
        // 3.写入到用户矿机表
        // 4.生成订单
        // 5.生成财务记录
        Db::startTrans();
        try{
            Db::name('user')
                ->where(['id' => $this->user_id])
                ->dec('usdt', $totalMoney + $total_efee)
                ->inc('suanli', $millInfo['suanli']*$param['num'])
                ->update();
            
            Db::name('goods_mill')
                ->where(['id' => $param['mill_id']])
                ->setDec('stock', $param['num']);
                
            $check = Db::name('user_mill')->where(['user_id' => $this->user_id, 'mill_id' => $param['mill_id']])->value('id');
            if($check) {
                Db::name('user_mill')
                    ->where(['id' => $check])
                    ->setInc('mill_num',$param['num']);
            }else{
                Db::name('user_mill')
                    ->insert([
                        'user_id'  => $this->user_id,
                        'mill_id'  => $param['mill_id'],
                        'mill_num' => $param['num'],
                    ]);
            };
            $res = Db::name('goods_mill_order')
                ->insert([
                    'goods_mill_id' => $param['mill_id'],
                    'user_id'       => $this->user_id,
                    'orderno'       => date('YmdHis').randNum(),
                    'num'           => $param['num'],
                    'price'         => $millInfo['price'],
                    'zhouqi'        => $millInfo['zhouqi'],
                    'status'        => 1,
                    'order_price'   => $totalMoney,
                    'create_time'   => time(),
                    'buy_time'      => date('Y-m-d H:i:s'),
                    'method'        => $method,
                    'efee_limit'    => $efee_limit,
                ]);
            Db::name('finance')->insert([
                'type'          => 6,
                'money_type'    => 'usdt',
                'mold'          => 'out',
                'user_id'       => $this->user_id,
                'money'         => $totalMoney,
                'create_time'   => time(),
            ]);
                
            // 上级返佣
            $commMoney_1 = ($totalMoney - $efee_amount) * ($millInfo['rp1'] / 100);
            $commMoney_2 = ($totalMoney - $efee_amount) * ($millInfo['rp2'] / 100);
            $commMoney_3 = ($totalMoney - $efee_amount) * ($millInfo['rp3'] / 100);
            if(!empty($userInfo['parent_1'])) {
                $rebate = $commMoney_1 * getRebateMultiple($userInfo['parent_1']);
                Db::name('user')->where(['id' => $userInfo['parent_1']])->setInc('usdt', $rebate); 
                Db::name('finance')->insert([
                    'type'        => 3,
                    'money_type'  => 'usdt',
                    'mold'        => 'in',
                    'user_id'     => $userInfo['parent_1'],
                    'money'       => $rebate,
                    'create_time' => time(),
                ]);
                
                Db::name('returning_servant')->insert([
                    'u_id' => $this->user_id,
                    'return_u_id' => $userInfo['parent_1'],
                    'money' => $rebate,
                    'created_time' => time()
                ]);
            }
            if(!empty($userInfo['parent_2'])) {
                $rebate = $commMoney_2 * getRebateMultiple($userInfo['parent_2']);
                Db::name('user')->where(['id' => $userInfo['parent_2']])->setInc('usdt', $rebate); 
                Db::name('finance')->insert([
                    'type'        => 3,
                    'money_type'  => 'usdt',
                    'mold'        => 'in',
                    'user_id'     => $userInfo['parent_2'],
                    'money'       => $rebate,
                    'create_time' => time(),
                ]);
                Db::name('returning_servant')->insert([
                    'u_id' => $this->user_id,
                    'return_u_id' => $userInfo['parent_2'],
                    'money' => $rebate,
                    'created_time' => time()
                ]);
            }
            if(!empty($userInfo['parent_3'])) {
                $rebate = $commMoney_3 * getRebateMultiple($userInfo['parent_3']);
                Db::name('user')->where(['id' => $userInfo['parent_3']])->setInc('usdt', $rebate); 
                Db::name('finance')->insert([
                    'type'        => 3,
                    'money_type'  => 'usdt',
                    'mold'        => 'in',
                    'user_id'     => $userInfo['parent_3'],
                    'money'       => $rebate,
                    'create_time' => time(),
                ]);
                Db::name('returning_servant')->insert([
                    'u_id' => $this->user_id,
                    'return_u_id' => $userInfo['parent_3'],
                    'money' => $rebate,
                    'created_time' => time()
                ]);
            }
            // 提交事务
            Db::commit();   
            sendRequest(200, '购买成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
                sendRequest(201, '购买失败，请稍后再试');
        }
    }
}