# FastAdmin AWS S3 云存储插件

[English](README_EN.md) | 简体中文

这是一个专为 **FastAdmin** 设计的 AWS S3 云存储插件，支持将文件自动上传到 Amazon S3 或兼容 S3 协议的对象存储服务。插件开箱即用，无需修改 FastAdmin 核心代码。

## ✨ 特性

- ✅ **开箱即用** - 无需修改 FastAdmin 核心代码，安装即用
- ✅ **完全独立** - 插件内置 S3 上传服务，不依赖外部服务类
- ✅ **后台上传** - 支持后台管理员上传文件到 S3
- ✅ **API 上传** - 支持 API 接口上传到 S3
- ✅ **分片上传** - 支持大文件分片上传
- ✅ **自动清理** - 上传成功后自动清理本地临时文件
- ✅ **同步删除** - 支持删除附件时同步删除 S3 文件
- ✅ **灵活配置** - 支持自定义文件路径、CDN 地址等

## 📋 环境要求

- **PHP** >= 7.4
- **FastAdmin** >= 1.0.0
- **ThinkPHP** 5.x
- **Composer** - 用于安装依赖
- **AWS S3 账户** 或兼容 S3 协议的对象存储服务

## 📦 安装步骤

### 1. 下载插件

将插件下载或克隆到 FastAdmin 的 `addons` 目录：

