<?php
/**
 * 新闻管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;

class ArticleController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'article');
        $this->model = model('Article');
    }

    /*
     * 列表
     *  */
    public function index()
    {
        $this->seo();
        return view();
    }

    /**
     * 查询数据
     */
    public function ajaxData(){
        $page = input('page',1);
        $perpage = input('limit',20);
        $keys = input('keys','');
        $map = [];
        if ($keys) {
            $map['title'] = array('like', '%' . $keys . '%');
        }

        $result = $this->model->where($map)->order('create_time desc')->page($page, $perpage)->select();
        $total = $this->model->where($map)->count();

        if($result){
            foreach($result as $k=>$v){
                $result[$k]['title'] = $v['title'];
                $result[$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);
                $result[$k]['update_time'] = date('Y-m-d H:i', $v['update_time']);
                $result[$k]['cover'] = "<img height='60px' src='{$this->model->get($v['id'])->attachmentCover->filepath}' />";
                $result[$k]['statusbar']  = $v['status'] == 1 ? '<button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status">显示</button>' : '<button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="status">隐藏</button>';
                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息：' . $v['title'] . '\', \'' . url("publish", ['id' => $v['id']]) . '\',1400,800);"><i class="layui-icon">&#xe642;</i></button>';
                $result[$k]['toolbar'] .= '<button class="layui-btn layui-btn-xs layui-btn-danger del-btn" lay-event="del"><i class="layui-icon">&#xe640;</i></button>';
            }
            return layData('数据获取成功', $result, $total);
        }else{
            return layData('当前数据为空', [], 0);
        }
    }

    /**
     * 添加/编辑
     */
    public function publish(){

        $id = input('id', 0);

        if($this->request->isPost()){
            //验证
            $validate = new \think\Validate([
                ['title', 'require', '标题不能为空'],
                ['content','require', '详情不能为空']
            ]);
            //验证部分数据合法性
            if (!$validate->check($this->param)){
                return ajaxError($validate->getError());
            }

            $this->param['update_time'] = time();
            $this->param['status'] = $this->param['status'] ? 1 : 0;

            if(empty($id)){	//新增
                $this->param['create_time'] = time();
                if($this->model->allowField(true)->save($this->param) == false){
                    return ajaxError('添加失败');
                }else{
                    return ajaxSuccess('添加成功');
                }
            }else{ //修改
                $this->param['update_time'] = time();
                if($this->model->allowField(true)->save($this->param, ['id'=>$id]) == false){
                    return ajaxError('修改失败');
                }else{
                    return ajaxSuccess('修改成功');
                }
            }
        }
        $id ? $rows = $this->model->get($id) :  $rows['status'] = 1;
        $this->seo();
        return view('', compact('rows'));
    }

    /**
     * 更新状态
     */
    public function doStatus(){
        if($this->request->isAjax()) {
            if(empty($this->param['id'])){
                return ajaxError('参数丢失');
            }else{
                $status = $this->request->param('status', 0, 'intval');

                $status = $status == 1 ? 0 : 1;

                $updateInfo = $this
                    ->model
                    ->where('id', $this->param['id'])
                    ->update([
                        "status" => $status,
                        'update_time' => time()
                    ]);
                if($updateInfo == false){
                    return ajaxError('更新失败');
                }else{
                    addlog($this->param['id']);
                    return ajaxSuccess('操作成功');
                }
            }
        }
    }

    /*
     * 审核通过
     * */
    public function docheck(){

        if($this->request->isAjax()) {
            if(empty($this->param['id'])){
                return ajaxError('参数丢失');
            }else{
                $check = input('check');
                $updateInfo = $this
                    ->model
                    ->where('id', $this->param['id'])
                    ->update([
                        "check" => $check,
                        'update_time' => time()
                    ]);
                if($updateInfo == false){
                    return ajaxError('更新失败');
                }else{
                    addlog($this->param['id']);
                    return ajaxSuccess('操作成功');
                }
            }
        }
    }

    /**
     * 删除数据
     */
    public function doDel(){
        if($this->request->isAjax()) {
            if(empty($this->param['id'])){
                return ajaxError('参数丢失');
            }else{
                if($this->model->where('id', $this->param['id'])->delete() == false){
                    return ajaxError('删除失败');
                }else{
                    addlog($this->param['id']);
                    return ajaxSuccess('数据删除成功');
                }
            }
        }
    }


}