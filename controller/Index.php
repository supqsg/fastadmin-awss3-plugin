<?php

namespace addons\awss3\controller;

use app\common\exception\UploadException;
use app\common\library\Upload;
use app\common\model\Attachment;
use think\addons\Controller;
use think\Config;

/**
 * AWS S3云存储控制器
 */
class Index extends Controller
{

    public function _initialize()
    {
        //跨域检测
        check_cors_request();

        parent::_initialize();
        Config::set('default_return_type', 'json');
    }

    public function index()
    {
        Config::set('default_return_type', 'html');
        $this->error("当前插件暂无前台页面");
    }

    /**
     * 服务器中转上传文件到AWS S3
     * 这里调用你已实现的 PackageImageUpload 方法
     *
     * @param bool $isApi
     */
    public function upload($isApi = false)
    {
        $config = get_addon_config('awss3');
        if ($isApi === true) {
            // API上传需要验证登录
            if (!$this->checkAuth()) {
                $this->error("请登录后再进行操作");
            }
        } else {
            // 后台上传需要验证令牌
            $this->check();
        }

        //检测删除本地文件
        $checkDeleteFile = function ($attachment, $upload, $force = false) use ($config) {
            try {
                //如果设定为不备份则删除本地文件 或 强制删除
                if ((isset($config['serverbackup']) && !$config['serverbackup']) || $force) {
                    // 只删除本地文件，不删除附件数据库记录
                    // 因为附件记录需要保留来引用S3上的文件
                    if ($upload && $upload->getFile()) {
                        //文件绝对路径
                        $filePath = $upload->getFile()->getRealPath() ?: $upload->getFile()->getPathname();
                        // 检查文件是否存在再删除
                        if ($filePath && file_exists($filePath)) {
                            @unlink($filePath);
                            \think\Log::info('AWS S3上传 - 清理本地文件: ' . $filePath);
                        }
                    }
                }

                // 如果是强制删除且上传失败，需要删除附件记录
                if ($force && $attachment && !empty($attachment['id']) && $attachment->storage !== 'awss3') {
                    $attachment->delete();
                    \think\Log::info('AWS S3上传 - 删除失败的附件记录: ' . $attachment->id);
                }
            } catch (\Exception $e) {
                \think\Log::error('删除文件异常: ' . $e->getMessage() . "\n堆栈: " . $e->getTraceAsString());
            }
        };

        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            // 分片上传处理
            $action = $this->request->post("action");
            $chunkindex = $this->request->post("chunkindex/d");
            $chunkcount = $this->request->post("chunkcount/d");
            $filename = $this->request->post("filename");

            if ($action == 'merge') {
                // 合并分片
                $attachment = null;
                $upload = null;
                $mergeSuccess = false;

                try {
                    $upload = new Upload();
                    // 合并本地分片文件
                    $attachment = $upload->merge($chunkid, $chunkcount, $filename);

                    // 获取本地文件路径
                    $filePath = $upload->getFile()->getRealPath() ?: $upload->getFile()->getPathname();

                    // 上传到S3
                    $s3Service = new \addons\awss3\library\S3UploaderService($config);
                    $s3Key = ltrim($attachment->url, '/');
                    $s3Url = $s3Service->upload($filePath, $s3Key);

                    if (!$s3Url) {
                        throw new \Exception('S3上传失败');
                    }

                    // 更新附件记录，标记为S3存储
                    // 注意：url保持相对路径，前端会通过CDN URL配置自动拼接
                    $attachment->storage = 'awss3';
                    $attachment->save();

                    // 清理本地文件
                    $checkDeleteFile($attachment, $upload);

                    $mergeSuccess = true;

                } catch (\Exception $e) {
                    $checkDeleteFile($attachment, $upload, true);
                    $errorMsg = $e->getMessage() ?: '未知错误';
                    \think\Log::error('S3上传异常: ' . $errorMsg . "\n堆栈跟踪: " . $e->getTraceAsString());
                    $this->error($errorMsg);
                }

                // 分片合并成功后返回结果（在 try-catch 外面，避免捕获 HttpResponseException）
                if ($mergeSuccess && $attachment) {
                    $this->success("上传成功", '', [
                        'url' => $attachment->url,
                        'fullurl' => cdnurl($attachment->url, true)
                    ]);
                }

            } else {
                // 上传分片文件
                $file = $this->request->file('file');
                try {
                    $upload = new Upload($file);
                    $upload->chunk($chunkid, $chunkindex, $chunkcount);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success("上传成功");
            }

        } else {
            // 普通文件上传（非分片）
            $attachment = null;
            $upload = null;
            $uploadSuccess = false;

            $file = $this->request->file('file');
            try {
                \think\Log::info('AWS S3上传开始 - 文件信息: ' . json_encode([
                    'name' => $file ? $file->getInfo('name') : 'null',
                    'size' => $file ? $file->getSize() : 0,
                ]));

                $upload = new Upload($file);

                // 先上传到本地（Upload类会自动进行所有验证）
                $attachment = $upload->upload();

                if (!$attachment) {
                    throw new UploadException('本地上传失败');
                }

                \think\Log::info('AWS S3上传 - 本地上传成功: ' . $attachment->url);

                // 获取本地文件路径
                $localFilePath = ROOT_PATH . 'public' . $attachment->url;

                \think\Log::info('AWS S3上传 - 开始上传到S3: ' . $localFilePath);

                // 上传到 S3
                $s3Service = new \addons\awss3\library\S3UploaderService($config);
                $s3Key = ltrim($attachment->url, '/');
                $s3Url = $s3Service->upload($localFilePath, $s3Key);

                if (!$s3Url) {
                    // S3上传失败，删除本地文件
                    \think\Log::error('AWS S3上传 - S3上传失败');
                    if (file_exists($localFilePath)) {
                        @unlink($localFilePath);
                    }
                    throw new \Exception('S3上传失败');
                }

                \think\Log::info('AWS S3上传 - S3上传成功: ' . $s3Url);

                // 更新附件记录，标记为S3存储
                // 注意：url保持相对路径，前端会通过CDN URL配置自动拼接
                $attachment->storage = 'awss3';
                $attachment->save();

                \think\Log::info('AWS S3上传 - 数据库更新成功');

                // 清理本地文件
                $checkDeleteFile($attachment, $upload);

                \think\Log::info('AWS S3上传 - 完成');

                $uploadSuccess = true;

            } catch (UploadException $e) {
                if ($upload) {
                    $checkDeleteFile($attachment, $upload, true);
                }
                $errorMsg = $e->getMessage() ?: '未知上传错误';
                \think\Log::error('上传异常(UploadException): ' . $errorMsg . "\n堆栈跟踪: " . $e->getTraceAsString());
                $this->error($errorMsg);
            } catch (\Exception $e) {
                if ($upload) {
                    $checkDeleteFile($attachment, $upload, true);
                }
                $errorMsg = $e->getMessage() ?: '未知错误';
                \think\Log::error('上传异常(Exception): ' . $errorMsg . "\n堆栈跟踪: " . $e->getTraceAsString());
                $this->error("上传失败: " . $errorMsg);
            }

            // 上传成功后返回结果（在 try-catch 外面，避免捕获 HttpResponseException）
            if ($uploadSuccess && $attachment) {
                $this->success("上传成功", '', [
                    'url' => $attachment->url,
                    'fullurl' => cdnurl($attachment->url, true)
                ]);
            }
        }

        return;
    }

    /**
     * 检查后台上传令牌
     * 参考阿里云OSS插件的验证机制
     */
    protected function check()
    {
        $awss3token = $this->request->post('awss3token', '', 'trim');
        if (!$awss3token) {
            // 如果没有token，尝试验证session（后台管理员）
            if (!session('admin.id')) {
                $this->error("请先登录后台");
            }
            return true;
        }

        // Token验证逻辑（可选）
        $config = get_addon_config('awss3');
        list($sign, $data) = explode(':', $awss3token);

        if (!$sign || !$data) {
            $this->error("参数不能为空");
        }

        $json = json_decode(base64_decode($data), true);
        if ($json['deadline'] < time()) {
            $this->error("请求已经超时");
        }

        return true;
    }

    /**
     * 检查API用户认证
     */
    protected function checkAuth()
    {
        // 检查是否登录
        $auth = \app\common\library\Auth::instance();
        return $auth->isLogin();
    }

}
