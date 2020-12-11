<?php
namespace app\api\model;

use think\Model;

class Order extends Model{


    public function orderDetails()
    {
        return $this->hasOne('orderDetails','od_sn','o_sn');
    }

}