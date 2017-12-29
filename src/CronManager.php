<?php
namespace SuperCronManager;

use SuperCronManager\Task;

class CronManager 
{
    /**
     * 消息队列消息类型: 用于发送与接收定时任务函数
     */
    const MSG_TASKID_TYPE = 1;

    /**
     * 消息队列消息类型: 用于发送与接收worker的运行状态
     */
    const MSG_WORKER_TYPE = 2;

    /**
     * 消息队列消息类型: 用于发送与接收定时任务信号
     */
    const MSG_SIG_TYPE = 3;

    /**
     * 任务列表
     * @var array [SuperCronManager\Task]
     */
    public static $tasks = [];

    /**
     * 进程列表
     * @var array []
     */
    public static $workers = [];

    /**
     * 记录log的日志文件
     * @var string
     */
    public static $logFile = '';

    /**
     * 进程PID文件
     * @var string
     */
    private $_pidFile = '';

    /**
     * 任务状态文件
     * @var string
     */
    private $_statusFile = '';

    /**
     * worker进程状态文件
     * @var string
     */
    private $_workerStatusFile = '';

    /**
     * 主进程
     * @var CronManager\Proccess
     */
    private $_master = null;

    /**
     * 主进程启动时间
     * @var integer
     */
    public $startTime = 0;

    /**
     * 启动进程数
     * @var integer
     */
    public $workerNum = 1;

    /**
     * 是否守护进程化
     * @var boolean
     */
    public $daemon = false;

    /**
     * 重定向输出,守护进程化时
     * @var string
     */
    public $output = '/dev/null';

    /**
     * 信号支持
     * @var array
     */
    public $_signalSupport = [
        'stop' => SIGUSR1,
        'restart' => SIGUSR2
    ];

    public function __construct()
    {
        date_default_timezone_set('Asia/Shanghai');

        $file = 'cronManager.' . substr(md5(basename(__FILE__)),0,16);

        $this->_pidFile = sys_get_temp_dir() . '/' . $file . '.pid';
        $this->_statusFile = sys_get_temp_dir() . '/' . $file . '.task_status';
        $this->_workerStatusFile = sys_get_temp_dir() . '/' . $file . '.worker_status';
        static::$logFile = sys_get_temp_dir() . '/' . $file . '.log';

        $this->_master = new Proccess([
            'title' => 'cron-manager-master'
        ]);

        $this->startTime = date('Y-m-d H:i:s');
    }

    /**
     * 入口方法
     */
    public function run()
    {

        $this->parseArgv();

        $this->checkFile();

        $this->welcome();

        $this->daemonize();

        $this->registerSignal();

        $this->startWorkers();

        $this->loop();

    }

    /**
     * 解析命令行
     */
    public function parseArgv()
    {
        global $argv;
        
        if (count($argv) >= 2) {

            if ($argv[1] == '-d') {
                $this->daemon = true;
            }

            if ($argv[1] == 'log') {
                if (static::$logFile) {
                    echo file_get_contents(static::$logFile);
                }
                exit;
            }
            if ($argv[1] == 'status') {
                if ($this->_statusFile) {
                    echo file_get_contents($this->_statusFile);
                }
                exit;
            }
            if ($argv[1] == 'worker') {
                if ($this->_statusFile) {
                    echo file_get_contents($this->_workerStatusFile);
                }
                exit;
            }
            if ($argv[1] == 'check') {
                echo ConsoleManager::checkExtensions();
                exit;
            }
            // 平滑终止
            if ($argv[1] == 'stop') {
                $pid = intval(@file_get_contents($this->_pidFile));
                echo "kill $pid\n";
                posix_kill($pid, $this->_signalSupport['stop']);
                echo "waiting {$argv[1]} workers\n";
                while (file_exists($this->_pidFile)) {
                }
                echo "{$argv[1]} OK..\n";
                exit;
            }

            if ($argv[1] == 'restart') {
                $pid = intval(@file_get_contents($this->_pidFile));
                posix_kill($pid, $this->_signalSupport['restart']);
                exit;
            }

            if (in_array($argv[1], array('stop', 'restart'))) {
                $pid = intval(@file_get_contents($this->_pidFile));
                posix_kill($pid, $this->_signalSupport[$argv[1]]);
                exit;
            }

        }
    }
     /**
     * 创建运行所需的文件
     * @return  void
     */
    public function checkFile()
    {

        if (file_exists($this->_pidFile)) {
            exit($this->_pidFile . " already exist!\n");
        }

        if (!file_exists($this->_statusFile)) {
            file_put_contents($this->_statusFile, getmypid());
        }

        if (!file_exists($this->_workerStatusFile)) {
            file_put_contents($this->_workerStatusFile, getmypid());
        }

        // 重置LOG日志
        if (file_exists(static::$logFile)) {
            @unlink(@static::$logFile);
        }

    }

