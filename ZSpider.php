<?php
/**
 * Created by PhpStorm.
 * User: xuezahngbb
 * Date: 2020/6/9
 * Time: 13:10
 */
use cache\ZCache;

class ZSpider
{
    public $taskDraw;

    public $DataQueue = [];

    public static $redis;

    public static $pageArray;

    public function start($configs)
    {
        $param = getopt('', ['key:']);
        $key = array_get($param, 'key');

        foreach ($configs as $configName => $config) {

            $this->getTaskDraw($config);

            $taskContainers = $this->getChildTasks($config);
            $taskContainer = first($taskContainers);

            $childTaskName = $taskContainer['name'];

            ZCache::init($config['cache']);

            ZCache::delete('data_queue');

            if (!empty($key) && $pauseData = first(array_get(\cache\ZFile::getDieTask($key), 'data_queue'))){
                echo "继续任务\n";
                ZCache::rpush('data_queue', $pauseData);
            }else{
                ZCache::rpush('data_queue', ['urlList' => [$config['start']], 'task_name' => $childTaskName]);
            }

            $task = array_get($config, 'task', 0);
            $index = 0;
            if ($task <= 1){
                $sleepCount = 5;
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

                    $taskName = $dataQueue['task_name'];
                    $taskData = $dataQueue['urlList'];
                    $carryData = $dataQueue['carry_data'] ?? '';

                    $this->doTask($this->taskDraw[$taskName], $taskData, $carryData);
                }
            }else{
                while ($index < array_get($config, 'task', 1)) {
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

                            $taskName = $dataQueue['task_name'];
                            $taskData = $dataQueue['urlList'];
                            $carryData = $dataQueue['carry_data'] ?? '';

                            $this->doTask($this->taskDraw[$taskName], $taskData, $carryData);
                        }
                        exit();
                    }
                }
            }
        }

    }

    public function doTask($taskContainer, $parentData, $carryData)
    {
        $name = $taskContainer['name'];
        $nextUrlSub = $taskContainer['nextUrlSub'];
        $home = array_get($taskContainer, 'home');
        $page = array_get($taskContainer, 'page');
        $data = array_get($taskContainer, 'data');

        $urlList = $parentData;
        $childTasks = $this->getChildTasks($taskContainer);

        foreach ($urlList as $url) {
            if (empty($url)) {continue;}

            $html = getHtml($url);

            $pageData = dataFun($data, ['url' => $url, 'html' => $html, 'carry_data' => $carryData]);

            $childUrlList = $this->getUrlList($html, $nextUrlSub, $pageData);

            $childUrlList = $home ? addUrlHome($childUrlList, $home, $url) : $childUrlList;

            if (empty($childTasks)) {continue;}

            foreach ($childTasks as $childTask) {
                if (empty($childUrlList)) {continue;}

                $childTaskName = $childTask['name'];
                $currentTaskName = $taskContainer['name'];

                if ($page){
                    if (is_numeric($page)){
                        isset(ZSpider::$pageArray[$name]) ? ZSpider::$pageArray[$name]-- : ZSpider::$pageArray[$name] = $page--;
                        (!ZSpider::$pageArray[$name]) ?: ZCache::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $currentTaskName]);
                    }else{
                        ZCache::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $currentTaskName, 'carry_data' => $pageData]);
                    }
                }else{
                    ZCache::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $childTaskName, 'carry_data' => $pageData]);
                }

                !function_exists('pcntl_signal_dispatch') ?: pcntl_signal_dispatch();
            }
        }
    }

    function getTaskDraw($taskContainer)
    {
        $childTasks = $this->getChildTasks($taskContainer);
        foreach ($childTasks as $childTask) {
            $childTaskName = $childTask['name'];
            $this->taskDraw[$childTaskName] = $childTask;
            $this->getTaskDraw($childTask);
        }
    }

    function getUrlList($html, $nextUrlSub, $pageData = [])
    {
        $urlList = [];
        if ($nextUrlSub == '$carry'){
            $urlList = $pageData['url'] ?? [];
            if (is_assoc($urlList)){
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

    function getPageUrlList($url, $html, $nextUrlSub, $home)
    {
        $pageUrlList = [];
        while ($html) {
            $pageUrl = $this->getUrlList($html, $nextUrlSub);

            if (empty($pageUrl)) {
                break;
            }

            $pageUrl = $home . first($pageUrl);
            $pageUrlList[] = $pageUrl;

            $html = getHtml($pageUrl);

            print_r(['page' => $pageUrl]);
        }

        array_unshift($pageUrlList, $url);

        return $pageUrlList;
    }

    function getChildTasks($taskContainer)
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
}