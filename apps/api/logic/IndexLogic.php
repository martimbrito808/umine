<?php
namespace app\api\logic;

use think\Model;
use think\Db;
use app\api\model\Goods;

class IndexLogic extends Model{

    private $goodsModel = '';
    protected $return_data=[
        'code'=>200,
        'msg'=>'success',
        'data'=>[],
        'count'=>0
    ];

    function __construct () {
        parent::__construct ();
        $this->goodsModel = new Goods();
    }

    /**
     * 咨询
     */
    public function getMessage()
    {
        $list = Db::name('article')
            ->where('status',1)
            ->order('create_time desc')
            ->select();
            
        $list = transArray($list);
        foreach($list as $k => $v){
            if(time() - $v['create_time'] < 120){
                $list[$k]['time'] = '刚刚';
            }else{
                $list[$k]['time'] = date('m-d H:i',$v['create_time']);
            }
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }

    /**
     * 咨询详情
     */
    public function getDetails($id)
    {
        $list = Db::name('article')
            ->where('id',$id)
            ->find();
        $list = transArray($list);
        $time = floor((time()-$list['create_time'])/60/60);
        if($time<24&&$time>0){
            $list['time'] = $time;
        }elseif($time==0){
            $list['time'] = '刚刚';
        }else{
            $list['time'] = date('m-d H:i',$list['create_time']);
        }
        $this->return_data['data'] = $list;
        return $this->return_data;
    }


}