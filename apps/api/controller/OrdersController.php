<?php
namespace app\api\controller;

use think\Model;
use think\Db;

class OrdersController extends BaseController{
	
	protected function _initialize(){
		parent::_initialize();
		$this->model = model('order');
	}

    /**
     * 确认订单（商品详情页点击立即购买
     */
    public function buyNow(){
        if(!$this->request->isPost()) {
            return callbackJson(0,'非法请求');
        }
        $goods_id = input('param.goods_id','');
        $specs_id = input('param.specs_id','');
        $number   = input('param.num', 1);
        $address_id = input('param.address_id', 0);

        if(empty($goods_id)) {
            return callbackJson(0,'未选择任何商品');
        }
        $goodsInfo = model('goods')->field('id,store_id,name,cover,intro,price,postage_id,tmp_id,type,sku_switch')->where(['id' => $goods_id])->find();
        if(empty($goodsInfo)){
            return callbackJson(0,'获取商品信息失败');
        }

        $data['address'] = model('userAddress')->getInfo(['user_id' =>$this->uid,'address_id' => $address_id]);  //收货地址
        $getPostageParams['goods_id'] = $goodsInfo['id'];
        if($data['address']) {
            $getPostageParams['address_id'] = $data['address']['id'];
        }else{
            $data['address'] = (object)[];
        }

        $goodsInfo['num']         = $number;  //商品数量
        $goodsInfo['goods_name']  = $goodsInfo['name'];  //商品数量
        $goodsInfo['goods_id']    = $goodsInfo['id'];  //device_token商品数量
        $goodsInfo['raw_price']   = $goodsInfo['price'];
        $goodsInfo['cover']       = getFile($goodsInfo['cover']); //封面图
        $goodsInfo['price']       = showprice($goodsInfo['price']); //商品价格格式化

        //如果开启了SKU
        if($goodsInfo['sku_switch']){
            if(empty($specs_id)) {
                return callbackJson(0,'缺少参数：规格ID');
            }
            $specs = model('goodsSpecs') -> get(['id' => $specs_id, 'goods_id' => $goodsInfo['tmp_id']]);
            if(empty($specs)) {
                return callbackJson(0,'非法请求');
            }
            $goodsInfo['raw_price']  = $specs['price'];
            $goodsInfo['specs_id']   = $specs['id'];
            $goodsInfo['specs_name'] = $specs['name'];
            $goodsInfo['price']      = showprice($specs['price']);

            $getPostageParams['specs_id'] = $specs_id;
        }

        $postage = model('postage')->getPostage($getPostageParams); //邮费
        $goodsInfo['postage']  = showprice($postage); //邮费格式化
        $data['total_price']   = showprice($goodsInfo['raw_price'] * $number + $postage); //总计费用
        $data['goodsInfo'][0]['total_postage'] = $goodsInfo['postage'];
        $data['goodsInfo'][0]['store_name'] = getField('Store', $goodsInfo['store_id'], 'name'); //店铺信息
        $data['goodsInfo'][0]['store_id'] = $goodsInfo['store_id']; //店铺ID
        $data['goodsInfo'][0]['goods'][0] = $goodsInfo;

        unset($goodsInfo['postage_id']);
        unset($goodsInfo['raw_price']);
        unset($goodsInfo['store_id']);
        unset($goodsInfo['tmp_id']);
        unset($goodsInfo['name']);
        unset($goodsInfo['id']);

        return callbackJson(1,'success',$data);
    }

