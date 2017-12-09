<?php

namespace SuperCronManager;

/**
 * 定时任务类
 */
class Task
{
	/**
	 * 用于ID自增
	 * @var integer
	 */
	static private $_id = 0;

	/**
	 * 任务ID 唯一 
	 * @var string
	 */
	private $id = '';

	/**
	 * 任务名称,别名 alias
	 * @var string
	 */
	private $name = '';

	/**
	 * 任务间隔标识 s@1 m@1 h@1 at@00:00
	 * @var string
	 */
	private $intvalTag = '';

	/**
	 * 任务状态 0开启 1关闭 2异常
	 * @var integer
	 */
	private $status = 0;

	/**
	 * 运行次数
	 * @var integer
	 */
	private $count = 0;

	/**
	 * 任务回调
	 * @var Closure
	 */
	private $closure = null;

	/**
	 * 任务下次运行时间
	 * @var integer
	 */
	private $nextTime = 0;

	/**
	 * 任务上次运行时间
	 * @var integer
	 */
	private $lastTime = 0;


	public function __construct($name, $intvalTag, \Closure $closure)
	{
		$this->name = $name;
		$this->intvalTag = $intvalTag;
		$this->closure = $closure;
		$this->id = static::$_id++;
	}

	/**
	 * 运行任务
	 */
	public function exec()
	{
		try {
			return call_user_func($this->closure);
		} catch (Exception $e) {
			// 设为异常状态
			$this->status = 1;
			CronManager::log('error', $e->getMessage());
		}
	}

	/**
	 * 任务周期校验
	 * @return boolean
	 */
	public function valid()
	{
		// 初始化
		if ($this->nextTime === 0) {
			$this->calcNextTime();
			$this->count = 0;
		}

		if ($this->status == 0 && time() >= $this->nextTime) {
			return true;
		}
		return false;
	}

	/**
     * 解析设定,计算下次运行的时间
     * @return void
     */
    public function calcNextTime()
    {

    	list($tag, $timer) = explode('@', $this->intvalTag);
    	
    	$this->lastTime = $this->nextTime;
    	$this->count++;

        // 指定每天运行日期  格式 00:00
        if ($tag == 'at' && strlen($timer) == 5) {
            if (time() >= strtotime($timer)) {
                $this->nextTime = strtotime($timer . " +1day");
            } 
            else {
                $this->nextTime = strtotime($timer);
            }
        }

        $timer = intval($timer);
        // 按秒
        if ($tag == 's' && $timer > 0) {
            $this->nextTime = time() + $timer;
        }

        // 按分钟
        if ($tag == 'i' && $timer > 0) {
            $this->nextTime = time() + $timer * 60;
        }

        // 按小时
        if ($tag == 'h' && $timer > 0) {
            $this->nextTime = time() + $timer * 60 * 60;
        }

    }

    /**
	 * 获取任务ID
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * 获取任务状态
	 * @return integer
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * 获取此对象的所有属性
	 * @return array
	 */
	public function getAttributes()
	{
		return get_object_vars($this);
	}
	
	/**
	 * 获取任务状态
	 * @return integer
	 * @param integer $status 
	 */
	public function setStatus($status)
	{
		$this->status = $status;
	}
	
}