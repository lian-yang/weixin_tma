<?php
define('IN_SYS', true);
require('common.php');

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';

if($action == 'list') {
    $url = pre_url('advertiser/get');
    $url .= '&page_size=100';
    //echo htmlspecialchars($url);
    try {
        $response = json_decode(curl_get($url), true);
        if (isset($response['code']) && $response['code'] > 0) {
            throw new Exception($response['message'], $response['code']);
        }
        $list = $response['data']['list'];
        //var_dump($list);die;
        $trs = '';
        foreach ($list as $item) {
            $trs .= '<tr>';
            $trs .= "<td>{$item['account_id']}</td>\n";
            $trs .= "<td><button onclick='wechatBind({$item["account_id"]})' type='button' info>绑定公众号</button></td>";
            $trs .= '</tr>';
        } 
        $html = <<<html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>广告创意</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- css -->
    <link rel="stylesheet" href="../static/css/easyhelper.min.css">
    <link rel="stylesheet" href="../static/css/common.css">
</head>
<body>
    <div class="container">
        <div class="helper-table helper-table-center">
            <table>
                <thead class="helper-table-thead">
                    <tr>
                        <td width="100">推广帐号 id</td>
                        <td width="100">操作</td>
                    </tr>
                </thead>
                <tbody class="helper-table-tbody">
                    {$trs}
                </tbody>
            </table>
        </div>
    </div>

    <!-- js -->
    <script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>
    <script src="../static/js/easyhelper.min.js"></script>
    <script>
        function wechatBind(account_id) {
            window.open('/wechat_bind.php?action=bind&account_id=' + account_id);
        }
    </script>
</body>
</html>
html;
        die($html);
    } catch (Exception $e) {
        die($e->getCode() . ':' . $e->getMessage());
    }
}

if($action == 'bind') {
    if(empty($_GET['account_id'])) {
        die('account_id is undefined');
    }
    $account_id = trim($_GET['account_id']); 
    $access_token = get_access_token();
    $redirect_uri = DOMAIN . "/wechat_bind.php?action=notify";
    $url = "https://developers.e.qq.com/authorization/wechat_bind?access_token={$access_token}&redirect_uri={$redirect_uri}&account_id={$account_id}";
    header('Location: ' . $url);
}

if($action == 'notify') {
    die('bind wechat success!');
}

die('undefined action!');