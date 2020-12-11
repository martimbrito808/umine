<?php
namespace app\api\logic;

use think\Model;
use think\Db;
use app\api\model\Goods;
use app\api\model\GoodsCategory;
use app\api\model\Integral;
use app\api\model\Order;
use app\api\model\User;
use app\api\model\ShippingAddress;

class GoodsLogic extends Model{

    private $goodsModel = '';
    private $goodsCategoryModel = '';
    private $integralModel = '';
    private $orderModel = '';
    private $orderDetailsModel = '';
    private $adressModel = '';
    private $userModel = '';
    protected $return_data=[
        'code'=>200,
        'msg'=>'success',
        'data'=>[],
        'count'=>0
    ];

    function __construct () {
        parent::__construct ();
        $this->goodsModel = new Goods();
        $this->goodsCategoryModel = new GoodsCategory();
        $this->integralModel = new Integral();
        $this->orderModel = new Order();
        $this->userModel = new User();
        $this->adressModel = new ShippingAddress();
    }

    /**
     * 商品分类
     */
    public function goodsCategory()
    {
        $list = $this->goodsCategoryModel
            ->order('add_time desc')
            ->select();
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 商品列表
     */
    public function goodsList($where,$order)
    {
        $model = $this->goodsModel;
            if(!empty($order)){
                $model = $model->order($order);
            }
        $list = $model->where($where)
            ->select();
        $data = [];
        $i = 0;
        $status = [1=>'主金币',2=>'子金币','3'=>'金币'];
        foreach($list as $k=>$v){
            $list[$k]['url'] = request()->domain().'/static/image/'.$v['image'];
            $list[$k]['title'] = $v['name'];
            $list[$k]['status_name'] = $status[$v['status']];
            if($k!=0 && $k%2==0){
                ++$i;
            }
            $data[$i][] = $v;
        }
        $this->return_data['data'] = $data;
        return $this->return_data;
    }

    /**
     * 商品详情
     */
    public function goodsDetails($where)
    {
        $list = $this->goodsModel
            ->where($where)
            ->find();
        $list = transArray($list);
        $status = [1=>'主金币',2=>'子金币','3'=>'金币'];
        if(!empty($list)){
            $list['status_name'] = $status[$list['status']];
            $list['images'] = json_decode($list['images']);
            $list['main_image'][] = request()->domain().'/static/image/'.$list['image'];
            if($list['images'] != '' && $list['image'] != 'null'){
                $list['details_images'] = explode(',',$list['images']);
                foreach($list['details_images'] as $k=>$v){
                    $list['details_images'][$k] = request()->domain().'/static/image/'.$v;
                    $list['main_image'][] = request()->domain().'/static/image/'.$v;
                }
            }else{
                $list['details_images'] = [];
            }
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 收货地址
     */
    public function address($where)
    {
        $list = $this->adressModel
            ->where($where)
            ->order('add_time desc')
            ->select();
        foreach($list as $k=>$v){
            $list[$k]['flag'] = $v['default'] == 1?false:true;
            $list[$k]['address'] = $v['province'].$v['city'].$v['area'].$v['address'];
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 收货地址
     */
    public function addressList($where)
    {
        $list = $this->adressModel
            ->where($where)
            ->find();
        $list['flag'] = $list['default'] == 1?false:true;
        $list['county'] = $list['area'];
        $list['areaCode'] = $list['area_code'];
        $list['tel'] = $list['phone'];
        $list['addressDetail'] = $list['address'];
        $list['isDefault'] = $list['default']==2?true:false;
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     *  收货地址添加
     */
    public function addressAdd($arr,$is_edit,$user_id)
    {
        try{
            if($is_edit>0){
                if($arr['default'] == 2){
                    $this->adressModel
                        ->where('user_id',$user_id)
                        ->where('default',2)
                        ->update(['default'=>1]);
                }
                $this->adressModel->where('id',$is_edit)->update($arr);
                $this->return_data['code'] = 200;
                $this->return_data['msg'] = '操作成功';
            }else{
                if($arr['default'] == 2){
                    $this->adressModel
                        ->where('user_id',$user_id)
                        ->where('default',2)
                        ->update(['default'=>1]);
                }
                $this->adressModel->isUpdate(false)->data($arr)->save();
            }
            $this->return_data['msg'] = '操作成功';
        }catch (\Exception $e){
            $this->return_data['msg'] = '操作失败';
            $this->return_data['code'] = 201;
        }
        return $this->return_data;
    }

    /**
     * 设置为默认地址
     */
    public function addressEdit($arr,$id)
    {
        try{
            Db::startTrans();
            if($arr['default'] == 2){
                $this->adressModel
                    ->where('default',2)
                    ->where('user_id',$id)
                    ->update(['default'=>1]);
            }
            $this->adressModel->isUpdate(true)->update($arr);
            Db::commit();
            $this->return_data['msg'] = '操作成功';
        }catch (\Exception $e){
            $this->return_data['msg'] = '操作失败';
            $this->return_data['code'] = 201;
            $this->return_data['data'] = $e->getMessage();
            Db::rollback();
        }
        return $this->return_data;
    }

    /**
     *  收货地址删除
     */
    public function addressDelete($param)
    {
        $res = $this->adressModel->where('id','=',$param['id'])->delete();
        if($res>0){
            $this->return_data['msg'] = '操作成功';
        }else{
            $this->return_data['msg'] = '操作失败';
            $this->return_data['code'] = 201;
        }
        return $this->return_data;
    }


}