<?php
namespace app\api\controller;

use think\Db;
use think\Image;

class PublicController extends BaseController{
	

    function __construct () {
        parent::__construct ();
    }
    /**
     * 图片上传 _通用
     * @return \think\response\Jsonp
     */
    public function imgUpload()
    {
        $file = request()->file('img');
        if(empty($file)){
            return callbackJson(0,'没有上传文件');
        }

        $info = $file
            ->validate([
                'size' => getConfig('file_size') * 1024,
                'ext'  => 'jpg,png,jpeg.gif'])
            ->rule('date')
            ->move(ROOT_PATH . 'public' . DS . 'uploads');
        if(!$info){
            return callbackJson(0, $file->getError());
        }

        $image = Image::open('./public/uploads/' . str_replace('\\', '/', $info->getSaveName()));
        $image->thumb(1000, 1000);
        $thumb_path = '/public/uploads/'. str_replace('.'.$info->getExtension(), '_thumb.'.$info->getExtension(), str_replace('\\', '/', $info->getSaveName()));
        $image->save('.'.$thumb_path);

        return callbackJson(1,'success',['showImg' => getFullPath($thumb_path), 'uploadImg'=> $thumb_path]);
	}
	
	    /**
     * base64 上传图片
     * @param string $base64_img 图片base64编码
     * @return array
     * */
    function baseimg_upload(){
        $base64_img = input('param.img');
        // $base64_img ='data:image/jpg;base64,'.str_replace('', '+', $base64_img);
        $base64_img = str_replace('', '+', $base64_img);
        $up_dir_date = 'public/uploads/'.date('Ymd',time());
        $up_dir = ROOT_PATH.$up_dir_date;
       

        if(!file_exists($up_dir)){
            mkdir($up_dir,0777,true);
        }

        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
            $type = $result[2];
            if(in_array($type, array('pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'))){
                $randStr = str_shuffle('1234567890'); //随机字符串
                $rand = substr($randStr,0,4); // 返回前四位
                $new_file = $up_dir.'/'.date('YmdHis').$rand.'.'.$type;  //完整路径 + 图片名

                if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
                    $img_path = str_replace($new_file);
                    $filename['url'] = '/'.$up_dir_date.'/'.date('YmdHis').$rand.'.'.$type;
                
                    return ['status' => 1, 'msg' => "图片上传成功", 'data' => $filename];
                }
                //上传失败
                return ['status' => 2, 'msg' => "图片上传失败"];
            }
            //文件类型错误
            return ['status' => 4, 'msg' => "文件类型错误"];
        }
        //文件错误
        return ['status' => 3, 'msg' => "文件错误"];
    }
}
