<?php

namespace addons\awss3\library;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use think\Log;

/**
 * AWS S3 上传服务类
 *
 * 该服务类提供了与 AWS S3 交互的基础功能，包括文件上传和删除。
 * 所有配置项从插件配置中读取，无需依赖外部 ServiceFactory。
 */
class S3UploaderService
{
    protected $s3Client;
    protected $bucket;
    protected $region;
    protected $config;

    /**
     * 构造函数
     *
     * @param array|null $config 插件配置数组，如果为空则自动加载
     */
    public function __construct($config = null)
    {
        // 如果没有传入配置，则从插件配置中读取
        if ($config === null) {
            $config = get_addon_config('awss3');
        }

        $this->config = $config;
        $this->bucket = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? 'us-east-1';

        // 验证必需的配置项
        if (empty($config['access_key_id']) || empty($config['secret_access_key'])) {
            Log::error('AWS S3配置错误: 缺少 access_key_id 或 secret_access_key');
            throw new \Exception('AWS S3配置错误: 请在插件配置中填写 AWS 访问密钥');
        }

        if (empty($this->bucket)) {
            Log::error('AWS S3配置错误: 缺少 bucket');
            throw new \Exception('AWS S3配置错误: 请在插件配置中填写 S3 Bucket 名称');
        }

        // 初始化 S3 客户端
        $clientConfig = [
            'version' => 'latest',
            'region'  => $this->region,
            'credentials' => [
                'key'    => $config['access_key_id'],
                'secret' => $config['secret_access_key'],
            ],
        ];

        // 可选：跳过 SSL 验证（仅用于开发环境）
        if (isset($config['skip_ssl_verify']) && $config['skip_ssl_verify']) {
            $clientConfig['http'] = [
                'verify' => false,
            ];
        }

        $this->s3Client = new S3Client($clientConfig);
    }

    /**
     * 上传文件到 S3
     *
     * @param string $filePath 本地文件路径（绝对路径）
     * @param string $key S3 存储路径（例如：uploads/20231201/file.jpg）
     * @param string $acl 访问控制列表，默认 'public-read'（公开读取）
     * @return string|null 返回文件 URL 或 null（失败时）
     *
     * @example
     * $service = new S3UploaderService();
     * $url = $service->upload('/var/www/public/uploads/file.jpg', 'uploads/file.jpg');
     */
    public function upload($filePath, $key, $acl = 'public-read')
    {
        try {
            // 验证本地文件是否存在
            if (!file_exists($filePath)) {
                Log::error('S3上传失败: 本地文件不存在 - ' . $filePath);
                return null;
            }

            Log::info('S3上传开始: ' . json_encode([
                'file' => $filePath,
                'key' => $key,
                'bucket' => $this->bucket,
            ]));

            // 上传到 S3
            $params = [
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'SourceFile' => $filePath,
                'ContentType' => mime_content_type($filePath), // 自动识别 MIME 类型
            ];

            // 注意：某些 S3 兼容服务不支持 ACL，可通过配置关闭
            if (!isset($this->config['disable_acl']) || !$this->config['disable_acl']) {
                $params['ACL'] = $acl;
            }

            $result = $this->s3Client->putObject($params);

            $objectUrl = $result['ObjectURL'] ?? null;

            Log::info('S3上传成功: ' . $objectUrl);

            return $objectUrl;

        } catch (AwsException $e) {
            Log::error('S3上传失败(AwsException): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        } catch (\Exception $e) {
            Log::error('S3上传失败(Exception): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * 从 S3 删除文件
     *
     * @param string $key S3 文件路径（例如：uploads/20231201/file.jpg）
     * @return bool 成功返回 true，失败返回 false
     *
     * @example
     * $service = new S3UploaderService();
     * $success = $service->delete('uploads/file.jpg');
     */
    public function delete($key)
    {
        try {
            Log::info('S3删除开始: ' . json_encode([
                'key' => $key,
                'bucket' => $this->bucket,
            ]));

            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            Log::info('S3删除成功: ' . $key);

            return true;

        } catch (AwsException $e) {
            Log::error('S3删除失败(AwsException): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        } catch (\Exception $e) {
            Log::error('S3删除失败(Exception): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * 获取文件的预签名 URL（用于私有文件的临时访问）
     *
     * @param string $key S3 文件路径
     * @param string $expires 过期时间（例如：'+20 minutes'）
     * @return string|null 预签名 URL 或 null
     */
    public function getPresignedUrl($key, $expires = '+20 minutes')
    {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, $expires);

            return (string) $request->getUri();

        } catch (\Exception $e) {
            Log::error('S3生成预签名URL失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 检查文件是否存在于 S3
     *
     * @param string $key S3 文件路径
     * @return bool 存在返回 true，不存在返回 false
     */
    public function exists($key)
    {
        try {
            return $this->s3Client->doesObjectExist($this->bucket, $key);
        } catch (\Exception $e) {
            Log::error('S3检查文件存在失败: ' . $e->getMessage());
            return false;
        }
    }
}
