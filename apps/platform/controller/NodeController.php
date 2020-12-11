<?php
/**
 * USDT充值
 * Author: Orzly
 * Date: 2020-06-29
 */
namespace app\platform\controller;

use think\Db;

class NodeController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'node');
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
        
        if (!empty($keys)) {
            $map['n.title|u.tel'] = array('like', '%' . $keys . '%');
        }
  
        if ($type) {
            $map['n.type'] = $type;
        }

        $result = Db::name('node')->alias('n')
            ->join('user u','u.id = n.user_id','LEFT')
            ->field('u.username, u.tel, n.*')
            ->where($map)
            ->order('vote desc')
            ->page($page, $perpage)
            ->select();

        $total = Db::name('node')->alias('n')
            ->join('user u','u.id = n.user_id','LEFT')
            ->where($map)
            ->count();

        if(empty($result)){
            return layData('当前数据为空', array(), 0);
        }
        foreach($result as $k => $v){
            $result[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
          $result[$k]['cover'] = $v['cover']
                ? '<a href="javascript:;" lay-event="showcover"><img src="' . $v['cover']. '"></a>'
                : '';
            $result[$k]['type_msg'] = $v['type'] == 1  
                ? '<span class="layui-badge layui-bg-green">常规节点</span>'
                : '<span class="layui-badge layui-bg-orange">超级节点</span>';
                
            switch($result[$k]['mill_id']){
                case 0:
                    if($v['type'] == 2) {
                        $result[$k]['statusbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="zengsongmill">点击赠送矿机</button>';  
                    }
                    break;
                default:
                    if($v['type'] == 2) {
                        $result[$k]['statusbar'] = '<span class="layui-badge layui-bg-orange layui-disabled">已赠送</span>';
                    }
                    break;
            }
        }
        return layData('数据获取成功', $result, $total);
    }

    /**
     * 充值审核
     * @return \think\response\Json
     */
    public function dozengsong(){

        if($this->request->isAjax()) {
            $node_id = $this->param['id'];
            $mill_id = getconfig('node_mill_id');
            if(empty($node_id)){
                return ajaxError('参数丢失');
            }
            if(empty($mill_id)) {
                return ajaxError('您还未设置赠送矿机');
            };
            
            $nodeInfo = Db::name('node')->where('id',$node_id)->find();
            if(empty($nodeInfo)) {
                return ajaxError('未查询到此节点信息,请稍后再试');
            };
            
            Db::startTrans();
            try{
                //赠送矿机 生成矿机订单,
                Db::name('goods_mill_order')
                    ->insert([
                        'goods_mill_id' => $mill_id,
                        'user_id'       => $nodeInfo['user_id'],
                        'orderno'       => date('YmdHis').randNum(),
                        'num'           => 1,
                        'price'         => 0,
                        'order_price'   => 0,
                        'buy_time'      => date('Y-m-d H:i:s'),
                        'create_time'   => time(),
                        'type'          => 2,
                    ]);
                //写入到用户矿机表
                $userMillId = Db::name('user_mill')->where(['user_id' => $nodeInfo['user_id'], 'mill_id' => $mill_id])->value('id');
                if(empty($userMillId)) {
                   
                    Db::name('user_mill')
                        ->insert([
                            'user_id'  => $nodeInfo['user_id'],
                            'mill_id'  => $mill_id,
                            'mill_num' => 1,
                        ]);
                }else{
                    Db::name('user_mill')
                        ->where(['id' => $userMillId])
                        ->setInc('mill_num', 1);
                }
                
                //修改Node表状态为已赠送
                Db::name('node')->where('id',$node_id)->setField('mill_id', $mill_id);
                Db::commit();
                addlog($this->param['id']);
                return ajaxSuccess('操作成功');
            } catch (\Exception $e) {
                Db::rollback();
                return ajaxError('操作失败');
            }
        }
    }
}