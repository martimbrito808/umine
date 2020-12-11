<?php
/**
 * 公告管理
 * Author: Orzly
 * Date: 2020-06-19
 */
namespace app\platform\controller;

use think\Db;

class NoticeController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'notice');
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
     * 公告列表
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ajaxData()
    {
        $page = input("page", 1);
        $perpage = input('limit', 20);
        $map = true;
        $result = Db::name('notice')->where($map)->order('create_time desc')->page($page, $perpage)->select();
        $total = Db::name('notice')->where($map)->count();

        if ($result) {
            foreach ($result as $k => $v) {
                $result[$k]['statusbar'] = $v['status'] == 1 ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">正常</button>' : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">禁用</button>';
//                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息：' . $v['title'] . '\', \'' . url("publish", array('id' => $v['id'])) . '\',800,600);"><i class="layui-icon">&#xe642;</i></button>';
                $result[$k]['toolbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';
            }
            return layData('数据获取成功', $result, $total);
        } else {
            return layData('当前数据为空', array(), 0);
        }
    }

    /**
     * 新增
     * @return \think\response\Json|\think\response\View
     */
    public function publish()
    {
        if ($this->request->isPost()) {
            $validate = new \think\Validate([
                ['title', 'require', '标题不能为空'],
                ['content', 'require', '内容不能为空']
            ]);
            if (!$validate->check($this->param)) {
                return ajaxError($validate->getError());
            }
            $this->param['create_time'] = date('Y-m-d H:i:s');
            Db::transaction(function(){
                $info = Db::name('notice')->insertGetId($this->param);
                // $list = Db::name('user')->where(['status' => 1])->column('id');
                // $noticeReceiverData = [];
                // foreach($list as $k => $v) {
                //     $noticeReceiverData[$k]['user_id'] = $v;
                //     $noticeReceiverData[$k]['notice_id'] = $info;
                //     $noticeReceiverData[$k]['create_time'] = time();
                // }
                // Db::name('notice_receiver')->insertAll($noticeReceiverData);
            });
            return ajaxSuccess('添加成功');
        }
        return view();
    }

    /**
     * 更新状态
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function doStatus()
    {
        if ($this->request->isAjax()) {
            if (empty($this->param['id'])) {
                return ajaxError('参数丢失');
            } else {
                $status = $this->request->param('status', 0, 'intval');
                $status = $status == 1 ? 0 : 1;

                $updateInfo = Db::name('notice')
                    ->where('id', $this->param['id'])
                    ->update([
                        "status" => $status,
                        'update_time' => time()
                    ]);
                if ($updateInfo == false) {
                    return ajaxError('更新失败');
                } else {
                    addlog($this->param['id']);
                    return ajaxSuccess('操作成功');
                }
            }
        }
    }

    /**
     * 删除数据
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function doDel()
    {
        if ($this->request->isAjax()) {
            if (empty($this->param['id'])) {
                return ajaxError('参数丢失');
            } else {
                if (Db::name('notice')->where('id', $this->param['id'])->delete() == false) {
                    return ajaxError('删除失败');
                } else {
                    addlog($this->param['id']);
                    return ajaxSuccess('数据删除成功');
                }
            }
        }
    }
}
