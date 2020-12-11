<?php
namespace app\api\controller;

use Firebase\JWT\JWT;
use think\Model;
use think\Db;

class GoodsController extends BaseController{

    protected $noNeedLogin = ['detail','goods_comment'];
	/**
	 * 初始化
	 */
	protected function _initialize(){
		parent::_initialize();
		$this->model = model('GoodsMill');
	}

	/**
	 * 商品详情
	 */
	public function detail(){
        $id = input('param.id');
//        获取Token
        $header = Request()->header();
        if(!empty($header['authorization'])) {
            $key = md5(config('jwt.key'));
            $jwtAuth = json_encode(JWT::decode($header['authorization'], $key, array('HS256')));
            $authInfo = json_decode($jwtAuth, true);
        }
	    if(!$this->request->isPost() || (isset($authInfo) && $authInfo['user_type'] != 'user'))
	        return callbackJson(0,'非法请求');

        if(empty($id))
            return callbackJson(0,'缺少参数: 商品/服务ID');

        //商品详情
        $rows = $this->model
            ->field('id, tmp_id, type, cover, store_id, price, name, sku_switch, album, intro, content')
            ->where(array('id'=>$id))
            ->find();
        if(empty($rows)) {
            return callbackJson(0, '获取商品详情失败，请稍后再试');
        }
        $rows['cover'] = getFile($rows['cover']);
        $rows['store_name'] = $this->model->get($rows['id'])->store->name;  //店铺名称
        $rows['content'] = editorGetFullImage(htmlspecialchars_decode($rows['content']));
        $rows['sales'] = model('OrderInfo')->getSale($rows['id']);
        $rows['collect'] = Db::name("goods_collect")->where(['user_id' => $authInfo['user_id'], 'goods_id' => $rows['id']])->count() ? 1 : 0;
        $rows['comment_count'] = model('comment')->where(['goods_id' => $id, 'status' => 1,'parent_id' => 0])->count();
        if($rows['type'] == 1){ //服务， 获取评价星级
            $rows['comment_star'] = model('comment')->where(['goods_id' => $id,'status' => 1])->avg('goods_star');
        }
        if($rows['album']){
            $rows['album'] = model('Attachment')->getAlbum($rows['album']);
        }else{
            $rows['album'] = [];
        }
        if($rows['sku_switch']){
            $rows['sku'] = model('GoodsSku')->getSku($rows['tmp_id']); //SKU属性
            $rows['specs'] = model('GoodsSpecs')->getSpecslist($rows['tmp_id'],'id, name, price, attrs'); //规格属性
            $rows['price'] = $rows['specs'][0]['price'] . ' 起';
        }else{
            $rows['price'] = showprice($rows['price']);
        }
        unset($rows['tmp_id']);
        return callbackJson(1, 'success', $rows);
    }

	/*
	 * 产品详情 _收藏商品
	 * */
    public function goods_collect()
    {
        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }

        $goods_id = input('goods_id','');
        $type = input('type', ' ');
        $user = getUserInfo($this->uid);

        if(empty($goods_id)) {
            return callbackJson(0,'缺少参数 商品ID');
        }
        if(empty($user)) {
            return callbackJson(0, '获取用户信息失败');
        }

        $info = Db::name('goods')->where(['id' => $goods_id, 'type' => $type])->find();
        if(empty($info)) {
            return callbackJson(0,"未查询到相关商品/服务，请稍后再试");
        }
        //查询是否收藏
        $favoriteInfo = Db::name("goods_collect")->where(['user_id' => $this->uid, 'goods_id' => $goods_id,'type' => $type ])->count() ? 1 : 0;
        //已经收藏
        if($favoriteInfo){
            if(Db::name('goods_collect')->where(['user_id' => $this->uid, 'goods_id' => $goods_id, 'type' => $type]) -> delete()){
                return callbackJson(1,'取消收藏成功');
            }
            return callbackJson(0, '取消收藏失败');
        }

        $insertInfo = Db::name('goods_collect')->insert([
            'create_time' => time(),
            'type'        => $type,
            'user_id'     => $this->uid,
            'goods_id'    => $goods_id
        ]);
        if($insertInfo){
            return callbackJson(1,'收藏成功');
        }
        return callbackJson(0, '收藏失败');
    }

    /*
     * 商品详情 _评论列表
     * */
    public function goods_comment(){
        $limit = input('param.limit', 10);
        $page  = input('param.page', 1);
        $page  = ($page - 1) * $limit;
        $goods_id = input('goods_id');

        if(empty($goods_id)) {
            return callbackJson(0,'缺少参数：商品ID');
        }

        $comment_count = model('comment')->where(['status' =>1,'goods_id' =>$goods_id,'parent_id' => 0])->count();
        $lists['totalpage'] = ceil($comment_count / $limit);

        $lists['list'] = model('comment')
            ->field('id, user_id, user_head, username, content, goods_star, create_time')
            ->where(['status' =>1, 'parent_id' => 0, 'goods_id' =>$goods_id])
            ->order('create_time desc')
            ->limit($page, $limit)
            ->select();

        if($lists['list']){
            foreach($lists['list'] as &$v){
                if($v['user_head']){
                    $v['user_head'] = getFullPath($v['user_head']);
                }else{
                    $v['user_head'] = '';
                }
                if(empty($v['content'])) {
                    $v['content'] = '此用户没有填写评价。';
                }
                $v['create_time'] = date('Y-m-d',$v['create_time']);
            }
        }
        return callbackJson(1,'success', $lists);
    }
    
	/**
	 * 详情H5页面
	 */
	public function view(){
		$goods_id = input('goods_id/d');
		if(!empty($goods_id)){
			$result = $this->model->get($goods_id);
			$this->assign('title', $result['name']);
			$this->assign('content', $result['content']);
			return view('detail/view');
		}
	}


}
