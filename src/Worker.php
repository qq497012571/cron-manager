<?php
namespace SuperCronManager;

use SuperCronManager\Interfaces\WorkerInterface;
use SuperCronManager\Interfaces\MiddlewareInterface;

class Worker implements WorkerInterface
{
	/**
	 * 休眠时间
	 */
	const WORKER_USLEEP = 500000;

	/**
	 * 中间件
	 * @var MiddlewareInterface
	 */
	private $middleware;

	/**
	 * 任务集合
	 * @var array
	 */
	private $tasks = [];

	/**
	 * 信号支持
	 * @var array
	 */
	private $signalSupport = [
		SIGUSR1 => 'stop',
        SIGUSR2 => 'restart'
	];

	/**
	 * 构造方法
	 * @param array $tasks 任务集合
	 */
	public function __construct(array $tasks)
	{
		$this->tasks = $tasks;
	}

	/**
	 * 设置通讯中间件
	 * @param MiddlewareInterface $middleware
	 * @return void
	 */
	public function setMiddleware(MiddlewareInterface $middleware)
	{
		$this->middleware = $middleware;
	}
	
	/**
	 * 设置进程名称
	 * @param string $title
	 * @return void
	 */
	public function setProcTitle($title)
	{
		if ($title && function_exists('cli_set_process_title')) {
            $this->cli_set_process_title($title . '-worker');
        }
	}
	
	/**
	 * 处理进程信号
	 * @return void
	 */
	public function waitSign()
	{
		$sig = $this->middleware->pop(CronManager::QUEUE_SIG_VALUE);

		if ($sig !== false) {
			return call_user_func([$this, $this->signalSupport[$sig]], intval($sig));
		}
	}

	/**
	 * 退出worker
	 * @param  integer $exitcode 退出码
	 * @return void
	 */
	public function stop($exitcode)
	{
		exit($exitcode);
	}

	/**
	 * 重启worker
	 * @return void
	 */
	public function restart($exitcode)
	{
		exit($exitcode);
	}

	/**
	 * worker主循环
	 * @return void
	 */
	public function loop()
	{
		while (1) {

			$this->waitSign();
			
			// 取队列任务ID
			if (($taskId = $this->middleware->pop(CronManager::QUEUE_TASK_ID)) !== false) {
				// 运行任务
				$task = $this->tasks[$taskId];
				$task->exec();
			}

			usleep(static::WORKER_USLEEP);

		}
	}
}