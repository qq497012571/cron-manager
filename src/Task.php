<?php
namespace SuperCronManager;

use SuperCronManager\CronParser;
/**
 * 定时任务类
 */
class Task
{
	/**
	 * 用于ID自增
	 * @var integer
	 */
	private static $_id = 1;

	/**
	 * 任务ID 唯一 
	 * @var string
	 */
	public $id = '';

	/**
	 * 任务名称,别名 alias
	 * @var string
	 */
	public $name = '';

	/**
	 * 任务间隔标识 s@1 m@1 h@1 at@00:00
	 * @var string
	 */
	public $intvalTag = '';

	/**
	 * 任务列表
	 * @var array
	 */
	public $intvalDateList = [];

	/**
	 * 任务状态 0开启 1关闭 2任务过期
	 * @var integer
	 */
	public $status = 0;

	/**
	 * 运行次数
	 * @var integer
	 */
	public $count = 0;

	/**
	 * 任务回调
	 * @var callable
	 */
	public $callable = null;

	/**
	 * 回调参数
	 * @var null
	 */
	public $param = null;

	/**
	 * 任务下次运行时间
	 * @var integer
	 */
	public $nextTime = 0;

	/**
	 * 任务上次运行时间
	 * @var integer
	 */
	public $lastTime = 0;

	/**
	 * 构造函数
	 * @param string   $name 任务名称
	 * @param string   $intvalTag 任务间隔标识 s@1 m@1 h@1 at@00:00
	 * @param callable $callable  回调函数
	 * @param mixed    $param     回调参数
	 */
	public function __construct($name, $intvalTag, callable $callable, $param = null)
	{
		$this->name = $name;
		$this->intvalTag = $intvalTag;
		$this->callable = $callable;
		$this->param = $param;
		$this->id = static::$_id++;
	}

	/**
	 * 运行任务
	 * @param array $param 任务运行参数
	 */
	public function exec()
	{
		try {
			return call_user_func($this->callable, $this->param);
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
		if ($this->status !== 0) {
			return false;
		}

		// 初始化
		if ($this->nextTime === 0) {
			$this->calcNextTime();
			$this->count = 0;
		}

		if (time() >= $this->nextTime) {
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

		$this->lastTime = $this->nextTime;

		if (CronParser::check($this->intvalTag) && empty($this->intvalListDate)) {
			$this->intvalListDate = CronParser::formatToDate($this->intvalTag, 200);
		}

		if (!empty($this->intvalListDate)) {
			$this->nextTime = strtotime(array_shift($this->intvalListDate));
			$this->count++;
			return;
		}

		if (strpos($this->intvalTag, '@') === false) {
			throw new Exception("解析错误: [{$this->intvalTag}]", 1);
		}

    	list($tag, $timer) = explode('@', $this->intvalTag);
		$this->lastTime = $this->nextTime;
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

    	$this->count++;
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