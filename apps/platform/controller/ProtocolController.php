<?php

namespace app\platform\controller;

use think\Db;

class ProtocolController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->assign('navbar', 'protocol');
    }

    /**
     * 主框架
     */
    public function index()
    {
        if ($this->request->isPost()) {
            $arr = input('setting/a');
            foreach ($arr as $k => $v) {
                $variable = $k;
                $value = $v;
                Db::name('config')
                    ->where([
                        'variable' => $variable
                    ])
                    ->setField([
                        'value' => $value
                    ]);
            }
            return ajaxSuccess('更新成功');
        }
        $list = Db::name('config')
            ->where('status', 1)
            ->where('type','editor')
            ->order('sort asc')
            ->select();
        $this->seo();
        return view('',compact('list'));
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
}
