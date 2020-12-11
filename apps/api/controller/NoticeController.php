<?php
/**
 * Author: Orzly
 * Date: 2020-07-20
 * 公告管理
 */
namespace app\api\controller;

use think\Db;

class NoticeController extends BaseController
{
    protected $noNeedLogin = ['notice_list'];

    /**
     * 公告列表
     * @return \think\response\Jsonp
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notice_list()
    {
        $store_id = input('param.store_id', 0);
        $limit = input('param.limit',10);
        $page  = input('param.page',1);
        $page  = ($page - 1) * $limit;
        $where['status'] =  1;

        if($store_id != 0){
            $where['store_id'] = $store_id;
        }

        $totallist = model('StoreNotice')->where($where)->count();
        $lists['data'] = model('StoreNotice')
            ->field('status, update_time, is_hot,content',true)
            ->where($where)
            ->limit($page, $limit)
            ->order('create_time desc')
            ->select();
        $lists['totalpage'] = ceil($totallist / $limit);
        if($lists['data']){
            foreach($lists['data'] as &$v){
                $v['store_name'] = model('StoreNotice')->get($v['id'])->store->name;
                $v['store_cover'] = getFile(model('StoreNotice')->get($v['id'])->store->cover);
                $v['create_time'] = date('Y-m-d,H:i:s',$v['create_time']);
            }
        }
        return callbackJson(1,'success',$lists);
    }

    /**
     * 消息列表
     * @return \think\response\Jsonp
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function message_list()
    {
        $type = input('type','');
        $limit = input('param.limit',10);
        $page  = input('param.page',1);
        $page  = ($page - 1) * $limit;

        if(empty($type)) {
            return callbackJson(0,'缺少参数：type');
        }
        //只显示半年之内的消息
        $map['create_time'] = ['gt', date('Y-m-d H:i:s', strtotime("-0 year -6 month -0 day"))];
        if($type == 1) { //系统消息
            $map['type'] = 1;
            Db::name('notice_receiver')->where(['user_id' => $this->uid])->setField('read_status',1);
        }else{  //交易消息
            $map['user_id'] = $this->uid;
            $map['type'] = 2;
//          未读的消息 把未读的消息设置为已读
            $unreadMsg = Db::name('notice')->where(['user_id' => $this->uid, 'type' => 2, 'status' => 1])->column('id');
            Db::name('notice')->where(['id' => ['in',$unreadMsg]])->setField('status', 2);
        }

        $total = Db::name('notice')->where($map)->count();
        $list = Db::name('notice')
            ->field('update_time',true)
            ->where($map)
            ->limit($page, $limit)
            ->order('id desc')
            ->select();

        foreach($list as &$v) {
            if($v['cover'] != '') {
                $v['cover'] = getFullPath($v['cover']);
            }else{
                $v['cover'] = '';
            }
        }
        $lists['totalpage'] = ceil( $total/ $limit);
        $lists['list'] = $list;
        return callbackJson(1,'success',$lists);
    }

    /**
     * 未读消息数量
     * @return \think\response\Jsonp
     * @throws \think\Exception
     */
    public function message_unread_count()
    {
        $info['system_msg'] = Db::name('notice_receiver')
            ->where(['user_id' => $this->uid, 'read_status' => 0])
            ->count();

        $info['trade_msg'] = Db::name('notice')
            ->where(['user_id' => $this->uid, 'type' => 2, 'status' => 1])
            ->count();

        return callbackJson(1,'success',$info);
    }
}