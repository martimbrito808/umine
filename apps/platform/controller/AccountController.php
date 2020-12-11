<?php
/**
 * 收款账号设置
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;

class AccountController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->permissions();
        $this->assign('navbar', 'account');
    }

    /*
     * 列表
     *  */
    public function index()
    {
        $this->seo();
        return view();
    }

    /**
     * 查询数据
     */
    public function ajaxData(){
        $keys = input('keys','');
        $map = [];

        if ($keys) {
            $map['title'] = array('like', '%' . $keys . '%');
        }
        
        $result = Db::name('config')
            ->where($map)
            ->where('variable',['=','yollon_usdt'],['=','yollon_btc'],['=', 'yollon_eth'],['=', 'yollon_ethu'],['=', 'yollon_euf'],['=','yollon_er'],'or')
            ->select();
        $total = Db::name('config')
            ->where($map)
            ->where('variable',['=','yollon_usdt'],['=','yollon_btc'],['=', 'yollon_eth'],['=', 'yollon_ethu'],['=', 'yollon_euf'],['=','yollon_er'],'or')
            ->count();

        if($result){
            foreach($result as $k=>$v){
                if($v['variable'] == 'yollon_btc') {
                    $result[$k]['name'] = 'BTC';
                }elseif($v['variable'] == 'yollon_usdt') {
                    $result[$k]['name'] = 'USDT';
                }elseif($v['variable'] == 'yollon_eth') {
                    $result[$k]['name'] = 'ETH';
                }elseif($v['variable'] == 'yollon_ethu') {
                    $result[$k]['name'] = 'ETHU';
                }elseif($v['variable'] == 'yollon_euf') {
                    $result[$k]['name'] = 'EUF';
                }elseif($v['variable'] == 'yollon_er') {
                    $result[$k]['name'] = 'ER';
                }
                
                $result[$k]['value'] = $v['value'] ? '<img src="' . getFile($v['value']) . '" width="40px">' : '';
                $result[$k]['toolbar'] = '<button class="layui-btn layui-btn-xs layui-btn-normal go-btn" onclick="showDiyWin(\'编辑信息：' . $v['title'] . '\', \'' . url("publish", ['variable' => $v['variable']]) . '\',1400,600);"><i class="layui-icon">&#xe642;</i></button>';
            }
            return layData('数据获取成功', $result, $total);
        }else{
            return layData('当前数据为空', [], 0);
        }
    }

    /**
     * 添加/编辑
     */
    public function publish(){
        $variable = input('variable', 0);

        if($this->request->isPost()){
            $validate = new \think\Validate([
                ['title', 'require', '收款地址不能为空'],
                ['cover','require', '收款二维码不能为空']
            ]);
            if (!$validate->check($this->param)){
                return ajaxError($validate->getError());
            }
            $this->param['value'] = $this->param['cover'];
            unset($this->param['cover']);
            $info = Db::name('config')
                ->where([
                    'variable' => $variable
                ])
                ->update($this->param);
            if(false == $info){
                return ajaxError('修改失败');
            }else{
                return ajaxSuccess('修改成功');
            }
        }
        $rows = Db::name('config')->where('variable',$variable)->find();
        
        if($rows['variable'] == 'yollon_btc') {
            $rows['name'] = 'BTC';
        }elseif($rows['variable'] == 'yollon_usdt') {
            $rows['name'] = 'USDT';
        }elseif($rows['variable'] == 'yollon_eth') {
            $rows['name'] = 'ETH';
        }elseif($rows['variable'] == 'yollon_ethu') {
            $rows['name'] = 'ETHU';
        }elseif($rows['variable'] == 'yollon_euf') {
            $rows['name'] = 'EUF';
        }elseif($rows['variable'] == 'yollon_er') {
            $rows['name'] = 'ER';
        }
        $this->seo();
        return view('', compact('rows'));
    }
}