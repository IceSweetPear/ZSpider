<?php
namespace IceSweetPear\phpspider;

use IceSweetPear\phpspider\cache\ZCache;
use IceSweetPear\phpspider\socket\SocketServer;
use IceSweetPear\phpspider\socket\SocketUser;

class ZSpider
{
    public $taskDraw;

    public $DataQueue = [];

    public $config = [];

    public static $redis;

    public static $pageArray;


    public function start($task)
    {
        require './init.php';

        //命令输入
        $param = getopt('', ['key:']);
        $key = array_get($param, 'key');

        //写入config
        $urlInfo = parse_url($task['start']);
        $domain = $urlInfo['scheme'] . '://' . $urlInfo['host'];
        $config = [
            'domain' => $domain
        ];
        $this->config = $config;

        $socket = $task['socket'] ?? '';

        $this->getTaskDraw($task);

        $taskContainers = $this->getChildTasks($task);
        $taskContainer = first($taskContainers);

        $childTaskName = $taskContainer['name'];

        //初始化缓存
        ZCache::init($task['cache']);
        ZCache::delete('data_queue');

        //继续任务
        if (!empty($key) && $pauseData = first(array_get(\cache\ZFile::getDieTask($key), 'data_queue'))) {
            echo "继续任务\n";
            ZCache::rpush('data_queue', $pauseData);
        } else {
            ZCache::rpush('data_queue', ['url_list' => [$task['start']], 'task_name' => $childTaskName]);
        }

        $process = array_get($task, 'process', 0);

        //单进程
        if ($process <= 1) {
            if ($socket == 'server') {
                $this->supportSocketServer();
            } else
                if ($socket == 'user') {
                    $this->supportSocketUser();
                } else {
                    $this->supportCommon();
                }
        } else {
            $this->supportProcess($task);
        }
    }

    public function doTask($taskData)
    {
        $taskResults = [];

        //基础信息
        $config = $taskData['config'];
        $urlList = $taskData['url_list'];
        $taskContainer = $taskData['task_container'];
        $carryData = $taskData['carry_data'];

        $name = $taskContainer['name'];

        $domain = $config['domain'];

        $page = array_get($taskContainer, 'page');
        $data = array_get($taskContainer, 'data');
        $nextUrlSub = array_get($taskContainer, 'nextUrlSub');
        $nextRule = array_get($taskContainer, 'nextRule');

        //子任务
        $childTasks = $this->getChildTasks($taskContainer);

        foreach ($urlList as $url) {
            if (empty($url)) {
                continue;
            }

            $html = getHtml($url);

            //执行闭包
            $pageData = dataFun($data, ['url' => $url, 'html' => $html, 'carry_data' => $carryData]);

            //获取截取的urls
            $childUrlList = $this->getUrlList($html, $nextUrlSub, $pageData);
            $childUrlList = $this->chooseUrlList($childUrlList, $nextRule);
            $childUrlList = $this->perfectUrlList($childUrlList, $domain, $url);

            if (empty($childTasks)) {
                continue;
            }

            foreach ($childTasks as $childTask) {
                if (empty($childUrlList)) {
                    continue;
                }

                $childTaskName = $childTask['name'];
                $currentTaskName = $taskContainer['name'];

                if ($page) {
//                    分页推入当页
                    if ($page && (ZSpider::$pageArray[$name]['add_now'] ?? '') != 1) {
                        ZCache::rpush('data_queue', ['url_list' => [$url], 'task_name' => $childTaskName, 'carry_data' => $pageData]);
                        ZSpider::$pageArray[$name]['add_now'] = 1;
                    }

                    if (is_numeric($page)) {

                        isset(ZSpider::$pageArray[$name]['num']) ? ZSpider::$pageArray[$name]['num']-- : ZSpider::$pageArray[$name]['num'] = $page--;

                        if (ZSpider::$pageArray[$name]['num']) {
                            $taskResults[] = ['url_list' => $childUrlList, 'task_name' => $currentTaskName, 'carry_data' => $pageData];
                            $taskResults[] = ['url_list' => $childUrlList, 'task_name' => $childTaskName, 'carry_data' => $pageData];
                        }
                    } else {
                        $taskResults[] = ['url_list' => $childUrlList, 'task_name' => $currentTaskName, 'carry_data' => $pageData];
                        $taskResults[] = ['url_list' => $childUrlList, 'task_name' => $childTaskName, 'carry_data' => $pageData];
                    }
                } else {
                    $taskResults[] = ['url_list' => $childUrlList, 'task_name' => $childTaskName, 'carry_data' => $pageData];
                }

                !function_exists('pcntl_signal_dispatch') ?: pcntl_signal_dispatch();
            }
        }

        return $taskResults;
    }

