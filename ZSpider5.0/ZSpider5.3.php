<?php

class ZRedis
{

    public static $redis;

    public static function init()
    {
        self::$redis = new Redis();
        self::$redis->connect('127.0.0.1', 6379);
    }

    public function __construct()
    {
        self::init();
    }

    public static function put($key, $value)
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        return self::$redis->set($key, $value);
    }

    public static function get($key)
    {
        $value = self::$redis->get($key);
        $value_ser = @unserialize($value);
        if (is_object($value_ser) || is_array($value_ser)) {
            return $value_ser;
        }
        return $value;
    }

    public static function lpush($key, $value)
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        return self::$redis->lpush($key, $value);
    }

    public static function rpush($key, $value)
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }

        return self::$redis->rpush($key, $value);
    }

    public static function lpop($key)
    {
        $value = self::$redis->lpop($key);
        $value_ser = @unserialize($value);
        if (is_object($value_ser) || is_array($value_ser)) {
            return $value_ser;
        }
        return $value;
    }

    public static function rpop($key)
    {
        $value = self::$redis->rpop($key);
        $value_ser = @unserialize($value);
        if (is_object($value_ser) || is_array($value_ser)) {
            return $value_ser;
        }
        return $value;
    }

    public static function delete($key)
    {
        return self::$redis->del($key);
    }

}

class ZSpider
{
    public $taskDraw;

    public $on_fetch;
    public $fetch_url;
    public $fetch_url_list;

    public $DataQueue = [];

    public static $redis;

    public function doTask($taskContainer, $parentData)
    {

        $name = $taskContainer['name'];
        $urlSub = $taskContainer['urlSub'];
        $home = array_get($taskContainer, 'home');
        $page = array_get($taskContainer, 'page');
        $data = array_get($taskContainer, 'data');

        $urlList = $parentData;
        $childTasks = getChildTasks($taskContainer);

        foreach ($urlList as $url) {

            foreach ($this->fetch_url as $key => $func) {
                if ($key == $name) {
                    $url = $func($url);
                    break;
                }
            }

            if (empty($url)) {
                continue;
            }

            $html = getHtml($url);
            if (empty($html)) {
                $html = curlGetHtml($url);
            }

//            print_r([$name => $url]);

            $pageData = dataFun($data, ['url' => $url, 'html' => $html]);
            fetchFun(array_get($this->on_fetch, $name), $pageData);

            if ($page) {

                ZRedis::rpush('data_queue', ['urlList' => [$url], 'task_name' => first($childTasks)['name']]);

                while ($html) {
                    $pageUrl = getUrlList($html, $urlSub);

                    if (empty($pageUrl)) {
                        break;
                    }

                    $pageUrl = $home . first($pageUrl);

                    ZRedis::rpush('data_queue', ['urlList' => [$pageUrl], 'task_name' => first($childTasks)['name']]);

                    $html = getHtml($pageUrl);

                    print_r(['pid' => posix_getpid(), 'page' => $pageUrl, 'llen' => ZRedis::$redis->llen('data_queue')]);
                }

                return;

            } else {
                $childUrlList = getUrlList($html, $urlSub);
                $childUrlList = $home ? addUrlHome($childUrlList, $home, $url) : $childUrlList;
            }

            foreach ($this->fetch_url_list as $key => $func) {
                if ($key == $name) {
                    $childUrlList = $func($childUrlList);
                    break;
                }
            }

            if (empty($childTasks)) {
                return;
            }

            foreach ($childTasks as $childTask) {

                if (empty($childUrlList)) {
                    continue;
                }

                $childTaskName = $childTask['name'];

                ZRedis::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $childTaskName]);

            }

        }

    }

    public function start($configs)
    {
        ZRedis::init();

        foreach ($configs as $configName => $config) {

            $this->getTaskDraw($config);

            $taskContainers = getChildTasks($config);
            $taskContainer = first($taskContainers);

            $childTaskName = $taskContainer['name'];

            ZRedis::delete('data_queue');
            ZRedis::rpush('data_queue', ['urlList' => [$config['start']], 'task_name' => $childTaskName]);

            $index = 0;
            while ($index < array_get($config, 'task', 1)) {
                $index++;
                $pid = pcntl_fork();
                if ($pid == -1) {
                } elseif ($pid > 0) {
                    pcntl_wait($status, WNOHANG);
                } elseif ($pid == 0) {

                    $sleepCount = 3;

                    while (true) {
                        $dataQueue = ZRedis::lpop('data_queue');

                        if (empty($dataQueue)) {
                            sleep(2);
                            echo posix_getpid() . "sleep\n";
                            $sleepCount--;
                            if ($sleepCount < 0) {
                                break;
                            }
                            continue;
                        }
                        $sleepCount = 3;

                        print_r([posix_getpid() => $dataQueue]);

                        $taskName = $dataQueue['task_name'];
                        $taskData = $dataQueue['urlList'];

                        $this->doTask($this->taskDraw[$taskName], $taskData);
                    }

                    exit();
                }
            }
        }

    }

    public function getTaskDraw($taskContainer)
    {
        $childTasks = getChildTasks($taskContainer);
        foreach ($childTasks as $childTask) {
            $childTaskName = $childTask['name'];
            $this->taskDraw[$childTaskName] = $childTask;
            $this->getTaskDraw($childTask);
        }
    }
}

