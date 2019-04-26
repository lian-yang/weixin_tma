## API

#### 创建广告计划 返回 campaign_id
*account_id: 广告主帐号 id，有操作权限的帐号 id，不支持代理商 id  
*campaign_name: 推广计划名称，同一帐号下的推广计划名称不允许重复字段长度最小 1 字节，长度最大 120 字节  
*campaign_type: CAMPAIGN_TYPE_WECHAT_MOMENTS 微信朋友圈广告，仅可投放微信朋友圈流量的广告  
*promoted_object_type: PROMOTED_OBJECT_TYPE_ECOMMERCE 推广目标类型电商推广  
configured_status: 客户设置的状态 AD_STATUS_NORMAL 有效 AD_STATUS_SUSPEND 暂停  
speed_mode: 投放速度模式，SPEED_MODE_STANDARD 标准投放 SPEED_MODE_FAST加速投放 默认值为标准投放  

#### 创建广告创意 返回 adcreative_id
*account_id: 广告主帐号 id，有操作权限的帐号 id，不支持代理商 id  
*campaign_id: 推广计划 id  
*adcreative_name: 创意名称 例:创意-20190412  
*adcreative_template_id: 创意规格 id 例:618  
*adcreative_elements: {  
&emsp;&emsp; //图片类型  
&emsp;&emsp; "image_list": [  
&emsp;&emsp;&emsp;&emsp; “图片id”  
&emsp;&emsp; ],  
&emsp;&emsp; //视频类型  
&emsp;&emsp; "short_video_struct": {  
&emsp;&emsp;&emsp;&emsp; "short_video1": “视频id”  
&emsp;&emsp; },  
&emsp;&emsp; "title": “外层文案”,  
&emsp;&emsp; "link_name_type": "GO_SHOPPING", //可选值: VIEW_DETAILS(查看详情), BUY_NOW(立即购买), GO_SHOPPING(去逛逛)  
} 创意元素  
*site_set: ["SITE_SET_WECHAT"] 投放站点集合 SITE_SET_WECHAT 微信  
*promoted_object_type: PROMOTED_OBJECT_TYPE_ECOMMERCE 推广目标类型 电商推广  
*page_type：PAGE_TYPE_CANVAS_WECHAT 微信原生推广页 PAGE_TYPE_DEFAULT 自定义落地页链接 落地页类型  
page_spec: {"page_url":""} 自定义落地页链接时必填  

share_content_spec: {share_title: 分享标题, share_description: 分享描述} 若该字段不为空，则表示投放简版原生推广页

#### 上传图片 images/add 返回image_id preview_url
*account_id: 广告主帐号id  
*upload_type: UPLOAD_TYPE_FILE 图片文件上传 UPLOAD_TYPE_BYTES 图片内容上传  
*signature: 图片文件签名，使用图片文件的 md5 值，用于检查上传图片文件的完整性  
*file: 图片二进制流，支持文件类型：jpg、png、gif 文件大小限制：小于等于 3M  
 
#### 上传视频 videos/add 返回video_id  
*account_id: 广告主帐号id  
*video_file: 被上传的视频文件，视频二进制流  
*signature: 视频文件签名  

#### 创意规格id参考
adcreative_template_id  
视频 618 -> 640*480  
单图文 263 -> 800*640  
单图文 310 -> 640*800  
单图文 311 -> 800*800  
单图文 450 -> 800*450 未支持 多了description字段 必填  


## 细节整理

### 素材包 压缩文件 .zip
    .txt 文件表示文案 \r\n 一行一个文案
    .mp4 文件表示视频 需上传到wx获取 video_id
    .png .jpg .jpeg .bmp 文件表示图片 需上传到wx获取 image_id

### 表单字段
    推广计划名称
    创意名称
    创意规格ID（根据图片尺寸映射创意规格id）
    分享标题
    分享描述
    素材类型 （图片 or 视频）文案不能同时和图片以及视频一起3xN组合 
    文字链类型（单选） VIEW_DETAILS(查看详情), BUY_NOW(立即购买), GO_SHOPPING(去逛逛)
    落地页类型 PAGE_TYPE_DEFAULT(自定义落地页链接) PAGE_TYPE_CANVAS_WECHAT(微信原生推广页，通过微信创建的落地页类型)

