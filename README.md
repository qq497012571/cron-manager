# cronManager

# 简介

cronManager是一个纯PHP实现的定时任务管理工具,api简单清晰,采用的是多进程模型,进程通信采用的是消息队列,任务监控也提供了简单的命令,方便易用

# 特性

* 多进程模型

* 支持守护进程

* 平滑重启

* 提供各种命令监控任务运行状态

* 兼容部分crontab语法

* 支持一个任务由多个进程同时,以不同的标识运行一个任务


## 更新日志

1. 时间设定支持crontab格式,更加灵活(`2018年01月05日`)
2. [支持thinkphp5](https://gitee.com/jianglibin/cron-manager/tree/master/doc/thinkphp5)(`2018年1月6日`)
3. 优化底层架构,优化消息队列稳定性, 增加STOP命令

## 环境要求

* `liunx`
* `pcntl扩展开启`
* `php 5.4以上`
* `composer`


## 安装

* `composer`安装

> composer require godv/cron-manager

* `zip包`安装

> 需进入cron-manager目录 `composer update`一下！


## 使用介绍

核心方法 `CronManager::taskInterval($name, $command, $callable, $ticks = [])` 

参数1 `string` $name 定时任务名称

参数2 `string` $command 

`方式一`: 兼容部分crontab格式的语法, 粒度最小为`分钟`, 仅支持 `[分钟 小时 日期 月份]`也就是 `* * * *` 

`方式二`: 

使用key@value的形式表示, 不懂请看下面的`入门示例!!`
1. `s@n` 表示每n秒运行一次 
2. `i@n` 表示每n分钟运行一次 
3. `h@n` 表示每n小时运行一次
4. `at@nn:nn` 表示指定每天的nn:nn执行 例如每天凌晨 at@00:00

参数3 `callable` $callable 回调函数,也就是定时任务业务逻辑

参数4 `array` $ticks 用于单任务多进程时标识

## 快速入门示例

```php
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
```

## 命令使用示例

* 检测扩展是否启动，缺少扩展将无法使用。 建议第一次运行先使用此命令查看扩展情况

> php test.php check

```
+----------+--------+------+------+
| name     | status | desc | help |
+----------+--------+------+------+
| php>=5.4 | [OK]   |      |      |
| pcntl    | [OK]   |      |      |
| posix    | [OK]   |      |      |
| sysvmsg  | [OK]   |      |      |
| sysvsem  | [OK]   |      |      |
| sysvshm  | [OK]   |      |      |
+----------+--------+------+------+
```

* 启动

>  php test.php

* 以守护进程方式启动（`无任何提示表示成功`）

>  php test.php -d

* 查看任务状态

>  php test.php status

```
+------------+---------------------+
| pid        | 27197               |
+------------+---------------------+
| output     | ./test.log          |
+------------+---------------------+
| task_num   | 7                   |
+------------+---------------------+
| worker_num | 10                  |
+------------+---------------------+
| queue_num  | 0                   |
+------------+---------------------+
| start_time | 2018-01-14 16:11:16 |
+------------+---------------------+
+----+-------------------------------+-------------+--------+-------+---------------------+---------------------+
| id | name                          | tag         | status | count | last_time           | next_time           |
+----+-------------------------------+-------------+--------+-------+---------------------+---------------------+
| 1  | 每个小时的1,3,5分钟时运行一次 | 1,3,5 * * * | 正常   | 0     | -                   | 2018-01-14 17:01:00 |
| 2  | 每1分钟运行一次               | */1 * * *   | 正常   | 0     | -                   | 2018-01-14 16:12:00 |
| 3  | 每天凌晨运行                  | 0 0 * *     | 正常   | 0     | -                   | 2018-01-15 00:00:00 |
| 4  | 每秒运行一次                  | s@1         | 正常   | 1     | 2018-01-14 16:11:18 | 2018-01-14 16:11:19 |
| 5  | 每分钟运行一次                | i@1         | 正常   | 0     | -                   | 2018-01-14 16:12:17 |
| 6  | 每小时钟运行一次              | h@1         | 正常   | 0     | -                   | 2018-01-14 17:11:17 |
| 7  | 指定每天00:00点运行           | at@00:00    | 正常   | 0     | -                   | 2018-01-15 00:00:00 |
+----+-------------------------------+-------------+--------+-------+---------------------+---------------------+
```

* 停止 `stop`平滑停止| `STOP`强制停止

>  php test.php stop|STOP


* 查看服务日志(这个有点LOW，就是直接读日志文件输出)

>  php test.php log

```
2017-12-09 14:03:44 PID:19690 [debug] master启动
```