$configs = [
    'gkw1' => [
        'task' => 4,
        'start' => 'http://www.uu234.cc/xuanhuan/',
        '%pageList' => [
            'name' => 'pageList',
            'home' => '',
            'urlSub' => '</a><a href="@" class="next" title="下一页">',
            'page' => true,
            'data' => [
                'page' => function ($pageInfo) {
                },
            ],
            '%xsList' => [
                'name' => 'xsList',
                'urlSub' => '<a href="@" class="green" target="_blank">',
                'page' => false,
                'home' => '',
                'data' => [
                    'page' => function ($pageInfo) {
                    },
                ],
                '%xsInfo' => [
                    'name' => 'xsInfo',
                    'urlSub' => '',
                    'page' => false,
                    'home' => '',
                    'data' => [
                        'page' => function ($pageInfo) {

                            $html = $pageInfo['html'];

                            $title = str_substr('<title>', 'txt下载_全集下载', $html, true);

                            $downLoadLink = first(preg_sub_url($html, '#http://down.uu234.cc/txt/(.*)#'));

                            if (empty($downLoadLink)) {
                                return;
                            }

                            print_r($title."\n");

                            $newFile = 'xs/' . $title;

                            $file = httpcopy($downLoadLink, $newFile);

                            $fileType = str_substr('/', ';', get_file_mini_type($file), true);

                            rename($newFile, $file . '.' . $fileType);

                        },
                    ],
                ],
            ],

        ],
    ],
];

$zspider = new ZSpider();

$zspider->fetch_url['fetch_url'] = function ($url) {
    if (strstr($url, 'yuwen')) {
        $url = '';
    }
    return $url;
};
$zspider->fetch_url_list['fetch_url_list'] = function ($url) {
    return $url;
};

$zspider->start($configs);

function get_file_mini_type($file){
    $fInfo = finfo_open(FILEINFO_MIME);
    $filename = $file;
    $type = finfo_file($fInfo, $filename);
    finfo_close($fInfo);
    return $type;
}

function str_substr($start, $end, $str, $isLimit = false)
{
    $resultList = [];
    $firstList = explode($start, $str);

    foreach ($firstList as $key => $firstInfo) {
        if ($key == 0) {
            continue;
        }
        $secondList = explode($end, $firstInfo);

        if (count($secondList) == 0 || count($secondList) == 1) {
            continue;
        }
        if ($isLimit) {
            return trim($secondList[0]);
        } else {
            $resultList[] = trim($secondList[0]);
        }

    }

    return $isLimit ? '' : $resultList;
}

function preg_sub_url($html, $urlSub)
{
    if (strstr($urlSub, '#')) {

        $urlSub = str_replace('/', '\/', $urlSub);

        $pregSubUrl = '/(' . trim($urlSub, '#') . ')/';

        $allUrl0 = str_substr('href="', '"', $html);
        $allUrl1 = str_substr("href='", "'", $html);

        $allUrl = array_merge($allUrl0, $allUrl1);

        $urlList = [];
        foreach ($allUrl as $aUrl) {
            if (preg_match($pregSubUrl, $aUrl)) {
                $urlList[] = $aUrl;
            }
        }
        return $urlList;
    } else {
        return false;
    }

}

function array_get($array, $index, $default = null)
{
    return !empty($array) && isset($array[$index]) ? $array[$index] : $default;
}

function first(Array $array)
{
    return reset($array);
}

function last(Array $array)
{
    return end($array);
}

function getHtml($url)
{
    $html = @file_get_contents($url);
    $html = @mb_convert_encoding((string)$html, 'UTF-8', 'UTF-8,gb2312');
    return $html;
}

function curlGetHtml($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $html = curl_exec($ch);
    $html = @mb_convert_encoding((string)$html, 'UTF-8', 'UTF-8,gb2312');

    return $html;
}

