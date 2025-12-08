<?php

namespace addons\awss3;

use think\Addons;
use think\App;
use think\Config;

/**
 * AWS S3云存储插件
 */
class Awss3 extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 判断是否来源于API上传
     */
    public function moduleInit($request)
    {
        $config = $this->getConfig();
        $module = strtolower($request->module());
        // 判断api/common/upload 是否使用云存储上传
        if ($module == 'api' && ($config['apiupload'] ?? 0) &&
            strtolower($request->controller()) == 'common' &&
            strtolower($request->action()) == 'upload') {
            request()->param('isApi', true);
            App::invokeMethod(["\\addons\\awss3\\controller\\Index", "upload"], ['isApi' => true]);
        }
    }

    /**
     * 加载配置 - 修改上传配置使其使用S3
     */
    public function uploadConfigInit(&$upload)
    {
        $config = $this->getConfig();

        $upload = array_merge($upload, [
            'cdnurl'     => $config['cdnurl'],
            'uploadurl'  => addon_url('awss3/index/upload', [], false, true), // 服务器中转上传
            'uploadmode' => 'server', // 强制使用服务器中转模式
            'bucket'     => $config['bucket'],
            'maxsize'    => $config['maxsize'],
            'mimetype'   => $config['mimetype'],
            'savekey'    => $config['savekey'],
            'chunking'   => (bool)($config['chunking'] ?? $upload['chunking']),
            'chunksize'  => (int)($config['chunksize'] ?? $upload['chunksize']),
            'storage'    => 'awss3', // 标识为awss3存储
            'multiple'   => (bool)$config['multiple'],
        ]);
    }

    /**
     * 附件删除后
     */
    public function uploadDelete($attachment)
    {
        $config = $this->getConfig();
        if ($attachment['storage'] == 'awss3' && isset($config['syncdelete']) && $config['syncdelete']) {
            // 删除S3端文件
            try {
                $s3Service = new \addons\awss3\library\S3UploaderService($config);
                $s3Key = ltrim($attachment->url, '/');
                $s3Service->delete($s3Key);
            } catch (\Exception $e) {
                \think\Log::error('S3删除文件失败: ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }

}
