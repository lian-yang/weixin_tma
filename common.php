<?php
/**
 * 公共库
 */
error_reporting(E_ALL); //E_ALL ^E_NOTICE
ini_set('display_errors', 1); //显示错误信息
!defined('IN_SYS') && die('Access Denied');


/**
 * 常量
 */
define('ROOT_PATH', dirname(__FILE__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('DOMAIN', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']);
define('IP', get_client_ip());

/**
 * 自动加载
 */
require(ROOT_PATH . 'vendor/autoload.php');

/**
 * 配置
 */
$cfg = require('config.php');


 /**
 * curl get 请求
 */
function curl_get($url, $headers = [], &$request = null, $timeout = 60)
{
    $ch = curl_init();//初始化
    curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);//ssl校验
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //超时
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); //解决乱码
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
    $result = curl_exec($ch);//运行curl
    $request = curl_getinfo($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        write_log("request url($url) error $error" . print_r($request, true), './logs/curl.log');
        throw new Exception($error);
    }
    curl_close($ch);
    return $result;
}


 /**
 * curl post请求
 * @param $url
 * @param array $params
 * @return mixed
 */
function curl_post($url, $params = [], $headers = [], &$request = null, $timeout = 60)
{
    $ch = curl_init();//初始化
    curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
    $result = curl_exec($ch);//运行curl
    $request = curl_getinfo($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        write_log("request url($url) error $error" . print_r($request, true), './logs/curl.log');
        throw new Exception($error);
    }
    curl_close($ch);
    return $result;
}


/**
 * curl post json数据
 */
function curl_json($url, $jsonStr, $headers = [], &$request = null, $timeout = 60)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($jsonStr)
    ], $headers));
    $response = curl_exec($ch);
    $request = curl_getinfo($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        write_log("request url($url) error $error" . print_r($request, true), './logs/curl.log');
        throw new Exception($error);
    }
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * 异步请求
 */
function _fsockopen($url, $post_data=array(), $cookie=array())
{
    $url_arr = parse_url($url);
    $port = isset($url_arr['port'])?$url_arr['port']:80;
    if ($url_arr['scheme'] == 'https') {
        $url_arr['host'] = 'ssl://'.$url_arr['host'];
    }
    $fp = fsockopen($url_arr['host'], $port, $errno, $errstr, 30);
    if (!$fp) {
        return false;
    }
    $getPath = isset($url_arr['path'])?$url_arr['path']:'/index.php';
    $getPath .= isset($url_arr['query'])?'?'.$url_arr['query']:'';
    $method = 'GET';  //默认get方式
    if (!empty($post_data)) {
        $method = 'POST';
    }
    $header = "$method  $getPath  HTTP/1.1\r\n";
    $header .= "Host: ".$url_arr['host']."\r\n";
    if (!empty($cookie)) {  //传递cookie信息
        $_cookie = strval(null);
        foreach ($cookie as $k=>$v) {
            $_cookie .= $k."=".$v.";";
        }
        $cookie_str = "Cookie:".base64_encode($_cookie)."\r\n";
        $header .= $cookie_str;
    }
    if (!empty($post_data)) {  //传递post数据
        $_post = array();
        foreach ($post_data as $_k=>$_v) {
            $_post[] = $_k."=".urlencode($_v);
        }
        $_post = implode('&', $_post);
        $post_str = "Content-Type:application/x-www-form-urlencoded; charset=UTF-8\r\n";
        $post_str .= "Content-Length: ".strlen($_post)."\r\n";  //数据长度
        $post_str .= "Connection:Close\r\n\r\n";
        $post_str .= $_post;  //传递post数据
        $header .= $post_str;
    } else {
        $header .= "Connection:Close\r\n\r\n";
    }
    fwrite($fp, $header);
    usleep(1000); // 这一句也是关键，如果没有这延时，可能在nginx服务器上就无法执行成功
    fclose($fp);
    return true;
}


/**
 * 获取 access_token
 */
function get_access_token($sandbox = false) {
    if($sandbox) {
        return '4cdaca570da013afcc4ee1fa7c0efd10';
    }
    $account_id = isset($_COOKIE['account_id']) ? $_COOKIE['account_id'] : 'token';
    if (file_exists(ROOT_PATH . '/token/' . $account_id . '.json')) {
        $token = json_decode(file_get_contents(ROOT_PATH . '/token/' . $account_id . '.json'), true);
        if ($token['access_token_deadline'] > time()) {
            return $token['access_token'];
        } else {
            if ($token['refresh_token_deadline'] > time()) {
                $token = refresh_token($account_id, $token['refresh_token']);
                return $token['access_token'];
            }
        }
    }
    header('Location: '. DOMAIN .'/index.php');
    exit;
}

/**
 * 获取 token
 */
