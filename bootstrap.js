/**
 * AWS S3 上传插件前端脚本
 * 由于使用服务器中转模式，前端无需特殊处理
 * 所有上传逻辑由后端 awss3/index/upload 控制器处理
 */
if (typeof Config !== 'undefined' && typeof Config.upload !== 'undefined' && Config.upload.storage === 'awss3') {
    console.log('AWS S3 Upload Plugin: Server-side upload mode enabled');
}
