<?php

class ZSpider
{
    public $urlBlackList = [];
    public $urlWhiteList = [];

    public function start($config)
    {
        $name = $config['name'];
        $home = isset($config['home']) ? $config['home'] : '';
        $start = $config['start'];
        $gradeList = $config['grade'];

        $this->urlBlackList = [['url' => $start, 'name' => $name]];

        foreach ($gradeList as $grade) {
            $subUrl = $grade['url'];
            $gradeName = $grade['name'];
            $carry = isset($grade['carry']) ? $grade['carry'] : '';

            foreach ($this->urlBlackList as $url) {
                print_r($url);

                $gradeHtml = @file_get_contents($url['url']);
                $gradeHtml = @mb_convert_encoding((string)$gradeHtml, 'UTF-8', 'UTF-8,gb2312');

                if (empty($url['url'])) {
                    continue;
                }

                if (empty($gradeHtml)) {
                    continue;
                }

                foreach ($grade['data'] as $dataName => $dataFun) {
                    $gradeData[$dataName] = $dataFun([
                        'url' => $url['url'],
                        'carry' => $url,
                        'html' => $gradeHtml,
                    ]);
                }

                if (!empty($this->on_fetch)) {
                    foreach ($this->on_fetch as $fetchName => $fetchFun) {
                        if ($gradeName == $fetchName) {
                            $fetchFun($gradeData);
                            break;
                        }
                    }
                }

                $gradeUrlList = [];

                if (strstr($subUrl, '@')) {
                    $gradeUrlList = $this->str_substr(explode('@', $subUrl)[0], explode('@', $subUrl)[1], $gradeHtml);
                } elseif (strstr($subUrl, '#')) {

                    $pregSubUrl = '/(' . trim($subUrl, '#') . ')/';
                    $allUrl = $this->str_substr('href="', '"', $gradeHtml);
                    $allUrl1 = $this->str_substr("href='", "'", $gradeHtml);

                    $allUrl = array_merge($allUrl, $allUrl1);

                    $resUrl = [];
                    foreach ($allUrl as $aUrl) {
                        if (preg_match($pregSubUrl, $aUrl)) {
                            $resUrl[] = $aUrl;
                        }
                    }
                    $gradeUrlList = $resUrl;
                }

                if (!empty($home)) {
                    foreach ($gradeUrlList as &$gradeUrl) {
                        $gradeUrl = $home . $gradeUrl;
                    }
                }

                $carryData = [];
                if (!empty($carry)) {
                    foreach ($carry as $carryName => $carrySub) {
                        $carrySubData = '';
                        if (strstr($carrySub, '@')) {
                            $carrySubData = $this->str_substr(explode('@', $carrySub)[0], explode('@', $carrySub)[1], $gradeHtml, true);
                        } elseif (strstr($carrySub, '#')) {
                            $pregSubCarry = '/(' . trim($subUrl, '#') . ')/';
                            preg_match($pregSubCarry, $gradeHtml, $match);
                            $carrySubData = $match[0];
                        }

                        $carryData[$carryName] = $carrySubData;
                    }
                }

                foreach ($gradeUrlList as &$gradeUrl) {
                    if (!empty($carryData)) {
                        $gradeUrl = array_merge(['url' => $gradeUrl], $carryData);
                    } else {
                        $gradeUrl = ['url' => $gradeUrl];
                    }
                }

                if (isset($grade['page']) && $grade['page'] == true) {
                    print_r($gradeUrlList);
                    if (!empty($gradeUrlList)) {
                        $pageUrl = $gradeUrlList;
                        while (!empty($pageUrl[0]['url'])) {
                            $pageHtml = @file_get_contents($pageUrl[0]['url']);
                            $pageHtml = @mb_convert_encoding((string)$pageHtml, 'UTF-8', 'UTF-8,gb2312');
                            $pageUrl = [['url' => $this->str_substr(explode('@', $subUrl)[0], explode('@', $subUrl)[1], $pageHtml, true)]];
                            if (empty($pageUrl[0]['url'])){
                                break;
                            }
                            print_r(['page' => $pageUrl]);
                            if (!empty($home)) {
                                foreach ($pageUrl as &$apageUrl) {
                                    $apageUrl = ['url' => $home . $apageUrl];
                                }
                            }
                            $gradeUrlList = array_merge($gradeUrlList, $pageUrl);

                        }
                        $gradeUrlList = array_merge([$url], $gradeUrlList);
                    }
                }

                $this->urlWhiteList = array_merge($this->urlWhiteList, $gradeUrlList);
                $gradeData = [];
            }

            $this->urlBlackList = $this->urlWhiteList;
            $this->urlWhiteList = [];

        }

    }

    public function str_substr($start, $end, $str, $isLimit = false)
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

$config = [
    'name' => 'schoolmajor',
    'home' => 'https://gaokao.chsi.com.cn',
    'start' => 'https://gaokao.chsi.com.cn/sch/search.do',
    'grade' => [
        [
            'name' => 'start',
            'url' => "<a href='@'><i class='iconfont'>&#xe601",
            'page' => true,
            'data' => [
                'page' => function ($pageInfo) {

                },
            ]
        ],
    ]
];

$zspider = new ZSpider();

$zspider->on_fetch['majorContent'] = function ($data) {
};

$zspider->start($config);

