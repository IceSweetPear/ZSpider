<?php


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

function preg_sub_url($html, $nextUrlSub)
{
    if (strstr($nextUrlSub, '#')) {

        $nextUrlSub = str_replace('/', '\/', $nextUrlSub);

        $pregSubUrl = '/(' . trim($nextUrlSub, '#') . ')/';

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
    $opts = array(
        'http' => array(
            'method' => "GET",
            'timeout' => 10,
        )
    );

    $html = @file_get_contents($url, false, stream_context_create($opts));
    $html = @mb_convert_encoding((string)$html, 'UTF-8', 'UTF-8,gb2312');

    if (empty($html)){
        $html = curlGetHtml($url);
    }

    return $html;
}

function curlGetHtml($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12.388 Version/12.15');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $curlInfo = curl_exec($ch);

    return $curlInfo;
}

function dataFun(Array $dataFunArray, $data)
{
    $pageData = [];
    foreach ($dataFunArray as $dataName => $dataFun) {
        $pageData[$dataName] = $dataFun($data);
    }
    return $pageData;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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

function is_assoc($arr){
    return array_keys($arr) !== range(0, count($arr) - 1);
}