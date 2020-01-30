<?php

$configs = [
    'gkw1' => [
        'cache' => 'file',
        'task' => 0,
        'start' => 'https://www.zhetian.org/sort.html',
        '%pageList' => [
            'name' => 'pageList',
            'home' => 'https://www.zhetian.org',
            'urlSub' => '<a href="@class="next number"',
            'page' => 10,
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
                        exit("zzz");
                    },
                ],
                '%xsInfo' => [
                    'name' => 'xsInfo',
                    'urlSub' => '',
                    'page' => false,
                    'home' => '',
                    'data' => [
                        'page' => function ($pageInfo) {
                            print_r($pageInfo['url']);
                            exit("ccc");
                        },
                    ],
                ],
            ],

        ],
    ],
];

interface Cache{
    static function get($key);

    static function put($key, $value);

    static function lpush($key, $value);

    static function rpush($key, $value);

    static function lpop($key);

    static function rpop($key);

    static function delete($key);
}

class ZRedis implements Cache
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

class ZArray implements Cache{
    public static $array;

    public static function show(){
        return self::$array;
    }

    public static function init(){
        self::$array = [];
    }

    public static function get($key){
        return self::$array[$key];
    }

    public static function put($key, $value){
        self::$array[$key] = $value;
    }

    public static function lpush($key, $value){
        return array_unshift(self::$array[$key], $value);
    }

    public static function rpush($key, $value){
        self::$array[$key][] = $value;
    }

    public static function lpop($key){
        return array_shift(self::$array[$key]);
    }

    public static function rpop($key){
        return array_pop(self::$array[$key]);
    }

    public static function delete($key){
        unset(self::$array[$key]);
    }
}

class ZFile implements Cache{
    public static $path;
    public static $runPath;
    public static $pausePath;

    public static function init(){
        $tempPath = sys_get_temp_dir() . "/ZSpider";

        $runTaskPath = $tempPath . "/run";
        $pauseTaskPath = $tempPath . "/pause";

        is_dir($tempPath) ?: mkdir($tempPath);

        is_dir($runTaskPath) ?: mkdir($runTaskPath);
        delDir($runTaskPath, 1);

        is_dir($pauseTaskPath) ?: mkdir($pauseTaskPath);

        self::$runPath = $runTaskPath;
        self::$pausePath = $pauseTaskPath;
    }

    public static function show(){
        return self::getFilesData(self::$runPath);
    }

    public static function get($key){
        return self::readFile($key);
    }

    public static function put($key, $value){
        self::writeFile($key, $value);
    }

    public static function lpush($key, $value){
        $data = (array)self::readFile($key);
        array_unshift($data, $value);
        self::writeFile($key, $data);
    }

    public static function rpush($key, $value){
        $data = (array)self::readFile($key);
        array_push($data, $value);
        self::writeFile($key, $data);
    }

    public static function lpop($key){
        $data = (array)self::readFile($key);
        $value = array_shift($data);
        self::writeFile($key, $data);
        return $value;
    }

    public static function rpop($key){
        $data = (array)self::readFile($key);
        $value = array_pop($data);
        self::writeFile($key, $data);
        return $value;
    }

    public static function delete($key){
        self::delFile($key);
    }

    public static function readFile($key){
        $file = self::$runPath . "/$key";
        if (is_file($file)){
            $value = file_get_contents($file);
            $value_ser = @unserialize($value);
            if (is_object($value_ser) || is_array($value_ser)) {
                return $value_ser;
            }
            return $value;
        }else{
            return null;
        }
    }

    public static function writeFile($key, $value){
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        $file = self::$runPath . "/$key";
        !is_file($file) ?: touch($file);
        file_put_contents($file, $value);
    }

    public static function delFile($key){
        $file = self::$runPath . "/$key";
        !is_file($file) ?: unlink($file);
    }

    public static function getFilesData($runPath){
        $fileList = array_filter((array)scandir($runPath), function ($value){
            return !in_array($value, ['.', '..']);
        });
        $data = [];
        array_walk($fileList, function($value)use(&$data){
            $data[$value] = self::readFile($value);
        });
        return $data;
    }

    public static function addDieTask($key, $value){
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }
        $file = self::$pausePath . "/$key";
        !is_file($file) ?: touch($file);
        file_put_contents($file, $value);
    }
}

class ZCache{

