<?php
namespace app\api\model;

use think\Model;

class User extends Model{

    public function userApply()
    {
        return $this->hasOne('userApply','ua_user_id','u_id');
    }
    public function parent_2()
    {
        return $this->hasOne('user','id','parent_1');
    }
    public function parent_3()
    {
        return $this->hasOne('user','id','parent_1');
    }
}