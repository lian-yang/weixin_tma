<?php
ini_set('memory_limit', '1024M');
ini_set("max_execution_time", 0);
set_time_limit(0);
ignore_user_abort(true);

define('IN_SYS', true);
require('./common.php');

$files = array();
if ($handle = opendir('./data')) {
    while (false !== ($fileName = readdir($handle))) {
        if ($fileName != '.' && $fileName != '..' && stripos($fileName, '.json') === strlen($fileName) -5) {
            $filePath = ROOT_PATH . 'data/' . $fileName;
            $files[] = $filePath;
        }
    }
    closedir($handle);
}

if (count($files) > 0) {
    sort($files);
    foreach ($files as $file) {
        try {
            $fp = fopen($file, "r");
            $filename = basename($file);
            if (flock($fp, LOCK_EX | LOCK_NB)) {  // 进行排它型锁定
                write_log("文件 {$file} 加锁成功");
                $content = fread($fp, filesize($file));
                $params = json_decode($content, true);
                $created = create_campaign($params); // 创建计划
                if(!$created) {
                    write_log("文件 {$file} 创建计划失败");
                }else{
                    $moved = rename($file, ROOT_PATH . "backup/{$filename}");
                    if(!$moved) {
                        write_log("文件 {$file} 移动到备份backup目录失败");
                    }
                    write_log("文件 {$file} 处理完成");
                }
                flock($fp, LOCK_UN);    // 释放锁定
                fclose($fp);
            } else {
                write_log("文件 {$file} 正在被占用");
                fclose($fp);
                continue;
            }
        } catch (Exception $e) {
            flock($fp, LOCK_UN);
            fclose($fp);
            write_log('errno: ' .$e->getCode() . ' error: ' . $e->getMessage());
            rename($file, ROOT_PATH . "error/{$filename}"); // 移动文件到错误目录
        }
    }
    die('任务处理完成');
} else {
    write_log('暂无任务需处理');
}

// 创建计划
function create_campaign(&$params) {
    $mp_info = get_mp($params['mp_appid']);
    //print_r($mp_info);die;
    $url = 'https://mp.weixin.qq.com/promotion/v3/create_campaign_info';
    $args = $params['campaign_args'];
    $target_groups = $params['campaign_template']['target_groups'];
    foreach ($target_groups as $k => &$target_group) {
        foreach ($target_group['ad_groups'] as &$ad) {
            //print_r($ad);die;
            $ad['ad_group'] = [
                'aid' => 0,
                'aname' => $args['campaign']['cname'] . '-' . ($k + 1),
                'timeset' => $ad['ad_group']['timeset'],
                'product_id' => '',
                'product_type' => $ad['ad_group']['product_type'],
                'end_time' => $args['campaign']['end_time'],
                'begin_time' => $args['campaign']['begin_time'],
                'strategy_opt' => $ad['ad_group']['strategy_opt'],
                'bid' => $ad['ad_group']['bid'],
                'budget' => $ad['ad_group']['budget'],
                'day_budget' => $ad['ad_group']['day_budget'],
                'contract_flag' => $ad['ad_group']['contract_flag'],
                'pos_type' => $ad['ad_group']['pos_type'],
                'exposure_frequency' => $ad['ad_group']['exposure_frequency'],
                'poi' => '',
                'expand_targeting_switch' => $ad['ad_group']['expand_targeting_switch'],
                'expand_targeting_setting' => $ad['ad_group']['expand_targeting_setting']

            ];
            $ad['ad_target'] = [
                'mid' => 0,
                'ad_behavior' => $ad['ad_target']['ad_behavior'],
                'education' => $ad['ad_target']['education'],
                'device_price' => $ad['ad_target']['device_price'],
                'area' => $ad['ad_target']['area'],
                'travel_area' => $ad['ad_target']['travel_area'],
                'area_type' => 'area',
                'gender' => $ad['ad_target']['gender'],
                'age' => $ad['ad_target']['age'],
                'device_brand_model' => $ad['ad_target']['device_brand_model'],
                'businessinterest' => $ad['ad_target']['businessinterest'],
                'app_behavior' => $ad['ad_target']['app_behavior'],
                'os' => $ad['ad_target']['os'],
                'marriage_status' => $ad['ad_target']['marriage_status'],
                'wechatflowclass' => $ad['ad_target']['wechatflowclass'],
                'connection' => $ad['ad_target']['connection'],
                'telcom' => $ad['ad_target']['telcom'],
                'payment' => $ad['ad_target']['payment'],
                'custom_poi' => $ad['ad_target']['custom_poi'],
                'weapp_version' => $ad['ad_target']['weapp_version'],
                'oversea' => $ad['ad_target']['oversea'],
                'in_dmp_audience' => $ad['ad_target']['in_dmp_audience'],
                'not_in_dmp_audience' => $ad['ad_target']['not_in_dmp_audience'],
                'behavior_interest' => $ad['ad_target']['behavior_interest']
            ];
        }
        $target_group['target_group'] = [
            'mp_conf' => $target_group['target_group']['mp_conf']
        ]; 
        //print_r($target_group);
    }
    $args['target_groups'] = $target_groups;
    $post_data = array();
    $post_data['args'] = json_encode($args, 320);
    $post_data['token'] = trim($mp_info['token']);
    $post_data['appid'] = '';
    $post_data['spid'] = '';
    $post_data['_'] = msectime();
    //echo $post_data['args'];die;
    //print_r($post_data);die;
    $response = curl_post($url, $post_data, [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie: ' . $mp_info['cookie'],
        'Host: mp.weixin.qq.com',
        'Origin: https://mp.weixin.qq.com',
        'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.trim($mp_info['token']).'&from_pos_type=999',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        'X-Requested-With: XMLHttpRequest'
    ]);
    $result = json_decode($response, true);
    if ($result['base_resp']['ret'] !== 0) {
        throw new Exception($result['base_resp']['ret_msg'], $result['base_resp']['ret']);
    }
    //print_r($result);die;
    $params['campaign_id'] = $result['sequence_id']; //计划id
    //更新创意
    update_creative($params, $mp_info);
    return true;
}

