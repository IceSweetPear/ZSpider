<?php

require 'init.php';

$file = fopen('./zzz.csv', 'w+');

$tasks = [
        'cache' => 'array',
        'process' => 0,
        'start' => 'https://www.26jio.com',
//        'socket' => 'server',

        '%' => [
            'name' => '1',
            'page' => false,
            'data' => [
                'page' => function ($pageInfo) {
                    print_r(['1' => $pageInfo['url']]);
                },
            ],
            'nextUrlSub' => '<li><a href="@" class="contbl"><span>国产',
//            'nextRule' => '0',//0   0-3

            '%' => [
                'name' => '2',
                'page' => true,
                'data' => [
                    'page' => function ($pageInfo) {
                        print_r(['2' => $pageInfo['url']]);
                    },
                ],
                'nextUrlSub' => '<a href="@" class="next pagegbk"',


                '%' => [
                    'name' => '3',
                    'page' => false,
                    'data' => [
                        'page' => function ($pageInfo) {

                        },
                    ],
                    'nextUrlSub' => '<a href="@" class="" target="_blank">',

                    '%' => [
                        'name' => '4',
                        'page' => false,
                        'data' => [
                            'page' => function ($pageInfo)use($file) {
                                print_r(['pageList' => $pageInfo['url']]);
                                $html = $pageInfo['html'];
                                $link = str_substr("var down_url = '", "';", $html, true);
                                $title = str_substr('<dd class="film_title"><h4>', "</h4></dd>", $html, true);

                                $link = str_replace('https://d.220zx.com/', 'https://992do09.com/', $link);

                                $data = [
                                    $title,
                                    $link
                                ];

                                fputcsv($file, $data);
                            },
                        ],
                        'nextUrlSub' => '',
                    ],
                ],
            ],
        ],
];

(new ZSpider())->start($tasks);


