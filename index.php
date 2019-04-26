<?php
define('IN_SYS', true);
require('common.php');
$action = isset($_GET['action']) ? trim($_GET['action']) : 'display';
global $cfg;
$response = [
    'code' => -1,
    'message' => '操作失败'
];

if($action == 'oauth2') {
    $state = '';
    $oauth_url = "https://developers.e.qq.com/oauth/authorize?client_id={$cfg['client_id']}&redirect_uri={$cfg['redirect_uri']}&state={$state}";
    echo '正在跳转授权页面...';
    header('Location: ' . $oauth_url);
    exit;
}

if($action == 'display') {
    $filePath = ROOT_PATH . 'conf/cookie.json';
    $content = file_get_contents($filePath);
}

if($action == 'submit') {
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $filePath = ROOT_PATH . 'conf/cookie.json';
    if(empty($password) || empty($content)) {
        $response['message'] = '配置内容或操作密码不能为空';
        die(json_encode($response));
    }
    if($cfg['action_password'] != $password) {
        $response['message'] = '操作密码错误';
        die(json_encode($response));
    }
    if(!is_json($content)) {
        $response['message'] = '配置内容不符合json格式';
        die(json_encode($response));
    }
    if (file_exists($filePath)) {
        if(!copy($filePath, $filePath . '.' . date('Ymd') . '.bk')) {
            $response['message'] = '备份原文件失败，请检查路径';
            die(json_encode($response));
        }
    }
    if(!file_put_contents($filePath, $content)) {
        $response['message'] = '写文件失败，检查是否有写权限';
        die(json_encode($response));
    }
    $response['code'] = 0;
    $response['message'] = '操作成功';
    die(json_encode($response));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>配置公众号</title>
    <link rel="stylesheet" href="./static/css/easyhelper.min.css">
    <link rel="stylesheet" href="./static/css/common.css">
    <style>
        .text { width: 100%; min-height: 500px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <p class="title">配置公众号[<a href="ads/creative.php">发布组合创意</a>]</p>
        <form action="" method="post">
            <textarea class="text" name="content" rows="8" ><?=$content?></textarea>
            <input name="password" type="password" placeholder="操作密码">
            <button id="formSubmit" type="button" info>保存</button>
        </form>
    </form>

    <!-- js -->
    <script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>
    <script src="../static/js/easyhelper.min.js"></script>
    <script>
        $('#formSubmit').click(function() {
            if($('input[name=content]').val() == '') {
                Helper.ui.notice({ title: '配置内容不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name=password]').val() == '') {
                Helper.ui.notice({ title: '操作密码不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            var self = $(this);
            self.prop('disabled', true).text('提交中');
            $.ajax({
                url: '<?php echo DOMAIN . '/index.php?&action=submit';?>',
                type: 'POST',
                data: $('form').serialize(),
                dataType: 'json',
                success: function(res) {
                    console.log(res);
                    if(res.code == 0) {
                        Helper.ui.notice({ title: res.message, type: "success", autoClose: 1000 });
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }else{
                        Helper.ui.notice({ title: res.message, type: "error", autoClose: 3000 });
                    }
                },
                error: function(err) {
                    console.log(err);
                    Helper.ui.notice({ title: err.responseJSON.code + ' ' + err.responseJSON.message, type: "error", autoClose: 3000 });
                },
                complete: function() {
                    self.prop('disabled', false).text('提交');
                }
            });
        });
    </script>
</body>
</html>


