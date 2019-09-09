<?php

class ZSpider
{
    public $on_fetch;

    public function doTask($taskContainer, $parentData)
    {

        $name = $taskContainer['name'];
        $urlSub = $taskContainer['urlSub'];
        $home = array_get($taskContainer, 'home');
        $page = array_get($taskContainer, 'page');
        $data = array_get($taskContainer, 'data');

        $urlList = $parentData['urlList'];
        $childTasks = getChildTasks($taskContainer);

        foreach ($urlList as $url) {
            $html = getHtml($url);
            print_r(['url'=>$url]);

            $pageData = dataFun($data, ['url' => $url, 'html' => $html]);
            fetchFun(array_get($this->on_fetch, $name), $pageData);

            if ($page){
                $childUrlList = getPageUrlList($url, $html, $urlSub, $home);
            }else{
                $childUrlList = getUrlList($html, $urlSub);
                $childUrlList = $home ? addUrlHome($childUrlList, $home) : $childUrlList;
            }

            foreach ($childTasks as $childTask) {
                $this->doTask($childTask, ['urlList'=>$childUrlList]);
            }

        }

    }

    public function start($configs)
    {

        foreach ($configs as $configName => $config) {

            $taskContainers = getChildTasks($config);

            foreach ($taskContainers as $taskName => $taskContainer) {

                $this->doTask($taskContainer, ['urlList' => [$config['start']]]);

            }

        }

    }

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

function getPageUrlList($url, $html, $urlSub, $home){

    $pageUrlList = [];
    while ($html){
        $pageUrl = getUrlList($html, $urlSub);

        if (empty($pageUrl)){
            break;
        }

        $pageUrl = $home . first($pageUrl);
        $pageUrlList[] = $pageUrl;

        $html = getHtml($pageUrl);

        print_r(['page'=>$pageUrl]);
    }

    array_unshift($pageUrlList, $url);

    return $pageUrlList;
}

function getUrlList($html, $urlSub)
{

    $urlList = [];
    if (strstr($urlSub, '@')) {
        $urlList = str_substr(first(explode('@', $urlSub)), last(explode('@', $urlSub)), $html);
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

function addUrlHome($urlList, $home)
{
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
    if (!empty($fetchFun)){
        $fetchFun($pageData);
    }
}

$configs = [
    'majorSchool' => [
        'start' => 'https://gaokao.chsi.com.cn/sch/search--ss-on,option-qg,searchType-1,start-0.dhtml',
        '%schoolPage' => [
            'name' => 'schoolPage',
            'home' => 'https://gaokao.chsi.com.cn',
            'urlSub' => "<a href='@'><i class='iconfont'>&#xe601",
            'page' => true,
            'data' => [
                'page' => function ($pageInfo) {
                },
            ],
            '%schoolLink' => [
                'name' => 'schoolLink',
                'urlSub' => "#/sch/schoolInfo--#",
                'page' => false,
                'home' => 'https://gaokao.chsi.com.cn',
                'data' => [
                    'page' => function ($pageInfo) {
                    },
                ],
                '%schoolInfo' => [
                    'name' => 'schoolInfo',
                    'urlSub' => "##",
                    'page' => false,
                    'home' => 'https://gaokao.chsi.com.cn',
                    'data' => [
                        'page' => function ($pageInfo) {
                        print_r($pageInfo);
                        exit("xxx");
                        },
                    ],
                ],
            ],
        ],
    ],
];

$zspider = new ZSpider();

$zspider->start($configs);

