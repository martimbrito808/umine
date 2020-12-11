<?php
/**
 * 智能合约订单管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;

class GoodsSmartOrderController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'goodssmartorder');

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
        
        $map['gwo.type'] = 1;
        if (!empty($keys)) {
            $map['gwo.orderno'] = ['like', '%' . $keys . '%'];
        }

        $result = Db::name('goodsWealthOrder')->alias('gwo')
            ->join('user u','gwo.user_id = u.id','LEFT')
            ->join('goods_wealth gw', 'gwo.goods_wealth_id = gw.id','LEFT')
            ->field('u.username, u.tel, gw.name as goods_name, gwo.*')
            ->where($map)
            ->order('gwo.id desc')
            ->page($page, $perpage)
            ->select();
            
        $total = Db::name('goodsWealthOrder')->alias('gwo')
            ->join('user u','gwo.user_id = u.id','LEFT')
             ->join('goods_wealth gw', 'gwo.goods_wealth_id = gw.id','LEFT')
            ->where($map)
            ->count();
            
        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['price'] = showprice($v['price']).' USDT';
                $result[$k]['duration'] = $v['duration']. '天';
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
                if (Db::name('goodsWealthOrder')->where('id',$id)->update(['beizhu' => $this->param['beizhu']]) == false) {
                    return ajaxSuccess('更新完成');
                } else {
                    return ajaxSuccess('更新成功');
                }
            }
        }

        $id = input('id/d');
        if (!empty($id)) {
             $result = Db::name('goodsWealthOrder')->alias('gwo')
                ->join('user u','gwo.user_id = u.id','LEFT')
                ->join('goods_wealth gw', 'gwo.goods_wealth_id = gw.id','LEFT')
                ->field('u.username, u.tel, gw.name as goods_name, gw.rengouedu , gw.fanxihuobi ,gw.apr_3, gw.apr_6, gw.apr_9, gw.apr_12, gwo.*')
                ->where('gwo.id',$id)
                ->find();
            $result['type_title'] = '租赁矿机';
            $this->assign('rows', $result);
        }

        $this->seo();
        return view();
    }
}
