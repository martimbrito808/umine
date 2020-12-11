<?php


namespace app\api\model;


class ShippingAddress extends \think\Model
{
    public function province()
    {
        return $this->hasOne('region','area_id','province');
    }

    public function city()
    {
        return $this->hasOne('region','area_id','city');
    }

    public function area()
    {
        return $this->hasOne('region','area_id','area');
    }

}