<?php
namespace app\api\controller;

use think\Db;

class NodeController extends BaseController
{

    function __construct () {
        parent::__construct ();
    }

    /**
     *首页
     */
    public function index()
    {
        $param = input('param.');
        $data['token'] = $param['token'];
        $data['user_id'] = $this->user_id;
    
        if(!empty($param['type'])) {
            $map['type'] = $param['type'];
        }else{
            $map['type'] = 1;
        }
        $list = Db::name('node')->where($map)->order('vote desc, id asc')->select();
        
        if(count($list) > 1) {
            $a = $list[0];
            $list[0]=$list[1];
            $list[1]=$a;
        }
        return $this->fetch('', compact('data', 'list'));
    }

    /**
     * 投票
     */
    public function vote() {
        $node_id = input('param.node_id', '');
        $num = input('param.num','');
        
        if(empty($node_id)) {
            sendRequest(201, "非法请求");
        }
        if(empty($num)) {
            sendRequest(201, "请输入投票数量");
        }
        if(!preg_match("/^[1-9][0-9]*$/", $num)){
            sendRequest(201, "请输入正确的投票数量");
        }
        $nodeInfo = Db::name('node')->where(['id' => $node_id])->find();
        $userInfo = Db::name('user')->where(['id' => $this->user_id])->find();
        
        if(empty($nodeInfo)) {
            sendRequest(201, "未查询到节点信息,请稍后再试");
        }
        if($nodeInfo['user_id'] == $this->user_id) {
            sendRequest(201, "不能给自己投票");
        }
        if($userInfo['er'] < toprice($num)) {
            sendRequest(201, "投票失败,余额不足");
        }
        
        if($nodeInfo['type'] == 1) {
            $money_type = 'ethu';
            $zengsongsuanli = getconfig('node_donate_suanli') * $num;
        }else{
            $money_type = 'euf';
            $zengsongsuanli =  getconfig('super_node_donate_suanli') * $num;
        }
        
        Db::startTrans();
        try{
            //扣除用户ER 
            //增加用户算力
            //生成财务记录
            //增加节点投票数量
            //写入到用户投票表
            $newsuanli = $zengsongsuanli + $userInfo['suanli'];
            Db::name('user')
                ->where(['id' => $this->user_id])
                ->dec('er', toprice($num))
                ->exp('suanli',$newsuanli)
                ->update();
                
            Db::name('finance')->insert([
                'type'          => 10,
                'money_type'    => 'er',
                'mold'          => 'out',
                'user_id'       => $this->user_id,
                'money'         => toprice($num),
                'create_time'   => time(),
            ]);
            
            Db::name('node')->where('id', $nodeInfo['id'])
                ->setInc('vote',$num);
            
            //投票ER超过10w
            //退回用户押金
            //节点表设置退回状态
            //生成财务记录
            $nodeVoteNum = Db::name('node')->where(['id' => $nodeInfo['id']])->value('vote');
            if($nodeInfo['back'] == 0 && $nodeVoteNum >= 10000) {
                Db::name('user')
                    ->where(['id' => $this->user_id])
                    ->setInc( $money_type, toprice($nodeInfo['deposit']) );
                    
                Db::name('node')->where('id', $nodeInfo['id'])
                    ->setField('back', 1);
                    
                Db::name('finance')->insert([
                    'type'          => 10,
                    'money_type'    => $money_type,
                    'mold'          => 'in',
                    'user_id'       => $this->user_id,
                    'money'         => toprice($nodeInfo['deposit']),
                    'create_time'   => time(),
                ]);
            }
            
            $checkUserVoteInfo = Db::name('user_poll')
                ->where([
                    'user_id' => $this->user_id,
                    'node_id' => $node_id,
                ])
                ->value('id');
            if($checkUserVoteInfo) {
                Db::name('user_poll')
                    ->where([
                        'id' => $checkUserVoteInfo
                    ])
                    ->setInc('num',$num);
            }else{
                Db::name('user_poll')
                    ->insert([
                        'user_id'       => $this->user_id,
                        'node_id'       => $node_id,
                        'num'           => $num,
                        'create_time'   => time(),
                    ]);
            }
            Db::commit();   
            sendRequest(200, "投票成功");
        } catch (\Exception $e) {
            sendRequest(200, "投票失败,请稍后再试");
            Db::rollback();
        }
    }     
    /**
     * 投票记录
     */ 
    public function voteList() {
        $param = input('param.');
        $auth['token'] = $param['token'];
        if(empty($param['type'])) {
            $param['type'] = 1;
        }
        $map['up.user_id'] =  $this->user_id;
        $map['n.type'] = $param['type'];
        $list = Db::name('user_poll')
            ->alias('up')
            ->field('n.title, n.cover, n.vote, n.type, up.* ')
            ->join('node n','n.id = up.node_id','LEFT')
            ->where($map)
            ->select();

        return $this->fetch('', compact('list','auth'));
    }
    
    /**
     * 申请节点 step_1 
     */
    public function apply_step1() {
        $param = input('param.');
        $auth['token'] = $param['token'];
        return $this->fetch('', compact('auth'));
    }
    
    /**
     * 申请节点 step_2
     */
    public function apply_step2() {
        $param = input('param.');
        $type = $param['type'] ?:1;
        if($type == 1) {
            $data['title'] = '常规节点';
            $data['money_type'] = 'ETHU';
            $data['money'] = 99;
        }elseif($type ==2) {
            $data['title'] = '超级节点';
            $data['money_type'] = 'EUF';
            $data['money'] = 9;
        }
        
        if(Request()->isPost()) {
            $validate = new \think\Validate([
                ['title', 'require', '请输入节点名称'],
                ['cover', 'require', '请上传节点图标'],
            ]);
            if (!$validate->check($param)) {
                 sendRequest(201, $validate->getError());
            }
        
            $checkNodeInfo = Db::name('node')
                ->where([
                    'type' => $param['type'], 
                    'user_id' => $this->user_id
                ])
                ->find();
            if($checkNodeInfo) {
                 sendRequest(201, "您已经创建过{$$data['title']}了");
            }

            $checkUserBalance = Db::name('user')
                ->where(['id' => $this->user_id])
                ->value($data['money_type']);
            if($checkUserBalance < $data['money']) {
                sendRequest(201, "创建失败, {$data['money_type']}不足");
            }
            
            // 扣除用户 euf / ethu 余额
            // 插入到节点表
            // 插入到财务明细表
            Db::name('user')
                ->where(['id' => $this->user_id])
                ->setDec(strtolower($data['money_type']), toprice($data['money']));
                
            Db::name('node')->insert([
                'type'      => $type,
                'title'     => $param['title'],
                'cover'     => $param['cover'],
                'deposit'   => $data['money'],
                'user_id'   => $this->user_id,
                'vote'      => 0,
                'create_time' => time(),
            ]);
            
            Db::name('finance')->insert([
                'type'          => 10,
                'money_type'    => strtolower($data['money_type']),
                'mold'          => 'out',
                'user_id'       => $this->user_id,
                'money'         => toprice($data['money']),
                'create_time'   => time(),
            ]);
            
            sendRequest(200, "创建成功");
        }
 
        
        $auth['token'] = $param['token'];
        return $this->fetch('',compact('auth','data'));
    }
    
    
    
    
    
    
    
    
    
}