    public static $cache;

    public static function init($cache){
        $cacheList = [
            'redis' => 'ZRedis',
            'array' => 'ZArray',
            'file' => 'ZFile',
        ];

        self::$cache = array_get($cacheList, $cache, $cacheList['array']);

        self::$cache::init();
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(self::$cache . "::$name", $arguments);
    }

    public static function dieDo(){
        $unFinishTask = self::$cache::show();

        if (empty($unFinishTask)){
            exit("No Remaining Tasks");
        }

        $key = substr(md5(time()), 0, 12);

        ZFile::addDieTask($key, $unFinishTask);

        echo $key;
        exit();
    }
}

!function_exists('pcntl_signal') ?: pcntl_signal(SIGINT,  'exitSignHandler');

function exitSignHandler($sign)
{
    !($sign == SIGINT) ?: ZCache::dieDo();
}

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

class ZSpider
{
    public $taskDraw;

    public $on_fetch;
    public $fetch_url;
    public $fetch_url_list;

    public $DataQueue = [];

    public static $redis;

    public static $pageArray;


    public function start($configs)
    {
        $param = getopt('', ['key:']);
        $key = array_get($param, 'key');

        foreach ($configs as $configName => $config) {

            $this->getTaskDraw($config);

            $taskContainers = getChildTasks($config);
            $taskContainer = first($taskContainers);

            $childTaskName = $taskContainer['name'];

            ZCache::init($config['cache']);

            ZCache::delete('data_queue');

            if (!empty($key) && $pauseData = ZFile::get($key)){
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

                    $this->doTask($this->taskDraw[$taskName], $taskData);
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

                            $this->doTask($this->taskDraw[$taskName], $taskData);
                        }
                        exit();
                    }
                }
            }
        }

    }

    public function doTask($taskContainer, $parentData)
    {
        $name = $taskContainer['name'];
        $urlSub = $taskContainer['urlSub'];
        $home = array_get($taskContainer, 'home');
        $page = array_get($taskContainer, 'page');
        $data = array_get($taskContainer, 'data');

        $urlList = $parentData;
        $childTasks = getChildTasks($taskContainer) ;

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

            $html = curlGetHtml($url);
            if (empty($html)) {
                $html = curlGetHtml($url);
            }

            print_r([$name => $url]);

            $pageData = dataFun($data, ['url' => $url, 'html' => $html]);
            fetchFun(array_get($this->on_fetch, $name), $pageData);

            $childUrlList = getUrlList($html, $urlSub);
            $childUrlList = $home ? addUrlHome($childUrlList, $home, $url) : $childUrlList;

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
                $currentTaskName = $taskContainer['name'];

                if ($page){
                    if (is_numeric($page)){
                        isset(ZSpider::$pageArray[$name]) ? ZSpider::$pageArray[$name]-- : ZSpider::$pageArray[$name] = $page--;
                        (!ZSpider::$pageArray[$name]) ?: ZCache::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $currentTaskName]);
                    }else{
                        ZCache::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $currentTaskName]);
                    }
                }else{
                    ZCache::rpush('data_queue', ['urlList' => $childUrlList, 'task_name' => $childTaskName]);
                }

                !function_exists('pcntl_signal_dispatch') ?: pcntl_signal_dispatch();
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
    $a = ['1'];

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

function curlGetHtml($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $html = curl_exec($ch);
    $html = @mb_convert_encoding((string)$html, 'UTF-8', 'UTF-8,gb2312');

    return $html;
}

function getCurl($url, $isPost = true, $data = '', $ch = '', $header = [], $isHeader = false)
{

    $ch = empty($ch) ? curl_init() : $ch;

    $data = is_array($data) ? http_build_query($data) : $data;

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

        $html = curlGetHtml($pageUrl);

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
            $url = trim($url);
            $url = trim($url, '"');
            $url = trim($url, "'");

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

function delDir($dir, $model = 0)
{
    if ($handle = @opendir($dir)) {
        while (($file = readdir($handle)) !== false) {
            if (($file == ".") || ($file == "..")) {
                continue;
            }
            if (is_dir($dir . '/' . $file)) {
                delDir($dir . '/' . $file);
            } else {
                unlink($dir . '/' . $file); // 删除文件
            }
        }
        @closedir($handle);
        !($model == 0 ) ?: rmdir($dir);
    }
}