<?php
namespace app\common\model;

use think\Model;

class Article extends Model
{
    protected $autoWriteTimestamp =true;

    /*
     * hasOne shop_attachment
     * Cover
     * */
    public function attachmentCover()
    {
        return $this->hasOne('Attachment','id','cover');
    }
}