function get_token($authorization_code)
{
    global $cfg;
    $url = 'https://api.e.qq.com/oauth/token?';
    $params = [
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'grant_type' => 'authorization_code',
        'authorization_code' => $authorization_code,
        'redirect_uri' => $cfg['redirect_uri'],
    ];
    $response = curl_get($url . http_build_query($params));
    $result = json_decode($response, true);
    if (isset($result['code']) && $result['code'] > 0) {
        write_log('获取token' . print_r($params, true) . print_r($result, true));
        throw new Exception($result['message'], $result['code']);
    }
    $time = time();
    $data = $result['data'];
    $data['access_token_deadline'] = intval($time + $data['access_token_expires_in'] - 200);
    $data['refresh_token_deadline'] = intval($time + $data['refresh_token_expires_in'] - 200);
    $src = ROOT_PATH . '/token/' . $data['authorizer_info']['account_id'] . '.json';
    file_put_contents($src, json_encode($data));
    setcookie('account_id', $data['authorizer_info']['account_id'], time() + 86400 * 30, '/');
    return $data;
}


/**
 * 刷新 token
 */
function refresh_token($account_id, $refresh_token)
{
    global $cfg;
    $url = 'https://api.e.qq.com/oauth/token?';
    $params = [
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
    ];
    $response = curl_get($url . http_build_query($params));
    $result = json_decode($response, true);
    if (isset($result['code']) && $result['code'] > 0) {
        write_log('刷新token' . print_r($params, true) . print_r($result, true));
        throw new Exception($result['message'], $result['code']);
    }
    $src = ROOT_PATH . '/token/' . $account_id . '.json';
    $token = json_decode(file_get_contents($src), true);
    $time = time();
    $data = $result['data'];
    $token['access_token'] = $data['access_token'];
    $token['refresh_token'] = $data['refresh_token'];
    $token['access_token_deadline'] = intval($time + $data['access_token_expires_in'] - 200);
    //$token['refresh_token_deadline'] = intval($time + $token['refresh_token_expires_in'] - 200);
    file_put_contents($src, json_encode($token));
    return $token;
}

 /**
 * 写入日志
 * @param string|array $values
 * @param string $dir
 * @return bool|int
 */