    /**
     * 确认订单 (购物车点击去结算
     */
    public function confirm(){
        $address_id = input('param.address_id', 0);

        $storeGroupList = Db::name('cart')
            ->where(['user_id' => $this->uid,'status' => 1])
            ->group('store_id')
            ->field('store_id')
            ->select();

        $data['address'] = model('userAddress')->getInfo(['user_id' =>$this->uid, 'address_id' => $address_id]) ?: (object)[];  //收货地址
        $data['total_price'] = 0;
        foreach($storeGroupList as $m => $n){
            $list[$m]['store_name'] = getField('store', $n['store_id'], 'name'); //店铺名称
            $list[$m]['store_id'] = $n['store_id']; //店铺ID
            $list[$m]['total_postage'] = 0;

            $cartList = Db::name('cart')
                -> field('user_id, store_id, create_time',true)
                -> where(['store_id' => $n['store_id'], 'user_id' => $this->uid,'status' => 1])
                -> order('id desc')
                -> select();
            foreach($cartList as $k => &$v){
                $goods = Db::name('goods')->field('id,name,sku_switch,cover')->where('id', $v['goods_id'])->find(); //商品信息

                if(count((array)$data['address'])){
                    $getPostageParams['address_id'] = $data['address']['id'];
                }
                $getPostageParams['goods_id'] = $v['goods_id'];

                if($goods['sku_switch'] != 0){
                    $getPostageParams['specs_id'] = $v['specs_id'];
                    $specs = model('GoodsSpecs')->get($v['specs_id']); //规格信息=
                    $cartList[$k]['specs_id'] = $specs['id'];
                    $cartList[$k]['specs_name'] = $specs['name'];
                }
                //邮费
                $postage = model('postage')->getPostage($getPostageParams);
                $cartList[$k]['postage'] = showprice($postage);
                $list[$m]['total_postage'] += $postage;
                //总计费用
                $data['total_price'] += $v['price'] * $v['num'] + $postage;

                $cartList[$k]['type']       = 2;   // 2=商品 服务没有加入购物车这一说;
                $cartList[$k]['cover']      = getFile($goods['cover']);
                $cartList[$k]['goods_name'] = $goods['name'];
                $cartList[$k]['sku_switch'] = $goods['sku_switch'];
                $cartList[$k]['price']      = showprice($v['price']);
                unset($v['status']);
            }
            $list[$m]['goods'] = $cartList;
            $list[$m]['total_postage'] = showprice($list[$m]['total_postage']);
        }
        $data['total_price'] = showprice($data['total_price']);
        $data['goodsInfo'] = $list;

        return callbackJson(1,'success', $data);
    }

    /**
     *提交订单
     */
    public function submitOrder()
    {
        $param = input('param.');
        $param['user_id'] = $this->uid;

        $validate = new \think\Validate([
            ['goodsInfo', 'require', '未选择任何商品|服务'],
            ['action', 'require', '请输入提交入口'],
        ]);
        if (!$validate->check($param)){
            return callbackJson(0, $validate->getError());
        }

        $result = $this->model->push($param);
        if($result['error'] != 0){
            return callbackJson(0, $result['msg']); die;
        }
        return callbackJson(1,'订单提交成功', $result['data']);
    }

    /**
     * 订单数量
     */
    public function ordersNum(){
        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        $type = input("param.type",'');
        if(empty($type)) {
            return  callbackJson(0,'缺少参数： type');
        }
        $map['user_id']      = $this->uid;
        $map['goods_type']   = $type;
        $map['suppliers_id'] = 0;
        $map['deleted']      = 0;

        //服务订单
        if($type == 1) {
            $data["status1"]  = $this->model->where(['status' => 1])->where($map)->count(); //未付款
            $data["status2"]  = $this->model->where(['status' => 3])->where($map)->count(); //待使用
            $data["status3"]  = $this->model->where(['status' => ['between','4,5']])->where($map)->count(); //待评价
            $data["status4"]  = $this->model->where(['status' => 8])->where($map)->count(); //已取消

        }elseif ($type ==2) {
            //商城订单 1/未付款  2/已付款  3/已发货  4/已收货(待评价  5/已完成  6/退款  7/拒绝退款  8/已取消
            $data["status1"]  = $this->model->where(['status' => 1])->where($map)->count(); //未付款
            $data["status2"]  = $this->model->where(['status' => 2])->where($map)->count(); //待发货
            $data["status3"]  = $this->model->where(['status' => 3])->where($map)->count(); //待收货
            $data["status4"]  = $this->model->where(['status' => ['between','4,5']])->where($map)->count(); //待评价
            $data["status5"]  = $this->model->where(['status' => 8])->where($map)->count(); //已取消
        }
        return callbackJson(1,'success', $data);
    }

