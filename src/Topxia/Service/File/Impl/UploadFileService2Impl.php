<?php

namespace Topxia\Service\File\Impl;

use Topxia\Common\ArrayToolkit;
use Topxia\Common\FileToolkit;
use Topxia\Service\Common\BaseService;
use Topxia\Service\File\UploadFileService2;
use Topxia\Service\CloudPlatform\Client\CloudAPI;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Topxia\Service\Common\ServiceKernel;
    
class UploadFileService2Impl extends BaseService implements UploadFileService2
{
	static $implementor = array(
        'local'=>'File.LocalFileImplementor2',
        'cloud' => 'File.CloudFileImplementor2',
    );

    public function initUpload($params)
    {
    	$user = $this->getCurrentUser();
        if (empty($user)) {
            throw $this->createServiceException("用户未登录，上传初始化失败！");
        }

        if (!ArrayToolkit::requireds($params, array('targetId', 'targetType', 'bucket', 'hashType', 'hashValue'))) {
            throw $this->createServiceException("参数缺失，上传初始化失败！");
        }

        $setting = $this->getSettingService()->get('storage');
        $params['storage'] = empty($setting['upload_mode']) ? 'local' : $setting['upload_mode'];

        $implementor = $this->getFileImplementorByStorage($params['storage']);

        $file = $this->getUploadFileDao()->addFile($implementor->prepareUpload($params));

        $file['bucket'] = $params['bucket'];
        $file['hashType'] = $params['hashType'];
        $file['hashValue'] = $params['hashValue'];

        $params = $implementor->initUpload($file);

        $file = $this->getUploadFileDao()->updateFile($file['id'], array('globalId' => $params['globalId']));

        return $params;
    }

    public function finishedUpload($params)
    {
        $file = $this->getUploadFileDao()->getFileByGlobalId($params['globalId']);
        if (empty($file['globalId'])) {
            throw $this->createServiceException("文件不存在(global id: #{$params['globalId']})，完成上传失败！");
        }

    	$file = $this->getUploadFileDao()->updateFile($file['id'], array(
            'status' => 'ok',
            'convertStatus' => 'waiting',
        ));
    }

    public function setFileProcessed($params)
    {
        try {

            $file = $this->getUploadFileDao()->getFileByGlobalId($params['globalId']);

            $qulities = array('sd', 'hd', 'shd');

            $metas = array();

            // UploadFileService2
            // {"convertor":"HLSEncryptedVideo","segtime":10,"videoQuality":"low","audioQuality":"low"}

            foreach ($params['data']['m3u8s'] as $index => $key) {
                $metas[$qulities[$index]] = array(
                    'type' => $qulities[$index],
                    'cmd' => array('hlsKey' => '1234567890123456'),
                    'key' => $key,
                );
            }

            $this->getUploadFileDao()->updateFile($file['id'], array(
                'metas2' => json_encode($metas),
                'convertStatus' => 'success',
                'convertParams' => json_encode(array(
                    'convertor' => 'HLSEncryptedVideo',
                    'videoQuality' => 'low',
                    'audioQuality' => 'low',
                ))
            ));
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            file_put_contents('/tmp/error', $msg);
        }
    }

    protected function getFileImplementor($file)
    {
    	return $this->getFileImplementorByStorage($file['storage']);
    }

    protected function getFileImplementorByStorage($storage)
    {
        return $this->createFileImplementor($storage);
    }

    protected function createFileImplementor($key)
    {
        if (!array_key_exists($key, self::$implementor)) {
            throw $this->createServiceException(sprintf("`%s` File Implementor is not allowed.", $key));
        }
        return $this->createService(self::$implementor[$key]);
    }

    protected function getSettingService()
    {
        return $this->createService('System.SettingService');
    }

    protected function getUploadFileDao()
    {
        return $this->createDao('File.UploadFileDao');
    }

}