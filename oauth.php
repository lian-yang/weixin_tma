<?php
define('IN_SYS', true);
require('common.php');

$authorization_code = isset($_GET['authorization_code']) ? $_GET['authorization_code'] : null;
$state = isset($_GET['state']) ? $_GET['state'] : null;
if(!$authorization_code) {
    die('authorization_code is undefined');
}
try {
    $access_token = get_token($authorization_code);
    var_dump($access_token);
} catch (Exception $e) {
    exit(json_encode([
        'code' => $e->getCode(),
        'message' => $e->getMessage()
    ]));
}

