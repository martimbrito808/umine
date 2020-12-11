<?php
namespace app\api\logic;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class UploadFileLogic {


    /*
     * 生成上传 Token
    */
    public function getToken($bucketSequence = 0){
        require_once __DIR__ . '/../../../vendor/vendor/qiniu/php-sdk/autoload.php';
            // 构建鉴权对象
        $auth = new Auth(config('app.qiniu.AK'), config('app.qiniu.SK'));

        // 要上传的空间
        $bucket = config('app.qiniu.BUCKET');
        // 生成上传 Token
        $token = $auth->uploadToken($bucket[$bucketSequence]);
        // 上传到七牛后保存的文件名
        $key = substr(md5($token), 0, 10). date('YmdHis') . rand(1000, 9999);
        return [$token, $key];
    }

    /*
     * 上传图片
     * */
    public function upload()
    {
        $uploadType = Input('file_upload_type/d', 0);// 文件上传形式，0二进制流，1文件上传
        $fileName = Input('file_name/s', 'file');
        $bucketSequence = Input('bucket/d', 0); // 七牛云空间序列 【0,1,2】，具体请参照配置文件，config('app.qiniu.BUCKET');
        $file = request()->file($fileName);
        // 裁剪
        $jcrop = Input('jcrop/s'); // 裁剪参数

        $_jcrop = [];
        if(!empty($jcrop)){
            $_j = explode(',', $jcrop);
            foreach ($_j as $item) {
                $_v = explode(':', $item);
                $_jcrop[$_v[0]] = $_v[1];
            }
        }

        if($file){
            // 生成上传 Token
            $_token = $this->getToken($bucketSequence);

            // 要上传文件的本地路径
            $filePath = $file->getRealPath();
            $ext = strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));  //后缀
            if($ext!="gif" && $ext!="jpg" && $ext!="jpeg" && $ext!="png"){
                sendRequest(1, '上传格式有误！');
            }

            // 生成上传 Token
            $token = $_token[0];
            // 上传到七牛后保存的文件名
            $key = $ext.'/'.$_token[1] . '.' . $ext;

