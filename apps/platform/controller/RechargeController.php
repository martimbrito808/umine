<?php
/**
 * 充值记录
 * Author: Orzly
 * Date: 2020-06-29
 */

namespace app\platform\controller;

use think\Db;

class RechargeController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'recharge');
    }

    /**
     * 主框架
     */
    public function index()
    {
        $this->seo();
        return view();
    }

    /**
     * 查询数据
     */
    public function ajaxData(){
        $page = input("page", 1);
        $perpage = input('limit', 20);
        $keys = input('keys', '');
        $type = input('type', '');
        if (!empty($keys)) {
            $map['u.username|u.tel'] = array('like', '%' . $keys . '%');
        }
        if ($type) {
            $map['r.type'] = $type;
        }
        $result = Db::name('recharge')->alias('r')
            ->join('user u','u.id = r.user_id')
            ->field('u.username, u.tel, r.*')
            ->where($map)
            ->order('r.create_time desc')
            ->page($page, $perpage)
            ->select();

        $total = Db::name('recharge')->alias('r')
            ->join('user u','u.id = r.user_id')
            ->where($map)
            ->count();

        if(empty($result)){
            return layData('当前数据为空', array(), 0);
        }
        foreach($result as $k => $v){
            $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            switch($result[$k]['status']){
                case 0:
                    $result[$k]['checkstatus'] = '<button type="button" lay-event="checkpass" class="layui-btn layui-btn-xs layui-btn-radius">通过</button>';
                    $result[$k]['checkstatus'].= '<button type="button" lay-event="checkreject" class="layui-btn layui-btn-xs layui-btn-danger layui-btn-radius">驳回</button>';
                    break;
                case 1:
                    $result[$k]['checkstatus'] = '<span class="layui-badge layui-bg-green">已通过</span>';
                    break;
                case 2:
                    $result[$k]['checkstatus'] = '<span class="layui-badge layui-bg-orange">已驳回</span>';
                    break;
            }
            
            $result[$k]['voucher'] = $v['voucher']
                ? '<a href="javascript:;" lay-event="showvoucher"><img src="' . $v['voucher']. '"></a>'
                : '';
            switch($v['type']) {
                case 1:
                    $type_name = 'BTC';
                    break;
                case 2:
                    $type_name = 'USDT';
                    break;
                case 3:
                    $type_name = 'ETH';
                    break;
                case 4:
                    $type_name = 'ETHU';
                    break;
                case 5:
                    $type_name = 'EUF';
                    break;
                case 6:
                    $type_name = 'ER';
                    break;
            }
            $result[$k]['type']  = "&nbsp;<span class='layui-badge layui-bg-green'>{$type_name}</span>";
            $result[$k]['money'] = showprice($v['money']). $result[$k]['type'];
        }
        return layData('数据获取成功', $result, $total);
    }

    /**
     * 充值审核
     * @return \think\response\Json
     */
    public function docheck(){

        if($this->request->isAjax()) {
            $check = $this->param['check'];
            $recharge_id = $this->param['id'];

            if(empty($recharge_id)){
                return ajaxError('参数丢失');
            }
            $rechargeInfo = Db::name('recharge')->where(['id' => $recharge_id])->find();
            if(empty($rechargeInfo)) {
                return ajaxError('非法请求');
            };
            // 启动事务
            Db::startTrans();
            try{
                Db::name('recharge')
                    ->where('id', $this->param['id'])
                    ->update([
                        "status" => $check,
                        'check_time' => time()
                    ]);
                if($check == 1) {
                    switch($rechargeInfo['type']) {
                        case 1:
                            $updateColumn = 'btc';
                            break;
                        case 2:
                            $updateColumn = 'usdt';
                            break;
                        case 3:
                            $updateColumn = 'eth';
                            break;
                        case 4:
                            $updateColumn = 'ethu';
                            break;
                        case 5:
                            $updateColumn = 'euf';
                            break;
                        case 6:
                            $updateColumn = 'er';
                            break;
                    }
                    $updateMoney = $rechargeInfo['money'];
                    
                    Db::name('user')
                        ->where(['id' => $rechargeInfo['user_id']])
                        ->setInc($updateColumn, $updateMoney);

                    Db::name('finance')
                        ->insert([
                            'user_id'       => $rechargeInfo['user_id'],
                            'money'         => $rechargeInfo['money'],
                            'mold'          => 'in',
                            'money_type'    => $updateColumn,
                            'type'          => 1,
                            'create_time'   => time()
                        ]);
                }
                Db::commit();
                addlog($this->param['id']);
                return ajaxSuccess('操作成功');
            } catch (\Exception $e) {
                Db::rollback();
                return ajaxError('更新失败');
            }
        }
    }
}
