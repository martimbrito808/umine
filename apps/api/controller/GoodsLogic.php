<?php
namespace app\api\logic;

use app\api\logic\WxPaymentLogic;
use think\Model;
use think\Db;
use app\api\model\Goods;
use app\api\model\GoodsComment;
use app\api\model\GoodsUser;
use app\api\model\GoodsCategory;
use app\api\model\Order;
use app\api\model\User;
use app\api\model\UserFollow;
use app\api\model\UserAddress;
use app\api\model\UserCoupon;
use app\api\model\UserCollection;
use app\api\model\UserIntegral;
use app\api\model\UserPrice;
use app\api\model\Region;
use app\api\model\WantBuy;
use app\api\model\Turntable;
use app\api\model\TurntableDetails;
use app\api\model\PrizeLog;
use app\api\model\Coupon;

class GoodsLogic extends Model{

    private $goodsModel = '';
    private $goodsCommentModel = '';
    private $goodsUserModel = '';
    private $goodsCategoryModel = '';
    private $orderModel = '';
    private $userModel = '';
    private $userFollowModel = '';
    private $userIntegralModel = '';
    private $userPriceModel = '';
    private $userAddressModel = '';
    private $userCouponModel = '';
    private $userCollectionModel = '';
    private $regionModel = '';
    private $wantBuyModel = '';
    private $turntableModel = '';
    private $turntableDetailsModel = '';
    private $prizeLogModel = '';
    private $couponModel = '';
    protected $return_data=[
        'code'=>200,
        'msg'=>'success',
        'data'=>[],
        'count'=>0
    ];

    function __construct () {
        parent::__construct ();
        $this->goodsModel = new Goods();
        $this->goodsCommentModel = new GoodsComment();
        $this->goodsUserModel = new GoodsUser();
        $this->goodsCategoryModel = new GoodsCategory();
        $this->orderModel = new Order();
        $this->userModel = new User();
        $this->userFollowModel = new UserFollow();
        $this->userIntegralModel = new UserIntegral();
        $this->userPriceModel = new UserPrice();
        $this->userAddressModel = new UserAddress();
        $this->userCouponModel = new UserCoupon();
        $this->userCollectionModel = new UserCollection();
        $this->regionModel = new Region();
        $this->wantBuyModel = new WantBuy();
        $this->turntableModel = new Turntable();
        $this->turntableDetailsModel = new TurntableDetails();
        $this->prizeLogModel = new PrizeLog();
        $this->couponModel = new Coupon();
    }

    /**
     * 商品留言
     */
    public function goodsMessage($arr,$param)
    {
        try{
            Db::startTrans();
            $this->goodsCommentModel->isUpdate(false)->data($arr)->save();
            $this->goodsUserModel->where('id',$param['goods_id'])->setInc('comment_num');
            Db::commit();
            $this->return_data['msg'] = '留言成功';
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['msg'] = '留言失败';
            $this->return_data['code'] = 201;
            $this->return_data['data'] = $e->getMessage();
        }
        return $this->return_data;
    }

    /**
     * 商品查看数
     */
    public function goodsSee($where)
    {
        $res = $this->goodsUserModel->where($where)->setInc('see_num');
        if(!$res){
            $this->return_data['msg'] = '失败';
            $this->return_data['code'] = 201;
        }
        return $this->return_data;
    }

    /**
     * 商品收藏
     */
    public function goodsCollection($where,$arr)
    {
        try{
            Db::startTrans();
            $this->userCollectionModel->isUpdate(false)->data($arr)->save();
            $this->goodsUserModel->where($where)->setInc('collection_num');
            Db::commit();
            $this->return_data['msg'] = '收藏成功';
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['msg'] = '收藏失败';
            $this->return_data['code'] = 201;
            $this->return_data['data'] = $e->getMessage();
        }
        return $this->return_data;
    }
    
    /**
     * 取消收藏
     */
    public function cancelCollection($where,$collection_where)
    {
        try{
            Db::startTrans();
            $this->userCollectionModel->where($collection_where)->delete();
            $this->goodsUserModel->where($where)->setDec('collection_num');
            Db::commit();
            $this->return_data['msg'] = '取消收藏成功';
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['msg'] = '取消收藏失败';
            $this->return_data['code'] = 201;
            $this->return_data['data'] = $e->getMessage();
        }
        return $this->return_data;
    }

