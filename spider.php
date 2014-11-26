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
    controller('Help');
}

function ListController() {
    $page = 2;
    if (param_count() > 1) {
        $page = (int) param_get(2);
    }
    catch_list();
}

function PageController() {
    $page = 0;
    if (param_count() > 1) {
        $page = (int) param_get(2);
    }
    catch_page();
}

function AllController() {
    catch_list();
    catch_page();
}

function HelpController() {
    echo <<<EOT
YYeTs Spider v0.1
Usage: php spider.php [options]

Options:
    all             Catch ALl, include all list and page.
    list [pagenum]  Catch only list, start from pagenum.
    page [pagenum]  Catch only page, start from pagenum.
    help            Show this help.
    version         Show version infomation.

EOT;
}

function VersionController() {
    echo <<<EOT
YYeTs Spider v0.1
Tom<tom@awaysoft.com>

EOT;
}

function init() {
    global $config;
    global $conn;
    $conn_string = "host={$config['db']['host']} port={$config['db']['port']} dbname={$config['db']['name']} user={$config['db']['user']} password={$config['db']['pass']}";
    $conn = pg_connect($conn_string) or die('Connect Database Error!');
}

function catch_page($page = 1) {
    global $conn;
    $url = 'http://www.yyets.com/resource/';
    
    $sql = 'select mid, yid from movies';
    $result = pg_query($conn, $sql);
    $position = 1;
    while (($row = pg_fetch_array($result))) {
        if ($position < $page) continue;
        
        spider_log("Catching Page: mid: {$row['mid']}, yid: {$row['yid']}");
        
        $retry_count = 0;
        do {
            $header = array(
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:33.0) Gecko/20100101 Firefox/33.0',
                /* @Todo 修改这里为你已经登录的Cookie */
                'Cookie' => 'last_item:26208=%E8%AE%B2%E4%B9%89%E4%B8%8B%E8%BD%BD; last_item_date:26208=1416920713; CNZZDATA3546884=cnzz_eid%3D509708039-1416913247-%26ntime%3D1417010548; mykeywords=a%3A3%3A%7Bi%3A0%3Bs%3A30%3A%22%E7%BE%8E%E5%9B%BD%E7%8E%B0%E4%BB%A3%E4%B8%BB%E4%B9%89%E6%96%87%E5%AD%A6%E9%80%89%E8%AF%BB%22%3Bi%3A1%3Bs%3A24%3A%22%E5%B8%8C%E7%89%B9%E5%8B%92%E5%A4%B1%E8%90%BD%E7%9A%84%E6%BD%9C%E8%89%87%22%3Bi%3A2%3Bs%3A12%3A%22%E7%BB%BF%E8%89%B2%E6%98%9F%E7%90%83%22%3B%7D; yy_pop3=0-1417013814945; yy_rich=3; PHPSESSID=ae14q8i3vmori1ilho37g8hqk7; yyets_slide_ad=1; GINFO=uid%3D3402957%26nickname%3Dyanjingtao%26group_id%3D1%26avatar_t%3Dhttp%3A%2F%2Ftu.rrsub.com%3A8014%2Fftp%2Favatar%2Ff_noavatar_t.gif%26main_group_id%3D0%26common_group_id%3D52; GKEY=fb39b9df69e7365509d3532cdcd24791c2e9fa626bd1652890e530c86ddcf8d6'
            );
            $content = curl_get_with_ip($url . $row['yid'], '116.251.210.245', $header);
            if ($content) {
                break;
            }
            $retry_count ++;
            if ($retry_count >= 5) {
                continue 2;
            }
            spider_log("Connect Error, wait 5s to retry...");
            sleep(5);
        } while ($retry_count < 5);
        $page_object = parse_page($content);
        $page_object['mid'] = $row['mid'];
        update_page($page_object);
        
        $position ++;
    }
}

