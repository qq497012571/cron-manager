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
$manager->taskInterval('每天凌晨运行一次', 'at@00:00', function(){
	echo "Hello crontabManager\n";
});
$manager->taskInterval('任务分片', 's@1', function($str){
	echo "$str\n";
},[1,2]);

$manager->taskInterval('分片测试', ['2017-12-20 23:06','2017-12-20 23:07'], function($index){
	echo "ticks $index\n";
});

$manager->run();