	/**
	 * 订单列表
	 */
	public function getOrderList(){
        $param = input('param.');

		if(!$this->request->isPost()){
		    return callbackJson(0,'非法请求');
        }
        $validate = new \think\Validate([
            ['status', 'require|number', '状态值不能为空|状态格式不正确'],
            ['type', 'require', 'type值不能为空'],
        ]);
        if (!$validate->check($param)){
            return callbackJson(0, $validate->getError());
        }

        $map['user_id']     = $this->uid;
        $map['status']      = $param['status'];
        if($param['status'] == 4) {
            $map['status'] = ['between','4,5'];
        }
        $map['goods_type']  = $param['type'];
        $map['deleted']     = 0;

        $limit = input('param.limit',10);
        $page  = input('param.page',1);
        $page  = ($page - 1) * $limit;

        $total = $this->model->where($map)->count();
        $result = $this->model
            ->where($map)
            ->field('id, store_id, goods_price, order_price, status, postage, delivery_type, status, orderno')
            ->page($page, $limit)
            ->order('id desc')
            ->select();

        //商城订单 1/未付款  2/已付款  3/已发货  4/已收货(待评价  5/已完成  6/退款  7/拒绝退款  8/已取消
        //服务订单 1/未付款  3/已发货  3/已收货  4（待评价  8已取消
        foreach($result as $k => $v){
            $store['store_id'] = $v['store_id'];
            $store['store_cover'] = getFile(getField('store', $v['store_id'], 'cover'));
            $store['store_name'] = getField('store', $v['store_id'], 'name');
            $result[$k]['store'] = $store;
            $result[$k]['goods'] = model('orderInfo')->getGoods($v['id']);
            $result[$k]['order_price'] = showprice($result[$k]['order_price']);
            $result[$k]['goods_price'] = showprice($result[$k]['goods_price']);
            $result[$k]['postage'] = showprice($result[$k]['postage']);

//            $result[$k]['count'] = 0;
//            foreach($result[$k]['goods'] as $m => $n){
//                $result[$k]['count'] += $n['num'];
//            }
            //判断订单是否可以售后
//            $result[$k]['services'] = model('Services')->check(array('orders_id'=>$v['id']));
//            $result[$k]['services_status'] = model('Services')->where(['orders_id'=>$v['id']])->value('status');
        }

        $datas['totalpage'] = ceil($total / $limit);
        $datas['list'] = $result;

        return callbackJson(1,'success', $datas);
	}
	
	/**
	 * 订单详情
	 */
	public function getInfo(){

        $order_id = input('param.order_id', '');

		if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        if(empty($order_id)) {
            return callbackJson(0,'订单ID不能为空');
        }
        $result = $this->model->get($order_id);
        if(empty($result)) {
            return callbackJson(0,'获取订单详情失败，请稍后再试');
        }

        //商城订单 1/未付款  2/已付款  3/已发货  4/已收货(待评价  5/已完成  6/退款  7/拒绝退款  8/已取消
        //服务订单 1/未付款  3/已发货  3/已收货  4（待评价  8已取消
        //自提订单|服务订单 显示店铺位置
        if($result['delivery_type'] == 2 || $result['goods_type'] == 1){
            $storeInfo = Db::name('store')->where(['id' => $result['store_id']])->find();
            $datas['address'] = [
                'name' => $storeInfo['name'],
                'phone' => $storeInfo['phone'],
                'address' => getArea($storeInfo['province_id'], 'name'). getArea($storeInfo['city_id'], 'name') . getArea($storeInfo['area_id'], 'name'). $storeInfo['address']
            ];
        }else{
            //收货信息
            $datas['address'] = [
                'name' => $result['address_name'],
                'phone' => $result['address_phone'],
                'address' => $result['address_address'],
            ];
        }

        // 已发货订单， 显示物流信息
        if($result['status'] == 3) {
            $expressResult = model('express') -> traces($order_id);
            if($expressResult['error'] != 0){
                $expresult = [
                    'ftime' => '',
                    'context' => '暂无物流信息'
                ];
            }else{
                $expressDatas = json_decode($expressResult['data'], true);
                if($expressDatas['message'] != 'ok'){
                    $expresult = [
                        'ftime' => '',
                        'context' => '暂无物流信息'
                    ];
                }else{
                    $expresult = [
                        'ftime' => $expressDatas['data'][0]['ftime'],
                        'context' => $expressDatas['data'][0]['context']
                    ];
                }
            }
            $datas['express'] = $expresult;
        }

        //商品信息
        $goods = model('orderInfo')->getGoods($result['id']);
        $datas['goods'] = $goods ? $goods : '';

        $detail['payway']       = getPayway($result['payway']);
        //订单基础信息
        $detail['id']           = $result['id'];
        $detail['store_id']     = $result['store_id'];
        $detail['store_name']   = getField('store', $result['store_id'],'name');
        $detail['store_id']     = $result['store_id'];
        $detail['charge_off']   = $result['charge_off'];
        $detail['orderno']      = $result['orderno'];
        $detail['delivery_type']= $result['delivery_type'];
        $detail['goods_type']   = $result['goods_type'];
        $detail['status']       = $result['status'];
        $detail['order_price']  = showprice($result['order_price']);
        $detail['goods_price']  = showprice($result['goods_price']);
        $detail['postage']      = showprice($result['postage']);
        $detail['intro']        = $result['intro'] ? $result['intro'] : '';

        $detail['create_time']  = !empty($result['create_time'])  ? date('Y-m-d H:i:s',$result['create_time']) : '';
        $detail['pay_time']     = !empty($result['pay_time'])     ? date('Y-m-d H:i:s', $result['pay_time']) : '';
        $detail['take_time']    = !empty($result['take_time'])    ? date('Y-m-d H:i:s', $result['take_time']) : '';
        $detail['deliver_time'] = !empty($result['deliver_time']) ? date('Y-m-d H:i:s', $result['deliver_time']) : '';
        $datas['detail']        = $detail;

        return callbackJson(1,'success',$datas);
	}