function catch_list($page = 2) {
    global $conn;
    $url = 'http://www.yyets.com/eresourcelist?page=';

    /* 开始抓取 */
    /* 抓取第一页，获取信息 */
    $first_page_content = curl_get_with_ip($url . '1', '116.251.210.245');
    $first_page = parse_list($first_page_content, true);
    if (!$first_page) continue;

    /* 获取总页数 */
    $max_page = $first_page['max_page'];
    update_list($first_page);
    spider_log("max_page:{$max_page}");

    /* 开始抓取其它页 */
    $retry_count = 0;
    while($page <= $max_page) {
        spider_log("Catching page: {$page}, max_page: {$max_page}");
        $content = curl_get_with_ip($url . $page, '116.251.210.245');
        $page_obj = parse_list($content);
        if (!update_list($page_obj)) {
            spider_log("Error Update List, page: {$page}");
            spider_log("Wait 5s to Retry");
            sleep(5);
            $retry_count ++;
            if ($retry_count <= 5) continue;
        } else {
            $retry_count = 0;
        }

        $page ++;
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

function update_list($list) {
    if (!$list) return false;

    $items = $list['items'];
    if (is_array($items)) {
        foreach($items as $item) {
            update_item($item);
        }
    }
    return true;
}

function update_item($item) {
    global $conn;
    $yid = pg_escape_string($item['yid']);
    $name = pg_escape_string($item['name']);
    /* 检查之前是否存在 */
    $sql = "SELECT * FROM movies WHERE yid = '{$yid}';";
    $result = pg_query($conn, $sql);
    $row = pg_fetch_array($result);
    /* 存在就更新 */
    if ($row) {
        spider_log("Updating {$item['yid']}, name:{$item['name']}");
        $sql = "UPDATE movies SET yid='{$yid}', name='{$name}' WHERE mid='{$row['mid']}'";
    } else {
        spider_log("Adding {$item['yid']}, name:{$item['name']}");
        $sql = "INSERT INTO movies (yid, name) VALUES('{$yid}', '{$name}');";
    }
    pg_query($conn, $sql) or spider_log("ERROR SQL:{$sql}");
}

function parse_page($content) {
    $info_regex = "/<ul class=\\\"r_d_info\\\">(.*?)<\/ul>/s";
    $page_obj = array();
    if (preg_match($info_regex, $content, $matches) === 1) {
        $info_content = trim($matches[1]);
        $page_obj['info'] = parse_page_info($info_content);
    }
    $page_obj['links'] = parse_page_link($content);
    return $page_obj;
}

function parse_page_info($content) {
        $key_list = array(
                'state' => '地区',
                'mtype' => '类&nbsp;&nbsp;&nbsp;&nbsp;型',
                'year' => '年代',
                'lang' => '语言',
                'company' => '制作公司',
                'starttime' => '首播时间',
                'starttime ' => '上映日期',
                'ename' => '英文',
                'oname' => '别名',
                'scriptwriter' => '编剧',
                'staring' => '主演',
                'director' => '导演',
                'description' => '简介');
    $info_obj = array();

        foreach($key_list as $key => $value) {
                $key = trim($key);
                $d_regex1 = "/<span>{$value}：<\/span><strong>(.*?)<\/strong>/";
                $d_regex2 = "/<font class=\\\"f5\\\">{$value}：<\/font>(.*?)<\/li>/";
                $d_regex3 = "/<span>{$value}：<\/span>(.*?)<\/li>/s";
                if (preg_match($d_regex1, $content, $matches) === 1) {
                        $info_obj[$key] = strip_tags($matches[1]);
                } elseif (preg_match($d_regex2, $content, $matches) === 1) {
                        $info_obj[$key] = strip_tags($matches[1]);
                } elseif (preg_match($d_regex3, $content, $matches) === 1) {
                        $info_obj[$key] = strip_tags($matches[1]);
                }
        }
    
    return $info_obj;
}

function parse_page_link($content) {
    $links_obj = array();

        $season_regex = "/<div class=\\\"resod_tit\\\">(.*?)<\/div>/s";
        $link_content_regex = "/<ul class=\\\"resod_list\\\" season=\\\"(\d+)\\\" style=\\\"display:none;\\\">(.*?)<\/ul>/s";
        $link_regex = "/<li episode=\\\"\d+\\\" itemid=\\\"\d+\\\" format=\\\"(.*?)\\\">.*?<span class=\\\"a\\\">(.*?)<\/span>.*?<font class=\\\"f5\\\">(.*?)<\/font>.*?<div class=\\\"download\\\">(.*?)<\/div>/";
        $ls_regex = array(
                'emule' => "/<a type=\\\"ed2k\\\" href=\\\"(.*?)\\\"/",
                'magnet' => "/.*<a href=\\\"(.*?)\\\" type=\\\"magnet\\\"/",
                'ct' => "/.*<a href=\\\"(.*?)\\\" target=\\\"_blank\\\">城通<\/a>/"
        );

        /* 提取季 */
        $seasons = array();
        if (preg_match($season_regex, $content, $matches) === 1) {
                $season_content = $matches[1];
                $seasons = parse_page_link_season($season_content);
        }

        /* 提取所有的链接html */
        if (preg_match_all($link_content_regex, $content, $matches) > 0) {
                for ($i = 0; $i < count($matches[0]); ++$i) {
                        /* 得到某一季的html */
                        $link_content = $matches[2][$i];
                        $season = $seasons[$matches[1][$i]];

                        /* 提取一个资源的html */
                        if (preg_match_all($link_regex, $link_content, $ms2) > 0) {
                                for ($j = 0; $j < count($ms2[0]); ++$j) {
                                        /* 提取一个资源的内容 */
                                        $link_obj = array(
                                                'season' => $season,
                                                'format' => $ms2[1][$j],
                                                'filename' => $ms2[2][$j],
                                                'filesize' => $ms2[3][$j],
                                                'alllink' => $ms2[4][$j]
                                        );
                                        foreach ($ls_regex as $t_name => $l_regex) {
                                                if (preg_match($l_regex, $link_obj['alllink'], $ms3) === 1) {
                                                        $link_obj[$t_name] = $ms3[1];
                                                }
                                        }
                                        array_push($links_obj, $link_obj);
                                }
                        }
                }
        }

    return $links_obj;
}

function parse_page_link_season($content) {
        $seasons_obj = array();

        $s_regex = "/<li format=\\\".*?\\\" season=\\\"(\d+)\\\"><a>(.*?)<\/a><\/li>/";
        if (preg_match_all($s_regex, $content, $matches) > 0) {
                for ($i = 0; $i < count($matches[0]); ++$i) {
                        $seasons_obj[trim($matches[1][$i])] = trim($matches[2][$i]);
                }
        }

        return $seasons_obj;
}

function update_page($page_object) {
    global $conn;
    $mid = $page_object['mid'];
    $info = $page_object['info'];
    $links = $page_object['links'];
    if ($info) {
        /* 更新info */
        foreach ($info as $key => $value) {
            $info[$key] = pg_escape_string($value);
        }
        $sql = gen_sql('update', $info);
        $sql2 = "UPDATE movies SET {$sql} WHERE mid={$mid}";
        pg_query($conn, $sql2) or spider_log("Error SQL: {$sql2}");
    }
    
    foreach ($links as $link) {
        $link['mid'] = $mid;
        foreach ($link as $key => $value) {
            $link[$key] = pg_escape_string($value);
        }
        /* 检查之前是否存在 */
        $sql = "SELECT * FROM links WHERE mid = '{$mid}' and filename = '{$link['filename']}' and format = '{$link['format']}';";
        $result = pg_query($conn, $sql);
        $row = pg_fetch_array($result);
        /* 存在就更新 */
        if ($row) {
            spider_log("Updating {$row['lid']}, name:{$link['filename']}");
            $sql = "UPDATE links SET " . gen_sql('update', $link) . " WHERE lid='{$row['lid']}'";
        } else {
            spider_log("Adding {$link['mid']}, name:{$link['filename']}");
            $sql = "INSERT INTO links " . gen_sql('insert', $link) . ";";
        }
        pg_query($conn, $sql) or spider_log("ERROR SQL:{$sql}");
    }
}

function gen_sql($mode, $arr) {
    $sql = '';
    if ($mode == 'insert') {
        $sql1 = '';
        $sql2 = '';
        foreach ($arr as $key => $value) {
            if ($sql1 !== '') {
                $sql1 .= ',';
            }
            if ($sql2 !== '') {
                $sql2 .= ',';
            }
            $sql1 .= $key;
            $sql2 .= "'{$value}'";
        }
        $sql = "({$sql1}) VALUES({$sql2})";
    } elseif ($mode == 'update') {
        foreach ($arr as $key => $value) {
            if ($sql !== '') {
                $sql .= ',';
            }
            $sql .= "{$key}='{$value}'";
        }
    }
    return $sql;
}

function spider_log($str) {
    static $fd = null;
    static $id = 1;
    if (!$fd) {
        $fd = fopen('log.log', "w");
    }
    $log_string = sprintf("%6d:[%s]:%s\n", $id++, date('Y-m-d h:i:s'), $str);
    echo $log_string;
    fprintf($fd, $log_string);
}

