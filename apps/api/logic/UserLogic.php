<?php
namespace app\api\logic;
;
use think\Model;
use think\Db;
use app\api\model\User;
use app\api\model\UserIntegralRecord;
use app\api\model\UserCoupon;
use app\api\model\Message;
use app\api\model\Order;
use app\api\model\ShippingAddress;
use app\api\model\Banner;

class UserLogic extends Model{

    private $userModel = '';
    private $userIntegralRecordModel = '';
    private $userCouponModel = '';
    private $messageModel = '';
    private $orderModel = '';
    private $shippingAddressModel = '';
    private $bannerModel = '';
    protected $return_data=[
        'code'=>200,
        'msg'=>'success',
        'data'=>[],
        'count'=>0
    ];

    function __construct () {
        parent::__construct ();
        $this->userModel = new User();
        $this->userIntegralRecordModel = new UserIntegralRecord();
        $this->userCouponModel = new UserCoupon();
        $this->messageModel = new Message();
        $this->orderModel = new Order();
        $this->shippingAddressModel = new ShippingAddress();
        $this->bannerModel = new Banner();
    }

    /**
     * 用户详情
     */
    public function userDetails($where)
    {
        $list = $this->userModel->where($where)->find();
        $list = transArray($list);
        $garden = ['1'=>'普通会员','2'=>'白银会员','3'=>'黄金会员','4'=>'钻石会员'];
        $garden_num = ['1'=>'500','2'=>'3000','3'=>'5000','4'=>'999999'];
        $list['garden_num'] = $garden_num[$list['gard_id']];
        $list['progress_bar'] = number_format($list['upgrade_gold']/$list['garden_num'],2)*100;
        $list['garden_name'] = $garden[$list['gard_id']];
        $list['user_name'] = $list['name'];
        if(isset($list['image']) && $list['image'] != ''){
            $list['image'] = request()->domain().'/static/image/'.$list['image'];
        }
        $list['qr_code'] = request()->domain().'/static/qrcode/'.$list['qr_code'];
        /*if($list['name']==''){
            $list['name'] = $list['phone'];
        }*/
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 用户编辑
     */
    public function userEdit($arr)
    {
        $res = $this->userModel->isUpdate(true)->save($arr);
        if($res){
            $this->return_data['msg'] = '编辑成功';
            return $this->return_data;
        }else{
            $this->return_data['data'] = '编辑失败';
            $this->return_data['code'] = 201;
            return $this->return_data;
        }
    }

    /**
    * 鸡蛋兑换券
    */
    public function eggCoupon($where,$id,$coupon_id)
    {
        $list = $this->userCouponModel
            ->where($where)
            ->count();
        if($list>0){
            if($coupon_id==1){
                $time = $this->userModel
                    ->where('id','=',$id)
                    ->value('last_use_four_time');
            }elseif($coupon_id==2){
                $time = $this->userModel
                    ->where('id','=',$id)
                    ->value('last_use_egg_time');
            }elseif($coupon_id==3){
                $time = $this->userModel
                    ->where('id','=',$id)
                    ->value('last_use_rice_time');
            }elseif($coupon_id==4){
                $time = $this->userModel
                    ->where('id','=',$id)
                    ->value('last_use_paper_time');
            }
            $time = transArray($time);
            if(empty($time)){
                $this_time = date('Y-m-d'.' '.'00:00:00');
            }else{
                $this_time = date('Y-m-d H:i:s',$time+30*24*60*60);
            }

            /*if($time+30*24*60*60 < time()){
                $this_time = date('Y-m-d H:i:s',time()-1);
            }else{
                $this_time = date('Y-m-d H:i:s',$time+30*24*60*60);
            }*/
            $data = [
                'num'=>$list,
                'time'=>$this_time,
            ];
        }else{
            $data = [];
        }
        $this->return_data['data'] = $data;
        return $this->return_data;
    }

    /**
     * 兑换记录
     */
    public function exchangeRecords($where)
    {
        $list = $this->orderModel
            ->where($where)
            ->order('add_time desc')
            ->select();
        $coupon =[1=> '四件套', 2=> '鸡蛋', 3=> '大米', 4=> '斑布纸巾'];
        foreach($list as $k=>$v){
            $list[$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
            $list[$k]['user_coupon_name'] = $coupon[$v['coupon_id']];
            $list[$k]['status_name'] = $v['status']==1?'未发货':'已发货';
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 金币记录
     */
    public function mainGold($where)
    {
        $list = $this->userIntegralRecordModel
            ->where($where)
            ->order('add_time desc')
            ->select();
        $status_name = [1=>'后台更改',2=>'用户消费',3=>'主推用户获得金币赠送'];
        foreach($list as $k=>$v){
            $list[$k]['status_name'] = $status_name[$v['status']];
            $list[$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
        }
        $this->return_data['data'] = $list;
        return $this->return_data;         
    }

    /**
     * 用户通知
     */
    public function notice()
    {
        $is_have = $this->messageModel
            ->where('status',2)
            ->order('add_time desc')
            ->select();
        $is_have = transArray($is_have);
        foreach($is_have as $k=>$v){
            $is_have[$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
        }
        $this->return_data['data'] = $is_have;
        return $this->return_data;
    }

    /**
     * 用户通知
     */
    public function oneNotice()
    {
        $is_have = $this->messageModel
            ->where('status',2)
            ->order('add_time desc')
            ->find();
        $is_have = transArray($is_have);
        if(empty($is_have)){
            $is_have['title'] = '';
        }
        $this->return_data['data'] = $is_have;
        return $this->return_data;
    }

    /**
     * 券的立即使用
     */
    public function useEggCoupon($param,$id,$phone)
    {
        $time = time();
        if($param['coupon_id'] == 1){
            //查券的最后一次使用时间,查询券是否还有未使用的
            $use_time = $this->userModel
                ->where('id',$id)
                ->value('last_use_four_time');
            if($use_time+30*24*60*60>=$time){
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '还未到使用时间';
                return $this->return_data;
            }
            $address = $this->shippingAddressModel
                ->where('user_id',$id)
                ->where('default',2)
                ->find();
            $address = transArray($address);
            if(empty($address)){
                $this->return_data['code'] = 205;
                $this->return_data['msg'] = '请先设置好默认收货地址';
                return $this->return_data;
            }
            try{
                Db::startTrans();
                $is_use = $this->userCouponModel
                    ->where('user_id',$id)
                    ->where('status',2)
                    ->where('coupon_id',1)
                    ->lock(true)
                    ->find();
                $is_use = transArray($is_use);
                if(empty($is_use)){
                    Db::rollback();
                    $this->return_data['code'] = 201;
                    $this->return_data['msg'] = '暂无可用兑换券';
                    return $this->return_data;
                }
                $arr = [
                    'user_id' =>$id,
                    'add_time' =>$time,
                    'pay_time' =>$time,
                    'coupon_id' =>1,
                    'user_coupon_id' =>$is_use['id'],
                    'status' =>1,
                    'address' =>$address['province'].$address['city'].$address['area'].$address['address'],
                    'phone'=>$phone,
                ];
                $this->userCouponModel
                    ->where('id',$is_use['id'])
                    ->update(['status'=>1,'use_time'=>$time]);
                $this->userModel
                    ->where('id',$id)
                    ->update(['last_use_four_time'=>$time]);
                $this->orderModel
                    ->isUpdate(false)
                    ->data($arr)
                    ->save();
                Db::commit();
                $this->return_data['code'] = 200;
                $this->return_data['msg'] = '兑换成功';
                return $this->return_data;
            }catch (\Exception $e){
                Db::rollback();
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '兑换失败';
                $this->return_data['data'] = $e->getMessage();
                return $this->return_data;
            }
        }elseif($param['coupon_id'] == 2){
            //查券的最后一次使用时间,查询券是否还有未使用的
            $use_time = $this->userModel
                ->where('id',$id)
                ->value('last_use_egg_time');
            if($use_time+30*24*60*60>=$time){
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '还未到使用时间';
                return $this->return_data;
            }
            $address = $this->shippingAddressModel
                ->where('user_id',$id)
                ->where('default',2)
                ->find();
            $address = transArray($address);
            if(empty($address)){
                $this->return_data['code'] = 205;
                $this->return_data['msg'] = '请先设置好默认收货地址';
                return $this->return_data;
            }
            try{
                Db::startTrans();
                $is_use = $this->userCouponModel
                    ->where('user_id',$id)
                    ->where('status',2)
                    ->where('coupon_id',2)
                    ->lock(true)
                    ->find();
                $is_use = transArray($is_use);
                if(empty($is_use)){
                    Db::rollback();
                    $this->return_data['code'] = 201;
                    $this->return_data['msg'] = '暂无可用兑换券';
                    return $this->return_data;
                }
                $arr = [
                    'user_id' =>$id,
                    'add_time' =>$time,
                    'pay_time' =>$time,
                    'coupon_id' =>2,
                    'user_coupon_id' =>$is_use['id'],
                    'status' =>1,
                    'address' =>$address['province'].$address['city'].$address['area'].$address['address'],
                    'phone'=>$phone,
                ];
                $this->userCouponModel
                    ->where('id',$is_use['id'])
                    ->update(['status'=>1,'use_time'=>$time]);
                $this->userModel
                    ->where('id',$id)
                    ->update(['last_use_egg_time'=>$time]);
                $this->orderModel
                    ->isUpdate(false)
                    ->data($arr)
                    ->save();
                Db::commit();
                $this->return_data['code'] = 200;
                $this->return_data['msg'] = '兑换成功';
                return $this->return_data;
            }catch (\Exception $e){
                Db::rollback();
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '兑换失败';
                return $this->return_data;
            }
        }elseif($param['coupon_id'] == 3){
            //查券的最后一次使用时间,查询券是否还有未使用的
            $use_time = $this->userModel
                ->where('id',$id)
                ->value('last_use_rice_time');
            if($use_time+30*24*60*60>=$time){
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '还未到使用时间';
                return $this->return_data;
            }
            $address = $this->shippingAddressModel
                ->where('user_id',$id)
                ->where('default',2)
                ->find();
            $address = transArray($address);
            if(empty($address)){
                $this->return_data['code'] = 205;
                $this->return_data['msg'] = '请先设置好默认收货地址';
                return $this->return_data;
            }
            try{
                Db::startTrans();
                $is_use = $this->userCouponModel
                    ->where('user_id',$id)
                    ->where('status',2)
                    ->where('coupon_id',3)
                    ->lock(true)
                    ->find();
                $is_use = transArray($is_use);
                if(empty($is_use)){
                    Db::rollback();
                    $this->return_data['code'] = 201;
                    $this->return_data['msg'] = '暂无可用兑换券';
                    return $this->return_data;
                }
                $arr = [
                    'user_id' =>$id,
                    'add_time' =>$time,
                    'pay_time' =>$time,
                    'coupon_id' =>3,
                    'user_coupon_id' =>$is_use['id'],
                    'status' =>1,
                    'address' =>$address['province'].$address['city'].$address['area'].$address['address'],
                    'phone'=>$phone,
                ];
                $this->userCouponModel
                    ->where('id',$is_use['id'])
                    ->update(['status'=>1,'use_time'=>$time]);
                $this->userModel
                    ->where('id',$id)
                    ->update(['last_use_rice_time'=>$time]);
                $this->orderModel
                    ->isUpdate(false)
                    ->data($arr)
                    ->save();
                Db::commit();
                $this->return_data['code'] = 200;
                $this->return_data['msg'] = '兑换成功';
                return $this->return_data;
            }catch (\Exception $e){
                Db::rollback();
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '兑换失败';
                return $this->return_data;
            }
        }elseif($param['coupon_id'] == 4){
            //查券的最后一次使用时间,查询券是否还有未使用的
            $use_time = $this->userModel
                ->where('id',$id)
                ->value('last_use_paper_time');
            if($use_time+30*24*60*60>=$time){
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '还未到使用时间';
                return $this->return_data;
            }
            $address = $this->shippingAddressModel
                ->where('user_id',$id)
                ->where('default',2)
                ->find();
            $address = transArray($address);
            if(empty($address)){
                $this->return_data['code'] = 205;
                $this->return_data['msg'] = '请先设置好默认收货地址';
                return $this->return_data;
            }
            try{
                Db::startTrans();
                $is_use = $this->userCouponModel
                    ->where('user_id',$id)
                    ->where('status',2)
                    ->where('coupon_id',4)
                    ->lock(true)
                    ->find();
                $is_use = transArray($is_use);
                if(empty($is_use)){
                    Db::rollback();
                    $this->return_data['code'] = 201;
                    $this->return_data['msg'] = '暂无可用兑换券';
                    return $this->return_data;
                }
                $arr = [
                    'user_id' =>$id,
                    'add_time' =>$time,
                    'pay_time' =>$time,
                    'coupon_id' =>4,
                    'user_coupon_id' =>$is_use['id'],
                    'status' =>1,
                    'address' =>$address['province'].$address['city'].$address['area'].$address['address'],
                    'phone'=>$phone,
                ];
                $this->userCouponModel
                    ->where('id',$is_use['id'])
                    ->update(['status'=>1,'use_time'=>$time]);
                $this->userModel
                    ->where('id',$id)
                    ->update(['last_use_paper_time'=>$time]);
                $this->orderModel
                    ->isUpdate(false)
                    ->data($arr)
                    ->save();
                Db::commit();
                $this->return_data['code'] = 200;
                $this->return_data['msg'] = '兑换成功';
                return $this->return_data;
            }catch (\Exception $e){
                Db::rollback();
                $this->return_data['code'] = 201;
                $this->return_data['msg'] = '兑换失败';
                return $this->return_data;
            }
        }

    }

    /**
     * banner
     */
    public function getBanner()
    {
        $list = $this->bannerModel
            ->select();
        foreach($list as $k=>$v){
            $list[$k]['image'] = request()->domain().'/static/image/'.$v['image'];
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

}
