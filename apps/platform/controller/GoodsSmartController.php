<?php
/**
 * 租赁挖矿管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;

class GoodsSmartController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'goodssmart');
        $this->model = model('GoodsWealth');
    }

    /**
     * 主框架
     */
    public function index()
    {
        $this -> seo();
        return view();
    }

    /**
     * 查询数据
     */
    public function ajaxData()
    {
        $page = input("page", 1);
        $perpage = input('limit', 20);
        $keys = input('keys', '');

        $map['type'] = 1;
        if (!empty($keys)) {
            $map['name'] = array('like', '%' . $keys . '%');
        }

        $result = $this->model->where($map)->order('sort asc, status asc, id desc')->page($page, $perpage)->select();
        $total = $this->model->where($map)->count();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['apr'] = "{$v['apr_0']}% - {$v['apr_12']}%";
                $result[$k]['zhouqi'] = $v['zhouqi'] . '天';
                $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $result[$k]['cover'] = $v['cover']
                    ? '<a href="javascript:;" lay-event="showcover"><img src="' . getFile($v['cover']) . '" width="30px%"></a>'
                    : '';
                $result[$k]['statusbar'] = $v['status'] == 1
                    ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">正常</button>'
                    : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">下线</button>';

                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息：' . $v['name'] . '\', \'' . url("publish", array('id' => $v['id'])) . '\',1400,700);"><i class="layui-icon">&#xe642;</i></button>';
//                $result[$k]['toolbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';
            }
            return layData('数据获取成功', $result, $total);
        } else {
            return layData('当前数据为空', array(), 0);
        }
    }

    /**
     * 添加/编辑
     */
    public function publish()
    {
        $id = input('id', 0);
        if ($this->request->isPost()) {
            $this->param['status'] = $this->param['status'] ? 1 : 0;
            $this->param['type'] = 1; //租赁挖矿
            $validate = new \think\Validate([
                ['name', 'require', '名称不能为空'],
                ['cover', 'require', '封面图不能为空'],
                ['apr_0', 'require', '请填写年化利率'],
                ['apr_3', 'require', '请填写年化利率'],
                ['apr_6', 'require', '请填写年化利率'],
                ['apr_9', 'require', '请填写年化利率'],
                ['apr_12', 'require', '请填写年化利率'],
                ['rengou_end', "after:rengou_begin", '认购结束期不能小于认购开始期'],
            ]);
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }
            // 添加|修改  插入数据库
            if (empty($id)) {
                if ($this->model->allowField(true)->save($this->param) == false) {
                    return ajaxError('添加失败');
                }
                $id = $this->model->id;
                $msg = '添加成功';
            } else { //修改
                $this->model->allowField(true)->save($this->param, ['id' => $id]);
                $msg = '修改成功';
            }
            addlog($id);
            return ajaxSuccess($msg);
        }

        if (!empty($id)) {
            $rows = $this->model->get($id);
        } else {
            $rows['status'] = 1;
            $rows['target'] = 0;
        }
        $this->seo();
        return view('', compact('rows' ));
    }

    /**
     * 更新状态
     */
    public function doStatus()
    {
        if ($this->request->isPost()) {
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
        } else {
            return ajaxError('请求失败');
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

            if ($this->model->where(array('id' => array('in', $ids)))->delete() == false) {
                return ajaxError('删除失败');
            } else {
                addlog($ids);
                return ajaxSuccess('数据删除成功');
            }
        }
    }
}