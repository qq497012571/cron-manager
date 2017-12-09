<?php

namespace SuperCronManager;

/**
 * 控制台输出
 */
class ConsoleManager
{

	/**
	 * 设置任务状态表格头
	 * @var array
	 */
	static private $_taskHeader = array('id', 'name', 'tag', 'status', 'count', 'last_time', 'next_time');

	/**
	 * 设置worker状态表格头
	 * @var array
	 */
	static private $_workerHeader = array('pid', 'exec_count', 'memory', 'start_time');

	/**
	 * 设置扩展检测表格头
	 * @var array
	 */
	static private $_checkHeader = array('name', 'status', 'desc', 'help');

	/**
	 * 向控制台输出任务状态信息
	 * @param  $tasks
	 * @return void
	 */
	public static function taskStatusTable($tasks)
	{
		$table = new \Console_Table();
		$table->setHeaders(static::$_taskHeader);
		$status = [
			'0' => '正常',
			'1' => '关闭',
			'2' => '报错',
		];
		foreach ($tasks as $id => $task) {
			$attr = $task->getAttributes();
			$table->addRow(array(
				$attr['id'], 
				$attr['name'], 
				$attr['intvalTag'], 
				$status[$attr['status']], 
				$attr['count'], 
				$attr['lastTime'] ? date('Y-m-d H:i:s',$attr['lastTime']) : '-', 
				$attr['nextTime'] ? date('Y-m-d H:i:s',$attr['nextTime']) : '-'
			));
		}
		return $table->getTable();
	}

	/**
	 * master状态
	 * @param  object $cronManager
	 * @return 
	 */
	public static function cronManageStatusTable(CronManager $cronManager)
	{
		$table = new \Console_Table();
		$table->addRow(array('pid', getmypid()));
		$table->addSeparator();
		$table->addRow(array('output', $cronManager->output));
		$table->addSeparator();
		$table->addRow(array('task_num', count(CronManager::$_tasks)));
		$table->addSeparator();
		$table->addRow(array('worker_num', $cronManager->workerNum));
		$table->addSeparator();
		$table->addRow(array('start_time', $cronManager->startTime));
		return $table->getTable();
	}

	/**
	 * 向控制台输出worker状态信息
	 * @param  $tasks
	 * @return void
	 */
	public static function workerStatusTable($workers)
	{
		$table = new \Console_Table();
		$table->setHeaders(static::$_workerHeader);
		foreach ($workers as $id => $worker) {
			$table->addRow(array(
				$worker['pid'], 
				$worker['execCount'], 
				$worker['memory'] == 0 ? '-' : round($worker['memory'] / 1024 / 1024, 2) . '(mb)',
				$worker['startTime'] == 0 ? '-' :date('Y-m-d H:i:s', $worker['startTime']),
			));
		}
		return $table->getTable();
	}

	/**
	 * 检查扩展是否开启
	 */
	public static function checkExtensions() {
		$table = new \Console_Table();
		$table->setHeaders(static::$_checkHeader);
		$exts = get_loaded_extensions();


		if (version_compare(PHP_VERSION, '5.4', ">=")) {
			$row = array('php>=5.4', '[OK]');
		} else {
			$row = array('php>=5.4', '[ERR]', '请升级PHP版本');
		}
		$table->addRow($row);
		
		$checks = array(
			array('name' => 'pcntl', 'remark' => '缺少扩展', 'help'=>'http://php.net/manual/zh/pcntl.installation.php'),
			array('name' => 'posix', 'remark' => '缺少扩展', 'help'=>'http://php.net/manual/zh/posix.installation.php'),
			array('name' => 'sysvmsg', 'remark' => '缺少扩展', 'help'=>'自行搜索安装方法,也可以推荐好的文章'),
			array('name' => 'sysvsem', 'remark' => '缺少扩展', 'help'=>'自行搜索安装方法,也可以推荐好的文章'),
			array('name' => 'sysvshm', 'remark' => '缺少扩展', 'help'=>'自行搜索安装方法,也可以推荐好的文章'),
		);

		foreach ($checks as $check) {
			$row = array();
			if(in_array($check['name'], $exts)) {
				$row = array($check['name'], '[OK]');
			} else {
				$row = array($check['name'], '[ERR]', $check['remark'], $check['help']);
			}
			$table->addRow($row);
		}
		return $table->getTable();
	}
} 