function getCurl($url, $isPost = true, $data = '', $query = false, $ch = '', $header = [], $isHeader = false)
{

    $ch = empty($ch) ? curl_init() : $ch;

    $data = $query ? http_build_query($data) : $data;

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, $isPost);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, $isHeader);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $curlInfo = curl_exec($ch);

    return $curlInfo;
}

function getPageUrlList($url, $html, $urlSub, $home)
{
    $pageUrlList = [];
    while ($html) {
        $pageUrl = getUrlList($html, $urlSub);

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

function getUrlList($html, $urlSub)
{

    $urlList = [];
    if (strstr($urlSub, '@')) {
        $urlList = str_substr(first(explode('@', $urlSub)), last(explode('@', $urlSub)), $html);
        foreach ($urlList as &$url) {
            if (strstr($url, '"')) {
                $url = str_between($url, '"');
            }
            if (strstr($url, "'")) {
                $url = str_between($url, "'");
            }
        }

    } elseif (strstr($urlSub, '#')) {

        $urlSub = str_replace('/', '\/', $urlSub);

        $pregSubUrl = '/(' . trim($urlSub, '#') . ')/';

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

function getChildTasks($taskContainer)
{
    $childTaskNames = array_filter(array_keys($taskContainer), function ($item) {
        return strstr($item, '%');
    });

    $childTasks = [];
    foreach ($childTaskNames as $childTaskName) {
        $childTasks[] = $taskContainer[$childTaskName];
    }

    return $childTasks;
}

function addUrlHome($urlList, $home, $url)
{
    if (strstr($home, '$')) {
        switch ($home) {
            case '$self':
                $home = $url;
                break;
            case '$parent':
                $home = $url;
                break;
        }
    }

    foreach ($urlList as &$url) {
        $url = $home . $url;
    }
    return $urlList;
}

function dataFun(Array $dataFunArray, $data)
{
    $pageData = [];
    foreach ($dataFunArray as $dataName => $dataFun) {
        $pageData[$dataName] = $dataFun($data);
    }
    return $pageData;
}

function fetchFun($fetchFun, $pageData)
{
    if (!empty($fetchFun)) {
        $fetchFun($pageData);
    }
}

function str_between($str, $sign)
{
    if (strstr($str, $sign)) {
        $betweenArray = explode($sign, $str);
        array_shift($betweenArray);
        return first($betweenArray);
    } else {
        return $str;
    }
}

function httpcopy($url, $file = "", $timeout = 60)
{

    $file = empty($file) ? pathinfo($url, PATHINFO_BASENAME) : $file;
    $dir = pathinfo($file, PATHINFO_DIRNAME);
    !is_dir($dir) && @mkdir($dir, 0755, true);
    $url = str_replace(" ", "%20", $url);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $temp = curl_exec($ch);
        if (@file_put_contents($file, $temp) && !curl_error($ch)) {
            return $file;
        } else {
            return false;
        }
    } else {
        $opts = array(
            "http" => array(
                "method" => "GET",
                "header" => "",
                "timeout" => $timeout)
        );
        $context = stream_context_create($opts);
        if (@copy($url, $file, $context)) {
            //$http_response_header
            return $file;
        } else {
            return false;
        }
    }
}

function http_header_to_arr($header_str)
{
    $header_list = explode("\n", $header_str);
    $header_arr = [];
    foreach ($header_list as $key => $value) {
        if (strpos($value, ':') === false) {
            continue;
        }
        list($header_key, $header_value) = explode(":", $value, 2);
        $header_arr[$header_key] = trim($header_value);
    }
    if (isset($header_arr['Content-MD5'])) {
        $header_arr['md5'] = bin2hex(base64_decode($header_arr['Content-MD5']));
    }
    return $header_arr;
}

function getUrlParameterArray($url)
{
    $urlArray = [];
    if (strstr($url, '?')) {
        $urlArrayInfo = explode('?', $url);
        $urlArray['home'] = first($urlArrayInfo);
        $urlParameterString = last($urlArrayInfo);
        $urlParameterArray = explode('&', $urlParameterString);

        foreach ($urlParameterArray as $urlParameter) {
            $urlParameterInfo = explode('=', $urlParameter);
            $urlArray[first($urlParameterInfo)] = last($urlParameterInfo);
        }

    } else {
        $urlArray['home'] = $url;
    }
    return $urlArray;
}

function getUrlParameter(Array $urlArray)
{
    $home = array_shift($urlArray);
    $parameterArray = [];
    foreach ($urlArray as $key => $value) {
        $parameterArray[] = $key . '=' . $value;
    }
    $parameterString = implode('&', $parameterArray);

    $parameter = !empty($parameterString) ? $home . '?' . $parameterString : $home;

    return $parameter;
}

function is_json($str)
{
    return !is_null(json_decode($str));
}
