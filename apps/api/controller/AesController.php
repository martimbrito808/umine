<?php
namespace app\api\controller;
use think\Controller;
class AesController extends Controller{


    /**
     * 获取私钥
     *
     */
    private  function getPrivateKey()
    {
//        $abs_path =Env::get('root_path'). 'ssl/rsa_private.pem';
//        $content = file_get_contents($abs_path);
        $content = '-----BEGIN PRIVATE KEY-----
MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBANKoqrBboJD2fmES
n6749nj5cciBh2d0mPNR8qQlX23VdK5AalpVRKXD27TSCjM8qkkjXBsrvHx6Op37
pZYjAW0PAPfE76X9HsHJIt78ETSJJoHkjZ/WVWPJRnLWEU1WEmZbpyCTPi8iIWAd
WOWRhrk/xdcO4tYRyEuNmTZwp08NAgMBAAECgYEAzrO1mIqvyM41P6b4jMW5gwaw
QR/n7vmXwtkcDzikpK8YaIrIUI7uZwEBqjGW1KOoK0/I5thJgJKmxbHQzrrWf5UR
6/r+FHU7+KeVDmti3qBo17vbGbtoPLjAcMWxALLDc7e30OBNIaM4vT+Exsaz8EPe
Dn+EhvN6RnIDBITdacECQQD9Oc9Yt5tsl3/3GM0pX+u8rnp1BbFayrr1KK3/ofMr
ZDqwIz4Q1PZTMw5ARzhchPKk8UXOQFCY5i81iKn5x/CZAkEA1Pd5hPLMbKZxxubY
lTqu8fi/wGm1sCd6KOX1KHe3+aJefIB3fhyOhGHCCcZzeidJ37tLdbqYrSYAnpHX
I8g2lQJAGt+GtKiPkv+k8eks5KYsU1LE5iRbhQIcwyW1CXr7XnB9lfG3hXvERGIX
shSc05y8T2rXeKL0qrVK70h4mWxxiQJAKp/hjY9/BNwHd7TqcmvNahbMYjmGKNyt
4ZOtDs1vYCJ0YNzhjbcveyWJzaUPpcpJSeNVxhlzx2wMwbAU7E99RQJBAOHuQdRU
wScVA+RgkwNxzNhGOQqH9uPu6tBUUeFK6yZwtnpT3O4afXSEjKitaIXcep1EOqwq
30Ml9pqLy5G16DI=
-----END PRIVATE KEY-----';

        return openssl_pkey_get_private($content);

    }


    /**
     * 获取公钥
     * @return bool|resource
     */
    private function getPublicKey()
    {
//        $abs_path = Env::get('root_path') . 'ssl/rsa_public_key.pem';

//        $content = file_get_contents($abs_path);

        $content = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDSqKqwW6CQ9n5hEp+u+PZ4+XHI
gYdndJjzUfKkJV9t1XSuQGpaVUSlw9u00gozPKpJI1wbK7x8ejqd+6WWIwFtDwD3
xO+l/R7BySLe/BE0iSaB5I2f1lVjyUZy1hFNVhJmW6cgkz4vIiFgHVjlkYa5P8XX
DuLWEchLjZk2cKdPDQIDAQAB
-----END PUBLIC KEY-----';
        return openssl_pkey_get_public($content);

    }

    /**
     * 私钥加密
     * @param string $data
     * @return null|string
     */
    public  function privEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_private_encrypt($data, $encrypted, $this->getPrivateKey()) ? base64_encode($encrypted) : null;
    }

    /**
     * 公钥加密
     * @param string $data
     * @return null|string
     */
    public  function publicEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_public_encrypt($data, $encrypted, $this->getPublicKey()) ? base64_encode($encrypted) : null;
    }

    /**
     * 私钥解密
     * @param string $encrypted
     * @return null
     */
    public  function privDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_private_decrypt(base64_decode($encrypted), $decrypted, $this->getPrivateKey())) ? $decrypted : false;
    }

    /**
     * 公钥解密
     * @param string $encrypted
     * @return null
     */
    public  function publicDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_public_decrypt(base64_decode($encrypted), $decrypted, $this->getPublicKey())) ? $decrypted : null;
    }



    /**
     *  加密
     * */

    public function aes_encrypt($str,$k)
    {
        if (!is_string($str)) {
            return false;
        }

        return openssl_encrypt($str, 'AES-128-ECB', $k);
    }

    /**  解密
     * */
    public function aes_decrypt($str,$k)
    {
        if (!is_string($str)) {
            return false;
        }
        $data=openssl_decrypt($str,'AES-128-ECB',$k);
        return $data;
    }



}