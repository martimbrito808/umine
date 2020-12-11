<?php

namespace app\platform\controller;

use think\Db;

class NodeMillController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'nodemill');
    }

    /**
     * 主框架
     */
    public function index()
    {
        if(Request()->isPost()) {
            $mill_id = input('param.mill_id');
            if(empty($mill_id)) {
                return ajaxError('非法请求');
            }
            Db::name('config')->where('variable', 'node_mill_id')->setField(array('value' => $mill_id));
            return ajaxSuccess('操作成功');
        }
        
        $this->seo();
        $list = Db::name('goods_mill')->where(['status' => 1,'stock' => ['gt','1']])->select();
        $defaultSet = Db::name('config')->where('variable','node_mill_id') -> value('value');
        return view('',compact('list','defaultSet'));
    }

}