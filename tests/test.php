<?php
require __DIR__ . '/../vendor/autoload.php';


$manager = new SuperCronManager\CronManager();

// 设置worker数
$manager->workerNum = 5;

// 设置输出重定向,守护进程模式才生效
$manager->output = './test.log';


$manager->taskInterval('每秒钟运行一次', 's@1', function(){
	echo "Hello crontabManager\n";
});
$manager->taskInterval('每分钟运行一次', 'i@1', function(){
	echo "Hello crontabManager\n";
});
$manager->taskInterval('每小时运行一次', 'h@1', function(){
	echo "Hello crontabManager\n";
});
$manager->taskInterval('指定每天00:00运行一次', 'at@00:00', function(){
	echo "Hello crontabManager\n";
});

$manager->run();