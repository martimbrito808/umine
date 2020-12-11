<?php

namespace app\platform\controller;

use think\Cache;
use think\Db;
use think\Controller;
use think\Session;

class BaseController extends Controller
{

    protected function _initialize()
    {

        $this->param = $this->request->param();

        $this->assign('module', strtolower(request()->module()));
        $this->assign('controller', strtolower(request()->controller()));
        $this->assign('action', strtolower(request()->action()));
    }

    /*
     * 检查是否登录
     */
    protected function permissions()
    {
        if (!Session::has('manager')) {
            $this->redirect('home/login');
        } else {
            $token = session('manager');
            $this->manager = Db::name('manager')->where(array('token' => $token))->find();
            if (empty($this->manager)) {
                $this->redirect('home/login');
            } else {
                $this->assign('manager', $this->manager);
                session('manager_id', $this->manager['id']);
            }
        }
    }

    /**
     * 优化标题输出
     */
    protected function seo()
    {
        $this->assign('webtitle', '系统总平台管理系统');
        $this->assign('seokeys', '系统总平台管理系统');
        $this->assign('seodesc', '系统总平台管理系统');
    }
}
