<?php
/**
 * 会员管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;
use think\Model;

class UserController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'user');
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
    public function ajaxData()
    {
        $page = input('page', 1);
        $perpage = input('limit', 20);

        if ($this->request->has('keys')) {
            $map['tel'] = array('like', '%' . $this->param['keys'] . '%');
        }

        $result = Db::name('user')->where($map)->order('id desc')->page($page, $perpage)->select();
        $total = Db::name('user')->where($map)->count();

        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['statusbar'] = $v['status'] == 1
                    ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">正常</button>'
                    : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">封号</button>';
                $result[$k]['email'] = $v['email']?: '暂未绑定';
                $result[$k]['btc'] = showprice($v['btc']) . '&nbsp <button class="layui-btn layui-btn-xs" onclick="showDiyWin(\'资金明细\',\'' . url('finance', ['id' => $v['id'],'type' => 'btc']) . '\',1000,800)"> <i class="layui-icon layui-icon-rmb">&nbsp;明细</i></button>';
                $result[$k]['usdt'] = showprice($v['usdt']) . '&nbsp <button class="layui-btn layui-btn-xs" onclick="showDiyWin(\'资金明细\',\'' . url('finance', ['id' => $v['id'],'type' => 'usdt']) . '\',1000,800)"> <i class="layui-icon layui-icon-rmb">&nbsp;明细</i></button>';
                $result[$k]['eth'] = showprice($v['eth']) . '&nbsp <button class="layui-btn layui-btn-xs" onclick="showDiyWin(\'资金明细\',\'' . url('finance', ['id' => $v['id'],'type' => 'eth']) . '\',1000,800)"> <i class="layui-icon layui-icon-rmb">&nbsp;明细</i></button>';
                $result[$k]['er'] = showprice($v['er']). '&nbsp <button class="layui-btn layui-btn-xs" onclick="showDiyWin(\'资金明细\',\'' . url('finance', ['id' => $v['id'],'type' => 'er']) . '\',1000,800)"> <i class="layui-icon layui-icon-rmb">&nbsp;明细</i></button>';
                $result[$k]['ethu'] = showprice($v['ethu']). '&nbsp <button class="layui-btn layui-btn-xs" onclick="showDiyWin(\'资金明细\',\'' . url('finance', ['id' => $v['id'],'type' => 'ethu']) . '\',1000,800)"> <i class="layui-icon layui-icon-rmb">&nbsp;明细</i></button>';
                $result[$k]['euf'] = showprice($v['euf']). '&nbsp <button class="layui-btn layui-btn-xs" onclick="showDiyWin(\'资金明细\',\'' . url('finance', ['id' => $v['id'],'type' => 'euf']) . '\',1000,800)"> <i class="layui-icon layui-icon-rmb">&nbsp;明细</i></button>';

                $result[$k]['create_time'] = date('Ym/d H:i', $v['create_time']);
                $result[$k]['update_time'] = isset($v['update_time']) ? date('Y-m-d H:i:s', $v['update_time']) : '暂未修改';
                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息\', \'' . url("publish", array('id' => $v['id'])) . '\',1000,800);"><i class="layui-icon">&#xe642;</i></button>';
                $result[$k]['toolbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';
            }
            return layData('数据获取成功', $result, $total);
        } else {
            return layData('当前数据为空', array(), 0);
        }
    }
    /**
     * 会员推荐结构图
     */
    public function relation()
    {
        $usersRoot  = Db::name('user')
        ->where(['parent_1' => 0])
        ->order('id desc')
        ->select();
        $data = [];
        foreach($usersRoot as $root)
        {
            $data[] = $this->retriveRealtionStack($root['id']);
        }
        print_r($data);exit;
        return view();
    }
    private function retriveRealtionStack($user_id)
    {
        $user = Db::name('user')
        ->find($user_id);
        $isEndUser = count(Db::name('user')
        ->whereOr(['parent_1' => $user_id])
        ->order('id desc')
        ->select()) == 0;
        if(!$isEndUser)
        {
            $childs = Db::name('user')
            ->whereOr(['parent_1' => $user_id])
            ->order('id desc')
            ->select();
            $children = [];
            foreach($childs as $child)
            {
                $children[] = ['title' => $user['tel'],'children' => $this->retriveRealtionStack($child['id'])];
            }
            return ['title' => $user['tel'],'children' => $children];
        }
        else
        {
            return ['title' => $user['tel']];
        }
    }
    /**
     * 添加/编辑
     */
    public function publish()
    {
        $id = input('id', 0);

        if ($this->request->isPost()) {
            $validate = new \think\Validate([
                ['tel', 'require', '账号不能为空'],
            ]);
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }

            if (!empty($this->param['password'])) {
                $pwd = userEncrypt($this->param['password']);
                $arr['password'] = $pwd['password'];
                $arr['encrypt'] = $pwd['encrypt'];
            }
            $arr['update_time'] = time();
            $arr['status'] = $this->param['status'];
            Db::name('user')->where('id',$id)->update($arr);
            return ajaxSuccess('修改成功');
        }

        if (!empty($id)) {
            $rows = Db::name('user')->where('id',$id)->find();
            $rows['btc'] = showprice($rows['btc']);
            $rows['usdt'] = showprice($rows['usdt']);
            $rows['eth'] = showprice($rows['eth']);
            $rows['fil'] = showprice($rows['fil']);
            $rows['ethu'] = showprice($rows['ethu']);
            $rows['euf'] = showprice($rows['euf']);
            $rows['er'] = showprice($rows['er']);
            $this->assign('rows', $rows);
        }
        $this->seo();
        return view();
    }


    /**
     * 资金管理 页面
     * @return \think\response\View
     */
    public function finance()
    {
        $user_id = input('id');
        $type = input('type');
        return view('',compact('user_id','type'));
    }
    /**
     * 资金管理 数据
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function financeList()
    {
        $user_id = input('user_id');
        $type = input('type');
        $page = input('page', 1);
        $perpage = input('limit', 20);
        if (empty($user_id)) {
            exit();
        }
        $map['user_id'] = $user_id;
        $map['money_type'] = $type;

        $result = Db::name('finance')
            ->where($map)
            ->order('id desc')
            ->page($page, $perpage)
            ->select();
        $total = Db::name('finance')
            ->where($map)
            ->count();

        if ($result) {
            foreach ($result as $k => $v) {
                switch ($v['type']) {
                    case 1:
                        $status = '<span class="layui-badge layui-bg-blue">充值</span>';
                        break;
                    case 2:
                        $status = '<span class="layui-badge layui-bg-orange">提现</span>';
                        break;
                    case 3:
                        $status = '<span class="layui-badge layui-bg-gray">返佣</span>';
                        break;
                    case 4:
                        $status = '<span class="layui-badge layui-bg-gray">挖矿</span>';
                        break;
                    case 5:
                        $status = '<span class="layui-badge layui-bg-gray">兑换</span>';
                        break;
                    case 6:
                        $status = '<span class="layui-badge layui-bg-gray">购买矿机</span>';
                        break;
                    case 7:
                        $status = '<span class="layui-badge layui-bg-gray">
						</span>';
                        break;
                    case 8:
                        $status = '<span class="layui-badge layui-bg-gray">财富</span>';
                        break;
                }
                $result[$k]['statusbar'] = $status;
                if($v['type'] == 2) {
                    $result[$k]['money'] = '-' . showprice($v['money']);
                }else{
                    $result[$k]['money'] = showprice($v['money']);
                }

                $result[$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);
            }
            return layData('数据获取成功', $result, $total);
        } else {
            return layData('当前数据为空', [], 0);
        }

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
                if (Db::name('user')->where('id', $this->param['id'])->setField(['status' => $status, 'update_time' => time()]) == false) {
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
                if (Db::name('user')->where('id', $this->param['id'])->setField($this->param['field'], $this->param['value']) == false) {
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
                if (Db::name('user')->where('id', $this->param['id'])->setField(['status' => 0]) == false) {
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

            if (Db::name('user')->where(array('id' => array('in', $ids)))->delete() == false) {
                return ajaxError('删除失败');
            } else {
                addlog($ids);
                return ajaxSuccess('数据删除成功');
            }
        }
    }
}
