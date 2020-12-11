<?php

namespace app\platform\controller;

class ApplyController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'apply');
        $this->model = model('Apply');

    }

    /**
     * 主框架
     */
    public function index()
    {
        $this->assign('keys', $this->param['keys']);
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
        $keys = input('keys', '');

        //------------ Search ---------------------------------------
        $map = [];
        if ($keys) {
            $map['company'] = array('like', '%' . $keys . '%');
        }

        $result = $this->model->where($map)->order('id desc')->page($page, $perpage)->select();
        $total = $this->model->where($map)->count();

        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['user'] = getUserField($v['user_id'], 'nickname');
                $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $result[$k]['address'] = getArea($v['province_id'], 'name') . getArea($v['city_id'], 'name') . getArea($v['area_id'], 'name') . $v['address'];
                $result[$k]['items'] = getField('items', $v['items_id'], 'title');
                $result[$k]['statusbar'] = $v['status'] == 1 ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">已处理</button>' : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">未处理</button>';
                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'查看信息：' . getUserField($v['user_id'], 'nickname') . '\', \'' . url("publish", array('id' => $v['id'])) . '\');"><i class="layui-icon">&#xe642;</i></button>';
                $result[$k]['toolbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';
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

        $id = input('id', 0, 'intval');

        if ($this->request->isPost()) {
            //验证
            $validate = new \think\Validate([
                ['items_id', 'require', '请选择栏目'],
                ['name', 'require', '商品名称不能为空'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }

            $this->param['edit_admin_id'] = $this->manager['id'];
            $this->param['update_time'] = time();
            $this->param['status'] = $this->param['status'] ? 1 : 0;
            $this->param['is_index'] = $this->param['is_index'] ? 1 : 0;
            $this->param['is_hot'] = $this->param['is_hot'] ? 1 : 0;
            $this->param['is_new'] = $this->param['is_new'] ? 1 : 0;
            $this->param['is_top'] = $this->param['is_top'] ? 1 : 0;
            $this->param['cover'] = $this->param['cover'] ? $this->param['cover'] : 0;

            if (!empty(input('items_id/d'))) {
                session('items_id', input('items_id/d'));
            }

            //图册
            if (!empty($this->param['album'])) {
                $albums = '0';
                foreach ($this->param['album'] as $k => $v) {
                    $albums .= ',' . $v;
                }
                $this->param['album'] = $albums;
            }

            if (empty($id)) {    //新增
                $this->param['admin_id'] = $this->manager['id'];
                $this->param['create_time'] = time();
                if ($this->model->allowField(true)->save($this->param) == false) {
                    return ajaxError('添加失败');
                } else {
                    $id = $this->model->id;
                    $msg = '添加成功';
                }
            } else {
                if ($this->model->allowField(true)->save($this->param, array('id' => $id)) == false) {
                    //return ajaxError('修改失败');
                    $msg = '修改成功';
                } else {
                    $msg = '修改成功';
                }
            }

            //日志记录
            addlog($id);

            //处理规格
            if (!empty($this->param['spec_id'])) {
                $data = [];
                foreach ($this->param['spec_id'] as $k => $v) {
                    if (!empty($v)) {
                        $arr = array();
                        $arr['markprice'] = isset($this->param['spec_markprice'][$k]) ? toprice($this->param['spec_markprice'][$k]) : 0;
                        $arr['price'] = isset($this->param['spec_price'][$k]) ? toprice($this->param['spec_price'][$k]) : 0;
                        $arr['weight'] = isset($this->param['spec_weight'][$k]) ? toprice($this->param['spec_weight'][$k]) : 0;
                        $arr['stock'] = isset($this->param['spec_stock'][$k]) ? (int)$this->param['spec_stock'][$k] : 999;
                        $arr['cover'] = isset($this->param['spec_cover'][$k]) ? (int)$this->param['spec_cover'][$k] : 0;
                        $arr['commission'] = isset($this->param['spec_commission'][$k]) ? toprice($this->param['spec_commission'][$k]) : 0;
                        $arr['id'] = $v;
                        $data[] = $arr;
                    }
                }
            }
            model('GoodsSpecs')->allowField(true)->saveAll($data);

            return ajaxSuccess($msg);
        }

        if (!empty($id)) {
            $rows = $this->model->get($id);
            $rows['album'] = model('Attachment')->getAlbum($rows['album']);
            $rows['specs'] = model('GoodsSpecs')->getSpecs($rows['tmp_id']);
        } else {
            $rows['items_id'] = session('items_id');
            $rows['status'] = 1;
            $rows['target'] = 0;
        }

        $this->assign('rows', $rows);

        $map = array();
        $map['status'] = 1;
        $map['mold'] = 'goods';
        $itemslist = $this->itemsModel->where($map)->order('sort desc, id asc')->select();
        $itemslist = $this->itemsModel->itemslist($itemslist);
        $this->assign('itemslist', $itemslist);

        $postagelist = model('Postage')->all(array('store_id' => $rows['store_id'], 'status' => 1));
        $this->assign('postagelist', $postagelist);

        $this->seo();
        return view();
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
