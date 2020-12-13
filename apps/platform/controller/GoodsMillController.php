<?php
/**
 * 实体矿机管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;

class GoodsMillController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'goodsmill');
        $this->model = model('GoodsMill');
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

        if (!empty($keys)) {
            $map['name'] = array('like', '%' . $keys . '%');
        }

        //$result = $this->model->where($map)->order('status asc, id desc')->page($page, $perpage)->select();
        $result = Db::name('goodsMill')->alias('gw')
                ->join('ocTypes o','gw.oc_type = o.id','LEFT')
                ->join('ipfsTypes i', 'gw.ipfs_type = i.id','LEFT')
                ->field('o.label as olabel, i.label as ilabel, gw.*')
                ->where($map)
                ->order('gw.id desc')
                ->page($page, $perpage)
                ->select();
        $total = $this->model->where($map)->count();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['price'] = showprice($v['price']) . 'USDT';
                $result[$k]['dianfei'] = showprice($v['dianfei']);
                $result[$k]['baoxianfei'] = showprice($v['baoxianfei']);
                $result[$k]['richanchu'] = showprice($v['richanchu']);
                $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $result[$k]['type'] = $v['type'] == 1 ? '现货' : '预售';
                $result[$k]['oc_type_label'] = $v['olabel'];
                $result[$k]['ipfs_type_label'] = $v['ilabel'];
                $result[$k]['cover'] = $v['cover']
                    ? '<a href="javascript:;" lay-event="showcover"><img src="' . getFile($v['cover']) . '"></a>'
                    : '';
                $result[$k]['statusbar'] = $v['status'] == 1
                    ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">上架中</button>'
                    : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">已下架</button>';

                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息：' . $v['name'] . '\', \'' . url("publish", array('id' => $v['id'])) . '\',1400,700);"><i class="layui-icon">&#xe642;</i></button>';
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
        $id = input('id', 0);
        if ($this->request->isPost()) {
            $this->param['status'] = $this->param['status'] ? 1 : 0;
            $dateNow = date('Y-m-d H:i:s');
   
            $validate = new \think\Validate([
                ['type', 'require', '请选择矿机分类'],
                ['oc_type', 'require', '请选择矿机分类'],
                ['name', 'require', '名称不能为空'],    
                ['location', 'require', '请输入矿机所在位置'],
                ['cover', 'require', '封面图不能为空'],
                ['cover_2', 'require', '详情页大图不能为空'],
                ['stock', 'require', '请输入库存数量'],
                ['price', 'require', '请输入价格'],
            ]);
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }
            if($this->param['type'] == 2) {
                $validate = new \think\Validate([
                    ['shangjia_time', "after:$dateNow", '预计上架日期不能小于当前日期'],
                    ['shangjia_time', "after:jieshu_time", '上架日期不能小于售卖结束日期'],   
                ]);
                if (!$validate->check($this->param)) {
                    return ajaxError($validate->getError());
                }
            }
            
            $this->param['price'] = toprice($this->param['price']); //格式化价格 | 分
            $this->param['dianfei'] = toprice($this->param['dianfei']); //格式化电费 | 分
            $this->param['baoxianfei'] = toprice($this->param['baoxianfei']); //格式化保险费 | 分
            $this->param['richanchu'] = toprice($this->param['richanchu']); //格式化日产出 | 分
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
            $rows['price']      = showprice($rows['price']);
            $rows['dianfei']    = showprice($rows['dianfei']);
            $rows['baoxianfei'] = showprice($rows['baoxianfei']);
            $rows['richanchu']  = showprice($rows['richanchu']);
        } else {
            $rows['status'] = 1;
        }
        $oc_types = Db::name('oc_types')
        ->order('uid asc')
        ->select();
        $ipfs_types = Db::name('ipfs_types')
        ->order('uid asc')
        ->select();
        $days = Db::name('days')
        ->where('type', 2)
        ->order('days asc')
        ->select();
        $this->seo();
        return view('', compact('rows', 'oc_types', 'ipfs_types', 'days' ));
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