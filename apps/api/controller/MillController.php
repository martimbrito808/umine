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
            
        $mill_disabled_list = Db::name('goods_mill')->alias('gm')
            ->join('ocTypes o','gm.oc_type = o.id','LEFT')
            ->field('o.label as olabel, gm.*')
            ->where(['status' => 1, 'stock' => 0])
            ->order('sort asc, id desc')
            ->select();
            
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
        
        return $this->fetch('', compact('auth', 'mill_list','mill_disabled_list','wealth_list', 'oc_types', 'ipfs_types'));
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
            {
                $info['buy'] = false;
            }
        }else{
            $info['buy'] = false;
        }
        return $this->fetch('',compact('info', 'auth','selfUsdt'));
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
        
        $userInfo = Db::name('user')->where(['id' => $this->user_id])->find();
        $totalMoney = $millInfo['price'] * $param['num'];
        if($userInfo['usdt'] < $totalMoney) {
             sendRequest(201, '余额不足，请先充值');
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
                ->dec('usdt', $totalMoney)
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
            Db::name('goods_mill_order')
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
                ]);
                
            Db::name('finance')
                ->insert([
                    'type'          => 6,
                    'money_type'    => 'usdt',
                    'mold'          => 'out',
                    'user_id'       => $this->user_id,
                    'money'         => $totalMoney,
                    'create_time'   => time(),
                ]);
                
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