    /**
     * 确认收货
     * @return \think\response\Jsonp
     * @throws \think\exception\DbException
     */
	public function take(){
        $order_id = input('param.order_id', '');

        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        if(empty($order_id)) {
            return callbackJson(0,'订单ID不能为空');
        }
        //查询订单信息
        $orders = model('order')->get($order_id);
        if(empty($orders)){
            return callbackJson(0,'查询不到订单信息');
        }
        if($this->uid != $orders['user_id']){
            return callbackJson(0,'您没有权限确认收货');
        }
        if($orders['status'] != 3){
            return callbackJson(0,'当前订单无法收货');
        }

        $result = model('order')->changeStatus(['orders_id' => $orders['id'], 'status'=> 4,'take_time' => time()]);
        if($result['error'] != 0 ){
            return callbackJson(0,$result['msg']);
        }
        return callbackJson(1,'收货成功');
	}

    /**
     * 取消订单
     * @return \think\response\Jsonp
     * @throws \think\exception\DbException
     */
	public function cancel(){
		if(!$this->request->isPost()) {
            return callbackJson(0, '非法请求');
        }

        $param = input('param.');
        $validate = new \think\Validate([
            ['order_id', 'require|number', '订单ID不能为空|订单ID格式不正确'],
        ]);
        if (!$validate->check($param)){
            return callbackJson(0,$validate->getError());
        }
        //查询订单信息
        $orders = model('order')->get($param['order_id']);
        if(empty($orders)){
            return callbackJson(0,'查询不到订单信息');
        }
        if($this->uid != $orders['user_id']){
            return callbackJson(0,'非法请求');
        }
        if($orders['status'] != 1){
            return callbackJson(0,'当前订单无法取消');
        }

        $result = model('order')->changeStatus(['orders_id' => $orders['id'], 'status' => 8]);
        if($result['error'] != 0){
            return callbackJson(0, $result['msg']);
        }
        return callbackJson(1,'取消成功', $orders['id']);

	}

    /**
     * 删除订单
     * @return \think\response\Jsonp
     * @throws \think\exception\DbException
     */
	public function delinfo(){

	    $param = input('param.');
		if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        //验证
        $validate = new \think\Validate([
            ['order_id', 'require|number', '订单ID不能为空|订单ID格式不正确'],
        ]);
        if (!$validate->check($param)){
            return callbackJson(0, $validate->getError());
        }
        //查询订单信息
        $orders = model('order')->get($param['order_id']);
        if(empty($orders)){
            return callbackJson(0,'查询不到订单信息');
        }

        if($this->uid != $orders['user_id']){
            return callbackJson(0,'您没有删除此订单的权限');
        }

        $result = $this->model->delInfo($param);
        if($result['error'] == 0){
            return callbackJson(1, $result['msg']);
        }else{
            return callbackJson(0, $result['msg']);
        }

	}

