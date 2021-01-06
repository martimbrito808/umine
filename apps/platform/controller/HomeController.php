<?php

namespace app\platform\controller;

use think\Db;
use think\model;

class HomeController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->seo();
        if (!in_array($this->request->action(), array('login'))) {
            $this->permissions();
        }
    }

    /**
     * 统计数据
     */
    public function index()
    {
        $datas = [];
//        $datas['total_user'] = model('user')->count();    //用户数
//        $datas['total_goods'] = model('goods')->where(['type' => 2,'status' =>1])->count();  //商品数
//        $datas['total_server'] = model('goods')->where(['type' => 1,'status' =>1])->count();  //服务数
//        $datas['total_orders'] = model('order')->where(['suppliers_id' => 0,])->count(); //总订单数
//
//        $datas['orders_status1'] = model('Order')->where(['suppliers_id' => 0, 'delivery_type' =>1, 'status' => 2, 'goods_type' => 2 ])->count();   //待发货商品订单
//        $datas['orders_status2'] = model('Order')->where(['suppliers_id' => 0, 'delivery_type' =>2, 'status' => 2, 'goods_type' => 2 ])->count();   //待自提商品订单
//        $datas['orders_status3'] = model('Order')->where(['suppliers_id' => 0, 'delivery_type' =>2, 'status' => 3, 'goods_type' => 1 ])->count();   //待消费服务订单
//
//        //商品销量排行
//        $goodsMap['type'] =2 ;
//        $goods = [];
//        $goods['today']     = model('OrderInfo')->whereTime('create_time', 'today')->where($goodsMap)->field('goods_id, count(id) as num')->group('goods_id')->order('num desc')->select()->toarray();
//        $goods['yesterday'] = model('OrderInfo')->whereTime('create_time', 'yesterday')->where($goodsMap)->field('goods_id, count(id) as num')->group('goods_id')->order('num desc')->select()->toarray();
//        $goods['week']      = model('OrderInfo')->whereTime('create_time', 'week')->where($goodsMap)->field('goods_id, count(id) as num')->group('goods_id')->order('num desc')->select()->toarray();
//        $goods['month']     = model('OrderInfo')->whereTime('create_time', 'month')->where($goodsMap)->field('goods_id, count(id) as num')->group('goods_id')->order('num desc')->select()->toarray();
//        $datas['goods'] = $goods;
//
//        //订单概述
//        $sales['today']     = model('OrderInfo')->whereTime('create_time', 'today')->where($goodsMap)->field('count(id) as num, sum(price) as total, avg(price) as avg')->select()->toarray();
//        $sales['yesterday'] = model('OrderInfo')->whereTime('create_time', 'yesterday')->where($goodsMap)->field('count(id) as num, sum(price) as total, avg(price) as avg')->select()->toarray();
//        $sales['week']      = model('OrderInfo')->whereTime('create_time', 'week')->where($goodsMap)->field('count(id) as num, sum(price) as total, avg(price) as avg')->select()->toarray();
//        $sales['month']     = model('OrderInfo')->whereTime('create_time', 'month')->where($goodsMap)->field('count(id) as num, sum(price) as total, avg(price) as avg')->select()->toarray();
//        $datas['sales'] = $sales;

        $this->assign('datas', $datas);
        return view();
    }

    /**
     * 统计图表
     */
    public function getChartsData()
    {
        //成效额，成交量
        $data1_arr = model('order')
            ->field('count(id) as num, sum(order_price) as total, FROM_UNIXTIME(create_time, "%Y-%m-%d") as datetime')
            ->where(['suppliers_id' => 0])
            ->group('datetime')
            ->order('datetime asc')
            ->limit(7)
            ->select()->toarray();

        if (!empty($data1_arr)) {
            foreach ($data1_arr as $k => $v) {
                $data1['x'][$k] = $v['datetime'];
                $data1['y'][$k] = $v['num'];
                $data1['z'][$k] = showprice($v['total']);
            }
        }
        $datas['data1'] = $data1;

        $data2_arr = model('order')
            ->field('sum(order_price) as total, user_id')
            ->where(['suppliers_id' => 0])
            ->group('user_id')
            ->limit(10)
            ->order('total desc')
            ->select()->toarray();

        if (!empty($data2_arr)) {
            foreach ($data2_arr as $k => $v) {
                $data2['x'][$k] = getUserField($v['user_id']);
                $data2['y'][$k] = showprice($v['total']);
            }
        }
        $datas['data2'] = $data2;
        return ajaxSuccess('数据获取成功', $datas,0);
        ob_end_flush();
    }

    /**
     * 主框架
     */
    public function indexbak()
    {
        $this->assign('navbar', 'home');
        $info = array(
            '操作系统' => PHP_OS,
            '运行环境' => $_SERVER["SERVER_SOFTWARE"],
            '主机名' => $_SERVER['SERVER_NAME'],
            'WEB服务端口' => $_SERVER['SERVER_PORT'],
            '网站文档目录' => $_SERVER["DOCUMENT_ROOT"],
            '浏览器信息' => substr($_SERVER['HTTP_USER_AGENT'], 0, 40),
            '通信协议' => $_SERVER['SERVER_PROTOCOL'],
            '请求方法' => $_SERVER['REQUEST_METHOD'],
            'ThinkPHP版本' => THINK_VERSION,
            '上传附件限制' => ini_get('upload_max_filesize'),
            '执行时间限制' => ini_get('max_execution_time') . '秒',
            '服务器时间' => date("Y年n月j日 H:i:s"),
            '北京时间' => gmdate("Y年n月j日 H:i:s", time() + 8 * 3600),
            '服务器域名/IP' => $_SERVER['SERVER_NAME'] . ' [ ' . gethostbyname($_SERVER['SERVER_NAME']) . ' ]',
            '用户的IP地址' => $_SERVER['REMOTE_ADDR'],
            '剩余空间' => round((disk_free_space(".") / (1024 * 1024)), 2) . 'M',
        );
        $this->assign('info', $info);
        $this->seo();
        return view();
    }

    /**
     * 修改密码
     * @return \think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pass()
    {
        if ($this->request->isPost()) {
            //验证
            $validate = new \think\Validate([
                ['oldpass', 'require', '旧密码不能为空|旧密码格式只能是字母、数字、——或_'],
                ['newpass', 'require|alphaDash', '新密码不能为空|新密码格式只能是字母、数字、——或_'],
                ['repass', 'require|alphaDash|confirm:newpass', '确认密码不能为空|确认密码格式只能是字母、数字、——或_|确认密码不一致'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }

            $result = Db::name('manager')->where('account', $this->param['account'])->find();
            if (empty($result)) {
                return ajaxError('账号未注册');
            } 
            
            if ($result['password'] != password($this->param['oldpass'])) {
                return ajaxError('旧密码不正确');
            } else {
                $newpass = password($this->param['newpass']);
                Db::name('manager')->where('id', $result['id'])->setField('password', $newpass);
                return ajaxSuccess('密码修改成功');
            }
        
        } else {
            return view();
        }
    }

    /**
     * 系统设置
     * @return \think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config()
    {
        $configModel = model('Config');
        $fileModel = model('Attachment');

        if ($this->request->isPost()) {
            $arr = input('setting/a');
            foreach ($arr as $k => $v) {
                $variable = $k;
                $value = $v;
                $configModel->where(array('variable' => $variable))->setField(array('value' => $value));
            }
            return ajaxSuccess('更新成功');
        } else {
            $list = $configModel
                ->where('status', 1)
                ->where('type','<>','editor')
                ->where('type','<>','file')
                ->order('sort asc')
                ->select();
            return view('',compact('list'));
        }
    }

    /**
     * 用户登录
     * @return \think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login()
    {
        if ($this->request->isPost()) {
            //验证
            $validate = new \think\Validate([
                ['account', 'require|alphaDash', '用户名不能为空|用户名格式只能是字母、数字、——或_'],
                ['password', 'require', '密码不能为空'],
                ['verify', 'require|captcha', '验证码不能为空|验证码不正确'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }

            $result = Db::name('manager')->where('account', $this->param['account'])->find();

            if (empty($result)) {
                return ajaxError('账号未注册');
                die;
            }

            // if ($result['password'] != password($this->param['password'])) {
            //     return ajaxError('账号密码不匹配');
            //     die;
            // }

            if ($result['status'] == 0) {
                return ajaxError('账号已被封禁');
                die;
            }

            //生成登录密钥
            $salt = randNum(16);
            $token = md5($salt . md5($result['account']) . $salt);
            session('manager', $token);
            //记录登录时间和ip
            model('Manager')->save(array('token' => $token, 'login_ip' => get_client_ip(), 'login_time' => time()), array('id' => $result['id']));
            return ajaxSuccess('登录成功');
        }

        $this->seo();
        return view();
    }

    /**
     * 退出登录
     * @return \think\response\Json
     */
    public function loginout()
    {
        model('Manager')->save(array('token' => ''), array('token' => session('manager')));
        session("manager", null);
        return ajaxSuccess('退出成功');
    }

    /**
     * 清除缓存
     * @return \think\response\Json
     */
    public function clear()
    {
        if (false == Cache::clear()) {
            return ajaxError('缓存清除失败');
        } else {
            return ajaxSuccess('缓存清除成功');
        }
    }

}