    public function getTaskDraw($taskContainer)
    {
        $childTasks = $this->getChildTasks($taskContainer);
        foreach ($childTasks as $childTask) {
            $childTaskName = $childTask['name'];
            $this->taskDraw[$childTaskName] = $childTask;
            $this->getTaskDraw($childTask);
        }
    }

    public function getUrlList($html, $nextUrlSub, $pageData = [])
    {
        $urlList = [];
        if ($nextUrlSub == '$carry') {
            $urlList = $pageData['url'] ?? [];
            if (is_assoc($urlList)) {
                $urlList = array_keys($urlList);
            }
        } elseif (strstr($nextUrlSub, '@')) {
            $urlList = str_substr(first(explode('@', $nextUrlSub)), last(explode('@', $nextUrlSub)), $html);

            foreach ($urlList as &$url) {
                $url = trim($url);

                $url = strip_tags($url);

                $url = str_replace("'", '"', $url);

                if (strstr($url, '"')) {
                    $url = str_between($url, '"');
                }

                $url = trim($url);
            }
        } elseif (strstr($nextUrlSub, '#')) {

            $nextUrlSub = str_replace('/', '\/', $nextUrlSub);

            $pregSubUrl = '/(' . trim($nextUrlSub, '#') . ')/';

            $allUrl0 = str_substr('href="', '"', $html);
            $allUrl1 = str_substr("href='", "'", $html);

            $allUrl = array_merge($allUrl0, $allUrl1);

            foreach ($allUrl as $aUrl) {
                if (preg_match($pregSubUrl, $aUrl)) {
                    $urlList[] = $aUrl;
                }
            }
        }
        return $urlList;
    }

    public function chooseUrlList($urlList, $subRule)
    {
        if (is_null($subRule)) {
            return $urlList;
        }

        if (is_numeric($subRule) && isset($urlList[(int)$subRule])) {
            return [$urlList[(int)$subRule]];
        }

        if (strstr($subRule, '-')) {
            $ruleArr = explode('-', $subRule);
            $str = $ruleArr[0];
            $end = $ruleArr[1];

            foreach ($urlList as $key => $url) {
                if ($key < $str || $key > $end) {
                    unset($urlList[$key]);
                }
            }

            return $urlList;
        }

        return [];
    }

    public function perfectUrlList($urlList, $domain, $nowUrl)
    {
        foreach ($urlList as &$url) {

            if (!strstr('http', $url)) {
                if (substr($url, 0, 1) == '/') {
                    $url = $domain . '/' . trim($url, '/');
                } else {
                    $url = trim($nowUrl, '/') . '/' . $url;
                }
            }
        }

        return $urlList;
    }

    public function getChildTasks($taskContainer)
    {
        $childTaskNames = array_filter(array_keys($taskContainer), function ($item) {
            return $item == '%';
        });

        $childTasks = [];
        foreach ($childTaskNames as $childTaskName) {
            $childTasks[] = $taskContainer[$childTaskName];
        }

        return $childTasks;
    }

