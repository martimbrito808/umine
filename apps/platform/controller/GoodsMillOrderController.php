<?php
/**
 * 商品订单管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;

class GoodsMillOrderController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'goodsmillorder');

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
        $perpage = input('limit', 1);
        $keys = input('keys', '');
        
        if (!empty($keys)) {
            $map['gmo.orderno'] = ['like', '%' . $keys . '%'];
        }

        $result = Db::name('goodsMillOrder')->alias('gmo')
            ->join('user u','gmo.user_id = u.id','LEFT')
            ->join('goods_mill gm', 'gmo.goods_mill_id = gm.id','LEFT')
            ->field('u.username, u.tel, gm.name as goods_name, gmo.*')
            ->where($map)
            ->order('gmo.id desc')
            ->page($page, $perpage)
            ->select();
            
        $total = Db::name('goodsMillOrder')->alias('gmo')
            ->join('user u','gmo.user_id = u.id','LEFT')
             ->join('goods_mill gm', 'gmo.goods_mill_id = gm.id','LEFT')
            ->where($map)
            ->count();
            
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['order_price'] = showprice($v['order_price']);
                $result[$k]['price'] = showprice($v['price']);
                $result[$k]['time'] = '<p>创建时间：' . date('Y-m-d H:i:s', $v['create_time']) . '</p>';
                $result[$k]['toolbar'] = '<p><a href="' . url('publish', ['id' => $v['id']]) . '" class="layui-btn layui-btn-xs layui-btn-normal">详情</a></p>';
            }
            return layData('数据获取成功', $result, $total);
        } else {
            return layData('当前数据为空', array(), 0);
        }
    }

    /**
     * 订单详情
     */
    public function publish()
    {
        if ($this->request->isPost()) {
            $id = $this->param['id'];
            if (empty($id)) {
                return ajaxError('参数丢失');
            } else {
                if (Db::name('goodsMillOrder')->where('id',$id)->update(['beizhu' => $this->param['beizhu']]) == false) {
                    return ajaxSuccess('更新完成');
                } else {
                    return ajaxSuccess('更新成功');
                }
            }
        }

        $id = input('id/d');
        if (!empty($id)) {
            $result = Db::name('goodsMillOrder')->alias('gmo')
                ->join('user u','gmo.user_id = u.id','LEFT')
                ->join('goods_mill gm', 'gmo.goods_mill_id = gm.id','LEFT')
                ->field('u.username, u.tel, gm.name as goods_name, gmo.*')
                ->where('gmo.id',$id)
                ->find();
            $this->assign('rows', $result);
        }

        $this->seo();
        return view();
    }
}
