<?php
/**
 * 活动券管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

class CouponController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'coupon');
        $this->model = model('coupon');
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
        $keys = input('keys', '');

        $map = [];
        if ($this->request->has('keys')) {
            $map['title'] = ['like', '%' . $keys . '%'];
        }
        $result = $this->model->where($map)->order('id desc')->page($page, $perpage)->select();
        $total = $this->model->where($map)->count();
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['price'] = showprice($v['price']);
                if ($v['etime'] < time()) {
                    $status = '&nbsp;&nbsp;<span class="layui-badge layui-bg-gray">已过期</span>&nbsp;&nbsp;';
                } else {
                    $status = '&nbsp;&nbsp;<span class="layui-badge layui-bg-orange">进行中</span>&nbsp;&nbsp;';
                }
                $status .= $v['status'] == 1
                    ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">正常</button>'
                    : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">禁用</button>';

                $result[$k]['statusbar'] = $status;
                $result[$k]['statusbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'发放记录\', \'' . url("card", array('id' => $v['id'])) . '\');">发放记录</button>';
                $result[$k]['statusbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';

                $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $result[$k]['stime'] = date('Y-m-d H:i:s', $v['stime']);
                $result[$k]['etime'] = date('Y-m-d H:i:s', $v['etime']);
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

            $validate = new \think\Validate([
                ['stime', 'require', '开始时间不能为空'],
                ['etime', 'require', '结束时间不能为空'],
                ['price', 'require|number', '金额不能为空|金额格式不正确'],
                ['num', 'require|number', '数量不能为空|数量格式不正确'],
                ['etime', 'after:' . date('Y-m-d H:i:s') . '', '结束时间不能小于当前时间'],
                ['etime', 'after:' . input('stime') . '', '结束时间不能小于开始时间'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }

            $this->param['stime'] = strtotime($this->param['stime']);
            $this->param['etime'] = strtotime($this->param['etime']);
            
            $result = $this->model->push($this->param);
            if ($result['error'] == 0) {
                model('CouponCard')->push(['coupon_id' => $result['data']]);
                return ajaxSuccess($result['msg'], $result['data']);
            } else {
                return ajaxError($result['msg']);
            }
            return ajaxSuccess($msg);
        }
        if (!empty($id)) {
            $rows = $this->model->get($id);
        } else {
            $rows['status'] = 1;
        }
        $this->seo();
        return view('', compact('rows'));
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
            }

            if ($this->model->where('id', $this->param['id'])->delete() == false) {
                return ajaxError('删除失败');
            } else {
                model('CouponCard')->where(['coupon_id' => $this->param['id']])->delete();
                addlog($this->param['id']);
                return ajaxSuccess('数据删除成功');
            }

        }
    }


//---------------------CouponCard---------------------------------------

    /**
     * 优惠券卡号列表
     */
    public function card()
    {
        $this->assign('coupon_id', $this->request->has('id') ? $this->param['id'] : '');
        $this->seo();
        return view();
    }

    /**
     * 优惠券卡号AJAX 数据
     */
    public function cardList()
    {

        $page = input('page', 1);
        $perpage = input('limit', 20);
        $coupon_id = input('coupon_id', '');
        $keys = input('keys', '');

        if (empty($coupon_id)) {
            exit();
        } else {
            $map['status'] = ['neq', 0];
            $map['coupon_id'] = $coupon_id;

            if (!empty($keys)) {
                $map['number'] = ['like', '%' . $keys . '%'];
            }
            $result = model('CouponCard')->where($map)->order('id desc')->page($page, $perpage)->select();
            $total = model('CouponCard')->where($map)->count();
            if ($result) {
                foreach ($result as $k => $v) {

                    $status = '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="card_del"><i class="layui-icon">&#xe640;</i></button>&nbsp;&nbsp;';
                    switch ($v['status']) {
                        case 1:
                            $status .= '<span class="layui-badge layui-bg-blue">正常</span>';
                            break;
                        case 2:
                            $status .= '<span class="layui-badge layui-bg-orange">已被领取</span>';
                            break;
                        case 3:
                            $status .= '<span class="layui-badge layui-bg-gray">已核销</span>';
                            break;
                    }

                    if ($v['etime'] < time()) {
                        $status .= ' &nbsp;&nbsp; <button class="layui-btn layui-btn-xs layui-btn-disabled">已过期</button>';
                    }
                    if ($v['status'] == 2) {
                        $status .= '&nbsp;&nbsp; <button class="layui-btn layui-btn-xs layui-btn-primary del-btn" lay-event="card_hexiao"><i class="layui-icon">点击核销</i></button>';
                    }
                    $result[$k]['statusbar'] = $status;


                    if (empty($v['user_id'])) {
                        $user = '<button class="layui-btn layui-btn-xs layui-btn-disabled">未领取</button>';
                    } else {
                        $user = getUserField($v['user_id']) . '(' . getUserField($v['user_id'], 'tel') . ')';
                    }
                    $result[$k]['user'] = $user;
                    $result[$k]['price'] = showprice($v['price']);
                    $result[$k]['stime'] = date('Y-m-d H:i:s', $v['stime']);
                    $result[$k]['etime'] = date('Y-m-d H:i:s', $v['etime']);
                }
                return layData('数据获取成功', $result, $total);
            } else {
                return layData('当前数据为空', array(), 0);
            }
        }
    }

    /**
     * 删除活动卡数据
     * 商户删除，假删除
     */
    public function cardDel()
    {

        if ($this->request->isAjax()) {
            if (empty($this->param['id'])) {
                return ajaxError('参数丢失');
            }
            if (model('CouponCard')->where('id', $this->param['id'])->update(['status' => 0]) == false) {
                return ajaxError('删除失败');
            }
            return ajaxSuccess('数据删除成功');
        }

    }

    /**
     * 活动卡核销
     */
    public function cardHexiao()
    {

        if ($this->request->isAjax()) {

            if (empty($this->param['id'])) {
                return ajaxError('参数丢失');
            }
            if (model('CouponCard')->where('id', $this->param['id'])->update(['status' => 3]) == false) {
                return ajaxError('核销失败');
            }

            return ajaxSuccess('核销成功');
        }
    }
}