// 更新创意
function update_creative(&$params, $mp_info)
{
    $url = 'https://mp.weixin.qq.com/promotion/v3/update_material';
    $campaign_info = get_campaign_info($mp_info['appid'], $params['campaign_id']);
    //print_r($campaign_info);die;
    $rid = $campaign_info['materials'][0]['rid'];
    $crt_info = array();
    $index = substr(md5($params['creative_material'][1]), 0, 10);
    if($params['material_type'] == 'image') {
        $crt_info[] = $params['mp_image_info'][$index];
    }else{
        $info = array();
        $info['button_hidden_flag'] = 0;
        $info['button_name'] = '了解更多';
        $info['button_url'] = '';
        $info['video_button_link_type'] = 1;
        $info['video_button_page_id'] = 0;
        $info['use_long_video'] = false;
        $info['short_video'] = $params['mp_short_video'][$index];
        $info['share_flag'] = 1;
        $info['share_text'] = '';
        $info['share_thumb'] = '';
        $info['share_thumb_material_id'] = '';
        $crt_info[] = $info;
    }
    //print_r($crt_info);die;
    $materials = [
        'rid' => $rid,
        'cid' => $params['campaign_id'],
        'desc' => trim($params['creative_material'][0]), //文案
        'link_hidden' => 0,
        'link_name' => $params['link_name_type'] == 'GO_SHOPPING' ? '去逛逛' : '立即购买',
        'is_show_friend' => 0,
        'interaction' => 0,
        'crt_size' => $params['material_type'] == 'image' ? 666 : 888,
        'tname' => $params['adcreative_name'],
        'page_id' => $campaign_info['materials'][0]['page_id'],
        'link_page_id' => intval($params['page_spec']['page_id']),
        'page_type' => 9,
        'link_page_type' => 25,
        'dest_conf' => '{}',
        'ext_click_url' => '',
        'ext_exposure_url' => '',
        'dest_url' => $params['page_spec']['page_url'],
        'appmsg_info' => '',
        'title' => '',
        'scheme_url' => '',
        'is_hidden_comment' => 0,
        'crt_info' => json_encode($crt_info, 320)
    ];
    $post_data = array();
    $args = [
        'cid' => $params['campaign_id'],
        'pos_type' => $params['campaign_args']['pos_type'],
        'materials' => [$materials],
        'product' => [
            'product_id' => $params['campaign_args']['product']['product_id'],
            'product_type' => $params['campaign_args']['product']['product_type']
        ],
        'additional_args' => [
            'simple_share_title' => $params['share_content_spec']['share_title'],
            'simple_share_desc' => $params['share_content_spec']['share_description'],
        ]
    ];
    $post_data['args'] = json_encode($args, 320);
    $post_data['token'] = trim($mp_info['token']);
    $post_data['appid'] = '';
    $post_data['spid'] = '';
    $post_data['_'] = msectime();
    //echo $post_data['args'];die;
    //print_r($post_data);die;
    $response = curl_post($url, $post_data, [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie: ' . $mp_info['cookie'],
        'Host: mp.weixin.qq.com',
        'Origin: https://mp.weixin.qq.com',
        'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.trim($mp_info['token']).'&cid='.$params['campaign_id'].'&pos_type=999',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        'X-Requested-With: XMLHttpRequest'
    ]);
    $result = json_decode($response, true);
    //print_r($result);die;
    if ($result['base_resp']['ret'] !== 0) {
        throw new Exception($result['base_resp']['ret_msg'], $result['base_resp']['ret']);
    }
    // 提交审核
    submit_campaign($params, $mp_info);
    return true;
}

// 提交审核
function submit_campaign(&$params, $mp_info)
{
    $url = 'https://mp.weixin.qq.com/promotion/v3/submit_campaign';
    $args = [
        'cid' => $params['campaign_id'],
        'pos_type' => $params['campaign_args']['pos_type']
    ];
    $post_data = array();
    $post_data['args'] = json_encode($args, 320);
    $post_data['token'] = trim($mp_info['token']);
    $post_data['appid'] = '';
    $post_data['spid'] = '';
    $post_data['_'] = msectime();
    $response = curl_post($url, $post_data, [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie: ' . $mp_info['cookie'],
        'Host: mp.weixin.qq.com',
        'Origin: https://mp.weixin.qq.com',
        'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.trim($mp_info['token']).'&cid='.$params['campaign_id'].'&pos_type=999',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        'X-Requested-With: XMLHttpRequest'
    ]);
    $result = json_decode($response, true);
    //print_r($result);die;
    if ($result['base_resp']['ret'] !== 0) {
        throw new Exception($result['base_resp']['ret_msg'], $result['base_resp']['ret']);
    }
    return true;
}