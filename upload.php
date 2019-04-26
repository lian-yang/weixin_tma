<?php
//echo json_encode(array_merge($_POST, $_FILES));die;
define('IN_SYS', true);
require('common.php');
function getFiles()
{
    foreach ($_FILES as $file) {
        $fileNum = count($file['name']);
        $files = array();
        for ($i = 0; $i < $fileNum; $i++) {
            $files[$i]['name'] = $file['name'][$i];
            $files[$i]['type'] = $file['type'][$i];
            $files[$i]['tmp_name'] = $file['tmp_name'][$i];
            $files[$i]['error'] = $file['error'][$i];
            $files[$i]['size'] = $file['size'][$i];
        }
    }
    return $files;
}

$type = isset($_POST['type']) ? trim($_POST['type']) : 'image';
$storage = new \Upload\Storage\FileSystem(UPLOAD_PATH . $type, true); //覆盖上传
$_FILES = getFiles();
$allowUploadSize = ['800*640', '640*800', '800*800'];
$success = array();
$fail = array();
foreach ($_FILES as $key => $tmpfile) {
    try {
        $file = new \Upload\File($key, $storage);
        if ($type == 'image') {
            $file->addValidations(array(
                new \Upload\Validation\Mimetype(['image/png', 'image/jpg', 'image/jpeg', 'image/bmp']),
                new \Upload\Validation\Size('3M')
            ));
            $dimensions = $file->getDimensions();
            $imageSize = $dimensions['width'] . '*' . $dimensions['height'];
            if(!in_array($imageSize, $allowUploadSize)) {
                $file->addError($tmpfile['name'] . " 图片尺寸不符合要求");
            }
            $file->setName($imageSize . '_' . $file->getMd5());
        } elseif ($type == 'video') {
            $file->addValidations(array(
                new \Upload\Validation\Mimetype(['video/mp4']),
                new \Upload\Validation\Size('1782579B')
            ));
            $file->setName($file->getMd5());
        }
        
        $data = array(
            'name'       => $file->getNameWithExtension(),
            'url' => '/uploads/' . $type . '/' . $file->getNameWithExtension(),
            'preview_url' => DOMAIN . '/uploads/' . $type . '/' . $file->getNameWithExtension(),
            'extension'  => $file->getExtension(),
            'mime'       => $file->getMimetype(),
            'size'       => $file->getSize(),
            'md5'        => $file->getMd5(),
            'dimensions' => $file->getDimensions()
        );
        $file->upload();
        $success[] = $data;
    } catch (\Exception $e) {
        $fail = array_merge($fail, $file->getErrors());
    }
}

$response = [
    'code' => -1,
    'message' => '上传失败',
    'data' => [
        'success' => $success,
        'fail' => $fail
    ]
];
if(count($fail) > 0 && count($success) > 0) {
    $response['code'] = 1;
    $response['message'] = '部分上传成功';
}
if(count($fail) == 0 && count($success) > 0) {
    $response['code'] = 0;
    $response['message'] = '上传成功';
}
die(json_encode($response));



