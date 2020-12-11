<?php
/**
 * 附件上传模型
 */
namespace app\common\model;

use think\Image;
use think\Db;
use think\Model;
use think\Reqeust;

class Attachment extends Model{	

	/**
	 * 获取关联图册
	 */
	public function getAlbum($ids){
		$ids_arr = explode(',', $ids);
		if(!empty($ids_arr)){
			$result = array();
			foreach($ids_arr as $k=>$v){
				$result[$k] = $this->where(array('id'=>$v))->field('id, filepath')->find();
				if(empty($result[$k])){
					unset($result[$k]);
				}else{
					$result[$k]['filepath'] = request()->domain() . $result[$k]['filepath'];
				}
			}
			if(!empty($result)){
				return array_values($result);
			}
		}
	}
	
	/**
	 * 上传图片
     * @param array $file 图片信息
	 */
	public function upload($file){
		$info = $file
            ->validate([
                'size' => getConfig('file_size') * 1024,
                'ext'  => getConfig('file_type')])
            ->rule('date')
            ->move(ROOT_PATH . 'public' . DS . 'uploads');

        if($info) {
            //写入到附件表
            $data['class']       = isset($file->classs)  ? $file->classs : 1;  //类型 1=图片； 2=视频；
            $data['filename']    = $info->getFilename(); //文件名
			$data['filedir']     = str_replace('\\', '/', $info->getSaveName()); //文件相对路径
            $data['filepath']    = '/public/uploads/' . str_replace('\\', '/', $info->getSaveName()); //文件全路径
            $data['fileext']     = $info->getExtension(); //文件后缀
            $data['filesize']    = $info->getSize(); //文件大小
            $data['create_time'] = time(); //时间
            $data['uploadip']    = get_client_ip(); //IP
			
			//生成缩略图
			$ext = $data['fileext'];
			if($ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'png'){				
				$image = Image::open('./public/uploads/' . str_replace('\\', '/', $info->getSaveName()));
				$image->thumb(150, 150);
				$thumb_path = '/public/uploads/'. str_replace('.'.$ext, '_thumb.'.$ext, str_replace('\\', '/', $info->getSaveName()));
				$image->save('.'.$thumb_path);
				$data['thumb'] = $thumb_path;
			}
			
			$fileinfo = $info->getInfo();
			$data['title'] = str_replace('.'.$ext, '', $fileinfo['name']);
			
			if($this->allowField(true)->save($data) == false){
				return arrData('上传失败');
			}else{
				$data['id'] = $this->id;
				return arrData('上传成功', 0, $data);
			}
        } else {
            // 上传失败获取错误信息
            return arrData($file->getError());
        }
	}
	
	/**
	 * 删除图片
	 */
	public function delfile($id)
    {
        if (empty($id))
            return arrData('没有图片');

        $attachment = $this->where('id', $id)->value('filepath');

        if (empty($attachment)) {
            return arrData('删除成功', '', 0);
        } else {
            if (file_exists(ROOT_PATH . $attachment)) {
                if (unlink(ROOT_PATH . $attachment)) {
                    if ($this->where('id', $id)->delete() == false) {
                        return arrData('删除失败');
                    } else {
                        addlog($id);
                        return arrData('删除成功', 0);
                    }
                } else {
                    return arrData('删除失败,没有权限');
                }
            } else {
                if ($this->where('id', $id)->delete() == false) {
                    return arrData('删除失败');
                } else {
                    addlog($id);
                    return arrData('删除成功', '', 0);
                }
            }
        }
    }
}