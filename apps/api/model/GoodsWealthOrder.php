<?php


namespace app\api\model;


use think\Model;

class GoodsWealthOrder extends Model
{
    
    public function goodsWealth(){
        return $this->hasOne('goodsWealth','id','goods_wealth_id')->field('id,name,cover,rengou_begin,rengou_end');
    }
    
    public function goodsWealthEarnings(){
        return $this->hasMany('goodsWealthEarnings','order_id','id')->field('order_id,price,earnings_date');
    }

}