<?php

namespace app\platform\controller;

use think\Controller;
use think\Db;
use think\Session;
use think\Model;
use app\common\model\Manager as managerModel;

class ManagerController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->assign('navbar', 'manager');
        $this->model = new managerModel();
    }

    /**
     * 主框架
     */
    public function index()
    {
        $this->assign('keys', $this->request->has('keys') ? $this->param['keys'] : '');

        $this->seo();
        return view();
    }

    /**
     * 查询数据
     */
    public function ajaxData()
    {
        $page = $this->request->has('page') ? $this->param['page'] : 1;
        $perpage = $this->request->has('limit') ? $this->param['limit'] : 20;

        $map = array();
        $map['group_id'] = 1;
        if ($this->request->has('keys')) {
            $map['account|name'] = array('like', '%' . $this->param['keys'] . '%');
        }

        $result = $this->model->where($map)->order('id desc')->page($page, $perpage)->select();
        $total = $this->model->where($map)->count();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $result[$k]['statusbar'] = $v['status'] == 1 ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">正常</button>' : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">禁用</button>';
                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息\', \'' . url("publish", array('id' => $v['id'])) . '\');"><i class="layui-icon">&#xe642;</i></button>';
                $result[$k]['toolbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';
            }
            return layData('数据获取成功', $result, $total);
        } else {
            return layData('当前数据为空', array(), 0);
        }
    }

    public function read()
    {
        $result = $this->model->where('id', 30)->find();
        echo $result['useravatar'];
        print_r($result);
    }

    /**
     * 添加/编辑
     */
    public function publish()
    {
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if ($this->request->isPost()) {
            //验证
            $validate = new \think\Validate([
                ['account', 'require', '账号不能为空']
            ]);
            //验证部分数据合法性
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }
            $this->param['group_id'] = 1;
            $this->param['status'] = $this->param['status'] ? 1 : 0;
            $this->param['update_time'] = time();

            $model_arr = input('model_arr/a');
            if (!empty($model_arr)) {
                $str = '';
                foreach ($model_arr as $k => $v) {
                    if (($k + 1) == count($model_arr)) {
                        $str .= $v;
                    } else {
                        $str .= $v . ',';
                    }
                }
                $this->param['model'] = $str;
            }
            $permissions_arr = input('permissions_arr/a');
            if (!empty($permissions_arr)) {
                $str = '';
                foreach ($permissions_arr as $k => $v) {
                    if (($k + 1) == count($permissions_arr)) {
                        $str .= $v;
                    } else {
                        $str .= $v . ',';
                    }
                }
                $this->param['permissions'] = $str;
            }


            if (empty($id)) {    //新增
                $result = $this->model->where('account', $this->param['account'])->field('id')->find();
                if (!empty($result)) {
                    return ajaxError('账号已注册');
                }

                $this->param['admin_id'] = Session::get('admins');                //设置创建人
                $this->param['edit_admin_id'] = $this->param['admin_id'];             //设置修改人
                $this->param['create_time'] = time();
                if (empty($this->param['password'])) {
                    return ajaxError('登录密码不能为空');
                } else {
                    $this->param['password'] = password($this->param['password']);
                }

                if ($this->model->allowField(true)->save($this->param) == false) {
                    return ajaxError('添加失败');
                } else {
                    addlog($this->model->id);
                    return ajaxSuccess('添加成功');
                }
            } else {
                if (!empty($this->param['password'])) {
                    $this->param['password'] = password($this->param['password']);
                } else {
                    $this->param['password'] = $this->param['oldpass'];
                }
                $post['edit_admin_id'] = Session::get('admins');                //设置修改人
                $post['update_time'] = time();
                if ($this->model->allowField(true)->save($this->param, array('id' => $id)) == false) {
                    return ajaxError('修改失败');
                } else {
                    addlog($id);
                    return ajaxSuccess('修改成功');
                }
            }
        } else {
            if (!empty($id)) {
                $rows = $this->model->get($id);
            } else {
                $rows['status'] = 1;
            }
            $this->assign('rows', $rows);

            $newsitems = model('Classify')->where(array('status' => 1))->order('sort desc,id asc')->select();
            $this->assign('newsitems', $newsitems);

            $this->seo();
            return view();
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
                if ($this->param['id'] == 10) {
                    return ajaxError('系统管理员无法删除');
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
    }
}
