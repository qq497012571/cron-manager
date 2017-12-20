<?php

namespace SuperCronManager;


/**
 * 进程管理类
 */
class Proccess 
{

	/**
	 * 消息队列用的KEY
	 */
	const MSG_KEY = __DIR__;

	/**
	 * 消息队列读取数据长度
	 */
	const MSG_READSIZE = 65535;

	/**
	 * 消息类型: 用于发送与接收定时任务函数
	 */
	const MSG_TASKID_TYPE = 1;

	/**
	 * 消息类型: 用于发送与接收worker的运行状态
	 */
	const MSG_WORKER_TYPE = 2;

	/**
	 * 消息类型: 用于发送与接收定时任务信号
	 */
	const MSG_SIG_TYPE = 3;

	/**
	 * 进程PID
	 * @var integer
	 */
	protected $pid = 0;

	/**
	 * 进程别名
	 * @var string
	 */
	protected $title = '';

	/**
	 * 消息队列
	 * @var null
	 */
	protected $queue = null;

	public function __construct($setting = [])
	{
		$this->pid = isset($setting['pid']) ? $setting['pid'] : getmypid();
		$this->title = isset($setting['title']) ? $setting['title'] : '';
		$this->queue = msg_get_queue(ftok(self::MSG_KEY, 'a'));

		if (!empty($this->title) && function_exists('cli_set_process_title')) {
			@cli_set_process_title($this->title);
		}
	}

	/**
	 * 获取进程pid
	 */
	public function getPid()
	{
		return $this->pid;
	}

	/**
	 * 向消息队列写数据
	 * @param  $msg 
	 * @param  $message_type 消息类型
	 * @param  boolean $blocking 是否阻塞
	 * @return boolean
	 */
	public function write($msg, $message_type, $blocking = true)
	{
		return msg_send($this->queue, $message_type, $msg, false, $blocking, $errcode);
	}

	/**
	 * 向消息队列取数据
	 * @param  integer $message_type 读取数据的消息类型
	 * @param  boolean $blocking 是否阻塞
	 * @return string
	 */
	public function read($message_type, $blocking = true)
	{
		$message = '';
		if ($blocking) {
			msg_receive($this->queue, $message_type, $type, self::MSG_READSIZE, $message, false);
		} else {
			msg_receive($this->queue, $message_type, $type, self::MSG_READSIZE, $message, false, MSG_IPC_NOWAIT);
		}
		return $message;
	}
	
	/**
	 * 关闭消息队列
	 */
	public function removeQueue()
	{
		msg_remove_queue($this->queue);
	}
}