    /**
     * 直接购买页面数据获取
     */
    public function purchaseData($user_id,$param)
    {
        //首先先把过期的优惠券状态修改为过期
        $this->userCouponModel
            ->where('end_time','<=',time())
            ->where('status','=',1)
            ->update(['status'=>3]);
        $address = $this->userAddressModel
            ->where('user_id',$user_id)
            ->where('default',2)
            ->findOrEmpty();
        $address = transArray($address);
        if(empty($address)){
            $address = $this->userAddressModel
                ->where('user_id',$user_id)
                ->where('default',1)
                ->findOrEmpty();
        }
        $coupon_where[] = ['full_price','<=',$param['price']];
        $coupon = $this->userCouponModel
            ->hasWhere('coupon',$coupon_where)
            ->with('coupon')
            ->where('UserCoupon.status',1)
            ->where('u_id',$user_id)
            ->findOrEmpty();
        $coupon = transArray($coupon);
        if(!empty($coupon)){
            $coupon['reduce_price'] = $coupon['coupon']['reduce_price'];
        }
        $data = [
            'address'=>$address,
            'coupon'=>$coupon
        ];
        $this->return_data['data'] = $data;
        return $this->return_data;
    }

    /**
     * 收货地址选择
     */
    public function userAddress($user_id)
    {
        $list = $this->userAddressModel
            ->where('user_id',$user_id)
            ->where('default','neq',3)
            ->select();
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 收货地址选择
     */
    public function addressDetails($where)
    {
        $list = $this->userAddressModel
            ->where($where)
            ->find();
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 收货地址删除
     */
    public function addressEdit($arr)
    {
        if(isset($arr['id'])){
            if($arr['default']==2){
                $this->userAddressModel
                    ->where('default',2)
                    ->where('user_id',$arr['user_id'])
                    ->update(['default'=>1]);
            }
            $res = $this->userAddressModel
                ->isUpdate(true)
                ->save($arr);
            $msg = '操作';
        }else{
            if($arr['default']==2){
                $this->userAddressModel
                    ->where('default',2)
                    ->where('user_id',$arr['user_id'])
                    ->update(['default'=>1]);
            }
            $res = $this->userAddressModel
                ->isUpdate(false)
                ->data($arr)
                ->save();
            $msg = '添加';
        }
        if($res){
            $this->return_data['msg'] = $msg.'成功';
            return $this->return_data;
        }else{
            $this->return_data['msg'] = $msg.'失败';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
    }

    /**
     * 收货地址删除
     */
    public function addressDel($arr)
    {
        $res = $this->userAddressModel
                ->isUpdate(true)
                ->saveAll($arr);
        if($res){
            $this->return_data['msg'] = '删除成功';
            return $this->return_data;
        }else{
            $this->return_data['msg'] = '删除失败';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
    }

    /**
     * 省市区三级联动
     */
    public function getParentCity($where)
    {
        $list = $this->regionModel
            ->where($where)
            ->select();
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 优惠券选择
     */
    public function userCoupon($where,$uc_where,$user_id)
    {
        $this->userCouponModel
            ->where('end_time','<=',time())
            ->where('status','=',1)
            ->update(['status'=>3]);
        $list = $this->userCouponModel
            ->hasWhere('coupon',$where)
            ->with('coupon')
            ->where($uc_where)
            ->where('u_id',$user_id)
            ->select();
        $list = transArray($list);
        foreach($list as $k=>$v){
            $list[$k]['reduce_price'] = $v['coupon']['reduce_price'];
            $list[$k]['full_price'] = $v['coupon']['full_price'];
            $list[$k]['time'] = date('Y.m.d',$v['start_time']).'-'.date('Y.m.d',$v['end_time']);
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }


    /**
     * 立即购买
     */
    public function buyNow($arr,$pay_data)
    {
        $res = $this->orderModel->isUpdate(false)->data($arr)->save();
        $turntable = Db::name('turntable')->find();
        if($arr['pay_type'] == 1 && $arr['pay_price']>0){
            $logic = new WxPaymentLogic();
            $pay = $logic->wxPay($pay_data);
            if($turntable['start_time']>time() && $turntable['end_time']<time()){
                $pay['data']['is_activity'] = 1;//是
            }else{
                $pay['data']['is_activity'] = 2;//否
            }
            if($res && $pay['code']==200){
                $pay['data']['order_id'] = $this->orderModel->id;
                $this->return_data['msg'] = '支付成功';
                $this->return_data['code'] = 200;
                $this->return_data['data'] = $pay['data'];
            }else{
                $this->return_data['msg'] = '失败';
                $this->return_data['code'] = 201;
            }
        }else if($arr['pay_type'] == 1 && $arr['pay_price']==0){
            if($turntable['start_time']>time() && $turntable['end_time']<time()){
                $this->return_data['data']['is_activity'] = 1;
            }else{
                $this->return_data['data']['is_activity'] = 2;
            }
            $this->return_data['msg'] = '支付成功';
            $this->return_data['code'] = 200;
            $this->return_data['data']['order_sn'] = $arr['order_sn'];
            $this->return_data['data']['order_id'] = $this->orderModel->id;
        }else{
            if($turntable['start_time']>time() && $turntable['end_time']<time()){
                $this->return_data['data']['is_activity'] = 1;
            }else{
                $this->return_data['data']['is_activity'] = 2;
            }
            $this->return_data['code'] = 200;
            $this->return_data['data']['order_sn'] = $arr['order_sn'];
            $this->return_data['data']['order_id'] = $this->orderModel->id;
        }
        return $this->return_data;
    }

    /**
     * 余额支付(确定后调用,或者微信支付为0是调用)
     */
    public function confirmPayment($where,$user_id)
    {
        $time = time();
        $order = $this->orderModel->where($where)->findOrEmpty();
        $order = transArray($order);
        if(empty($order)){
            $this->return_data['msg'] = '支付失败';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
        $user = $this->userModel->where('id',$user_id)->field('money,integral')->find();
        if($order['pay_price']>$user['money']){
            $this->return_data['msg'] = '余额不足';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
        $balance_log = [
            'before' =>$user['money'],
            'change' =>$order['pay_price'],
            'after' =>$user['money']-$order['pay_price'],
            'add_time' =>$time,
            'u_id' =>$user_id,
            'type' =>1,
        ];
        //判断今天是否增加了购物积分,没有则增加20积分同时添加积分变动记录
        $today_integral = $this->userIntegralModel
            ->whereTime('add_time', 'today')
            ->where('u_id',$user_id)
            ->where('type',8)
            ->findOrEmpty();
        $today_integral = transArray($today_integral);
        $user_arr = [
            'id'=>$user_id,
            'money'=>$user['money']-$order['pay_price']
        ];
        $user_coupon = [
            'id'=>$order['user_coupon_id'],
            'status'=>2
        ];
        $goods_user = [
            'id'=>$order['good_id'],
            'sold'=>2
        ];
        try{
            Db::startTrans();
            if(empty($today_integral)){
                $integral_log = [
                    'before' =>$user['integral'],
                    'change' =>20,
                    'after' =>$user['integral']+20,
                    'add_time' =>$time,
                    'u_id' =>$user_id,
                    'type' =>8
                ];
                $user_arr['integral'] = $user['integral']+20;
                $this->userIntegralModel->isUpdate(false)->data($integral_log)->save();
            }
            $this->userModel->isUpdate(true)->save($user_arr);
            $this->userPriceModel->isUpdate(false)->data($balance_log)->save();
            //修改订单状态
            $this->orderModel->where($where)->update(['status'=>2,'pay_time'=>time()]);
            $this->userCouponModel->isUpdate(true)->save($user_coupon);
            $this->goodsUserModel->isUpdate(true)->save($goods_user);
            Db::commit();
            $this->return_data['msg'] = '支付成功';
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['msg'] = '支付失败';
            $this->return_data['code'] = 201;
            $this->return_data['data'] = $e->getMessage();
        }
        return $this->return_data;
    }

    /**
     * 积分商城
     */
    public function integralGoods($user_id)
    {
        $user = $this->userModel->where('id',$user_id)->value('integral');
        $goods = $this->goodsModel->where('status',1)->order('add_time desc')->select();
        foreach ($goods as $k=>$v){
            $goods[$k]['main_image'] = request()->domain().'/static/image/'.$v['main_image'];
        }
        $data = [
            'user'=>$user,
            'goods'=>$goods
        ];
        $this->return_data['data'] = $data;
        return $this->return_data;
    }

    /**
     * 积分商城商品详情
     */
    public function integralGoodsDetails($where)
    {
        $goods = $this->goodsModel->where($where)->find();
        $goods = transArray($goods);
        $arr = explode(',',json_decode($goods['images'],true));
        array_unshift($arr,$goods['main_image']);
        foreach ($arr as $k=>$v){
            $goods['images_arr'][$k] = request()->domain().'/static/image/'.$v;
        }
        $this->return_data['data'] = $goods;
        return $this->return_data;
    }

    /**
     * 积分商品兑换
     */
    public function integralExchange($arr,$param,$user_id)
    {
        //先查询用户积分,减积分,添加积分变动记录,添加订单
        $integral = $this->userModel->where('id',$user_id)->value('integral');
        if($param['price']>$integral){
            $this->return_data['msg'] = '积分不足';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
        $integral_log = [
            'before' =>$integral,
            'change' =>'-'.$param['price'],
            'after' =>$integral-$param['price'],
            'add_time' =>time(),
            'u_id' =>$user_id,
            'type' =>9
        ];
        $user_arr = [
            'integral'=>$integral-$param['price'],
            'id'=>$user_id
        ];
        try{
            Db::startTrans();
            $this->userIntegralModel->isUpdate(false)->data($integral_log)->save();
            $this->userModel->isUpdate(true)->save($user_arr);
            $this->orderModel->isUpdate(false)->data($arr)->save();
            Db::commit();
            $this->return_data['msg'] = '兑换成功';
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['msg'] = '兑换失败';
            $this->return_data['code'] = 201;
            $this->return_data['data'] = $e->getMessage();
        }
        return $this->return_data;
    }

    /**
     * 他的主页
     */
    public function hisHomepage($user_id,$this_id)
    {
        $user = $this->userModel
            ->where('id',$user_id)
            ->field('image,name,is_image,add_time')
            ->find();
        $user = transArray($user);
        $user['is_follow'] = 1;//已关注
        $user['sell'] = 0;//卖出的商品数
        $user['earn'] = 0;//赚的钱
        $user['time'] = ceil((time()-$user['add_time'])/(24*60*60));//注册天数
        $user['time'] = ceil((time()-$user['add_time'])/(24*60*60));//注册天数
        $is_follow = $this->userFollowModel
            ->where('u_id',$user_id)
            ->where('follow_u_id',$this_id)
            ->findOrEmpty();
        $is_follow = transArray($is_follow);
        if(empty($is_follow)){
            $user['is_follow'] = 2;//未关注
        }
        $goods = $this->orderModel
            ->where('sale_id',$user_id)
            ->where('type',1)
            ->where('status',2)
            ->field('count(*) num,sum(pay_price) all_price')
            ->select();
        $goods = transArray($goods);
        if(!empty($goods)){
            if($goods[0]['num']>0){
                $user['sell'] = $goods[0]['num'];//卖出的商品数
                $user['earn'] = $goods[0]['all_price'];//赚的钱
            }
        }
        $follow = $this->userFollowModel
            ->where('follow_u_id',$user_id)
            ->count();
        $user['follow'] = $follow;//关注数
        $fans = $this->userFollowModel
            ->where('u_id',$user_id)
            ->count();
        $user['fans'] = $fans;//粉丝
        $this->return_data['data'] = $user;
        return $this->return_data;
    }

    /**
     * 关注他
     */
    public function follow($arr)
    {
        $res = $this->userFollowModel
            ->isUpdate(false)
            ->data($arr)
            ->save();
        if($res){
            $this->return_data['msg'] = '关注成功';
            return $this->return_data;
        }else{
            $this->return_data['msg'] = '关注失败';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
    }

    /**
     * 取消关注
     */
    public function cancelFollow($where)
    {
        $res = $this->userFollowModel
            ->where($where)
            ->delete();
        if($res){
            $this->return_data['msg'] = '取消关注成功';
            return $this->return_data;
        }else{
            $this->return_data['msg'] = '取消关注失败';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
    }

    /**
     * 他要卖的
     */
    public function wantSell($where)
    {
        $list = $this->goodsUserModel
            ->where($where)
            ->field('name,id,price,image')
            ->select();
        $list = transArray($list);
        foreach($list as $k=>$v){
            $list[$k]['image'] = request()->domain().'/static/image/'.explode(',',$v['image'])[0];
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 他要买的
     */
    public function wantBuy($where)
    {
        $list = $this->wantBuyModel
            ->where($where)
            ->field('name,id,details')
            ->select();
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 积分大转盘
     */
    public function integralTurntable($user_id)
    {
        $turntable = $this->turntableModel->value('integral');
        $details = $this->turntableDetailsModel
            ->where('status',1)
            ->select();
        $log = $this->prizeLogModel
            ->with('user,turntableDetails')
            ->where('status',1)
            ->order('add_time desc')
            ->limit(100)
            ->select();
        $arr = [];
        foreach ($log as $k=>$v){
            if($v['prize_id']>0){
                $arr[] = [
                    'details'=>'恭喜'.$v['user']['name'].'抽中',
                    'name'=>$v['turntable_details']['name']
                ];
            }else{
                $arr[] = [
                    'details'=>$v['details'],
                    'name'=>$v['name']
                ];
            }
        }
        $user = $this->userModel
            ->where('id',$user_id)
            ->field('integral,is_free')
            ->find();
        $data = [
            'user'=>$user,
            'turntable'=>$turntable,
            'details'=>$details,
            'log'=>$arr
        ];
        $this->return_data['data'] = $data;
        return $this->return_data;
    }

    /**
     * 抽奖
     */
    public function luckDraw($param,$user_id)
    {
        //先判断用户积分是否充足,添加积分变动记录,减掉用户积分,添加中奖记录,类型1,2,3,4
        $user = $this->userModel
            ->where('id',$user_id)
            ->find();
        $integral = $user['integral'];
        $integral_log = [];
        $order_arr = [];
        if($user['is_free']<=0){
            if($param['integral']>$integral){
                $this->return_data['msg'] = '积分不足';
                $this->return_data['code'] = 201;
                return $this->return_data;
            }
            $integral_log[] = [
                'before' =>$integral,
                'change' =>'-'.$param['integral'],
                'after' =>$integral-$param['integral'],
                'add_time' =>time(),
                'u_id' =>$user_id,
                'type' =>10
            ];
            $user_arr = [
                'id'=>$user_id,
                'integral'=>$user['is_free']-1
            ];
        }else{
            $user_arr = [
                'id'=>$user_id,
                'integral'=>$integral-$param['integral']
            ];
        }
        $status = 1;
        $type = 1;
        $is_real = 2;
        if($param['type'] == 2){//增加用户积分
            $type = 2;
            /*$after = $integral-$param['integral']+$param['goods_name'];
            $user_arr = [
                'id'=>$user_id,
                'integral'=>$after
            ];
            $integral_log[] = [
                    'before' =>$integral-$param['integral'],
                    'change' =>$param['goods_name'],
                    'after' =>$integral-$param['integral']+$param['goods_name'],
                    'add_time' =>time(),
                    'u_id' =>$user_id,
                    'type' =>1
                ];*/
        }elseif($param['type'] == 3){//增加优惠券
            $type = 2;
            /*$today = strtotime(date('Y-m-d'));
            $coupon = $this->couponModel
                ->where('id',$param['goods_name'])
                ->value('expire_time');
            $user_coupon = [
                'u_id' =>$user_id,
                'coupon_id' =>$param['goods_name'],
                'start_time' =>$today,
                'end_time' =>$today+$coupon*24*60*60-1,
                'status' =>1,
                'add_time' =>time(),
            ];*/
        }elseif($param['type'] == 4){
           $status = 2;
        }elseif($param['type'] == 1){
            $type = 2;
            $is_real = 1;
            $order_sn = time().mt_rand(100,999);
            $order_arr = [
                'order_sn' =>$order_sn,
                'type' =>3,
                'price' =>0,
                'coupon_id' =>0,
                'user_coupon_id' =>0,
                'coupon_price' =>0,
                'pay_price' =>0,
                'status' =>2,
                'pay_type' =>4,
                'u_id' =>$user_id,
                'good_id' =>0,
                'add_time' =>time(),
                'u_name' =>$user['name'],
                'u_phone' =>$user['phone'],
                'free_price' =>0,
                'address_id' =>0,
                'goods_name' =>$param['goods_name'],
            ];
        }
        $prize_log = [
            'u_id'=>$user_id,
            'status' =>$status,
            'prize_id'=>$param['prize_id'],
            'add_time' =>time(),
            'type' =>$type,
            'is_real' =>$is_real,
            'p_type' =>$param['type'],
            'name'=>$param['goods_name'],
            'details' =>$param['goods_name'],
        ];
        try{
            Db::startTrans();
            $this->userModel->isUpdate(true)->save($user_arr);
            if(isset($user_coupon)){
                $this->userCouponModel->isUpdate(false)->data($user_coupon)->save();
            }
            if(!empty($integral_log)){
                $this->userIntegralModel->isUpdate(false)->saveAll($integral_log);
            }
            if(!empty($order_arr)){
                $this->orderModel->isUpdate(false)->data($order_arr)->save();
            }
            $this->prizeLogModel->isUpdate(false)->data($prize_log)->save();
            Db::commit();
            $this->return_data['data']['id']= $this->prizeLogModel->id;
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['code'] = 201;
            $this->return_data['msg']= '抽奖失败';
            $this->return_data['data']= $e->getMessage();
        }
        return $this->return_data;
    }

    /**
     * 立即领奖
     */
    public function nowCollarPrize($param,$where,$user)
    {
        if(isset($param['id'])){
            $list = $this->prizeLogModel
                ->where($where)
                ->findOrEmpty();
        }else{
            $list = $this->prizeLogModel
                ->whereTime('add_time','-5 minutes')
                ->where($where)
                ->findOrEmpty();
        }
        $list = transArray($list);
        if(empty($list)){
            $this->return_data['code'] = 201;
            $this->return_data['msg']= '稍后重试!';
            return $this->return_data;
        }
//        $order_sn = time().mt_rand(100,999);
        $log = [
            'id'=>$list['id'],
            'type'=>1
        ];
        if($list['p_type'] == 2){//增加用户积分
            $user = $this->userModel
                ->where('id',$list['u_id'])
                ->field('integral,is_free')
                ->find();
            $after = $user['integral']+$list['name'];
            $user_arr = [
                'id'=>$list['u_id'],
                'integral'=>$after
            ];
            $integral_log[] = [
                    'before' =>$user['integral'],
                    'change' =>$list['name'],
                    'after' =>$after,
                    'add_time' =>time(),
                    'u_id' =>$list['u_id'],
                    'type' =>1
            ];
        }elseif($list['p_type'] == 3){//增加优惠券
            $today = strtotime(date('Y-m-d'));
            $coupon = $this->couponModel
                ->where('id',$list['name'])
                ->value('expire_time');
            $user_coupon = [
                'u_id' =>$list['u_id'],
                'coupon_id' =>$list['name'],
                'start_time' =>$today,
                'end_time' =>$today+$coupon*24*60*60-1,
                'status' =>1,
                'add_time' =>time(),
            ];
        }elseif($param['type'] == 1){
            $order_arr = [
                'address_id' =>$param['address_id'],
            ];
        }
        /*$order_arr = [
            'order_sn' =>$order_sn,
            'type' =>3,
            'price' =>0,
            'coupon_id' =>0,
            'user_coupon_id' =>0,
            'coupon_price' =>0,
            'pay_price' =>0,
            'status' =>2,
            'pay_type' =>4,
            'u_id' =>$user['user_id'],
            'good_id' =>0,
            'add_time' =>time(),
            'u_name' =>$user['user_name'],
            'u_phone' =>$user['user_phone'],
            'free_price' =>0,
            'address_id' =>$param['address_id'],
            'goods_name' =>$list['details'],
        ];*/
        try{
            Db::startTrans();
            if(isset($user_arr)){
                $this->userModel->isUpdate(true)->save($user_arr);
            }
            if(isset($user_coupon)){
                $this->userCouponModel->isUpdate(false)->data($user_coupon)->save();
            }
            if(!empty($integral_log)){
                $this->userIntegralModel->isUpdate(false)->saveAll($integral_log);
            }
            if(isset($order_arr)){
                $this->orderModel->where('order_sn',$list['order_sn'])->update($order_arr);
            }
//            $this->orderModel->isUpdate(false)->data($order_arr)->save();
            $this->prizeLogModel->isUpdate(true)->save($log);
            Db::commit();
            $this->return_data['msg']= '领奖成功';
        }catch (\Exception $e){
            Db::rollback();
            $this->return_data['msg']= '领奖失败';
            $this->return_data['code']= 201;
            $this->return_data['data']= $e->getMessage();
        }
        return $this->return_data;
    }

    /**
     * 中奖记录
     */
    public function winningRecord($user_id)
    {
        $list = $this->prizeLogModel
            ->with('turntableDetails')
            ->where('u_id',$user_id)
            ->order('add_time desc')
            ->select();
        $data = [];
        $details = '很遗憾,您未中奖';
        foreach($list as $k=>$v){
            if($v['status'] == 1){
                $details = '恭您抽中'.$v['turntable_details']['name'];
            }
            $data[$k] = [
                'add_time'=>date('Y-m-d H:i:s',$v['add_time']),
                'details'=>$details,
                'id'=>$v['id'],
                'is_real'=>$v['is_real'],
                'type'=>$v['type']
            ];
        }
        $this->return_data['data']= $data;
        return $this->return_data;
    }


}