<?php

/**
 * @name YYeTs.com Spider
 * @version 0.1
 * @author Tom <tom@awaysoft.com>
 * @date 2014-11-25
 * @description YYeTs.com Spider
 * @copyright Apache License, Version 2.0
 */


$config = array(
    'db' => array(
        'host' => '127.0.0.1',
        'port' => '5432',
        'user' => 'spider',
        'pass' => 'spider',
        'name' => 'spider'
    )
);
$conn = null;

require 'AlonePHP/index.php';

function IndexController() {
    echo "Welcome to use YYeTs Spider!\n";
    catchList();
}

function init() {
    global $config;
    global $conn;
    $conn_string = "host={$config['db']['host']} port={$config['db']['port']} dbname={$config['db']['name']} user={$config['db']['user']} password={$config['db']['pass']}";
    $conn = pg_connect($conn_string) or die('Connect Database Error!');
}

function catchList() {
    $urls = array(
        'http://www.yyets.com/eresourcelist?channel=movie&page=',
        'http://www.yyets.com/eresourcelist?channel=tv&page=',
        'http://www.yyets.com/eresourcelist?channel=documentary&page=',
        'http://www.yyets.com/eresourcelist?channel=openclass&page=',
    );

    /* 开始抓取 */
    foreach ($urls as $type => $url) {
        spider_log("Catching type:{$type}");
        /* 抓取第一页，获取信息 */
        $pUrl = $url . '1';
        $first_page_content = curl_get($pUrl);
        $first_page = parse_list($first_page_content, true);
        if (!$first_page) continue;

        /* 获取总页数 */
        $max_page = $first_page['max_page'];
        update_list($first_page, $type);
        spider_log("max_page:{$max_page}");

        $page = 2;
        /* 开始抓取其它页 */
        while($page <= $max_page) {
            spider_log("Catching type: {$type}, page: {$page}, max_page: {$max_page}");
            $content = curl_get($url . $page);
            $page_obj = parse_list($content);
            update_list($page_obj, $type);

            $page ++;
        }
    }
}

function parse_list($content, $first = false) {
    if (!$content) return false;

    $max_page_regex = '/\.\.\.(\d+)<span><\/span><\/a><\/div><\/div>/';
    $movie_regex = '/\/resource\/(\d+)\">.*?<strong>《(.+?)》/';

    $page_obj = array(
        'max_page' => 1,
        'items' => array()
    );
    if ($first) {
        /* 抓取总页数 */
        if (preg_match($max_page_regex, $content, $matches) === 1) {
            $page_obj['max_page'] = (int)$matches[1];
        }
    }

    if (preg_match_all($movie_regex, $content, $matches) > 0) {
        $count = count($matches[0]);
        for ($i = 0; $i < $count; ++$i) {
            $item = array(
                'name' => $matches[2][$i],
                'yid' => $matches[1][$i]
            );
            array_push($page_obj['items'], $item);
        }
    }
    return $page_obj;
}

function update_list($list, $type) {
    if (!$list) return false;

    $items = $list['items'];
    if (is_array($items)) {
        foreach($items as $item) {
            update_item($item, $type);
        }
    }
}

function update_item($item, $type) {
    global $conn;
    $yid = pg_escape_string($item['yid']);
    $name = pg_escape_string($item['name']);
    /* 检查之前是否存在 */
    $sql = "SELECT * FROM movies where yid = '{$yid}';";
    $result = pg_query($sql);
    $row = pg_fetch_array($result);
    /* 存在就更新 */
    if ($row) {
        spider_log("Updating {$item['yid']}, name:{$item['name']}");
        $sql = "UPDATE movies SET yid='{$yid}', name='{$name}', type='{$type}' WHERE mid='{$row['mid']}'";
    } else {
        spider_log("Adding {$item['yid']}, name:{$item['name']}");
        $sql = "INSERT INTO movies (yid, name, type) VALUES('{$yid}', '{$name}', '{$type}');";
    }
    pg_query($conn, $sql) or spider_log("ERROR SQL:{$sql}");
}

function spider_log($str) {
    $log_string = sprintf("[%s]:%s\n", date('Y-m-d h:i:s'), $str);
    echo $log_string;
}