    /**
     * 订单评价
     * @return \think\response\Jsonp
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function comment()
    {
        $order_id = input('order_id', '');
        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        if(empty($order_id)) {
            return callbackJson(0,'订单ID不能为空');
        }
        $orderInfo =  model('order')->field('id, store_id, status')->where(['id' => $order_id])->find();
        if(empty($orderInfo)){
            return callbackJson(0,'查询不到订单信息');
        }
        if($orderInfo['status'] != 4) {
            return callbackJson(0,'非法请求');
        }
        $result['order_id'] = $order_id;
        $storeInfo = Db::name('store')->field('cover as store_cover, name as store_name,id as store_id')->where(['id' => $orderInfo['store_id']])->find();
        $storeInfo['store_cover'] = getFile($storeInfo['store_cover']);
        $storeInfo['store_label'] = model('classify')->field('id,title')->where(['class_id' => 6, 'status' => 1])->order('sort asc, id desc')->select();
        $result['store'] = $storeInfo;

        $goodsInfo = model('orderInfo')->field('id, goods_id, goods_name, specs_name')->where(['orders_id' => $order_id])->order('id asc')->select();
        foreach($goodsInfo as &$v) {
            $v['specs_name'] = $goodsInfo['specs_name']?:'';
            $v['goods_cover'] = getFile(getField('goods',$v['goods_id'],'cover'));
        }
        $result['goods'] = $goodsInfo;

        return callbackJson(1,'success', $result);
	}

    /**
     * 订单评价 提交
     * @return \think\response\Jsonp
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function commentPost()
    {
        $commentJsonArr = [
            'order_id' => 77,
            'goods_comment' => [
                [
                    'id'        => 52,
                    'goods_id'  => 41,
                    'star'      => 5,
                    'imgs'      => '/public/uploads/20200617/f9f55c90d76d33fb7c82de4a0d38ef6c_thumb.png,/public/uploads/20200617/5412f1fde43a13bb73f8f000f989752c_thumb.png',
                    'content'   => '商品收到了， 成色很好，好评'
                ],
                [
                    'id'        => 53,
                    'goods_id'  => 41,
                    'star'      => 3,
                    'imgs'      => '',
                    'content'   => '一星保护'
                ],
            ],
            'store_comment' => [
                'store_id'  => 9,
                'star'      => 5,
                'imgs'      => '',
                'content'   => '',
            ]
        ];

        $param = input('param.');
        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }

        $validate = new \think\Validate([
            ['order_id', 'require|number', '订单ID不能为空|订单ID格式不正确'],
            ['store_comment', 'require', '请对商家进行评价'],
            ['goods_comment', 'require', '请对商品进行评价']
        ]);
        if (!$validate->check($param)){
            return callbackJson(0,$validate->getError());
        }
        $orderInfo =  model('order')->get($param['order_id']);
        if(empty($orderInfo)){
            return callbackJson(0,'查询不到订单信息');
        }
        if(empty($param['store_comment']['store_id'])) {
            return callbackJson(0,'商家ID不能为空');
        }
        if($orderInfo['status'] != 4 || $orderInfo['user_id'] != $this->uid ) {
            return callbackJson(0,'该订单不能评价');
        }

        $goodsComment = $param['goods_comment'];
        $storeComment = $param['store_comment'];
        $order_id     = $param['order_id'];
        $user_id      = $this->uid;
        $user_name    = getField('user',$user_id,'username');
        $user_img     = getField('user',$user_id,'head_img');

        //商品|服务 评价
        foreach($goodsComment as $gk => $gv) {
            $commentData[$gk]['type']           = getField('order_info',$gv['id'],'type');
            $commentData[$gk]['order_id']       = $order_id;
            $commentData[$gk]['user_id']        = $user_id;
            $commentData[$gk]['username']       = $user_name;
            $commentData[$gk]['user_head']      = $user_img;
            $commentData[$gk]['store_id']       = $storeComment['store_id'];
            $commentData[$gk]['goods_id']       = $gv['goods_id'];
            $commentData[$gk]['rec_id']         = $gv['id'];
            $commentData[$gk]['content']        = $gv['content'];
            $commentData[$gk]['imgs']           = $gv['imgs'];
            $commentData[$gk]['goods_star']     = $gv['star'] ?: 5;
            $commentData[$gk]['store_star']     = '';
            $commentData[$gk]['store_label']    = '';
            $commentData[$gk]['create_time']    = time();
        }
        //店铺评价
        $arr[0]['type']         = 3;
        $arr[0]['order_id']     = $order_id;
        $arr[0]['user_id']      = $user_id;
        $arr[0]['username']     = $user_name;
        $arr[0]['user_head']    = $user_img;
        $arr[0]['store_id']     = $storeComment['store_id'];
        $arr[0]['goods_id']     = '';
        $arr[0]['rec_id']       = '';
        $arr[0]['content']      = $storeComment['content'];
        $arr[0]['imgs']         = $storeComment['imgs'];
        $arr[0]['goods_star']   = '';
        $arr[0]['store_star']   = $storeComment['star'] ?: 5;
        $arr[0]['store_label']  = $storeComment['label'];
        $arr[0]['create_time']  = time();

        array_walk($arr,function($item) use (&$commentData) {
            array_unshift($commentData, $item);
        });
        Db::startTrans();
        $insertInfo = Db::name('comment')->insertAll($commentData);
        if(false == $insertInfo) {
            Db::rollback();
            return callbackJson(0,'评论失败，请稍后再试');
        }

        if(isset($storeComment['label'])){
            $labelArr = explode(',',$storeComment['label']);
            foreach($labelArr as $lk => $lv) {
                $check = Db::name('store_gain_label')->where(['label_id' => $lv, 'store_id' => $storeComment['store_id']])->find();
                if($check){
                    $info = Db::name('store_gain_label')->where(['id' => $check['id']])->update([
                        'count' => Db::raw('count+1'),
                        'update_time' => time()
                    ]);
                }else{
                    $info = Db::name('store_gain_label')->insert([
                        'store_id'      => $storeComment['store_id'],
                        'label_name'    => getField('classify',$lv,'title'),
                        'label_id'      => $lv,
                        'count'         => 1,
                        'create_time'   => time(),
                    ]);
                }
                if(false == $info){
                    Db::rollback();
                    return callbackJson(0,'评论失败，请稍后再试');
                }
            }
        }
        $overallRating = Db::name('comment')->where(['store_id' => $storeComment['store_id'], 'type' => 3])->avg('store_star');
        $overallRating = round($overallRating,1);
        Db::name('store')->where(['id' => $storeComment['store_id']])->setField('score',$overallRating);  //修改店铺星级评价
        Db::name('order')->where(['id' => $order_id])->setField('status',5); //订单表状态改为 5 已完成
        Db::commit();
        return callbackJson(1,'评论成功');
	}

    /**
     * 再次购买
     * @return \think\response\Jsonp
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function buyAgain()
    {
        $order_id = input('order_id', '');

        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        if(empty($order_id)) {
            return callbackJson(0,'订单ID不能为空');
        }
        $orderInfo =  model('order')->field('id, store_id, status')->where(['id' => $order_id])->find();
        if(empty($orderInfo)){
            return callbackJson(0,'查询不到订单信息');
        }
        if($orderInfo['status'] != 4 && $orderInfo['status'] != 5 && $orderInfo['status'] != 8) {
            return callbackJson(0,'该商品不能再次购买');
        }

        $goodsInfo = model('orderInfo')
            ->field('goods_id, store_id, specs_id, num')
            ->where(['orders_id' => $order_id])
            ->order('id asc')
            ->select();

        foreach($goodsInfo as $k => $v){
            $insertData['goods_id'] = $v['goods_id'];
            $insertData['specs_id'] = $v['specs_id'] ? $v['specs_id'] : 0;
            $insertData['user_id']  = $this->uid;
            $insertData['num']      = $v['num'];
            $insertData['status']   = 1;
            model('cart')->push($insertData);
        }
        return callbackJson(1,'success');

	}

    /**
     * 物流接口
     */
    public function express(){
        $order_id = input('param.order_id', '');
        if(!$this->request->isPost()){
            return callbackJson(0,'非法请求');
        }
        if(empty($order_id)) {
            return callbackJson(0,'订单ID不能为空');
        }
        $result = model('express') -> traces($order_id);
        if($result['error'] != 0){
            return callbackJson(0,'没有物流信息2');
        }
        $datas = json_decode($result['data'], true);
        if($datas['message'] != 'ok'){
            return callbackJson(0,'没有物流信息');
        }

        $orders = model('Order')->get($order_id);
        $info['order']['express_name'] = $orders['express_name'];
        $info['order']['express_number'] = $orders['express_number'];
        $info['express'] = array_reverse($datas['data']);

        return callbackJson(1,'物流信息获取成功', $info);
    }

