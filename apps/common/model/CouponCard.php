<?php
namespace app\common\model;

use think\Model;
use think\Db;

class CouponCard extends Model{
	
	/**
	 * 获取优惠券信息
	 */
	public function getInfo($param){
		$coupon_card_id = $param['coupon_card_id'];
		$user_id = $param['user_id'];
		
		$result = $this->get(['id'=>$coupon_card_id, 'status'=>0]);
		if(empty($result)){
			return arrData('查询不到优惠券信息');
		}else{
			if($result['user_id'] == $user_id){
				$coupon = model('Coupon')->get($result['coupon_id']);
				if(empty($coupon)){
					return arrData('优惠券已失效');
				}else{
					if($coupon['stime'] > time()){
						return arrData('优惠券不在有效期内');
					}elseif($coupon['etime'] < time()){
						return arrData('优惠券已过期');
					}else{
						$datas = [];
						$datas['id'] = $coupon_card_id;
						if(!empty($coupon['goods_id'])){
							$datas['full'] = '仅限商品： ' . getField('goods', $coupon['goods_id'], 'name') . ' 使用';
						}else{
							$datas['full'] = '满 ' . showprice($coupon['full']) . ' 可使用';
						}
						$datas['price'] = showprice($result['price']);
						$datas['stime'] = $coupon['stime'];
						$datas['etime'] = $coupon['etime'];
					}
					return arrData('数据获取成功', 0, $datas);
				}
			}else{
				return arrData('优惠券信息有误');
			}
		}
	}
	

	/**
	 * 查找可用优惠券列表
	 * user_id	用户ID
	 * total	商品总价
	 * goods_ids 商品ID数组
	 */
	public function search($param){
	
		$goods_ids = $param['goods_ids'];
		$user_id = $param['user_id'];
		$total = $param['total'];

		$coupon_list = Db::name('CouponCard')->where(['full'=>['lt', $total], 'goods_id'=>0, 'user_id'=>$user_id, 'status'=>0])->field('id, goods_id, coupon_id, stime, etime, full, price')->order('price desc')->select();
		if(count($goods_ids) == 1){
			//只有一个商品的时候，查询是否有这个商品专用的优惠券
			$coupon_goods = Db::name('CouponCard')->where(['full'=>['lt', $total], 'goods_id'=>$goods_ids[0], 'user_id'=>$user_id, 'status'=>0])->field('id, goods_id, coupon_id, stime, etime, full, price')->order('price desc')->select();
			if(!empty($coupon_goods)){
				//合并查询结果
				$coupon = array_merge($coupon_goods, $coupon_list);
			}else{
				$coupon = $coupon_list;
			}
		}else{
			$coupon = $coupon_list;
		}
		
		if(!empty($coupon)){
			foreach($coupon as $k=>$v){
				if(!empty($v['goods_id'])){
					$coupon[$k]['full'] = '仅限商品： ' . getField('goods', $v['goods_id'], 'name') . ' 使用';
				}else{
					$coupon[$k]['full'] = '满 ' . showprice($v['full']) . ' 可使用';
				}
	
				$coupon[$k]['used'] = $this->where(array('coupon_id'=>$v['coupon_id'], 'user_id'=>array('gt', 0)))->count();
				$coupon[$k]['num'] = $this->where(array('coupon_id'=>$v['coupon_id'], 'user_id'=>0))->count();
				$coupon[$k]['count'] = $this->where(array('coupon_id'=>$v['coupon_id']))->count();
				$coupon[$k]['price'] = showprice($v['price']);
			}
		}
		
		return $coupon ? $coupon : 0;
	}
	
	/**
	 * 添加优惠券卡
     * @param array $param 优惠券id coupon_id => 'coupon_id'
     * @return array arrData;
	 */
	public function push($param){
		$coupon = model('Coupon') -> get($param['coupon_id']);
		if(empty($coupon)) {
            return arrData('查询不到会员信息');
        }

        $num = $coupon['num'] ? $coupon['num'] : 10;
        $exist_array = $this->column('number'); //已经存在的卷码
//     	$characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnpqrstuvwxyz";
        $characters = "123456789";
        $promotion_codes = array(); //这个数组用来接收生成的优惠码

        for($j = 0; $j < $num; $j++) {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                if($i>0 && $i%4 == 0){
                    $code.='-';
                }
                $code .= $characters[mt_rand(0, strlen($characters)-1)];
            }
            //如果生成的随机数不再我们定义的$promotion_codes数组里面
            if( !in_array($code, $promotion_codes) ) {
                if( is_array($exist_array) ) {
                    if( !in_array($code,$exist_array) ) {//排除已经使用的优惠码
                        $promotion_codes[$j] = $code; //将生成的新优惠码赋值给promotion_codes数组
                    } else {   
                        $j--;
                    }                
                } else {
                    $promotion_codes[$j] = $code;//将优惠码赋值给数组 
                }
            }else {
                $j--;
            }
        }

        for($i=0; $i<$num; $i++){
            $info[$i]['coupon_id']   = $coupon['id'];
            $info[$i]['number']      = $promotion_codes[$i];
            $info[$i]['stime']       = $coupon['stime'];
            $info[$i]['etime']       = $coupon['etime'];
            $info[$i]['price']       = $coupon['price'];
            $info[$i]['status']      = 1;
            $info[$i]['create_time'] = time();
        }

        $this->allowField(true)->isUpdate(false)->saveAll($info);
        return arrData('success', 0);
	}
}