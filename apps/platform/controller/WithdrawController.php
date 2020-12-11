<?php
/**
 * 提现管理
 * Author: Orzly
 * Date: 2020-06-24
 */

namespace app\platform\controller;

use think\Model;
use think\Db;

class WithdrawController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'withdraw');
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
        if ($type) {
            $map['w.type'] = $type;
        }
        if (!empty($keys)) {
            $map['u.username|u.tel'] = array('like', '%' . $keys . '%');
        }
        $result = Db::name('withdraw')->alias('w')
            ->join('user u','u.id = w.user_id','LEFT')
            ->field('u.username, u.tel, w.*')
            ->where($map)
            ->order('create_time desc')
            ->page($page, $perpage)
            ->select();

        $total = Db::name('withdraw')->alias('w')
            ->join('user u','u.id = w.user_id','LEFT')
            ->where($map)
            ->count();

        if($result){
            foreach($result as $k=>$v){
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
                $result[$k]['rate_num'] = showprice($v['rate_num']);
                $result[$k]['pay_num'] = showprice($v['pay_num']);
                $result[$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);

                switch ($v['status']) {
                    case '-1':
                        $result[$k]['statusbar'] = '<span class="layui-badge layui-bg-gray">拒绝</span>';
                        break;
                    case '0':
                        $result[$k]['statusbar'] = '<span class="layui-badge layui-bg-blue">审核中</span>';
                        break;
                    case '1':
                        $result[$k]['statusbar'] = '<span class="layui-badge layui-bg-green">提现成功</span>';
                        break;
                    case '2':
                        $result[$k]['statusbar'] = '<span class="layui-badge layui-bg-red">提现失败</span>';
                        break;
                }
                $result[$k]['toolbar'] = '<p><a href="' . url('publish', ['id' => $v['id']]) . '" class="layui-btn layui-btn-xs layui-btn-normal">查看</a></p>';
            }
            return layData('数据获取成功', $result, $total);
        }else{
            return layData('当前数据为空', array(), 0);
        }
    }

    /**
     * 添加/编辑
     */
    public function publish(){
        $id = input('id', 0);

        if($this->request->isPost()){
            if($this->param['status'] == 1){
                $this->param['check_time'] = time();
            }else{
                $this->param['refuse_time'] = time();
            }
            Db::startTrans();
            try{
                $info = Db::name('withdraw')->where('id',$this->param['id'])->update($this->param);
                if($info){
                    $withdrawInfo = Db::name('withdraw')->where('id',$this->param['id'])->find();
                    switch($withdrawInfo['type']) {
                        case 1:
                            $moneyType = 'btc';
                            break;
                        case 2:
                            $moneyType = 'usdt';
                            break;
                        case 3:
                            $moneyType = 'eth';
                            break;
                        case 4:
                            $moneyType = 'ethu';
                            break;
                        case 5:
                            $moneyType = 'euf';
                            break;
                        case 6:
                            $moneyType = 'er';
                            break;
                    }
                    
                    if($this->param['status'] == 1){  //打款成功
                        $insertData = [
                            'type'          =>  2,
                            'money_type'    =>  $moneyType,
                            'user_id'       =>  $withdrawInfo['user_id'],
                            'mold'          =>  'out',
                            'money'         =>  $withdrawInfo['money'],
                            'create_time'   =>   time()
                        ];
                        Db::name('finance')->insert($insertData);
                    }else{
                        Db::name('user')->where('id',$withdrawInfo['user_id'])->setInc($moneyType, $withdrawInfo['money']);
                    }
                }
                Db::commit();
                return ajaxSuccess('操作成功');
            } catch (\Exception $e) {
                Db::rollback();
                return ajaxError('操作失败');
            }
        }

        if(!empty($id)){
            $rows = Db::name('withdraw')->alias('w')
                ->join('user u','u.id = w.user_id','LEFT')
                ->field('u.username, u.tel, u.id = user_id, u.btc, u.usdt, u.eth, u.er, u.ethu, u.euf, w.*')
                ->where(['w.id' => $id])
                ->find();
            
           switch($rows['type']) {
                case 1:
                    $rows['type_msg'] = 'BTC';
                    break;
                case 2:
                    $rows['type_msg'] = 'USDT';
                    break;
                case 3:
                    $rows['type_msg'] = 'ETH';
                    break;
                case 4:
                    $rows['type_msg'] = 'ETHU';
                    break;
                case 5:
                    $rows['type_msg'] = 'EUF';
                    break;
                case 6:
                    $rows['type_msg'] = 'ER';
                    break;
        }
            
            $rows['btc'] = showprice($rows['btc']);
            $rows['usdt'] = showprice($rows['usdt']);
            $rows['eth'] = showprice($rows['eth']);
            $rows['er'] = showprice($rows['er']);
            $rows['ethu'] = showprice($rows['ethu']);
            $rows['euf'] = showprice($rows['euf']);
            $rows['money'] = showprice($rows['money']);
            $rows['rate_num'] = showprice($rows['rate_num']);
            $rows['pay_num'] = showprice($rows['pay_num']);
        }
        $this->seo();
        return view('',compact('rows'));
    }


    /**
     * 更新状态
     */
    public function doStatus()
    {
        if ($this->request->isAjax()) {
            if (empty($this->param['id'])) {
                return ajaxError('参数丢失');
            } else {
                $status = $this->request->param('status', 0, 'intval');
                if ($status == 1) {
                    $status = 0;
                } else {
                    $status = 1;
                }
                if ($this->model->where('id', $this->param['id'])->setField('status', $status) == false) {
                    return ajaxError('更新失败');
                } else {
                    addlog($this->param['id']);
                    return ajaxSuccess('操作成功');
                }
            }
        }
    }

    /**
     * 更新数据
     */
    public function doUpdate()
    {
        if ($this->request->isAjax()) {
            if (!empty($this->param['field']) && !empty($this->param['id'])) {
                if ($this->model->where('id', $this->param['id'])->setField($this->param['field'], $this->param['value']) == false) {
                    return ajaxError('更新失败');
                } else {
                    addlog($this->param['id']);
                    return ajaxSuccess('数据更新成功');
                }
            } else {
                return ajaxError('数据丢失');
            }
        }
    }

    /**
     * 删除数据
     */
    public function doDel()
    {
        if ($this->request->isAjax()) {
            if (empty($this->param['id'])) {
                return ajaxError('参数丢失');
            } else {
                if ($this->model->where('id', $this->param['id'])->delete() == false) {
                    return ajaxError('删除失败');
                } else {
                    addlog($this->param['id']);
                    return ajaxSuccess('数据删除成功');
                }
            }
        }
    }

    /**
     * 批量删除
     */
    public function batch_del()
    {
        $data = $this->request->has('data') ? $this->param['data'] : '';
        if (empty($data)) {
            return ajaxError('未选择数据');
        } else {
            $ids = array();
            foreach ($data as $k => $v) {
                $ids[$k] = $v['id'];
            }

            if ($this->model->where(array('id' => array('in', $ids)))->setField('status',-2) == false) {
                return ajaxError('删除失败');
            } else {
                addlog($ids);
                return ajaxSuccess('数据删除成功');
            }
        }
    }
}
