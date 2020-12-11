<?php
/**
 * 资源管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Model;

class AttachmentController extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = model('Attachment');
    }

    /**
     * 图片管理器
     */
    public function album()
    {
        $box = input('box', 'cover');
//      分类  1=图片； 2=视频；
        if ($box != 'video') {
            $class = 1;
        } else {
            $class = 2;
        }
        $result = $this ->model
            ->where('class', $class)
            ->order('id desc')
            ->paginate(15, false, ['query' => $this->param]);

        foreach ($result as $k => $v) {
            $result[$k]['title'] = mb_strlen($v['title'], 'UTF-8') > 10 ? substr($v['title'], 0, 10) : $v['title'];
        }
        $this->seo();
        return view('', compact('box', 'result'));
    }

    /**
     * 文件上传
     */
    public function upload()
    {
        if ($this->request->file('file')) {
            $file = $this->request->file('file');
        } else {
            return ajaxError('没有上传文件');
        }
        $result = $this->model->upload($file);
        if ($result['error'] == 0) {
            return ajaxSuccess('上传成功', $result['data']);
        } else {
            return ajaxError($result['msg']);
        }
    }

    /**
     * 删除文件
     */
    public function delfile()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if (!empty($id)) {
                $result = $this->model->delfile($id);
                if ($result['error'] == 0) {
                    return ajaxSuccess('删除成功');
                } else {
                    return ajaxError($result['msg']);
                }
            }
        }
    }


}
