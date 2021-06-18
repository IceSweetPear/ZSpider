<?php
use cache\ZCache;

echo "xxx";
exit();

require './vendor/autoload.php';


$uploadCh = curl_init();
$uploadUrl = 'http://www.xuezhangbb.com/api/v1/news/schoolexam/upload';
$getHeaderCh = curl_init();
$grades = [
    '高一',
    '高二',
    '高三',
];
$subjects = [
    '语文',
    '数学',
    '英语',
    '政治',
    '历史',
    '地理',
    '生物',
    '物理',
    '化学',
    '理综',
    '文综',
];
$provinces = [
    '北京',
    '天津',
    '河北',
    '山西',
    '内蒙古',
    '辽宁',
    '吉林',
    '黑龙江',
    '上海',
    '江苏',
    '浙江',
    '安徽',
    '福建',
    '江西',
    '山东',
    '河南',
    '湖北',
    '湖南',
    '广东',
    '广西',
    '海南',
    '重庆',
    '四川',
    '贵州',
    '云南',
    '西藏',
    '陕西',
    '甘肃',
    '青海',
    '宁夏',
    '新疆',
    '台湾',
    '香港',
    '澳门',
];

$configs = [
    'xkb' => [
        'cache' => 'array',
        'task' => 0,
        'start' => 'https://www.xkb1.com/',


        '%' => [
            'name' => 'subjectList',
            'home' => 'https://www.xkb1.com',
            'page' => false,
            'data' => [
                'page' => function ($pageInfo) {
                },
            ],
            'nextUrlSub' => "<a href='@/' target=\"_blank\">高中",


            '%' => [
                'name' => 'gradeList',
                'page' => false,
                'home' => 'https://www.xkb1.com',
                'data' => [
                    'page' => function ($pageInfo) {
                        print_r(['subjectList' => $pageInfo['url']]);

                    },
                ],
                'nextUrlSub' => '<td align="center"  ><a href@试卷</a>                    </td>',


                '%' => [
                    'name' => 'pageList',
                    'page' => false,
                    'home' => '$self',
                    'data' => [
                        'page' => function ($pageInfo) {
                            print_r(['gradeList' => $pageInfo['url']]);
                        },
                    ],
                    'nextUrlSub' => "<li><a href='@'>下一页</a></li>",


                    '%' => [
                        'name' => 'examList',
                        'page' => false,
                        'home' => 'https://www.xkb1.com',
                        'data' => [
                            'page' => function ($pageInfo) {
                                print_r(['pageList' => $pageInfo['url']]);
                            },
                        ],
                        'nextUrlSub' => '<td><a href=@target="_blank" >',


                        '%' => [
                            'name' => 'allExamList',
                            'page' => false,
                            'home' => 'https://www.xkb1.com',
                            'data' => [
                                'page' => function ($pageInfo) {
                                    print_r(['examList' => $pageInfo['url']]);
                                },
                            ],
                            'nextUrlSub' => "·<a href='@'>进入下载地址列表</a>",


                            '%' => [
                                'name' => 'downLoadList',
                                'nextUrlSub' => '',
                                'page' => false,
                                'home' => 'https://www.xkb1.com',
                                'data' => [
                                    'page' => function ($pageInfo) use ($uploadCh, $uploadUrl, $getHeaderCh, $subjects, $grades, $provinces) {
//
                                        $html = $pageInfo['html'];

                                        $title = str_substr("style='font-size:11pt'>", '</a>', $html, true);

                                        $url = 'https://www.xkb1.com' . str_substr('<li><a href="', '" target="_blank"> 下载地址一:点击下载', $html, true);

                                        preg_match('/[1-3]{1}[0-9]{1}[0-9]{1}[0-9]{1}/', $title, $match);
                                        $examYear = array_get($match, 0);
                                        if ((int)$examYear < 2018){
                                            print_r($examYear);
                                            return;
                                        }

                                        $province = '';
                                        foreach ($provinces as $provinceInfo) {
                                            if (strstr($title, $provinceInfo)) {
                                                $province = $provinceInfo;
                                                break;
                                            }
                                        }
                                        $grade = '';
                                        foreach ($grades as $gradeInfo) {
                                            if (strstr($title, $gradeInfo)) {
                                                $grade = $gradeInfo;
                                                break;
                                            }
                                        }
                                        $subject = '';
                                        foreach ($subjects as $subjectInfo) {
                                            if (strstr($title, $subjectInfo)) {
                                                $subject = $subjectInfo;
                                                break;
                                            }
                                        }

                                        curl_setopt($getHeaderCh, CURLOPT_URL, $url);
                                        curl_setopt($getHeaderCh, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($getHeaderCh, CURLOPT_POST, false);
                                        curl_setopt($getHeaderCh, CURLOPT_HEADER, true);
                                        curl_setopt($getHeaderCh, CURLOPT_TIMEOUT, 10);
                                        curl_setopt($getHeaderCh, CURLOPT_USERAGENT, 'Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15');
                                        curl_setopt($getHeaderCh, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
                                        curl_setopt($getHeaderCh, CURLOPT_FOLLOWLOCATION, true);
                                        curl_setopt($getHeaderCh, CURLOPT_RETURNTRANSFER, TRUE);
                                        $headerInfo = curl_exec($getHeaderCh);
                                        $headerSize = curl_getinfo($getHeaderCh, CURLINFO_HEADER_SIZE);
                                        $header = substr($headerInfo, 0, $headerSize);

                                        $downLoadLink = array_get(http_header_to_arr($header), 'location');

                                        $newFile = sys_get_temp_dir() . "/" . time();

                                        $file = httpcopy($downLoadLink, $newFile);
                                        if (empty($file)){
                                            return;
                                        }
                                        $md5File = md5_file($file);

                                        $data = array(
                                            'key' => 'xuezhangbb',
                                            'province' => $province,
                                            'grade' => $grade,
                                            'subject' => $subject,
                                            'title' => $title,
                                            'md5_file' => $md5File,
                                            'filename' => new CURLFile(realpath($file)),
                                        );

                                        curl_setopt($uploadCh, CURLOPT_URL, $uploadUrl);
                                        curl_setopt($uploadCh, CURLOPT_POST, 1);
                                        curl_setopt($uploadCh, CURLOPT_HEADER, 0);
                                        curl_setopt($uploadCh, CURLOPT_RETURNTRANSFER, 1);
                                        curl_setopt($uploadCh, CURLOPT_POSTFIELDS, $data);
                                        $return = curl_exec($uploadCh);
                                        print_r($return);
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];


!function_exists('pcntl_signal') ?: pcntl_signal(SIGINT,  'exitSignHandler');

function exitSignHandler($sign)
{
    !($sign == SIGINT) ?: ZCache::dieDo();
}

$zspider = new ZSpider();

$zspider->start($configs);