### 逻辑流程 （openApi 和 抓包方式 流程 参数 基本完全不一样）
1. 获取依赖参数 token 广告主 account_id 等
2. 前端缓存提交表单，方便出错回填（优化体验，暂时跳过）
3. 校验表单（计划名称 创意名称120字 解压素材包校验 视频尺寸640*480 大小不超过1741 KB mp4格式 | 图片大小不超过300 KB）
4. 根据提交的推广计划名称创建一个计划 获得 推广计划 campaign_id
5. 根据图片尺寸映射创意规格id，如果是视频 创意规格id=618
6. 组合request参数之后转json保存在data目录
7. 定时任务读取data目录下的文件加读锁之后curl请求成功之后解锁并移动文件到backup目录备份供以后排查使用

### 其他备注
图片尺寸(三选一)：800*800像素、640*800像素、800*640像素
图片格式：大小要求在300KB以内，若超出系统会自动压缩，不支持GIF格式，其中文字占图片篇幅不超过30%；

6-15秒视频自动循环播放
视频尺寸：640像素 x 480像素
视频大小：文件大小不超过1.7MB

### 抓包分析

```
创建广告计划 POST
https://mp.weixin.qq.com/promotion/v3/create_campaign_info
请求头
Accept: application/json, text/javascript, */*; q=0.01
Accept-Encoding: gzip, deflate, br
Accept-Language: zh-CN,zh;q=0.9,en;q=0.8
Connection: keep-alive
Content-Length: 10556
Content-Type: application/x-www-form-urlencoded; charset=UTF-8
Cookie: 
Host: mp.weixin.qq.com
Origin: https://mp.weixin.qq.com
Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token=2577548495&from_pos_type=999
User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36
X-Requested-With: XMLHttpRequest

Form Data
args: {
    "pos_type":999,
    "product":{
        "product_type":"PRODUCTTYPE_WECHAT_SHOP",
        "product_id":"",
        "product_info":""
    },
    "campaign":{
        "cid":0,
        "ctype":"CAMPAIGNTYPE_AUCTION",
        "cname":"20190412-推广我的商品-朋友圈信息流-竞价购买广告",
        "end_time":1557590399,
        "begin_time":1554998400
    },
    "sub_product":{
        "subordinate_product_id":"",
        "product_type":"PRODUCTTYPE_WECHAT_SHOP",
        "product_id":"",
        "spname":""
    },
    "target_groups":[
        {
            "target_group":{
                "mp_conf":"{"original_age":[14,66],"poi_show":0,"version":[],"city_level":3,"show_area":[3],"custom_poi":[],"ocpm_type":[0],"import_target_used":false,"dmp_type":"","selected_ocpm_item":{"bat_desc":"元/下单","bid_action_type":7,"bid_objective":7,"bo_desc":"获取更多下单量","max_bid":2000,"min_bid":0.5,"permission":1},"bid_action_type":"ocpm"}"
            },
            "ad_groups":[
                {
                    "ad_group":{
                        "aid":0,
                        "aname":"Test",
                        "timeset":"",
                        "product_id":"",
                        "product_type":"PRODUCTTYPE_WECHAT_SHOP",
                        "end_time":1557590399,
                        "begin_time":1554998400,
                        "strategy_opt":"{"bid_objective":7,"bid_action_type":7}",
                        "bid":50,
                        "budget":100000,
                        "day_budget":100000,
                        "contract_flag":2,
                        "pos_type":999,
                        "exposure_frequency":6,
                        "poi":"",
                        "expand_targeting_switch":"EXPAND_TARGETING_SWITCH_CLOSE",
                        "expand_targeting_setting":"[]"
                    },
                    "ad_target":{
                        "mid":0,
                        "ad_behavior":"[{"in_action_list":[],"not_in_action_list":[]}]",
                        "education":"[]",
                        "device_price":"[]",
                        "area":["130100""130200",]",
                        "travel_area":"[]",
                        "area_type":"area",
                        "gender":"[]",
                        "age":"["30~66"]",
                        "device_brand_model":"[]",
                        "businessinterest":"[]",
                        "app_behavior":"{}",
                        "os":"[]",
                        "marriage_status":"[]",
                        "wechatflowclass":"[]",
                        "connection":"[]",
                        "telcom":"[]",
                        "payment":"[]",
                        "custom_poi":"[]",
                        "weapp_version":"{"min_ios_version":0,"min_android_version":0}",
                        "oversea":"[]",
                        "in_dmp_audience":"[]",
                        "not_in_dmp_audience":"[]",
                        "behavior_interest":"{}"
                    }
                }
            ]
        }
    ],
    "expected_ret":0,
    "materials":[
        {
            "tname":"创意-20190412",
            "crt_size":666
        }
    ]
}
token: 2577548495
appid: 
spid: 
_: 1555061763704

Response 
{"base_resp":{"cur_time":"1555061774","ret":0,"ret_msg":"ok"},"msg":"ok","ret":0,"sequence_id":1841208338}

创意 POST
https://mp.weixin.qq.com/promotion/v3/update_material
Accept: application/json, text/javascript, */*; q=0.01
Accept-Encoding: gzip, deflate, br
Accept-Language: zh-CN,zh;q=0.9,en;q=0.8
Connection: keep-alive
Content-Length: 3147
Content-Type: application/x-www-form-urlencoded; charset=UTF-8
Cookie: 
Host: mp.weixin.qq.com
Origin: https://mp.weixin.qq.com
Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token=162771918&cid=1841208338&pos_type=999
User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36
X-Requested-With: XMLHttpRequest
Form Data
args: {
    "cid":1841208338,
    "pos_type":999,
    "materials":[
        {
            "rid":1841208342,
            "cid":1841208338,
            "desc":"测试",
            "link_hidden":0,
            "link_name":"去逛逛",
            "is_show_friend":0,
            "interaction":0,
            "crt_size":888,
            "tname":"创意-20190412",
            "page_id":0,
            "link_page_id":1798101716,
            "page_type":9,
            "link_page_type":25,
            "dest_conf":"{}",
            "ext_click_url":"",
            "ext_exposure_url":"",
            "dest_url":"https://7aa68d3b.fyeds8.com/?r_id=1798101716_36eef1c11&pagetype=SINGLE&_bid=2759",
            "appmsg_info":"",
            "title":"",
            "scheme_url":"",
            "is_hidden_comment":0,
            "crt_info":"[{"button_hidden_flag":0,"button_name":"了解更多","button_url":"","video_button_link_type":1,"video_button_page_id":0,"use_long_video":false,"short_video":{"thumb_height":480,"thumb_md5":"825cf5e5091eb900b9b0d9efca34976e","thumb_size":33714,"thumb_url":"http://wxsnsdythumb.wxs.qq.com/109/20250/snsvideodownload?m=825cf5e5091eb900b9b0d9efca34976e&filekey=30340201010420301e02016d040253480410825cf5e5091eb900b9b0d9efca34976e02030083b2040d00000004627466730000000131&hy=SH&storeid=32303139303431323137353531303030303130633039313336666664393330343561333230613030303030303664&bizid=1023","thumb_width":640,"video_duration":14,"video_md5":"e7c7969755b213808d5f0a7dcec4c0a7","video_size":1440289,"video_url":"http://wxsnsdy.wxs.qq.com/105/20210/snsdyvideodownload?m=e7c7969755b213808d5f0a7dcec4c0a7&filekey=30340201010420301e020169040253480410e7c7969755b213808d5f0a7dcec4c0a7020315fa21040d00000004627466730000000131&hy=SH&storeid=32303139303431323137353531303030303137313732313336666664393330343561333230613030303030303639&bizid=1023","materialId":"0","raw_thumb_url":"http://wxsnsdythumb.wxs.qq.com/109/20250/snsvideodownload?m=825cf5e5091eb900b9b0d9efca34976e&filekey=30340201010420301e02016d040253480410825cf5e5091eb900b9b0d9efca34976e02030083b2040d00000004627466730000000131&hy=SH&storeid=32303139303431323137353531303030303130633039313336666664393330343561333230613030303030303664&bizid=1023"},"share_flag":1,"share_text":"","share_thumb":"","share_thumb_material_id":""}]"
        }
    ],
    "product":{
        "product_id":"",
        "product_type":"PRODUCTTYPE_WECHAT_SHOP"
    },
    "additional_args":{
        "simple_share_title":"分享",
        "simple_share_desc":"描述"
    }
}
token: 162771918
appid: 
spid: 
_: 1555063115352

提交审核广告 POST
https://mp.weixin.qq.com/promotion/v3/submit_campaign
Form Data
args: {"cid":1841208338,"pos_type":999}
token: 162771918
appid: 
spid: 
_: 1555063420974
Response
{"base_resp":{"cur_time":"1555063431","ret":0,"ret_msg":"ok"},"msg":"ok","ret":0}

上传视频 POST
https://mp.weixin.qq.com/promotion/landingpage/snsvideo?1=1
POST /promotion/landingpage/snsvideo?1=1 HTTP/1.1
Host: mp.weixin.qq.com
Connection: keep-alive
Content-Length: 1441971
Origin: https://mp.weixin.qq.com
User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryLAOdXddTaCkLxqrB
Accept: */*
Referer: https://mp.weixin.qq.com/promotion/frame?t=ad_system/common_simple_frame&t1=campaign/edit&token=162771918&cid=1841208338&pos_type=999
Accept-Encoding: gzip, deflate, br
Accept-Language: zh-CN,zh;q=0.9,en;q=0.8
Cookie: Cookie
Form Data
token: 162771918
f: json
action: upload
video_type: 3
media_arg: {"px":[{"max_w":640,"min_w":640,"max_h":480,"min_h":480}],"size":{"min":1782579.2,"max":1782579.2},"duration":{"max":15000,"min":6000},"profile":"Main","bitRate":819200}
material_pos_type: 1
id: WU_FILE_1
name: hh.mp4
type: video/mp4
lastModifiedDate: Fri Apr 12 2019 17:53:36 GMT 0800 (中国标准时间)
size: 1440289
chunk: 0
chunks: 1
video_file: (binary)

```

