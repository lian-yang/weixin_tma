<?php
/**
 * 单元测试
 */
define('IN_SYS', true);
require('./common.php');



$fp = fopen("data/TESTB1_20190420.json", "r");
if (flock($fp, LOCK_EX | LOCK_NB)) {  // 进行排它型锁定
    echo "加锁成功";
    rename("logs/app.log", "data/backup/app.log");
    flock($fp, LOCK_UN);    // 释放锁定
} else {
    echo "文件正在被其他程序占用";
}
fclose($fp);




