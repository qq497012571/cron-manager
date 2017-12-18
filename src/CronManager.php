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
     * cronjob pool
     * @var array
     */
    static public $_tasks = [];

    /**
     * proccess pool
     * @var array
     */
    static public $_workers = [];

    /**
     * 记录log的日志文件
     * @var string
     */
    static public $logFile = '';

    /**
     * 进程PID文件
     * @var string
     */
    private $pidFile = '';

    /**
     * 任务状态文件
     * @var string
     */
    private $statusFile = '';

    /**
     * worker进程状态文件
     * @var string
     */
    private $workerStatusFile = '';

    /**
     * 主进程
     * @var CronManager\Proccess
     */
    private $master = null;

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
    public $signalSupport = [
        'stop' => SIGUSR1,
        'restart' => SIGUSR2
    ];

    public function __construct()
    {
        date_default_timezone_set('Asia/Shanghai');

        $file = 'cronManager.' . substr(md5(basename(__FILE__)),0,16);

        $this->pidFile = sys_get_temp_dir() . '/' . $file . '.pid';
        $this->statusFile = sys_get_temp_dir() . '/' . $file . '.task_status';
        $this->workerStatusFile = sys_get_temp_dir() . '/' . $file . '.worker_status';
        static::$logFile = sys_get_temp_dir() . '/' . $file . '.log';

        $this->master = new Proccess([
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

        $this->daemonize();

        $this->welcome();

        $this->startWorkers();

        $this->registerSignal();

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
                if ($this->statusFile) {
                    echo file_get_contents($this->statusFile);
                }
                exit;
            }
            if ($argv[1] == 'worker') {
                if ($this->statusFile) {
                    echo file_get_contents($this->workerStatusFile);
                }
                exit;
            }
            if ($argv[1] == 'check') {
                echo ConsoleManager::checkExtensions();
                exit;
            }
            // 平滑终止
            if ($argv[1] == 'stop') {
                $pid = intval(@file_get_contents($this->pidFile));
                echo "kill $pid\n";
                posix_kill($pid, $this->signalSupport['stop']);
                echo "waiting {$argv[1]} workers\n";
                while (file_exists($this->pidFile)) {
                }
                echo "{$argv[1]} OK..\n";
                exit;
            }

            if ($argv[1] == 'restart') {
                $pid = intval(@file_get_contents($this->pidFile));
                posix_kill($pid, $this->signalSupport['restart']);
                exit;
            }

            if (in_array($argv[1], array('stop', 'restart'))) {
                $pid = intval(@file_get_contents($this->pidFile));
                posix_kill($pid, $this->signalSupport[$argv[1]]);
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

        if (file_exists($this->pidFile)) {
            exit($this->pidFile . " already exist!\n");
        }

        if (!file_exists($this->statusFile)) {
            file_put_contents($this->statusFile, getmypid());
        }

        if (!file_exists($this->workerStatusFile)) {
            file_put_contents($this->workerStatusFile, getmypid());
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

        if (!file_exists($this->pidFile)) {
            file_put_contents($this->pidFile, getmypid());
        }

        $this->resetStd();
    }
    /**
     * 重定向输出
     * @return [type] [description]
     */
    private function resetStd()
    {
        global $stdin, $stdout, $stderr;

        //关闭打开的文件描述符
        @fclose(STDIN);
        @fclose(STDOUT);
        @fclose(STDERR);

        $stdin  = fopen($this->output, 'r');
        $stdout = fopen($this->output, 'a');
        $stderr = fopen($this->output, 'a');

        if ($this->output != '/dev/null' && !file_exists($this->output)) {
            @touch($this->output);
            @chmod($this->output, 0755);
        }

    }

    /**
     * 欢迎界面
     */
    public function welcome()
    {
        echo ConsoleManager::cronManageStatusTable($this);
    }


    /**
     * 启动所有进程
     * @return void
     */
    private function startWorkers()
    {
        for ($i=0; $i < $this->workerNum; $i++) { 
            $this->forkWorker();
        }
    }

    /**
     * 创建worker进程
     */
    private function forkWorker()
    {
        $pid = pcntl_fork();
        switch ($pid) {
            case -1:
                exit;
                break;
            case 0:
                $worker = new Worker(static::$_tasks,[
                    'title' => 'cron-manager-worker'
                ]);
                $worker->loop();
                break;
            default:
                static::$_workers[$pid] = [
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
     */
    public function dispatchSign($sign)
    {
        switch ($sign) {
            //通知worker停止
            case $this->signalSupport['stop']:
                for ($i=0; $i < count(static::$_workers); $i++) { 
                    $this->master->write($this->signalSupport['stop'], CronManager::MSG_SIG_TYPE);
                }
                break;
            //通知worker进程重启
            case $this->signalSupport['restart']:
                for ($i=0; $i < count(static::$_workers); $i++) { 
                    $this->master->write($this->signalSupport['restart'], CronManager::MSG_SIG_TYPE);
                }
                break;
            // 通知台ctrl+c退出
            case SIGINT:
                posix_kill($this->master->getPid(), $this->signalSupport['stop']);
                break;
            default:
                return;
                break;
        }
    }

    /**
     * master 主循环
     */
    public function loop()
    {
        static::log('debug', 'master启动');
        while (true) {
            
            pcntl_signal_dispatch();

            foreach (static::$_tasks as $id => &$task) {
                if ($task->valid()) {
                    
                    $task->calcNextTime();
                    // 向worker进程写任务ID
                    $this->master->write(
                        $id,
                        CronManager::MSG_TASKID_TYPE
                    );

                }
            }

            // 将任务运行状态记录进文件
            file_put_contents(
                $this->statusFile,
                ConsoleManager::cronManageStatusTable($this) .
                ConsoleManager::taskStatusTable(static::$_tasks)
            );

            // 记录worker状态
            $this->readWorkerStatus();


            foreach (static::$_workers as $pid => $workerStatus) {
                $pid = pcntl_wait($status, WNOHANG);
                if ($pid > 0) {
                    unset(static::$_workers[$pid]);
                    static::log('debug', 'worker退出');
                    $exit = pcntl_wexitstatus($status);
                    if ($exit == $this->signalSupport['restart']) {
                        $this->forkWorker();
                    }
                }
            }

            // 所有worker已退出
            if (empty(static::$_workers)) {
                $this->clear();
                break;
            }

            sleep(1);
        }
        static::log('debug', 'master结束');
    }

    /**
     * 读取worker运行状态
     */
    public function readWorkerStatus()
    {
        while (($msg = $this->master->read(CronManager::MSG_WORKER_TYPE, false)) != '') {
            $workerStatus = json_decode($msg,true);
            static::$_workers[$workerStatus['pid']] = array_merge(static::$_workers[$workerStatus['pid']], $workerStatus);
        }

        file_put_contents(
            $this->workerStatusFile, 
            ConsoleManager::cronManageStatusTable($this).
            ConsoleManager::workerStatusTable(static::$_workers)
        );
    }

    
    /**
     * 清理残留文件
     */
    public function clear()
    {
        $this->master->removeQueue();
        @unlink($this->pidFile);
        @unlink($this->statusFile);
        @unlink($this->workerStatusFile);
    }

    /**
     * 添加定时任务
     * @param  string  $name      
     * @param  string  $intvalTag 
     * @param  Closure $closure   
     */
    public function taskInterval($name, $intvalTag, \Closure $closure)
    {   
        $t = new Task($name, $intvalTag, $closure);
        static::$_tasks[$t->getId()] =& $t;
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