<?php
/**
 * 会员管理
 * Author: Orzly
 * Date: 2020-06-19
 */

namespace app\platform\controller;

use think\Db;
use think\Model;

class UserEfeeStatController extends BaseController
{
  protected function _initialize()
  {
      parent::_initialize();
      $this->permissions();
      $this->assign('navbar', 'user');
  }

  public function index()
  {
      $this->seo();
      return view();
  }
  public function ajaxData()
  {
      $page = input('page', 1);
      $perpage = input('limit', 20);
      $map = [];
      if ($this->request->has('keys')) {
          $map['tel'] = array('like', '%' . $this->param['keys'] . '%');
      }

      $result = Db::name('user_login_log')->alias('ull')
                  ->join('user u','ull.user_id = u.id','LEFT')
                  ->field('u.tel, ull.*')
                  ->where($map)
                  ->order('login_date desc')->page($page, $perpage)->select();
      $total = Db::name('user_login_log')->count();
      return layData('数据获取成功', $result, $total);
  }
}