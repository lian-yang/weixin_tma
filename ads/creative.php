<?php
/**
 * 广告创意
 */
define('IN_SYS', true);
require('../common.php');
$action = isset($_GET['action']) ? trim($_GET['action']) : 'display';

try {
    //页面显示
    if($action == 'display') {
        //获取公众号列表
        $mps = get_mp();
        $mp_list = array();
        foreach ($mps as $mp) {
            $mp_list[] = ['text' => $mp['name'], 'value' => $mp['appid']];
        }
        //print_r($mp_list);die;
    }
    

    //获取指定公众号推广计划
    if($action == 'get_campaign_data') {
        $appid = trim($_GET['appid']);
        $list = get_campaign_data($appid, 1);
        //die(json_encode($list));
        $campaigns = [];
        foreach ($list as $item) {
            $campaigns[] = [
                'text' => $item['campaign_info']['cname'], 
                'value' => $item['campaign_info']['cid']
            ];
        }
        //print_r($campaigns);die;
        die(json_encode($campaigns));
    }

    //获取计划信息
    if($action == 'get_campaign_info') {
        $appid = trim($_GET['appid']);
        $campaign_id = trim($_GET['campaign_id']);
        $campaign_info = get_campaign_info($appid, $campaign_id);
        $material = $campaign_info['materials'][0];
        $share_info = get_share_info($appid, $material['page_id'], $campaign_id);
        //die(json_encode($share_info));
        $data = array();
        $data['dest_url'] = $material['dest_url'];
        $data['page_id'] = $material['page_id'];
        $data['link_page_id'] = $material['link_page_id'];
        $data['share_title'] = $share_info['shareTitle'];
        $data['share_desc'] = $share_info['shareDesc'];
        die(json_encode($data));
    }
    
    //获取所有计划（开放API）
    /* $campaigns = get_campaigns($_COOKIE['account_id']);
    $campaign_template = array();
    foreach ($campaigns as $campaign) {
        $campaign_template[] = ['text' => $campaign['campaign_id'], 'value' => $campaign['campaign_id']];
    }
    print_r($campaigns);die; */

    //提交表单
    if($action == 'submit') {
        /* $_POST = [
            'campaign_name' => 'campaign',
            'adcreative_name' => 'adcreative',
            'adcreative_title' => '创意文案1
创意文案2
创意文案3',
            'image_list' => [
                '800*800_da614f1f37fcea0c527ea7165766f3d9.jpeg',
            ],
            'video_list' => [
                'e7c7969755b213808d5f0a7dcec4c0a7.mp4',
            ],
            'share_content_spec' => [
                'share_title' => '分享标题',
                'share_description' => '分享描述'
            ],
            'page_spec' => [
                'page_id' => '2019',
                'page_url' => 'http://'
            ],
            'mp_appid' => 'wxc757bebdc4d092e0',
            'campaign_tpl_id' => '1845967806',
            'material_type' => 'image',
            'link_name_type' => 'GO_SHOPPING',
            'page_type' => 'PAGE_TYPE_DEFAULT',
        ]; */

        $campaign_template = get_campaign_info($_POST['mp_appid'], $_POST['campaign_tpl_id']);
        //print_r($campaign_template);die;

        $_POST['adcreative_title'] = explode("|", trim($_POST['adcreative_title'], "|"));
        // 过滤字符串超过限制的文案
        /* if(count($_POST['adcreative_title'])) {
            foreach ($_POST['adcreative_title'] as $index => $value) {
                if(strlen($value) > 40) {
                    unset($_POST['adcreative_title'][$index]);
                }
            }
            sort($_POST['adcreative_title']);
        } */
        if($_POST['material_type'] == 'image') {
            foreach ($_POST['image_list'] as $k => $image) {
                try {
                    $uploadinfo = uploadMaterial($_POST['mp_appid'], '/uploads/image/' . $image, 'image');
                    $uploadinfo['image_info']['materialId'] = "0";
                    $_POST['image_list'][$k] = $uploadinfo['image_info']['image_url'];
                    $index = substr(md5($uploadinfo['image_info']['image_url']), 0, 10);
                    $_POST['mp_image_info'][$index] = $uploadinfo['image_info'];
                } catch (Exception $e) {
                    write_log('creative.php line: '.__LINE__.' errno: ' . $e->getCode() . 'error: ' . $e->getMessage());
                    unset($_POST['image_list'][$k]);
                    continue;
                }
            }
            sort($_POST['image_list']);
            $adcreative_compose = dikaer([$_POST['adcreative_title'], $_POST['image_list']]);
        }else {
            foreach ($_POST['video_list'] as $k => $video) {
                try {
                    $uploadinfo = uploadMaterial($_POST['mp_appid'], '/uploads/video/' . $video, 'video');
                    $uploadinfo['data']['materialId'] = "0";
                    $uploadinfo['data']['raw_thumb_url'] = $uploadinfo['data']['thumb_url'];
                    $_POST['video_list'][$k] = $uploadinfo['data']['video_url'];
                    $index = substr(md5($uploadinfo['data']['video_url']), 0, 10);
                    $_POST['mp_short_video'][$index] = $uploadinfo['data'];
                } catch (Exception $e) {
                    write_log('creative.php line: '.__LINE__.' errno: ' . $e->getCode() . 'error: ' . $e->getMessage());
                    unset($_POST['video_list'][$k]);
                    continue;
                }
            }
            sort($_POST['video_list']);
            $adcreative_compose = dikaer([$_POST['adcreative_title'], $_POST['video_list']]);
        }
        //print_r($_POST);die;

        $changed = 0;
        $begin_time = mktime(0,0,0, date('m') + 1, date('d'));
        $end_time = strtotime('+30 day', $begin_time - 1);
        foreach ($adcreative_compose as $key => $val) {
            $campaign_name = trim($_POST['campaign_name']) . ($key + 1);
            $data = $_POST;
            $data['creative_material'] = $val;
            $data['campaign_template'] = $campaign_template;
            $data['campaign_args'] = [
                'pos_type' => 999,
                'product' => [
                    'product_type' => 'PRODUCTTYPE_WECHAT_SHOP',
                    'product_id' => '',
                    'product_info' => ''
                ],
                'campaign' => [
                    'cid' => 0,
                    'ctype' => 'CAMPAIGNTYPE_AUCTION',
                    'cname' => $campaign_name,
                    'end_time' => $end_time,
                    'begin_time' => $begin_time,
                ],
                'sub_product' => [
                    'subordinate_product_id' => '',
                    'product_type' => 'PRODUCTTYPE_WECHAT_SHOP',
                    'product_id' => '',
                    'spname' => '',
                ],
                //'target_groups' => [],
                'expected_ret' => 0,
                //创意
                'materials' => [
                    [
                        'tname' => trim($_POST['adcreative_name']) . ($key + 1),
                        'crt_size' => $_POST['material_type'] == 'image' ? 666 : 888
                    ]
                ]
            ];
            $filename = ROOT_PATH . '/data/' . $campaign_name . '_' . date('Ymd') .'.json';
            $content = json_encode($data, 320);
            if(file_put_contents($filename, $content)) {
                $changed++;
            }
        }
        $response = [
            'code' => -1,
            'message' => '操作失败',
        ];
        if ($changed > 0) {
            $response['code'] = 0;
            $response['message'] = '操作成功';
            _fsockopen(DOMAIN . '/task.php'); //触发异步请求
        }
        die(json_encode($response));
    }
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['code' => $e->getCode(), 'message' => $e->getMessage()]));
}


