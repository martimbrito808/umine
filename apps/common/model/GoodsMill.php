<?php
namespace app\common\model;

use think\Model;

class GoodsMill extends Model{

    protected $autoWriteTimestamp = true;
    /*
     * hasOne shop_attachment
     * */
    public function attachment()
    {
        return $this->hasOne('Attachment','id','cover');
    }
}