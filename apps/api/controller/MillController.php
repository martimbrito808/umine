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
            ->where(['status' => 1,'stock' => ['gt','0']])
            ->order('sort asc, id desc')
            ->select();
        $jieshu_times = [];
        $today = strtotime(date('Y-m-d H:i:s'));
        $days = Db::name('days')
                ->order('days asc')
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
            $jieshu_times[] = ['id' => $mill['id'], 'time' => strtotime($mill['jieshu_time']) - $today];

        }
        $mill_disabled_list = Db::name('goods_mill')->alias('gm')
            ->join('ocTypes o','gm.oc_type = o.id','LEFT')
            ->field('o.label as olabel, gm.*')
            ->where(['status' => 1, 'stock' => 0])
            ->order('sort asc, id desc')
            ->select();
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
        $info = Db::name('goods_mill')->where('id',$id)->find();
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
            $efees[$key]['amount'] = getMillEfee($info, $efee['days'],$efee['rebate']);
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
        //calc efee
        $total_efee = getMillEfee($millInfo, 30);
        $days = 30;
        $efee_data = [
            [
                'amount' => $total_efee,
                'days' => 30,
                'type' => 1,
                'paid_at' => date('Y-m-d H:i:s')
            ]
        ];
        $efee_finance_data = [
            [
                'type' => 16,
                'money_type'    => 'usdt',
                'mold'          => 'out',
                'user_id'       => $this->user_id,
                'money'         => $total_efee,
                'create_time'   => time(),
            ]
        ];
        if($param['efee_id'] != 0)
        {
            $selected_efee = Db::name('efee_rebate')->find($param['efee_id']);
            $selected_efee_amount = getMillEfee($millInfo, $selected_efee['days'],$selected_efee['rebate']);
            $efee_data[] = 
                [
                    'amount' => $selected_efee_amount,
                    'days' => $selected_efee['days'],
                    'type' => 2,
                    'paid_at' => date('Y-m-d H:i:s')
                ];
            $efee_finance_data[] = [
                'type' => 16,
                'money_type'    => 'usdt',
                'mold'          => 'out',
                'user_id'       => $this->user_id,
                'money'         => $selected_efee_amount,
                'create_time'   => time(),
            ];
            $total_efee += $selected_efee_amount;
            $days += $efee['days'];
        }
        
        $efee_limit = date('Y-m-d', strtotime('+'.$days.' day'));

        $userInfo = Db::name('user')->where(['id' => $this->user_id])->find();
        $totalMoney = $millInfo['price'] * $param['num'] + $total_efee;
        if($userInfo['usdt'] < ($totalMoney + $total_efee)) {
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
                ->insertGetId([
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
                    'efee'          => $total_efee,
                ]);
            //log->efee
            foreach($efee_data as $key => $efee)
            {
                $efee_data[$key]['order_id'] = $res;
            }
            Db::name('mill_order_efees')->insertAll($efee_data);
            //log->finance
            $efee_finance_data[] = [
                'type'          => 6,
                'money_type'    => 'usdt',
                'mold'          => 'out',
                'user_id'       => $this->user_id,
                'money'         => $totalMoney,
                'create_time'   => time(),
            ];
            Db::name('finance')->insertAll($efee_finance_data);
                
            // 上级返佣
            $commMoney_1 = $totalMoney * (getconfig('commission_1') / 100);
            $commMoney_2 = $totalMoney * (getconfig('commission_2') / 100);
            $commMoney_3 = $totalMoney * (getconfig('commission_3') / 100);
            if(!empty($userInfo['parent_1'])) {
                Db::name('user')->where(['id' => $userInfo['parent_1']])->setInc('usdt', $commMoney_1); 
                Db::name('finance')->insert([
                    'type'        => 3,
                    'money_type'  => 'usdt',
                    'mold'        => 'in',
                    'user_id'     => $userInfo['parent_1'],
                    'money'       => $commMoney_1,
                    'create_time' => time(),
                ]);
                
                Db::name('returning_servant')->insert([
                    'u_id' => $this->user_id,
                    'return_u_id' => $userInfo['parent_1'],
                    'money' => $commMoney_1,
                    'created_time' => time()
                ]);
            }
            if(!empty($userInfo['parent_2'])) {
                Db::name('user')->where(['id' => $userInfo['parent_2']])->setInc('usdt', $commMoney_2); 
                Db::name('finance')->insert([
                    'type'        => 3,
                    'money_type'  => 'usdt',
                    'mold'        => 'in',
                    'user_id'     => $userInfo['parent_2'],
                    'money'       => $commMoney_2,
                    'create_time' => time(),
                ]);
                Db::name('returning_servant')->insert([
                    'u_id' => $this->user_id,
                    'return_u_id' => $userInfo['parent_2'],
                    'money' => $commMoney_2,
                    'created_time' => time()
                ]);
            }
            if(!empty($userInfo['parent_3'])) {
                Db::name('user')->where(['id' => $userInfo['parent_3']])->setInc('usdt', $commMoney_3); 
                Db::name('finance')->insert([
                    'type'        => 3,
                    'money_type'  => 'usdt',
                    'mold'        => 'in',
                    'user_id'     => $userInfo['parent_3'],
                    'money'       => $commMoney_3,
                    'create_time' => time(),
                ]);
                Db::name('returning_servant')->insert([
                    'u_id' => $this->user_id,
                    'return_u_id' => $userInfo['parent_3'],
                    'money' => $commMoney_3,
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