?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>发布组合创意</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- css -->
    <link rel="stylesheet" href="../static/css/easyhelper.min.css">
    <link rel="stylesheet" href="../static/css/common.css">
</head>
<body>
    <div class="container">
        <p class="title">发布组合创意</p>
        <form action="" method="post">
            <div class="form-item">
                <span class="form-label"><i>*</i>选择公众号：</span>
                <div id="select_mp"></div>
            </div>
            <div class="form-item" style="display:none;">
                <span class="form-label"><i>*</i>计划模板：</span>
                <div id="campaign_template_id" style="width: 320px; height: 34px; position: relative;"></div>
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>计划名称：</span>
                <input name="campaign_name" type="text" placeholder="自动创建推广计划名称前缀，后缀递增数字">
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>创意名称：</span>
                <input name="adcreative_name" type="text" placeholder="创意名称">
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>创意文案：</span>
                <textarea name="adcreative_title" rows="8" placeholder="单个文案最多40个字符，多个文案|分隔"></textarea>
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>素材类型：</span>
                <div style="width: 320px;" id="material_type"></div>
            </div>
            <div class="form-item" id="uploadImageButton">
                <span class="form-label"></span>
                <div style="width: 320px;display: flex;justify-content: start;">
                    <button type="button" style="position: relative;" info-ghost>上传图片
                        <input type="file" onchange="uploadfile(this, 'image')" accept="image/png,image/jpg,image/jpeg,image/bmp" class="upload" name="file" multiple>
                    </button>
                </div>
            </div>
            <div class="form-item" style="display: none;" id="previewImages">
                <span class="form-label"></span>
                <div class="img-list"></div>
            </div>
            <div class="form-item" id="uploadVideoButton" style="display:none;">
                <span class="form-label"></span>
                <div style="width: 320px;display: flex;justify-content: start;">
                    <button type="button" style="position: relative;" info-ghost>上传视频
                        <input type="file" onchange="uploadfile(this, 'video')" accept="video/mp4" class="upload" name="file" multiple>
                    </button>
                </div>
            </div>
            <div class="form-item" style="display: none;" id="previewVideos">
                <span class="form-label"></span>
                <div class="video-list"></div>
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>分享标题：</span>
                <input type="text" name="share_content_spec[share_title]" placeholder="最多14个字符">
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>分享描述：</span>
                <input type="text" name="share_content_spec[share_description]" placeholder="最多20个字符">
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>文字链类型：</span>
                <div style="width: 320px;" id="link_name_type"></div>
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>枫页落地页ID：</span>
                <input type="text" name="page_spec[page_id]" placeholder="">
            </div>
            <div class="form-item">
                <span class="form-label"><i>*</i>枫页落地页URL：</span>
                <input type="text" name="page_spec[page_url]" placeholder="">
            </div>
            <div class="form-item">
                <span class="form-label"></span>
                <input type="hidden" name="mp_appid" value="">
                <input type="hidden" name="campaign_tpl_id" value="">
                <input type="hidden" name="material_type" value="image">
                <input type="hidden" name="link_name_type" value="GO_SHOPPING">
                <input type="hidden" name="page_type" value="PAGE_TYPE_DEFAULT">
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
                url: '<?php echo DOMAIN . '/ads/creative.php?action=get_campaign_data&appid=';?>' + appid,
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
                            getCampaignInfo(appid, value);
                        }
                    });
                },
                error: function(err) {
                    console.log(err);
                    Helper.ui.dialogError("发生错误", err.responseJSON.message);
                }
            });
        }

        //获取计划信息
        function getCampaignInfo(appid, campaign_id) {
            $.ajax({
                url: '<?php echo DOMAIN . '/ads/creative.php?action=get_campaign_info&appid=';?>' + appid + '&campaign_id=' + campaign_id,
                type: 'get',
                dataType: 'json',
                success: function(res) {
                    console.log(res);
                    if(res) {
                        $('input[name="share_content_spec[share_title]"]').val(res.share_title || '');
                        $('input[name="share_content_spec[share_description]"]').val(res.share_desc || '');
                        $('input[name="page_spec[page_id]"]').val(res.link_page_id || '');
                        $('input[name="page_spec[page_url]"]').val(res.dest_url || '');
                    }
                },
                error: function(err) {
                    console.log(err);
                    Helper.ui.dialogError("发生错误", err.responseJSON.message);
                }
            });
        }
        

        /**
            文字链类型 checked: true
         */
        Helper.ui.radio("#link_name_type", [
            { value: 'GO_SHOPPING', text: "去逛逛", checked: true },
            { value: 'BUY_NOW', text: "立即购买" }
        ], {
            change: function ( value, text ) {
                $('input[name=link_name_type]').val(value);
            } 
        });

        // 素材类型
        Helper.ui.radio("#material_type", [
            { value: 'image', text: "图片型", checked: true },
            { value: 'video', text: "视频型" }
        ], {
            change: function ( value, text ) {
                $('input[name=material_type]').val(value);
                if(value == 'image') {
                    $('#uploadImageButton').show();
                    $('#uploadVideoButton').hide();
                    $('#previewVideos').hide();
                    $('#previewImages').show();
                }else{
                    $('#uploadImageButton').hide();
                    $('#uploadVideoButton').show();
                    $('#previewImages').hide();
                    $('#previewVideos').show();
                }
            } 
        });

        // 删除
        function removeItem(el) {
            console.log($(el));
            $(el).remove();
            Helper.ui.notice({ title: '删除成功', type: "success", autoClose: 1000 });
        }

        // 上传文件
        function uploadfile(event, type) {
            var files = event.files;
            console.log(files);
            let formData = new FormData();
            formData.append('type', type);
            for (var i = 0; i <= files.length; i++) {
                formData.append('files[]', files[i]);
            }
            $.ajax({
                url: '<?php echo DOMAIN . '/upload.php';?>',
                type: 'POST',
                data: formData,
                cache: false,
                processData: false,
                contentType: false,
                dataType: 'json',
                success : function(res) {
                    console.log(res);
                    if(res.code == 0 || res.code == 1) {
                        if(type == 'image') {
                            var node = '';
                            res.data.success.forEach(function(item) {
                                node += '<div onclick="removeItem(this)" class="img-item">\
                                            <div class="remove-item"><img src="../static/img/delete.png"></div>\
                                            <input type="hidden" name="image_list[]" value="'+ item.name +'">\
                                            <img src="'+ item.preview_url +'">\
                                        </div>';
                            });
                            console.log(node);
                            $('#previewImages').css('display', 'flex').find('.img-list').append(node);
                        }else{
                            var node = '';
                            res.data.success.forEach(function(item) {
                                node += '<div onclick="removeItem(this)" class="video-item">\
                                            <div class="remove-item"><img src="../static/img/delete.png"></div>\
                                            <input type="hidden" name="video_list[]" value="'+ item.name +'">\
                                            <video width="145" autoplay="true" preload="auto" loop="loop" x-webkit-airplay="true" webkit-playsinline="" playsinline="true" muted="muted">\
                                                <source src="'+ item.preview_url +'" type="video/mp4">\
                                            </video>\
                                        </div>';
                            });
                            console.log(node);
                            $('#previewVideos').css('display', 'flex').find('.video-list').append(node);
                        }
                    }
                    if(res.data.fail.length > 0) {
                        res.data.fail.forEach(function(item) {
                            Helper.ui.notice({ title: item, type: "error", autoClose: 3000 });
                        });
                    }
                    if(res.code == 0) {
                        Helper.ui.notice({ title: res.message, type: "success", autoClose: 1000 });
                    }else if(res.code == 1) {
                        Helper.ui.notice({ title: res.message, type: "info", autoClose: 2000 });
                    }else {
                        Helper.ui.notice({ title: res.message, type: "error", autoClose: 3000 });
                    }
                },
                error:function(err){
                    console.log(err);
                    Helper.ui.dialogError("发生错误", "上传发生错误，请联系QQ:395486566");
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
                Helper.ui.notice({ title: '请选择计划模板', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name=campaign_name]').val() == '') {
                Helper.ui.notice({ title: '计划名称不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name=adcreative_name]').val() == '') {
                Helper.ui.notice({ title: '创意名称不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('textarea[name=adcreative_title]').val() == '') {
                Helper.ui.notice({ title: '创意文案不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name="share_content_spec[share_title]"]').val() == '') {
                Helper.ui.notice({ title: '分享标题不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name="share_content_spec[share_description]"]').val() == '') {
                Helper.ui.notice({ title: '分享描述不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name="page_spec[page_id]"]').val() == '') {
                Helper.ui.notice({ title: '枫页落地页ID不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            if($('input[name="page_spec[page_url]"]').val() == '') {
                Helper.ui.notice({ title: '枫页落地页URL不能为空', type: 'warn', autoClose: 1000 });
                return;
            }
            var self = $(this);
            self.prop('disabled', true).text('正在提交，请稍后...');
            $.ajax({
                url: '<?php echo DOMAIN . '/ads/creative.php?&action=submit';?>',
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
                    Helper.ui.dialogError("发生错误", err.responseJSON.message);
                },
                complete: function() {
                    self.prop('disabled', false).text('提交');
                }
            });
        });
    </script>

</body>
</html>