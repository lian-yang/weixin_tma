<?php
/**
 * 批量复制计划
 */
define('IN_SYS', true);
require('../common.php');
$action = isset($_GET['action']) ? trim($_GET['action']) : 'display';

try {
    // 显示页面
    if ($action == 'display') {
        //获取公众号列表
        $mps = get_mp();
        $mp_list = array();
        foreach ($mps as $mp) {
            $mp_list[] = ['text' => $mp['name'], 'value' => $mp['appid']];
        }
        //print_r($mp_list);die;
    }

    // 获取指定公众号推广计划
    if ($action == 'get_campaign_data') {
        $appid = trim($_GET['appid']);
        $list = get_campaign_data($appid, 1); //查询1个月内的计划
        //die(json_encode($list));
        $campaigns = [];
        foreach ($list as $item) {
            $campaigns[] = ['text' => $item['campaign_info']['cname'], 'value' => $item['campaign_info']['cid']];
        }
        //print_r($campaigns);die;
        die(json_encode($campaigns));
    }
    
    // 提交表单
    if ($action == 'submit') {
        $response = ['code' => -1, 'message' => '操作失败'];
        $mp_appid = isset($_POST['mp_appid']) ? trim($_POST['mp_appid']) : '';
        $campaign_tpl_id = isset($_POST['campaign_tpl_id']) ? trim($_POST['campaign_tpl_id']) : '';
        $rename = isset($_POST['rename']) ? trim($_POST['rename']) : '';
        $num = isset($_POST['num']) ? trim($_POST['num']) : '';
        if (empty($mp_appid) || empty($campaign_tpl_id) || empty($num)) {
            $response['message'] = '缺少请求参数';
            die(json_encode($response));
        }
        $campaign_template = get_campaign_info($mp_appid, $campaign_tpl_id);
        $materials_template = $campaign_template['materials'][0];
        $share_info = get_share_info($mp_appid, $materials_template['page_id']);
        //print_r($share_info);die;
        //die(json_encode($campaign_template, 320));
        $mp_info = get_mp($mp_appid);
        $changed = 0;
        for ($i = 1; $i <= $num; $i++) {
            $campaign_name = $rename == '' ? $campaign_template['campaign']['cname'] . '-' . $i : $rename . '-' . $i;
            $target_groups = $campaign_template['target_groups'];
            //$begin_time = $campaign_template['campaign']['begin_time'];
            //$end_time = $campaign_template['campaign']['end_time'];
            $begin_time = mktime(0, 0, 0, date('m') + 1, date('d'));
            $end_time = strtotime('+30 day', $begin_time - 1);
            foreach ($target_groups as $k => &$target_group) {
                foreach ($target_group['ad_groups'] as &$ad) {
                    //print_r($ad);die;
                    $ad['ad_group'] = [
                        'aid' => 0,
                        'aname' => $campaign_name . '-' . ($k + 1),
                        'timeset' => $ad['ad_group']['timeset'],
                        'product_id' => '',
                        'product_type' => $ad['ad_group']['product_type'],
                        'end_time' => $end_time,
                        'begin_time' => $begin_time,
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
            $args = array();
            $args['pos_type'] = 999;
            $args['product'] = [
                'product_type' => $campaign_template['product']['product_type'],
                'product_id' => '',
                'product_info' => ''
            ];
            $args['campaign'] = [
                'cid' => 0,
                'ctype' => $campaign_template['campaign']['ctype'],
                'cname' => $campaign_name,
                'end_time' => $end_time,
                'begin_time' => $begin_time,
            ];
            $args['sub_product'] = [
                'subordinate_product_id' => '',
                'product_type' => 'PRODUCTTYPE_WECHAT_SHOP',
                'product_id' => '',
                'spname' => ''
            ];
            $args['target_groups'] = $target_groups;
            $args['expected_ret'] = 0;
            $args['materials'] = [
                [
                    'tname' => $materials_template['tname'],
                    'crt_size' => $materials_template['crt_size']
                ]
            ];
            $post_data = array();
            $post_data['args'] = json_encode($args, 320);
            $post_data['token'] = trim($mp_info['token']);
            $post_data['appid'] = '';
            $post_data['spid'] = '';
            $post_data['_'] = msectime();
            //print_r($post_data);die;
            $url = 'https://mp.weixin.qq.com/promotion/v3/create_campaign_info';
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

            // 更新创意
            $campaign_id = $result['sequence_id']; //计划id
            $campaign_info = get_campaign_info($mp_info['appid'], $campaign_id);
            $rid = $campaign_info['materials'][0]['rid'];
            $materials = [
                'rid' => $rid,
                'cid' => $campaign_id,
                'desc' => $materials_template['desc'],
                'link_hidden' => $materials_template['link_hidden'],
                'link_name' => $materials_template['link_name'],
                'is_show_friend' => $materials_template['is_show_friend'],
                'interaction' => $materials_template['interaction'],
                'crt_size' => $materials_template['crt_size'],
                'tname' => $materials_template['tname'],
                'page_id' => $campaign_info['materials'][0]['page_id'],
                'link_page_id' => $materials_template['link_page_id'],
                'page_type' => $materials_template['page_type'],
                'link_page_type' => $materials_template['link_page_type'],
                'dest_conf' => $materials_template['dest_conf'],
                'ext_click_url' => $materials_template['ext_click_url'],
                'ext_exposure_url' => $materials_template['ext_exposure_url'],
                'dest_url' => $materials_template['dest_url'],
                'appmsg_info' => $materials_template['appmsg_info'],
                'title' => $materials_template['title'],
                'scheme_url' => $materials_template['scheme_url'],
                'is_hidden_comment' => $materials_template['is_hidden_comment'],
                'crt_info' => $materials_template['crt_info']
            ];
            $post_data = array();
            $args = [
                'cid' => $campaign_id,
                'pos_type' => 999,
                'materials' => [$materials],
                'product' => [
                    'product_id' => $campaign_template['product']['product_id'],
                    'product_type' => $campaign_template['product']['product_type']
                ],
                'additional_args' => [
                    'simple_share_title' => $share_info['shareTitle'],
                    'simple_share_desc' => $share_info['shareDesc'],
                ]
            ];
            $post_data['args'] = json_encode($args, 320);
            $post_data['token'] = trim($mp_info['token']);
            $post_data['appid'] = '';
            $post_data['spid'] = '';
            $post_data['_'] = msectime();
            $url = 'https://mp.weixin.qq.com/promotion/v3/update_material';
            $response = curl_post($url, $post_data, [
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Cookie: ' . trim($mp_info['cookie']),
                'Host: mp.weixin.qq.com',
                'Origin: https://mp.weixin.qq.com',
                'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.trim($mp_info['token']).'&cid='.$campaign_id.'&pos_type=999',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
                'X-Requested-With: XMLHttpRequest'
            ]);
            $result = json_decode($response, true);
            //print_r($result);die;
            if ($result['base_resp']['ret'] !== 0) {
                throw new Exception($result['base_resp']['ret_msg'], $result['base_resp']['ret']);
            }

            // 提交审核
            $url = 'https://mp.weixin.qq.com/promotion/v3/submit_campaign';
            $args = [
                'cid' => $campaign_id,
                'pos_type' => 999
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
                'Cookie: ' . trim($mp_info['cookie']),
                'Host: mp.weixin.qq.com',
                'Origin: https://mp.weixin.qq.com',
                'Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token='.trim($mp_info['token']).'&cid='.$campaign_id.'&pos_type=999',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
                'X-Requested-With: XMLHttpRequest'
            ]);
            $result = json_decode($response, true);
            //print_r($result);die;
            if ($result['base_resp']['ret'] !== 0) {
                throw new Exception($result['base_resp']['ret_msg'], $result['base_resp']['ret']);
            }
            $changed++;
        }
        if($changed > 0 && $changed == $num) {
            $response = ['code' => 0, 'message' => '批量复制成功'];
        }else if($changed > 0 && $changed != $num) {
            $response = ['code' => 0, 'message' => '成功复制' . $changed . '次, 失败' . $num - $changed . '次'];
        }else{
            $response['message'] = '批量复制失败';
        }
        die(json_encode($response));
    }
} catch (Exception $e) {
    write_log('errno: ' . $e->getCode() . ' error: ' . $e->getMessage());
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['code' => $e->getCode(), 'message' => $e->getMessage()]));
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>批量复制计划</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- css -->
    <link rel="stylesheet" href="../static/css/easyhelper.min.css">
    <link rel="stylesheet" href="../static/css/common.css">
</head>
<body>
    <div class="container">
        <p class="title">批量复制计划</p>
        <form action="" method="post">
            <div class="form-item">
                <span class="form-label"><i>*</i>选择公众号：</span>
                <div id="select_mp"></div>
            </div>
            <div class="form-item" style="display:none;">
                <span class="form-label"><i>*</i>选择复制计划：</span>
                <div id="campaign_template_id" style="width: 320px; height: 34px; position: relative;"></div>
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>复制次数：</span>
                <input name="num" type="text" placeholder="建议复制次数不要太多，可能导致平台触发限流...">
            </div>
            <div class="form-item">
                <span class="form-label">重命名计划前缀：</span>
                <input name="rename" type="text" placeholder="选填, 不填使用复制计划名称，自动递增数字">
            </div>
            <div class="form-item">
                <span class="form-label"></span>
                <input type="hidden" name="mp_appid" value="">
                <input type="hidden" name="campaign_tpl_id" value="">
                <button id="formSubmit" style="width: 300px;" type="button" info>提交</button>
            </div>
        </form>
    </div>

    <!-- js -->
    <script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>
    <script src="../static/js/easyhelper.min.js"></script>
    <script>
        //选择公众号
        Helper.ui.select("#select_mp", <?php echo json_encode($mp_list); ?>, {
            width: '320px',
            change: function(appid) {
                $('input[name=mp_appid]').val(appid);
                selectCampaignChange(appid);
            }
        });

        //推广计划下拉
        function selectCampaignChange(appid) {
            $('#campaign_template_id').children().remove();
            $.ajax({
                url: '<?php echo DOMAIN . '/ads/copy.php?action=get_campaign_data&appid=';?>' + appid,
                type: 'get',
                dataType: 'json',
                success: function(res) {
                    console.log(res);
                    if(!res.length) {
                        Helper.ui.notice({ title: "请先登录服务商系统创建模板计划...", type: "error", autoClose: 3000 });
                        return;
                    }
                    $('#campaign_template_id').parent().show();
                    Helper.ui.select("#campaign_template_id", res, {
                        width: '320px',
                        search: true,
                        change: function(value) {
                            $('input[name=campaign_tpl_id]').val(value);
                        }
                    });
                },
                error: function(err) {
                    console.log(err);
                    Helper.ui.notice({ title: err.responseJSON.code + ' ' + err.responseJSON.message, type: "error", autoClose: 3000 });
                }
            });
        }

        // 提交表单
        $('#formSubmit').click(function() {
            if($('input[name=mp_appid]').val() == '') {
                Helper.ui.notice({ title: '请选择公众号', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name=campaign_tpl_id]').val() == '') {
                Helper.ui.notice({ title: '请选择复制计划', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name=num]').val() == '') {
                Helper.ui.notice({ title: '请填写复制数量', type: 'warn', autoClose: 1000 });
                $('input[name=num]').focus();
                return;
            }
            if(!/^[1-9]\d*$/.test($('input[name=num]').val())) {
                Helper.ui.notice({ title: '复制数量必须是正整数', type: 'warn', autoClose: 1000 });
                $('input[name=num]').val('').focus();
                return;
            }
            var self = $(this);
            self.prop('disabled', true).text('正在处理，请稍后...');
            $.ajax({
                url: '<?php echo DOMAIN . '/ads/copy.php?&action=submit';?>',
                type: 'POST',
                data: $('form').serialize(),
                dataType: 'json',
                success : function(res) {
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