\`\`\`bash
cd /path/to/your/fastadmin
git clone https://github.com/YOUR_USERNAME/fastadmin-awss3.git addons/awss3
\`\`\`

或手动下载后解压到 `addons/awss3` 目录。

### 2. 安装 AWS SDK

插件依赖 AWS SDK for PHP，需要通过 Composer 安装：

\`\`\`bash
cd /path/to/your/fastadmin
composer require aws/aws-sdk-php
\`\`\`

### 3. 后台安装插件

1. 登录 FastAdmin 后台
2. 进入 **插件管理** (`/admin/addon/index`)
3. 找到 **AWS S3云存储** 插件
4. 点击 **安装** 按钮
5. 点击 **启用** 按钮

## ⚙️ 配置插件

### 1. 获取 AWS 访问密钥

在使用插件前，需要先在 AWS 控制台创建访问密钥：

1. 登录 [AWS 管理控制台](https://console.aws.amazon.com/)
2. 进入 **IAM (Identity and Access Management)**
3. 创建新用户或选择现有用户
4. 为用户分配 S3 访问权限（推荐使用策略：\`AmazonS3FullAccess\` 或自定义策略）
5. 创建访问密钥，记录 **Access Key ID** 和 **Secret Access Key**

**推荐的 S3 权限策略示例：**

\`\`\`json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::your-bucket-name/*"
    }
  ]
}
\`\`\`

### 2. 创建 S3 存储桶

1. 在 AWS 控制台进入 **S3** 服务
2. 点击 **创建存储桶 (Create Bucket)**
3. 输入存储桶名称（例如：\`my-bucket\`）
4. 选择区域（例如：\`us-east-1\`）
5. 根据需要配置存储桶设置（推荐开启版本控制）

**配置存储桶公开访问：**

如果需要让上传的文件可公开访问，需要配置存储桶策略：

\`\`\`json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::your-bucket-name/*"
    }
  ]
}
\`\`\`

### 3. 配置插件

在 FastAdmin 后台，点击插件的 **配置** 按钮，填写以下信息：

| 配置项 | 说明 | 示例 |
|--------|------|------|
| **AWS Access Key ID** | AWS 访问密钥 ID | \`AKIAIOSFODNN7EXAMPLE\` |
| **AWS Secret Access Key** | AWS 访问密钥 | \`wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY\` |
| **S3 Bucket名称** | S3 存储桶名称 | \`my-bucket\` |
| **S3 Region** | S3 区域 | \`us-east-1\` |
| **CDN地址** | S3 访问地址或 CDN 地址 | \`https://my-bucket.s3.us-east-1.amazonaws.com\` |
| **上传模式** | 固定为服务器中转 | \`server\` |
| **服务器备份** | 是否保留本地副本 | \`否\` (推荐，节省空间) |
| **保存文件名** | 文件路径模板 | \`/uploads/{year}{mon}{day}/{filemd5}{.suffix}\` |
| **最大上传大小** | 单个文件最大大小 | \`100M\` |
| **可上传格式** | 允许的文件扩展名 | \`jpg,png,gif,pdf,zip\` |
| **分片上传** | 是否启用分片上传 | \`关闭\` (大文件建议开启) |
| **同步删除S3文件** | 删除附件时是否删除 S3 文件 | \`是\` |
| **API接口使用S3存储** | API 上传是否使用 S3 | \`是\` |

**CDN 地址说明：**

- 如果直接使用 S3 地址，格式为：\`https://[bucket].s3.[region].amazonaws.com\`
- 如果使用 CloudFront CDN，填写 CDN 域名：\`https://d1234567890.cloudfront.net\`
- 如果使用自定义域名，填写自定义域名：\`https://cdn.yourdomain.com\`

### 4. 禁用其他云存储插件

如果之前启用了阿里云 OSS 等其他云存储插件，需要先禁用：

1. 进入 **插件管理**
2. 找到其他云存储插件（例如：**阿里云OSS**）
3. 点击 **禁用** 按钮

## 🚀 使用方法

### 后台上传

插件安装并启用后，后台所有的文件上传操作会自动使用 AWS S3：

1. 在任何表单中使用上传组件（例如：编辑文章时上传图片）
2. 选择文件并上传
3. 文件会自动上传到 S3
4. 数据库 \`mk_attachment\` 表中的 \`storage\` 字段会被标记为 \`awss3\`

**查看上传的文件：**

在 **附件管理** (\`/admin/general/attachment\`) 中可以查看所有上传的文件：

- \`storage\` 字段显示为 \`awss3\` 表示存储在 S3
- \`url\` 字段显示文件相对路径（例如：\`/uploads/20231201/abc123.jpg\`）
- 完整访问 URL = CDN地址 + url

### API 上传

如果启用了 "API接口使用S3存储" 选项，API 接口的上传也会使用 S3：

\`\`\`bash
POST /api/common/upload
Content-Type: multipart/form-data

file: [binary data]
\`\`\`

**响应示例：**

\`\`\`json
{
  "code": 1,
  "msg": "上传成功",
  "data": {
    "url": "/uploads/20231201/abc123.jpg",
    "fullurl": "https://my-bucket.s3.us-east-1.amazonaws.com/uploads/20231201/abc123.jpg"
  }
}
\`\`\`

### 程序中使用

如果需要在自定义代码中使用 S3 上传服务：

\`\`\`php
<?php

// 引入 S3 上传服务类
use addons\\awss3\\library\\S3UploaderService;

// 创建服务实例（自动从插件配置读取参数）
\$s3Service = new S3UploaderService();

// 上传文件
\$localFilePath = '/path/to/local/file.jpg';
\$s3Key = 'uploads/file.jpg'; // S3 中的路径
\$s3Url = \$s3Service->upload(\$localFilePath, \$s3Key);

if (\$s3Url) {
    echo "上传成功: " . \$s3Url;
} else {
    echo "上传失败";
}

// 删除文件
\$success = \$s3Service->delete('uploads/file.jpg');

// 检查文件是否存在
\$exists = \$s3Service->exists('uploads/file.jpg');

// 生成预签名 URL（用于私有文件的临时访问）
\$presignedUrl = \$s3Service->getPresignedUrl('uploads/file.jpg', '+20 minutes');
\`\`\`

## 🔍 技术架构

### 工作流程

\`\`\`
用户上传文件
    ↓
FastAdmin 后台/API
    ↓
awss3 插件拦截 (uploadConfigInit 钩子)
    ↓
修改上传配置 (uploadurl → awss3/index/upload)
    ↓
addons\\awss3\\controller\\Index::upload()
    ↓
调用 S3UploaderService::upload()
    ↓
上传到 AWS S3
    ↓
保存附件记录 (storage=awss3)
    ↓
清理本地临时文件
    ↓
返回 S3 URL
\`\`\`

### 插件钩子

插件使用 FastAdmin 的以下钩子：

1. **uploadConfigInit** - 修改上传配置，将上传请求指向插件控制器
2. **uploadDelete** - 附件删除时同步删除 S3 文件
3. **moduleInit** - API 模块初始化时拦截上传请求

### 目录结构

\`\`\`
addons/awss3/
├── Awss3.php              # 插件主类（钩子处理）
├── config.php             # 插件配置定义
├── info.ini               # 插件信息
├── bootstrap.js           # 前端脚本
├── controller/
│   └── Index.php          # 上传控制器
├── library/
│   └── S3UploaderService.php  # S3 上传服务类
└── README.md              # 说明文档
\`\`\`

## ❓ 故障排查

### 1. 上传失败

**问题：** 点击上传后提示 "上传失败" 或 "S3上传失败"

**解决：**

1. **检查日志：** 查看 \`runtime/log/\` 目录下的日志文件，搜索 "S3" 相关错误
   \`\`\`bash
   tail -f runtime/log/\$(date +%Y%m%d).log | grep "S3"
   \`\`\`

2. **检查 AWS 凭证：** 确认插件配置中的 Access Key ID 和 Secret Access Key 正确无误

3. **检查存储桶：** 确认 S3 Bucket 存在且有写入权限

4. **检查网络：** 确认服务器能访问 AWS S3（可尝试 ping \`s3.amazonaws.com\`）

5. **检查 PHP 扩展：** 确认 PHP 已安装 \`curl\` 扩展

### 2. 文件无法访问

**问题：** 上传成功但无法访问文件（404 错误）

**解决：**

1. **检查存储桶权限：** 确认 S3 Bucket 的访问权限设置为公开读取（或配置了正确的 Bucket 策略）

2. **检查 CDN 地址：** 确认插件配置中的 "CDN地址" 正确（例如：\`https://my-bucket.s3.us-east-1.amazonaws.com\`）

3. **检查 CORS 配置：** 如果是跨域访问问题，需要配置 S3 Bucket 的 CORS 规则

**S3 CORS 配置示例：**

\`\`\`json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedOrigins": ["*"],
    "ExposeHeaders": []
  }
]
\`\`\`

### 3. 插件未生效

**问题：** 安装后仍然上传到本地

**解决：**

1. **确认插件已启用：** 在 **插件管理** 中确认插件状态为 "已启用"

2. **清空缓存：** 后台 → 系统管理 → 清空缓存，或手动删除：
   \`\`\`bash
   rm -rf runtime/cache/*
   \`\`\`

3. **禁用其他插件：** 禁用其他云存储插件（如阿里云 OSS）

4. **检查配置：** 确认所有必填配置项都已填写

### 4. 附件表中 storage 字段显示错误

**问题：** 上传后 \`mk_attachment\` 表中 \`storage\` 字段不是 \`awss3\`

**解决：**

1. 检查插件代码中 \`storage\` 字段的赋值逻辑（应为 \`\$attachment->storage = 'awss3';\`）
2. 清空缓存并重新上传测试

### 5. 大文件上传失败

**问题：** 上传大文件时超时或失败

**解决：**

1. **启用分片上传：** 在插件配置中开启 "分片上传"
2. **调整 PHP 配置：**
   \`\`\`ini
   upload_max_filesize = 200M
   post_max_size = 200M
   max_execution_time = 300
   \`\`\`
3. **调整分片大小：** 在插件配置中设置合适的 "分片大小"（默认 4MB）

## 🔐 安全建议

1. **使用 IAM 用户：** 不要使用 AWS 根账户的访问密钥，创建专用的 IAM 用户
2. **最小权限原则：** 只授予 IAM 用户必要的 S3 权限（PutObject、GetObject、DeleteObject）
3. **定期轮换密钥：** 定期更换 AWS 访问密钥
4. **启用 MFA：** 为 AWS 账户启用多因素认证
5. **配置 Bucket 策略：** 根据需要配置存储桶的访问策略
6. **启用日志记录：** 启用 S3 服务器访问日志记录，便于审计

## 🛠️ 兼容性

### 支持的对象存储服务

除了 AWS S3，插件还支持以下兼容 S3 协议的对象存储服务：

- **MinIO** - 开源对象存储
- **阿里云 OSS** (S3 兼容模式)
- **腾讯云 COS** (S3 兼容模式)
- **Backblaze B2** (S3 兼容 API)
- **Wasabi** - 低成本对象存储
- **DigitalOcean Spaces**

**使用 S3 兼容服务的配置：**

对于非 AWS S3 的服务，可能需要自定义 endpoint：

\`\`\`php
// 修改 library/S3UploaderService.php 的构造函数
\$clientConfig = [
    'version' => 'latest',
    'region'  => \$this->region,
    'endpoint' => 'https://s3.example.com', // 自定义 endpoint
    'credentials' => [
        'key'    => \$config['access_key_id'],
        'secret' => \$config['secret_access_key'],
    ],
    'use_path_style_endpoint' => true, // 某些服务需要此选项
];
\`\`\`

## 📝 更新日志

### v1.0.0 (2024-12-XX)

- ✅ 首次发布
- ✅ 支持后台文件上传到 S3
- ✅ 支持 API 上传到 S3
- ✅ 内置独立的 S3 上传服务类
- ✅ 支持分片上传
- ✅ 支持附件删除时同步删除 S3 文件
- ✅ 完整的配置界面
- ✅ 详细的文档和故障排查指南

## 📄 许可证

MIT License

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 💬 技术支持

- **GitHub Issues:** [https://github.com/YOUR_USERNAME/fastadmin-awss3/issues](https://github.com/YOUR_USERNAME/fastadmin-awss3/issues)
- **文档:** 查看本 README 文件
- **日志:** 查看 \`runtime/log/\` 目录下的日志文件

## 🌟 致谢

感谢以下项目：

- [FastAdmin](https://www.fastadmin.net/) - 快速开发框架
- [ThinkPHP](http://www.thinkphp.cn/) - PHP 框架
- [AWS SDK for PHP](https://github.com/aws/aws-sdk-php) - AWS 官方 SDK