### cookie 变化部分
```
mmad_session=9381d01796b9e3738216c86715ec2d11b15679ac873536e390c5010053ffe7af2daebd0b37b21369a1f9fa746375ad90e51c63842297f82f7712d0eb01fc1db63c6a8b12d58f5605699dbb66dd0e646e6aa58f7254f58d3f414ca910b756b942bbb54a18af2c420affbd3069bd416311992080cc4b0aa3781de67cc649fc662adf82611350bd0b6fab2b1f622ccb955c; 
data_bizuin=3548985722; 
data_ticket=d/xEaC2UWZn8DxYHx BKghhfdfPOZkpa5f7miek7u1wFPAtzHNoY/i3Yz0U/4Oq7; 
sp_login_mark=155548427447514

mmad_session=eed23c4806eaac99b3181f9ad6c175cc875f7cebbea68b2d7a05a0d3e41150f77379a7f9f268670857ef837564dd6b82dd842fccb6d615974f5a72f5e088ad9718c29f4d0292aa9cfd8e1692df9e8b29d3ba813d4154fb4694e84f5b8adb972c3dae33cd3c2232283120f38bd302144a24dc3f335999fd0d52191c48977518001e0417c05bae818fbd155aa7cd69690a; 
data_bizuin=3562792034; 
data_ticket=ZYtcDgCCI/V5loHH1SJVYcKSVsNHyE7vktodhU0H/nd5tg9Qoj9/t w 5v14me9h; 
sp_login_mark=155548433526359
```

### 获取推广计划列表
```
https://mp.weixin.qq.com/promotion/as_rock?action=get_campaign_data&args={"op_type":1,"where":{},"page":1,"page_size":20,"pos_type":999,"advanced":true,"ad_filter":{"product_type":["PRODUCTTYPE_WECHAT_SHOP"]},"create_time_range":{"start_time":1547568021},"query_index":"[\"paid\",\"exp_pv\",\"convclk_pv\",\"convclk_cpc\",\"ctr\",\"comindex\",\"cpa\",\"cvr\",\"order_pv\",\"order_amount\",\"order_pct\",\"order_roi\",\"begin_time\",\"end_time\"]","time_range":{"start_time":1554048000,"last_time":1555516799}}&token=1989540481&appid=&spid=&_=1555488621397
```

状态码
1001 登录态失效，请重新登录账号