            if($uploadType){
                $res = $this->uploadFile($token, $key, $filePath);
            }else{
                if(!empty($_jcrop)){
                    $res = $this->uploadBinary($token, $key, $this->jcropImage($filePath, $_jcrop));
                }else{
                    $res = $this->uploadBinary($token, $key, $this->resizeImage($filePath));
                }
            }
            if($res){
                $bucket = config('app.qiniu.BUCKET');
                $domain = config('app.qiniu.domain');
                $res['bucket'] = $bucket[$bucketSequence];
                $res['domain'] = $bucketSequence===1?$domain['videos']:$domain['images'];
                $res['src'] = $res['domain'] . $res['key'];
                sendRequest(0, '上传成功！', $res);
            }else{
                sendRequest(1, '上传失败！');
            }
        }
        sendRequest(1, '上传失败，找不到资源！');
    }

    /*
     * 上传视频
     * */
    public function uploadVideo()
    {
        $bucketSequence = 1;
        $fileName = Input('file_name/s', 'file');
        $file = request()->file($fileName);
        if($file){
            // 生成上传 Token
            $_token = $this->getToken($bucketSequence);

            // 要上传文件的本地路径
            $filePath = $file->getRealPath();
            $ext = strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));  //后缀
            if($ext!="mp4" && $ext!="rmvb"){
                sendRequest(1, '上传格式有误！');
            }
            // 生成上传 Token
            $token = $_token[0];
            // 上传到七牛后保存的文件名
            $key = $ext.'/'.$_token[1] . '.' . $ext;

            $res = $this->uploadFile($token, $key, $filePath);
            if($res){
                $bucket = config('app.qiniu.BUCKET');
                $domain = config('app.qiniu.domain');
                $res['bucket'] = $bucket[$bucketSequence];
                $res['domain'] = $domain['videos'];
                $res['src'] = $res['domain'] . $res['key'];
                sendRequest(0, '上传成功！', $res);
            }else{
                sendRequest(1, '上传失败！');
            }
        }
        sendRequest(1, '上传失败！');
    }

    /*
     * 上传文件
     * */
    public function uploadFiles()
    {
        $bucketSequence = 2;
        $fileName = Input('file_name/s', 'file');
        $file = request()->file($fileName);
        if($file){
            // 生成上传 Token
            $_token = $this->getToken($bucketSequence);

            // 要上传文件的本地路径
            $filePath = $file->getRealPath();
            $ext = strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));  //后缀
            if($ext!="rar" && $ext!="zip" && $ext!="apk"){
                sendRequest(1, '上传格式有误！');
            }
            // 生成上传 Token
            $token = $_token[0];
            // 上传到七牛后保存的文件名
            $key = $ext.'/'.$_token[1] . '.' . $ext;

            $res = $this->uploadFile($token, $key, $filePath);
            if($res){
                $bucket = config('app.qiniu.BUCKET');
                $domain = config('app.qiniu.domain');
                $res['bucket'] = $bucket[$bucketSequence];
                $res['domain'] = $domain['files'];
                $res['src'] = $res['domain'] . $res['key'];
                sendRequest(0, '上传成功！', $res);
            }else{
                sendRequest(1, '上传失败！');
            }
        }
        sendRequest(1, '上传失败！');
    }

    /*
     * 上传小程序二维码
     * */
    public function xcxErCode()
    {
        $bucketSequence = 0; // 七牛云空间序列 【0,1,2】，具体请参照配置文件，config('app.qiniu.BUCKET');
        // 生成上传 Token
        $_token = $this->getToken($bucketSequence);
        $ext = 'png';  //后缀
        $data = Input('erCode');

        // 生成上传 Token
        $token = $_token[0];
        // 上传到七牛后保存的文件名
        $key = $_token[1] . '.' . $ext;

        if(!empty($data)){
            $res = $this->uploadBinary($token, $key, $data);
            if($res){
                $bucket = config('app.qiniu.BUCKET');
                $domain = config('app.qiniu.domain');
                $res['bucket'] = $bucket[$bucketSequence];
                $res['domain'] = $bucketSequence===1?$domain['videos']:$domain['images'];
                $res['src'] = $res['domain'] . $res['key'];
                sendRequest(0, '上传成功！', $res);
            }
        }
        sendRequest(1, '上传失败！');
    }


    /*
     * 二进制流上传图片
    */
    private function uploadBinary($token, $key, $data){
        // 初始化 UploadManager 对象并进行文件的上传
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 put 方法进行文件的上传，二进制流上传
        list($res, $err) = $uploadMgr->put($token, $key, $data);
        return $res;
    }

    /*
     * 裁剪图片
     * */
    private function jcropImage($image, $jcrop){
        $imgstream = file_get_contents($image);
        $im = imagecreatefromstring($imgstream);
        $x = imagesx($im);//获取图片的宽
        $y = imagesy($im);//获取图片的高

        if($jcrop){
            if(function_exists("imagecreatetruecolor")) {
                $dim = imagecreatetruecolor($jcrop['w']*$jcrop['sx'], $jcrop['h']*$jcrop['sy']); // 创建目标图gd2
            } else {
                $dim = imagecreate($jcrop['w']*$jcrop['sx'], $jcrop['h']*$jcrop['sy']); // 创建目标图gd1
            }
            //imageCopyreSampled($dim,$im,0,0,$jcrop['x']*$jcrop['sx'],$jcrop['y']*$jcrop['sy'],$jcrop['dsw'],$jcrop['dsh'],$x,$y);
            imageCopyreSampled($dim,$im,0,0,$jcrop['x']*$jcrop['sx'],$jcrop['y']*$jcrop['sy'],$x,$y,$x,$y);

            //不写入服务
            ob_start(); //开启缓存
            imagejpeg($dim, null, 95);
            return ob_get_clean(); //获取二进制
        }else{
            return $this->resizeImage($image);
        }
    }

    /**
     * 上传图片处理 原图像素宽度大于1200的处理
     * $maxWidth = 1200
     */
    function resizeImage($image, $maxWidth = 1200){
        $imgstream = file_get_contents($image);
        $im = imagecreatefromstring($imgstream);
        $x = imagesx($im);//获取图片的宽
        $y = imagesy($im);//获取图片的高

        if(($maxWidth && $x > $maxWidth)/* || ($maxHeight && $y > $maxHeight)*/){
            $sy = 0;
            $sx = 0;
            // 仅固定图片宽度，高度不限制
            $thumbW = $maxWidth;
            $thumbH = $maxWidth * $y / $x;

            if(function_exists("imagecreatetruecolor")) {
                $dim = imagecreatetruecolor($thumbW, $thumbH); // 创建目标图gd2
            } else {
                $dim = imagecreate($thumbW, $thumbH); // 创建目标图gd1
            }
            imageCopyreSampled($dim,$im,0,0,$sx,$sy,$thumbW,$thumbH,$x,$y);

            //不写入服务
            ob_start(); //开启缓存
            imagejpeg($dim, null, 95);
            return ob_get_clean(); //获取二进制
        }else{ //像素没超过规定范围，返回原图二进制
            ob_start();
            imagejpeg($im, null, 95);
            return ob_get_clean();
        }
    }

    /*
     * 上传文件，文件形式上传
    */
    private function uploadFile($token, $key, $filePath){
        if($token&&$key&&$filePath){
            // 初始化 UploadManager 对象并进行文件的上传
            $uploadMgr = new UploadManager();
            // 调用 UploadManager 的 putFile 方法进行文件的上传
            list($res, $err) = $uploadMgr->putFile($token, $key, $filePath);
            return $res;
        }
    }

}