    //------------------------------------售后----------------------------------

    /**
	 * 订单退换货
	 */
	public function services(){
		if($this->request->isPost()){
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['orders_id', 'require|number', '订单ID不能为空|订单ID格式不正确'],
				['mold', 'require|number', '售后类型不能为空|售后类型格式不正确'], //售后类型：1 仅退款 2 退款退货
				['content', 'require', '退款原因不能为空']
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}

			//查询用户信息
			$user = getUserInfo($this->param['token']);
			if(empty($user)){
				return ajaxError(config('error_code')['token']['msg'], config('error_code')['token']['error']);
			}else{
				$this->param['user_id'] = $user['id'];
			}

			$result = model('Services')->push($this->param);
			if($result['error'] == 0){
				return ajaxSuccess($result['msg'], $result['data']);
			}else{
				return ajaxError($result['msg']);
			}
		}
	}

	/**
	 * 售后详情
	 */
	public function servicesDetail(){
		if($this->request->isPost()){
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['orders_id', 'require|number', '订单ID不能为空|订单ID格式不正确'],
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}

			//查询用户信息
			$user = getUserInfo($this->param['token']);
			if(empty($user)){
				return ajaxError(config('error_code')['token']['msg'], config('error_code')['token']['error']);
			}

			$services = model('services')->get(['orders_id'=>$this->param['orders_id']]);
			if(empty($services)){
				return ajaxError('此订单没有售后信息');
			}else{
				$result = $this->model->get($this->param['orders_id']);
				if(empty($result)){
					$datas['detail'] = null;
				}else{
					//退货商品信息
					$goods = model('OrderInfo')->getGoods($result['id']);
					$datas['goods'] = $goods ? $goods : null;
					//收货信息
					$address = [];
					$address['name'] = $result['address_name'];
					$address['phone'] = $result['address_phone'];
					$address['address'] = $result['address_address'];
					$datas['address'] = $address;

					//快递信息
					$express = [];
					$express['express'] = $result['express_name'];
					$express['express_number'] = $result['express_number'];
					$datas['express'] = empty($express['express_number']) ? null : $express;

					//售后信息
					$services['money'] = showprice($services['money']);
					$services['imgs'] = model('Attachment')->getAlbum($services['imgs']);
					$datas['services'] = $services;

					//订单基础信息
					$detail = [];
					$detail['id'] = $result['id'];
					$detail['orderno'] = $result['orderno'];
					$detail['status'] = $result['status'];
					$detail['is_cancel'] = $result['is_cancel'];
					$detail['price'] = showprice($result['price']);
					$detail['goods_price'] = showprice($result['goods_price']);
					$detail['postage'] = showprice($result['postage']);
					$detail['coupon'] = showprice($result['coupon']);
					$detail['gift'] = showprice($result['gift']);
					$detail['create_time'] = $result['create_time'];
					$detail['intro'] = $result['intro'] ? $result['intro'] : null;
					$detail['payway'] = $result['payway'];
					$detail['payprice'] = $result['payprice'];
					$detail['paytime'] = $result['paytime'];
					$datas['detail'] = $detail;
				}
				return ajaxSuccess('数据获取成功', $datas);
			}
		}
	}
	
	/**
	 * 退货发货
	 */
	public function servicesDeliver(){
		if($this->request->isPost()){
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['services_id', 'require|number', '售后ID不能为空|售后ID格式不正确'],
				['express_id', 'require', '快递ID不能为空'],
				['express_number', 'require', '快递单号不能为空']
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}
			
			//查询用户信息
			$user = getUserInfo($this->param['token']);
			if(empty($user)){
				return ajaxError(config('error_code')['token']['msg'], config('error_code')['token']['error']);
			}else{
				$this->param['user_id'] = $user['id'];
			}
			
			$result = model('Services')->deliver($this->param);
			if($result['error'] == 0){
				return ajaxSuccess($result['msg'], $result['data']);
			}else{
				return ajaxError($result['msg']);
			}			
		}
	}
	
	/**
	 * 撤回售后
	 */
	public function recall(){
		if($this->request->isPost()){
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['services_id', 'require|number', '服务ID不能为空|服务ID格式不正确'],
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}
			
			//查询用户信息
			$user = getUserInfo($this->param['token']);
			if(empty($user)){
				return ajaxError(config('error_code')['token']['msg'], config('error_code')['token']['error']);
			}
			
			$result = model('Services')->recall($this->param);
			if($result['error'] == 0){
				return ajaxSuccess($result['msg'], $result['data']);
			}else{
				return ajaxError($result['msg']);
			}	
		}
	}

	
}
