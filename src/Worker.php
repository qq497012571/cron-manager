<?php

namespace SuperCronManager;

use SuperCronManager\Proccess;

class Worker extends Proccess
{

	/**
	 * 缓存任务
	 * @var array [SuperCronManager\Task]
	 */
	private static $_cacheTasks = [];


	/**
	 * 间隔
	 * @var integer
	 */
	private $usleep = 400000;

	/**
	 * worker启动时间
	 * @var integer
	 */
	public $startTime = 0;

	/**
	 * 执行任务次数
	 * @var integer
	 */
	public $execCount = 0;

	/**
	 * worker占用内存 byte
	 * @var integer
	 */
	public $memory = 0;

	/**
	 * 信号
	 * @var boolean
	 */
	private $signalSupport = [
		SIGUSR1 => 'stop',
		SIGUSR2 => 'restart',
	];

	/**
	 * 构造函数
	 * @param array $tasks array
	 * @param array $setting 配置
	 */
	public function __construct($tasks = [], $setting = [])
	{
		parent::__construct($setting);
		static::$_cacheTasks = $tasks;
	}
	
	/**
	 * 读取队列信号
	 * @return void
	 */
	public function readSignal()
	{
		while (($sig = $this->read(Proccess::MSG_SIG_TYPE, false)) != '') {
			if (isset($this->signalSupport[$sig])) {
				call_user_func([$this, $this->signalSupport[$sig]], $sig);
			}
		}
	}

	/**
	 * 退出
	 * @param  integer $exitcode
	 */
	public function stop($exitcode=0)
	{
		exit(intval($exitcode));
	}

	/**
	 * 重启
	 * @param  integer $exitcode
	 */
	public function restart($exitcode=0)
	{
		exit(intval($exitcode));
	}

	/**
	 * worker主循环
	 * @return [type] [description]
	 */
	public function loop()
	{
		$this->startTime = time();
		
		while (true) {
			// wait signal
			$this->readSignal();

			try {
				// 接收要运行的任务ID
				$taskId = $this->read(Proccess::MSG_TASKID_TYPE, false);
				if ($taskId !== false && isset(static::$_cacheTasks[$taskId])) {
					// 运行任务
					$task = static::$_cacheTasks[$taskId];
					$task->exec();
					$this->execCount++;
					$this->memory = memory_get_usage();
					
					$workerStatus = [
						'pid' => $this->pid,
						'execCount' => $this->execCount,
						'memory' => $this->memory,
						'startTime' => $this->startTime,
					];
					// 记录worker运行状态
					$this->write(json_encode($workerStatus), Proccess::MSG_WORKER_TYPE);
				}
			} catch (Exception $e) {
				CronManager::log('error', $e->getMessage());
			}
			
			usleep($this->usleep);
		}

		$this->stop();
	}

}