    public function getTaskData($dataQueue)
    {
        $taskName = $dataQueue['task_name'];
        $urlList = $dataQueue['url_list'];
        $carryData = $dataQueue['carry_data'] ?? '';

        $taskData = [
            'config' => $this->config,
            'task_name' => $taskName,
            'task_container' => $this->taskDraw[$taskName],
            'url_list' => $urlList,
            'carry_data' => $carryData,
        ];

        return $taskData;
    }

    public function supportCommon()
    {
        $sleepCount = 3;

        while (true) {

            $dataQueue = ZCache::lpop('data_queue');

            if (empty($dataQueue)) {
                sleep(3);
                echo "sleep---$sleepCount\n";
                $sleepCount--;
                if ($sleepCount < 0) {
                    break;
                }
                continue;
            }
            $sleepCount = 3;

            $taskData = $this->getTaskData($dataQueue);

            $taskResults = $this->doTask($taskData);

            foreach ($taskResults as $taskResult) {
                ZCache::rpush('data_queue', $taskResult);
            }
        }
    }

    public function supportSocketServer()
    {
        //自己不消费让客户端消费

        $socketObject = new SocketServer();
        $socket = $socketObject->server();

        echo "开始监听客户端请求\n";

        while (true) {

            $sleepCount = 3;
            if (empty($dataQueue)) {
                sleep(3);
                echo "sleep---$sleepCount\n";
                $sleepCount--;
                if ($sleepCount < 0) {
                    break;
                }
                continue;
            }

            //根据请求 返回不同的数据
            $socketObject->response($socket, function ($request) {

                $code = array_get($request, 'code');
                //客户端准备就绪 发送任务给客户端
                if ($code == 1) {

                    $dataQueue = ZCache::lpop('data_queue');

                    if (empty($dataQueue)) {
                        return ['code' => 404];
                    }

                    return ['code' => 200, 'message' => '发送任务给客户端', 'data_queue' => $dataQueue];
                }

                //收到你传来的任务了
                if ($code == 2) {
                    $dataQueue = array_get($request, 'data_queue');

                    ZCache::rpush('data_queue', $dataQueue);

                    return ['code' => 201, 'message' => '收到你传来的任务了'];
                }
            });
        }
    }

    public function supportSocketUser()
    {
        $socketObject = new SocketUser();

        $sleepCount = 5;

        while (true) {

            //从客户端请求任务
            $response = $socketObject->request(['code' => 1]);

            $dataQueue = $response['data_queue'] ?? [];

            if (empty($dataQueue)) {
                echo "暂未读取到任务\n";

                sleep(3);
                echo "sleep---$sleepCount\n";
                $sleepCount--;
                if ($sleepCount < 0) {
                    break;
                }
                continue;
            }

            $taskData = $this->getTaskData($dataQueue);

            $taskResults = $this->doTask($taskData);

            foreach ($taskResults as $taskResult) {
                //客户端把任务结果给服务端
                echo "任务结果给服务端\n";
                $socketObject->request(['code' => 2, 'message' => '任务结果给服务端', 'data_queue' => $taskResult]);

            }
        }
    }

    public function supportProcess($task)
    {
        $index = 0;

        while ($index < array_get($task, 'process', 1)) {
            $index++;
            $pid = pcntl_fork();
            if ($pid == -1) {
            } elseif ($pid > 0) {
                pcntl_wait($status, WNOHANG);
            } elseif ($pid == 0) {
                $sleepCount = 3;
                while (true) {
                    $dataQueue = ZCache::lpop('data_queue');

                    if (empty($dataQueue)) {
                        sleep(2);
                        echo posix_getpid() . "---sleep---$sleepCount\n";
                        $sleepCount--;
                        if ($sleepCount < 0) {
                            break;
                        }
                        continue;
                    }
                    $sleepCount = 3;

                    $taskData = $this->getTaskData($dataQueue);

                    $this->doTask($taskData);
                }
                exit();
            }
        }
    }
}