function write_log($values, $dir = null)
{
    if (is_array($values)) {
        $values = print_r($values, true);
    }
    // 日志内容
    $content = '[' . date('Y-m-d H:i:s') . ']' . PHP_EOL . $values . PHP_EOL . PHP_EOL;
    try {
        // 文件路径
        $filePath = $dir == null ? ROOT_PATH . 'logs/' : $dir;
        // 路径不存在则创建
        !is_dir($filePath) && mkdir($filePath, 0755, true);
        // 写入文件
        return file_put_contents($filePath . date('Ymd') . '.log', $content, FILE_APPEND);
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * 预处理api地址
 */
function pre_url($api, $sandbox = false)
{
    $url = $sandbox ? 'https://sandbox-api.e.qq.com/v1.1/' : 'https://api.e.qq.com/v1.1/';
    $access_token = get_access_token($sandbox);
    $timestamp = time();
    $nonce = random_code();
    return "{$url}{$api}?access_token={$access_token}&timestamp={$timestamp}&nonce={$nonce}";
}


/**
 * 生成随机字符串
 */
function random_code($len = 32)
{
    $str  = strtoupper(md5(uniqid()));
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $str[mt_rand(0, 31)];
    }
    return $code;
}


/**
 * 二维数组排序
 * @param $arr
 * @param $keys
 * @param bool $desc
 * @return mixed
 */
function array_sort($arr, $keys, $desc = false)
{
    $key_value = $new_array = array();
    foreach ($arr as $k => $v) {
        $key_value[$k] = $v[$keys];
    }
    if ($desc) {
        arsort($key_value);
    } else {
        asort($key_value);
    }
    reset($key_value);
    foreach ($key_value as $k => $v) {
        $new_array[$k] = $arr[$k];
    }
    return $new_array;
}

/**
 * 获取客户端ip
 */
function get_client_ip()
{
    static $ip = null;
    if ($ip !== null) {
        return $ip[0];
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $pos = array_search('unknown', $arr);
        if (false !== $pos) {
            unset($arr[$pos]);
        }
        $ip = trim($arr[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[0];
}

/**
 * 获取推广计划
 */
function get_campaigns($account_id = null)
{
    if(!$account_id) {
        throw new Exception('get_campaigns() account_id is not null');
    }
    $page = 1;
    $page_size = 100;
    $total_page = null;
    $filter = array();
    // 推广类型 => 微信朋友圈
    //$filter[] = ['field' => 'campaign_type', 'operator' => 'EQUALS', 'values' => ['CAMPAIGN_TYPE_WECHAT_MOMENTS']];
    $filter = '&filtering=' . json_encode($filter);
    $campaigns = [];
    do {
        $url = pre_url('campaigns/get');
        $url .= "&account_id={$account_id}&page={$page}&page_size={$page_size}";
        $response = json_decode(curl_get($url), true);
        if (isset($response['code']) && $response['code'] > 0) {
            throw new Exception($response['message'], $response['code']);
        }
        if(is_null($total_page)) {
            $total_page = $response['data']['page_info']['total_page'] - 1;
        }else {
            $total_page--;
        }
        $page++;
        $campaigns = array_merge($campaigns, $response['data']['list']);
    } while ($total_page > 0);
    //print_r($campaigns);
    return $campaigns;
}

/**
 * 返回当前的毫秒时间戳
 */
function msectime()
{
    list($msec, $sec) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
}


/**
 * 获取指定公众号推广计划
 */
function get_campaign_data($appid, $month = 1)
{
    $mp_info = get_mp($appid);
    $token = trim($mp_info['token']);
    $create_time = mktime(0, 0, rand(1,30), date('m') - $month, date('d')); //随机秒和服务商平台保持一致
    $start_time = mktime(0, 0, 0, date('m'), 1);
    $last_time= mktime(23, 59, 59, date('m'), date('d'));
    $page = 1;
    $total_page = null;
    $list = [];
    do {
        $args = '{"op_type":1,"where":{},"page":'.$page.',"page_size":25,"pos_type":999,"advanced":true,"ad_filter":{"product_type":["PRODUCTTYPE_WECHAT_SHOP"]},"create_time_range":{"start_time":'.$create_time.'},"query_index":"[\"paid\",\"exp_pv\",\"convclk_pv\",\"convclk_cpc\",\"ctr\",\"comindex\",\"cpa\",\"cvr\",\"order_pv\",\"order_amount\",\"order_pct\",\"order_roi\",\"begin_time\",\"end_time\"]","time_range":{"start_time":'.$start_time.',"last_time":'.$last_time.'}}';
        $args = urlencode($args);
        $url = "https://mp.weixin.qq.com/promotion/as_rock?action=get_campaign_data&args={$args}&token={$token}&appid=&spid=&_=" . msectime();
        $response = curl_get($url, [
            'Accept: */*',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Cookie: ' . $mp_info['cookie'],
            'Host: mp.weixin.qq.com',
            'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_frame&t1=campaign/manage&token='.$token.'&type=1',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
            'X-Requested-With: XMLHttpRequest'
        ]);
        $result = json_decode($response, true);
        if($result['ret'] > 0) {
            throw new Exception($result['ret_msg'], $result['ret']);
        }
        if(is_null($total_page)) {
            $total_page = $result['conf']['total_page'] - 1;
        }else {
            $total_page--;
        }
        $page++;
        $list = array_merge($list, !empty($result['list']) ? $result['list'] : []);
    } while ($total_page > 0);
    //echo count($list);die;
    //print_r($list);die;
    return $list;
}


/**
 * 获取计划详情
 */
function get_campaign_info($appid, $campaign_id)
{
    $mp_info = get_mp($appid);
    $token = trim($mp_info['token']);
    $args = urlencode('{"cid":'. $campaign_id .',"pos_type":999}');
    $url = "https://mp.weixin.qq.com/promotion/v3/get_campaign_info?args={$args}&token={$token}&appid=&spid=&_=" . msectime();
    $response = curl_get($url, [
        'Accept: */*',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Cookie: ' . $mp_info['cookie'],
        'Host: mp.weixin.qq.com',
        'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.$token.'&cid='.$campaign_id.'&pos_type=999',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        'X-Requested-With: XMLHttpRequest'
    ]);
    $result = json_decode($response, true);
    if ($result['ret'] > 0) {
        throw new Exception($result['ret_msg'], $result['ret']);
    }
    return $result['data'];
}

/**
 * 获取分享信息
 */
function get_share_info($appid, $page_id, $campaign_id = '')
{
    $mp_info = get_mp($appid);
    $token = trim($mp_info['token']);
    $msectime = msectime();
    $url = "https://mp.weixin.qq.com/promotion/landingpage_manager?action=get&page_id={$page_id}&token={$token}&appid=&spid=&_={$msectime}";
    $response = curl_get($url, [
        'Accept: */*',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Cookie: ' . trim($mp_info['cookie']),
        'Host: mp.weixin.qq.com',
        "Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token={$token}&cid={$campaign_id}&pos_type=999&type=single",
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        'X-Requested-With: XMLHttpRequest'
    ]);
    $result = json_decode($response, true);
    //print_r($result);die;
    if ($result['ret'] > 0) {
        throw new Exception($result['err_msg'], $result['ret']);
    }
    $canvas_info = json_decode($result['canvas_info'], true);
    return [
        'shareTitle' => $canvas_info['adCanvasInfo']['shareTitle'],
        'shareDesc' => $canvas_info['adCanvasInfo']['shareDesc']
    ];
}

/**
 * 是否json格式
 */
function is_json($data = '', $assoc = false)
{
    $data = json_decode($data, $assoc);
    if ($data && (is_object($data)) || (is_array($data) && !empty(current($data)))) {
        return true;
    }
    return false;
}



/**
 * 获取公众号列表
 */
function get_mp($appid = null)
{
    $file = ROOT_PATH . 'conf/cookie.json';
    if(!file_exists($file)) {
        throw new Exception($file . ' file does not exist', 400);
    }
    $content = file_get_contents($file);
    if(!is_json($content, true)) {
        throw new Exception($file . ' file do not conform to JSON format', 400);
    }
    $data = json_decode($content, true);
    $list = $data['list'];
    if($appid) {
        $result = null;
        foreach ($list as $item) {
            if(trim($item['appid']) == trim($appid)) {
                $result = $item;
                break;
            }
        }
        return $result;
    }
    return $list;
}

 /**
 * curl file请求
 * @param $url
 * @param array $params
 * @return mixed
 */
function curl_file($url, $post_data = [], $headers = [], &$request = null, $timeout = 0)
{
    $ch = curl_init();//初始化
    curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//显示header
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); //解决乱码
    $result = curl_exec($ch);//运行curl
    $request = curl_getinfo($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        write_log("request url($url) error $error" . print_r($request, true), './logs/curl.log');
        throw new Exception($error);
    }
    curl_close($ch);
    return $result;
}


/**
 * 上传图片或视频
 */
function uploadMaterial($appid, $path, $type = 'image') {
    $pathinfo = pathinfo($path);
    $file = ROOT_PATH . ltrim($path, '/');
    $mp_info = get_mp($appid);
    $post_data = [
        'token' => $mp_info['token'],
        'f' => 'json',
        'id' => 'WU_FILE_0',
        'name' => $pathinfo['basename'],
        'chuck' => 0,
        'chunks' => 1,
        'lastModifiedDate' => gmstrftime("%a %b %d %Y %T %Z 0800 (中国标准时间)"),
        'szie' => filesize(ROOT_PATH . ltrim($path, '/'))
    ];
    if($type == 'image') {
        $url = 'https://mp.weixin.qq.com/promotion/landingpage/snsimage?1=1&';
        //$url = 'http://tmad.cn1.utools.club/upload.php';
        $post_data['image_file'] = new CURLFile($file);
        $post_data['action'] = 'update_sns_crt_image';
        $post_data['cid'] = 0;
        $post_data['is_single'] = 1;
        $post_data['check'] = 1;
        $post_data['min_width'] = 640;
        $post_data['max_width'] = 800;
        $post_data['min_height'] = 640;
        $post_data['max_height'] = 800;
        $post_data['type'] = 'image/' . $pathinfo['extension'];
        $post_data['need_preprocess'] = 0;
        $post_data['ops'] = '';
    }else if($type == 'video') {
        $url = 'https://mp.weixin.qq.com/promotion/landingpage/snsvideo?1=1';
        $post_data['video_file'] = new CURLFile($file);
        $post_data['action'] = 'upload';
        $post_data['video_type'] = 3;
        $post_data['media_arg'] = '{"px":[{"max_w":640,"min_w":640,"max_h":480,"min_h":480}],"size":{"min":1782579.2,"max":1782579.2},"duration":{"max":15000,"min":6000},"profile":"Main","bitRate":819200}';
        $post_data['material_pos_type'] = 1;
        $post_data['type'] = 'video/mp4';
    }
    $headers = [
        'Accept: */*',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Cookie: '. $mp_info['cookie'],
        'Host: mp.weixin.qq.com',
        'Origin: https://mp.weixin.qq.com',
        'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.$mp_info['token'].'&from_pos_type=999',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36'
    ];
    $response = curl_file($url, $post_data, $headers, $request);
    $result = json_decode($response, true);
    if ($result['base_resp']['ret'] > 0) {
        throw new Exception($result['base_resp']['err_msg'], $result['base_resp']['ret']);
    }
    return $result;
}

/**
 * 迪卡尔
 */
function dikaer($arr)
{
    $arr1 = array();
    $result = array_shift($arr);
    while ($arr2 = array_shift($arr)) {
        $arr1 = $result;
        $result = array();
        foreach ($arr1 as $v) {
            foreach ($arr2 as $v2) {
                if (!is_array($v)) {
                    $v = array($v);
                }
                if (!is_array($v2)) {
                    $v2 = array($v2);
                }
                $result[] = array_merge_recursive($v, $v2);
            }
        }
    }
    return $result;
}