     /**
     * 守护进程化
     */
    public function daemonize()
    {
        if (!$this->daemon) {
            return;
        } 
        
        umask(0);

        $pid = pcntl_fork();
        if (-1 === $pid) {
            die('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            die("setsid fail");
        }

        $pid = pcntl_fork();
        if (-1 === $pid) {
            die("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }

        if (!file_exists($this->_pidFile)) {
            file_put_contents($this->_pidFile, getmypid());
        }

        $this->_resetStd();
    }
    /**
     * 重定向输出
     * @return void
     */
    private function _resetStd()
    {
        global $stdin, $stdout, $stderr;

        //关闭打开的文件描述符    
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        if ($this->output != '/dev/null' && !file_exists($this->output)) {
            touch($this->output);
            chmod($this->output, 0755);
        }

        $stdin  = fopen($this->output, 'r');
        $stdout = fopen($this->output, 'a');
        $stderr = fopen($this->output, 'a');
    }

    /**
     * 欢迎界面
     * @return vold
     */
    public function welcome()
    {
        echo ConsoleManager::cronManageStatusTable($this);
    }


    /**
     * 启动所有进程
     * @return void
     */
    public function startWorkers()
    {
        for ($i=0; $i < $this->workerNum; $i++) { 
            $this->forkWorker();
        }
    }

    /**
     * 创建worker进程
     * @return vold
     */
    public function forkWorker()
    {
        $pid = pcntl_fork();
        switch ($pid) {
            case -1:
                exit;
                break;
            case 0:
                $worker = new Worker(static::$tasks,[
                    'title' => 'cron-manager-worker'
                ]);
                $worker->loop();
                break;
            default:
                static::$workers[$pid] = [
                    'pid' => $pid,
                    'execCount' => 0,
                    'startTime' => time(),
                    'memory' => 0
                ];
                break;
        }
    }

    /**
     * 注册信号
     * @return vold
     */
    public function registerSignal()
    {
        pcntl_signal(SIGINT, [$this, 'dispatchSign'], false);
        pcntl_signal(SIGUSR1, [$this, 'dispatchSign'], false);
        pcntl_signal(SIGUSR2, [$this, 'dispatchSign'], false);
    }

    /**
     * 触发信号
     * @param $sign 信号量
     * @return vold
     */
    public function dispatchSign($sign)
    {
        switch ($sign) {
            //通知worker停止
            case $this->_signalSupport['stop']:
                for ($i=0; $i < count(static::$workers); $i++) { 
                    $this->_master->write($this->_signalSupport['stop'], CronManager::MSG_SIG_TYPE);
                }
                break;
            //通知worker进程重启
            case $this->_signalSupport['restart']:
                for ($i=0; $i < count(static::$workers); $i++) { 
                    $this->_master->write($this->_signalSupport['restart'], CronManager::MSG_SIG_TYPE);
                }
                break;
            // 通知台ctrl+c退出
            case SIGINT:
                posix_kill($this->_master->getPid(), $this->_signalSupport['stop']);
                break;
            default:
                return;
                break;
        }
    }

    /**
     * master 主循环
     * @return vold
     */
    public function loop()
    {
        static::log('debug', 'master启动');
        while (true) {
            
            pcntl_signal_dispatch();

            foreach (static::$tasks as $id => &$task) {
                if ($task->valid()) {
                    
                    $task->calcNextTime();
                    // 向消息队列写任务ID,通知worker运行
                    $this->_master->write(
                        $id,
                        CronManager::MSG_TASKID_TYPE
                    );

                }
            }

            // 将任务运行状态记录进文件
            file_put_contents(
                $this->_statusFile,
                ConsoleManager::cronManageStatusTable($this) .
                ConsoleManager::taskStatusTable(static::$tasks)
            );

            // 记录worker状态
            $this->readWorkerStatus();


            foreach (static::$workers as $pid => $workerStatus) {
                $pid = pcntl_wait($status, WNOHANG);
                if ($pid > 0) {
                    unset(static::$workers[$pid]);
                    static::log('debug', 'worker退出');
                    $exit = pcntl_wexitstatus($status);
                    if ($exit == $this->_signalSupport['restart']) {
                        $this->forkWorker();
                    }
                }
            }

            // 所有worker已退出
            if (empty(static::$workers)) {
                $this->clear();
                break;
            }

            sleep(1);
        }
        static::log('debug', 'master结束');
    }

    /**
     * 读取worker运行状态
     * @return vold
     */
    public function readWorkerStatus()
    {
        while (($msg = $this->_master->read(CronManager::MSG_WORKER_TYPE, false)) != '') {
            $workerStatus = json_decode($msg,true);
            static::$workers[$workerStatus['pid']] = array_merge(static::$workers[$workerStatus['pid']], $workerStatus);
        }

        file_put_contents(
            $this->_workerStatusFile, 
            ConsoleManager::cronManageStatusTable($this).
            ConsoleManager::workerStatusTable(static::$workers)
        );
    }

    
    /**
     * 清理残留文件
     * @return vold
     */
    public function clear()
    {
        $this->_master->removeQueue();
        @unlink($this->_pidFile);
        @unlink($this->_statusFile);
        @unlink($this->_workerStatusFile);
    }

    /**
     * 添加定时任务
     * @param  string  $name      
     * @param  string/array  $intvalTag 
     * @param  callable $callable 
     * @param  array $ticks 进程分片  
     */
    public function taskInterval($name, $intvalTag, callable $callable, array $ticks = [])
    {   
        if (!empty($ticks)) {
            foreach ($ticks as $tick) {
                $t = new Task($name, $intvalTag, $callable, $tick);
                static::$tasks[$t->getId()] = $t;
            }
        } else {
            $t = new Task($name, $intvalTag, $callable);
            static::$tasks[$t->getId()] = $t;
        }
    }

    /**
     * 记录日志
     * @param  string $tag    日志标识
     * @param  string $message 内容
     */
    public static function log($tag, $message)
    {
        $datetime = date('Y-m-d H:i:s');
        $template = "$datetime PID:%d [%s] %s\n";
        file_put_contents(static::$logFile, sprintf($template, getmypid(), $tag, $message), FILE_APPEND);
    }

}