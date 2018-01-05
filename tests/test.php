<?php
require __DIR__ . '/../vendor/autoload.php';


$manager = new SuperCronManager\CronManager();

// bash的alias脚本别名,要生效需要用 source .bash_xxxxx 生效!
$manager->alias = 'cron-manager';

// 设置worker数
$manager->workerNum = 5;

// 设置输出重定向,守护进程模式才生效
$manager->output = './test.log';

// crontab格式解析
$manager->taskInterval('每个小时的1,3,5分钟时运行一次', '1,3,5 * * *', function(){
	echo "每个小时的1,3,5分钟时运行一次\n";
});

$manager->taskInterval('每1分钟运行一次', '*/1 * * *', function(){
	echo "每1分钟运行一次\n";
});

$manager->taskInterval('每天凌晨运行', '0 0 * *', function(){
	echo "每天凌晨运行\n";
});

$manager->taskInterval('每秒运行一次', 's@1', function(){
	echo "每秒运行一次\n";
});

$manager->taskInterval('每分钟运行一次', 'i@1', function(){
	echo "每分钟运行一次\n";
});

$manager->taskInterval('每小时钟运行一次', 'h@1', function(){
	echo "每小时运行一次\n";
});

$manager->taskInterval('指定每天00:00点运行', 'at@00:00', function(){
	echo "指定每天00:00点运行\n";
});


$manager->run();