<?php
/**
 * 活动券管理
 * @Authon 老八
 * @Date 2020/07/23
 * */
namespace app\api\controller;

use think\Model;

class CouponController extends BaseController{
	
	protected function _initialize(){
		parent::_initialize();
		$this->model = model('Coupon');
	}
	
	/**
	 * 活动券列表
	 */
	public function coupon_list(){

		$store_id = input('store_id');
        if(empty($store_id)) {
            return callbackJson(0,'缺少参数：店铺ID');
        }

        //查询用户信息
        $user_coupon = model('coupon_card')
            ->field('coupon_id, status')
            ->where(['user_id' => $this->uid])
            ->select()
            ->toArray();

        $user_coupon_id = array_column(array_filter($user_coupon,function($val){
            return $val['coupon_id'];
        }),'coupon_id');

        $result = model('coupon')
            ->field('status, create_time,num',true)
            ->where([
                'store_id' => $store_id,
                'status'   => 1,
                'etime'    => ['gt',time()]
            ])->select();

        if($result){
            foreach($result as $k => &$v){
                if(in_array($v['id'],$user_coupon_id)){
                    $v['lingqu'] = 1;
                }else{
                    $v['lingqu'] = 0;
                }
                $v['stime'] = date('Y-m-d H:i:s',$v['stime']);
                $v['etime'] = date('Y-m-d H:i:s',$v['etime']);
//                $v['num'] = model('CouponCard')->where(array('coupon_id'=>$v['id'], 'user_id'=>0))->count();
//                $result[$k]['count'] = model('CouponCard')->where(array('coupon_id'=>$v['id']))->count();
                $result[$k]['price'] = showprice($v['price']);
            }
        }
        return callbackJson(1,'success', $result);
    }


	/**
	 * 领取优惠券
	 */
	public function get_coupon(){
		if($this->request->isPost()){
            $coupon_id = input('param.coupon_id');

		    if(empty($coupon_id)){
                return callbackJson(0,'活动券ID不能为空');
            }
		    //查询优惠券信息
            $coupon = model('Coupon')->get($coupon_id);
            if(empty($coupon)){
                return callbackJson(0,'活动券信息有误');
            }
            //查询是否已经领取此优惠券
            $result = model('CouponCard')->get(['user_id' => $this->uid, 'coupon_id' => $coupon_id]);
            if($result){
                return callbackJson(0,'您已领取过此优惠券');
            }
            $card = model('CouponCard')->get(array('user_id' => 0, 'coupon_id'=>$coupon_id));
            if(empty($card)){
                return callbackJson(1,'来晚一步，该活动券已被领光');
            }
            if(time() > $card['etime']){
                return callbackJson(0,'该优惠券已失效');
            }

            model('CouponCard')->save(['user_id' => $this->uid, 'get_time'=>time(),'status'=> 2], ['id' => $card['id']]);
            return callbackJson(1,'领取成功');
		}
	}


//====================================================商家接口============================================================
	/**
	 * 添加优惠券
	 * 商家接口
	 * token 用户标识
	 * province_id 省ID
	 * city_id 市ID
	 * fee 费用
	 */
	public function add(){
		if($this->request->isPost()){
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['stime', 'require|number', '开始时间不能为空|开始时间格式不正确'],
				['etime', 'require|number', '结束时间不能为空|结束时间格式不正确'],
				['full', 'require|number', '限制金额不能为空|限制金额格式不正确'],
				['price', 'require|number', '金额不能为空|金额格式不正确'],
				['num', 'require|number', '数量不能为空|数量格式不正确'],
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}

			//查询用户信息
			$member = model('Member')->get(array('token'=>$this->param['token']));
			if(empty($member)){
				return ajaxError('查询不到用户信息');
			}else{
				$this->param['member_id'] = $member['id'];
			}

			$result = $this->model->push($this->param);
			if($result['error'] == 0){
				//写入卡号
				model('CouponCard')->push(array('coupon_id'=>$result['data'], 'num'=>$this->param['num']));
				return ajaxSuccess($result['msg'], $result['data']);
			}else{
				return ajaxError($result['msg']);
			}
		}
	}

	/**
	 * 增加数量
	 * 商家接口
	 */
	public function addNum(){
		if($this->request->isPost()){
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['coupon_id', 'require|number', '优惠券ID不能为空|优惠券ID格式不正确'],
				['num', 'require|number', '数量不能为空|数量格式不正确'],
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}

			//查询用户信息
			$member = model('Member')->get(array('token'=>$this->param['token']));
			if(empty($member)){
				return ajaxError('查询不到用户信息');
			}else{
				$member_id = $member['id'];
			}

			$result = $this->model->get($this->param['coupon_id']);
			if(!empty($result)){
				if($result['member_id'] == $member_id){
					//写入卡号
					model('CouponCard')->push(array('coupon_id'=>$this->param['coupon_id'], 'num'=>$this->param['num']));
					return ajaxSuccess('增加成功', $this->param['coupon_id']);
				}else{
					return ajaxError('这不是您的优惠券');
				}
			}else{
				return ajaxError($result['msg']);
			}

		}
	}
	
	
	/**
	 * 删除优惠券信息
	 */
	public function delInfo(){
		if($this->request->isPost()){			
			//验证
			$validate = new \think\Validate([
				['token', 'require', '用户登录标识不能为空'],
				['coupon_id', 'require|number', '优惠券ID不能为空|优惠券ID格式不正确'],
			]);
			//验证部分数据合法性
			if (!$validate->check($this->param)){
				return ajaxError($validate->getError());
			}
			
			//查询用户信息
			$member = model('Member')->get(array('token'=>$this->param['token']));
			if(empty($member)){
				return ajaxError('查询不到用户信息');
			}else{
				$this->param['member_id'] = $member['id'];
			}
			
			$result = $this->model->delInfo($this->param);
			if($result['error'] == 0){
				return ajaxSuccess($result['msg']);
			}else{
				return ajaxError($result['msg']);
			}
			
		}
	}
	
}
