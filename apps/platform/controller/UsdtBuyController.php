<?php
/**
 * USDT充值
 * Author: Orzly
 * Date: 2020-06-29
 */
namespace app\platform\controller;

use think\Db;

class UsdtBuyController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'usdtbuy');
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
        if (!empty($keys)) {
            $map['ro.order_sn|u.tel'] = array('like', '%' . $keys . '%');
        }
  
        $map['ro.type'] = 1;
        $map['ro.status'] = ['in', '2, 3, 5'];
        $result = Db::name('transaction_order')->alias('ro')
            ->join('user u','u.id = ro.u_id','LEFT')
            ->field('u.username, u.tel,u.bindname, u.bankname, u.banknum, ro.*')
            ->where($map)
            ->order('ro.create_time desc')
            ->page($page, $perpage)
            ->select();

        $total = Db::name('transaction_order')->alias('ro')
            ->join('user u','u.id = ro.u_id','LEFT')
            ->where($map)
            ->count();

        if(empty($result)){
            return layData('当前数据为空', array(), 0);
        }
        foreach($result as $k => $v){
            // $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $result[$k]['pay_time'] = $v['pay_time']>0?date('Y-m-d H:i:s', $v['pay_time']):'';
           
            switch($result[$k]['status']){
                case 1:
                    $result[$k]['checkstatus'] = '<span class="layui-badge layui-bg-danger">未支付</span>';
                    break;
                case 2:
                
                    $result[$k]['checkstatus'].= '<button type="button" lay-event="checkpass" class="layui-btn layui-btn-xs layui-btn-radius">通过</button>';
                    $result[$k]['checkstatus'].= '<button type="button" lay-event="checkreject" class="layui-btn layui-btn-xs layui-btn-danger layui-btn-radius">驳回</button>';
                    break;
                case 3:
                    $result[$k]['checkstatus'] = '<span class="layui-badge layui-bg-green">已通过</span>';
                    break;
                case 5:
                    $result[$k]['checkstatus'] = '<span class="layui-badge layui-bg-orange">已驳回</span>';
                    break;
            }
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
            $order_id = $this->param['id'];

            if(empty($order_id)){
                return ajaxError('参数丢失');
            }
            $info = Db::name('transaction_order')->where(['id' => $order_id])->find();
            if(empty($info)) {
                return ajaxError('非法请求');
            };
            
            if($check == 1) {
                $newcheck = 3;
            }else{
                $newcheck = 5;
            }
            
            // 启动事务
            Db::startTrans();
            try{
                Db::name('transaction_order')
                    ->where('id', $order_id)
                    ->update([
                        "status" => $newcheck,
                        'check_time' => time()
                    ]);
                if($check == 1) {
                    //增加USDT
                    Db::name('user')  
                        ->where(['id' => $info['u_id']])
                        ->setInc('usdt', toprice($info['num']));

                    Db::name('finance')
                        ->insert([
                            'user_id'       => $info['u_id'],
                            'money'         => toprice($info['num']),
                            'mold'          => 'in',
                            'money_type'    => 'usdt',
                            'type'          => 9,
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