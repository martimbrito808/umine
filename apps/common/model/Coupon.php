<?php
namespace app\common\model;
use think\Model;
class Coupon extends Model{

    protected $autoWriteTimestamp = true;

	/**
	 * 添加优惠券
	 * user_id  用户ID
	 * stime 开始时间
	 * etime 结束时间
	 * full 使用条件
	 * price 优惠券价格
	 */
	public function push($param){
	    //店铺Id
        //优惠券数量
        if(empty($param['num'])) {
            return arrData('数量不能为空');
        }

        $param['full'] = toPrice($param['full']);
        $param['price'] = toPrice($param['price']);
        $param['status'] = 1;
        $param['create_time'] = time();
        if($this->allowField(true)->save($param) == false){
            return arrData('添加失败');
        }else{
            return arrData('添加成功', 0,  $this->id);
        }

	}
	
	/**
	 * 删除优惠券
	 */
	public function delInfo($param){
		$user_id = $param['user_id'];
		$id = $param['coupon_id'];
		$result = $this->get(array('id'=>$id, 'user_id'=>$user_id));
		if(empty($result)){
			return arrData('查询不到信息');
		}else{
			if($this->where('id', $id)->delete() == false){
				return arrData('删除失败');
			}else{
				model('CouponCard')->where(array('coupon_id'=>$id))->delete();
				return arrData('数据删除成功', 0);
			}
		}
	}
}